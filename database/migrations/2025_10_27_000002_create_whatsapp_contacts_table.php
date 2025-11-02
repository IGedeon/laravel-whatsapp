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
            $table->string('wa_id')->index();
            $table->string('name')->nullable();
            $table->string('profile_pic_url')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('last_messages_received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
