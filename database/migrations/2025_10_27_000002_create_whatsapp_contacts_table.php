<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_phone_id')->constrained('whatsapp_api_phone_numbers')->onDelete('cascade');
            $table->string('wa_id')->index();
            $table->string('name')->nullable();
            $table->string('profile_pic_url')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('last_messages_received_at')->nullable();
            $table->timestamps();
            $table->index(['api_phone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
