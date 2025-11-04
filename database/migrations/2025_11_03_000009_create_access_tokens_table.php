<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('whatsapp_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp_id')->unique();
            $table->string('name');
            $table->string('access_token');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            
            $table->foreignId('meta_app_id')->nullable()->constrained('whatsapp_meta_apps')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_access_tokens');
    }
};
