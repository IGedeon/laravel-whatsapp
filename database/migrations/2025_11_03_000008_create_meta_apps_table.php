<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('whatsapp_meta_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('meta_app_id')->unique();
            $table->string('app_secret');
            $table->string('verify_token');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_meta_apps');
    }
};
