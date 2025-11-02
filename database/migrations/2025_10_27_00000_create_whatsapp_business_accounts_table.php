<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_business_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_id')->unique();
            $table->string('name')->nullable();
            $table->string('currency')->nullable();
            $table->string('timezone_id')->nullable();
            $table->string('message_template_namespace')->nullable();
            $table->string('access_token')->nullable();
            $table->json('subscribed_apps')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_business_accounts');
    }
};
