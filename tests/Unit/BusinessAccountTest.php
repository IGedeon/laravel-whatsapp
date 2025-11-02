<?php

namespace Tests\Unit;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use LaravelWhatsApp\Models\Template;
use LaravelWhatsApp\Models\BusinessAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BusinessAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes()
    {
        $account = new BusinessAccount([
            'whatsapp_id' => 'waba_1',
            'name' => 'Test Business',
            'currency' => 'USD',
            'timezone_id' => 'America/New_York',
            'message_template_namespace' => 'namespace',
            'access_token' => 'secret',
            'subscribed_apps' => ['app1', 'app2'],
        ]);

        $this->assertEquals('waba_1', $account->whatsapp_id);
        $this->assertEquals('Test Business', $account->name);
        $this->assertEquals('USD', $account->currency);
        $this->assertEquals('America/New_York', $account->timezone_id);
        $this->assertEquals('namespace', $account->message_template_namespace);
        $this->assertEquals(['app1', 'app2'], $account->subscribed_apps);
    }

    public function test_hidden_access_token()
    {
        $account = new BusinessAccount(['access_token' => 'secret']);
        $array = $account->toArray();
        $this->assertArrayNotHasKey('access_token', $array);
    }

    public function test_phone_numbers_relationship()
    {
        $account = BusinessAccount::factory()->create();
        $phoneModel = config('whatsapp.apiphone_model');
        $phone = $phoneModel::factory()->create(['business_account_id' => $account->id]);
        $this->assertTrue($account->phoneNumbers->contains($phone));
    }

    public function test_templates_relationship()
    {
        $account = BusinessAccount::factory()->create();
        $template = Template::factory()->create(['business_account_id' => $account->id]);
        $this->assertTrue($account->templates->contains($template));
    }

    public function test_get_from_meta_updates_fields_and_relations()
    {
        $account = BusinessAccount::factory()->create();
        

        $stubPath = realpath(__DIR__.'/../../stubs/waba_info_response.json');
    if ($stubPath === false) {
        throw new \Exception('Stub file not found');
    }

    $content = file_get_contents($stubPath);

        $data = json_decode($content, true);
        Http::fake([
            '*' => Http::response($data, 200),
        ]);

        $account->getFromMeta();
        $account->refresh();

        
        $this->assertEquals(Arr::except($data, ['id', 'phone_numbers', 'message_templates']), Arr::except($account->toArray(), ['id', 'whatsapp_id', 'created_at', 'updated_at']));
        // dd(Arr::except($data, ['id','phone_numbers', 'message_templates']), Arr::except($account->toArray(), ['id', 'whatsapp_id', 'created_at', 'updated_at']));
        
        $this->assertCount(count($data['phone_numbers']['data']), $account->phoneNumbers);
        $dataPhoneNumber = $data['phone_numbers']['data'][0];
        $phoneNumber = $account->phoneNumbers->first();
        $this->assertEquals($dataPhoneNumber['id'], $phoneNumber->whatsapp_id);
        $this->assertEquals(Arr::get($dataPhoneNumber, 'throughput.level'), $phoneNumber->throughput_level);
        $this->assertEquals(Arr::get($dataPhoneNumber, 'webhook_configuration.application'), $phoneNumber->webhook_configuration_application);
        $expectedDataPhoneNumber = Arr::except($dataPhoneNumber, ['id', 'throughput', 'webhook_configuration']);
        $storedPhoneNumberData = Arr::except($phoneNumber->toArray(), ['id', 'business_account_id', 'created_at', 'updated_at', 'throughput_level', 'webhook_configuration_application', 'whatsapp_id']);
        $this->assertEquals($expectedDataPhoneNumber, $storedPhoneNumberData);

        $this->assertCount(count($data['message_templates']['data']), $account->templates);
        $dataTemplate = $data['message_templates']['data'][0];
        $storedTemplate = $account->templates->first();
        $this->assertEquals($dataTemplate['id'], $storedTemplate->whatsapp_id);
        $dataTemplate = Arr::except($dataTemplate, ['id']);
        $storedTemplateData = Arr::except($storedTemplate->toArray(), ['id', 'business_account_id', 'created_at', 'updated_at', 'whatsapp_id']);
        $this->assertEquals($dataTemplate, $storedTemplateData);


    }
}
