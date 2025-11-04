<?php

use Illuminate\Support\Arr;
use LaravelWhatsApp\Models\BusinessAccount;
use LaravelWhatsApp\Models\Template;
// TestCase & RefreshDatabase already applied via tests/Pest.php

it('fills attributes defined as fillable', function () {
    $account = new BusinessAccount([
        'whatsapp_id' => 'waba_1',
        'name' => 'Test Business',
        'currency' => 'USD',
        'timezone_id' => 'America/New_York',
        'message_template_namespace' => 'namespace',
        'access_token' => 'secret',
        'subscribed_apps' => ['app1', 'app2'],
    ]);

    expect($account->whatsapp_id)->toBe('waba_1')
        ->and($account->name)->toBe('Test Business')
        ->and($account->currency)->toBe('USD')
        ->and($account->timezone_id)->toBe('America/New_York')
        ->and($account->message_template_namespace)->toBe('namespace')
        ->and($account->subscribed_apps)->toBe(['app1', 'app2']);
})->group('business-account');

it('hides access_token when casting to array', function () {
    $account = new BusinessAccount(['access_token' => 'secret']);
    $array = $account->toArray();
    expect($array)->not->toHaveKey('access_token');
})->group('business-account');

it('relates phone numbers to business account', function () {
    $account = BusinessAccount::factory()->create();
    $phoneModel = config('whatsapp.apiphone_model');
    $phone = $phoneModel::factory()->create(['business_account_id' => $account->id]);
    expect($account->phoneNumbers->contains($phone))->toBeTrue();
})->group('business-account');

it('relates templates to business account', function () {
    $account = BusinessAccount::factory()->create();
    $template = Template::factory()->create(['business_account_id' => $account->id]);
    expect($account->templates->contains($template))->toBeTrue();
})->group('business-account');

it('fills fields & relationships from Meta API payload', function () {
    $account = BusinessAccount::factory()->create();

    $stubPath = realpath(__DIR__.'/../../stubs/waba_info_response.json');
    if ($stubPath === false) {
        throw new Exception('Stub file not found');
    }

    $content = file_get_contents($stubPath);
    $data = json_decode($content, true);
    $account->fillFromMeta($data);

    $filteredData = Arr::except($data, ['id', 'phone_numbers', 'message_templates']);
    $accountData = Arr::except($account->toArray(), ['id', 'whatsapp_id', 'created_at', 'updated_at', 'phone_numbers', 'templates']);
    expect($accountData)->toEqual($filteredData);

    expect($account->phoneNumbers)->toHaveCount(count($data['phone_numbers']['data']));
    $dataPhoneNumber = $data['phone_numbers']['data'][0];
    $phoneNumber = $account->phoneNumbers->first();
    expect($phoneNumber->whatsapp_id)->toBe($dataPhoneNumber['id'])
        ->and($phoneNumber->throughput_level)->toBe(Arr::get($dataPhoneNumber, 'throughput.level'))
        ->and($phoneNumber->webhook_configuration_application)->toBe(Arr::get($dataPhoneNumber, 'webhook_configuration.application'));

    $expectedDataPhoneNumber = Arr::except($dataPhoneNumber, ['id', 'throughput', 'webhook_configuration']);
    $storedPhoneNumberData = Arr::except($phoneNumber->toArray(), ['id', 'business_account_id', 'created_at', 'updated_at', 'throughput_level', 'webhook_configuration_application', 'whatsapp_id']);
    expect($storedPhoneNumberData)->toEqual($expectedDataPhoneNumber);

    expect($account->templates)->toHaveCount(count($data['message_templates']['data']));
    $dataTemplate = $data['message_templates']['data'][0];
    $storedTemplate = $account->templates->first();
    expect($storedTemplate->whatsapp_id)->toBe($dataTemplate['id']);
    $dataTemplate = Arr::except($dataTemplate, ['id']);
    $storedTemplateData = Arr::except($storedTemplate->toArray(), ['id', 'business_account_id', 'created_at', 'updated_at', 'whatsapp_id']);
    expect($storedTemplateData)->toEqual($dataTemplate);
})->group('business-account');

