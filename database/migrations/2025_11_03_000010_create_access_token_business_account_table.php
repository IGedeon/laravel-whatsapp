<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('whatsapp_business_tokens');

        Schema::create('whatsapp_business_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('access_token_id');
            $table->unsignedBigInteger('business_account_id');
            $table->timestamps();

            $table->foreign('access_token_id')->references('id')->on('whatsapp_access_tokens')->onDelete('cascade');
            $table->foreign('business_account_id')->references('id')->on('whatsapp_business_accounts')->onDelete('cascade');
            $table->unique(['access_token_id', 'business_account_id'], 'whatsapp_business_tokens_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_business_tokens');
    }
};
