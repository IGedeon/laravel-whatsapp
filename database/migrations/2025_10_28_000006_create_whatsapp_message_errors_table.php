<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('whatsapp_messages')->cascadeOnDelete();
            $table->string('code')->nullable()->index();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->json('error_data')->nullable();
            $table->string('href')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_errors');
    }
};
