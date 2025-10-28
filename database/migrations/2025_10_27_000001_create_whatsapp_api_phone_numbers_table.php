<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_api_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('display_phone_number');
            $table->string('phone_number_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_api_phone_numbers');
    }
};
