<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('message_send_ttl_seconds')->nullable();
            $table->string('parameter_format')->nullable();
            $table->json('components')->nullable();
            $table->string('language')->nullable();
            $table->string('status')->nullable();
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('whatsapp_id')->nullable();
            $table->unsignedBigInteger('business_account_id')->nullable();
            $table->timestamps();

            $table->foreign('business_account_id')
                ->references('id')
                ->on('whatsapp_business_accounts')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
