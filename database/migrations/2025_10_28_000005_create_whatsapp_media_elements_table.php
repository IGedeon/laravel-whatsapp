<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_media_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('whatsapp_messages')->cascadeOnDelete();
            $table->string('wa_media_id')->nullable()->index();
            $table->string('url')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('sha256')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('filename')->nullable();
            $table->foreignId('api_phone_number_id')->constrained('whatsapp_api_phone_numbers')->cascadeOnDelete();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_media_elements');
    }
};
