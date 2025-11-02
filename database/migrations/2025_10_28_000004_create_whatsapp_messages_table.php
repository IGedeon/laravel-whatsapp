<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            // Assumptions:
            // - 'direction', 'type', 'status' stored as string enums from LaravelWhatsApp\Enums
            // - 'content' and 'context' are JSON payloads for message body and metadata
            // - Pricing fields are nullable because not all messages are billable or priced the same
            // - 'timestamp' is the original message creation time from WhatsApp API
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('whatsapp_contacts')->nullOnDelete();
            $table->foreignId('api_phone_number_id')->nullable()->constrained('whatsapp_api_phone_numbers')->nullOnDelete();
            $table->string('direction')->index();
            $table->string('wa_message_id')->nullable()->unique();
            $table->timestamp('timestamp')->nullable()->index();
            $table->string('type')->nullable()->index();
            $table->json('content')->nullable();
            $table->string('status')->nullable()->index();
            $table->timestamp('status_timestamp')->nullable();
            // Removed conversation_id FK until whatsapp_conversations table is implemented
            // $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->boolean('pricing_billable')->nullable();
            $table->string('pricing_model')->nullable();
            $table->string('pricing_type')->nullable();
            $table->string('pricing_category')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
