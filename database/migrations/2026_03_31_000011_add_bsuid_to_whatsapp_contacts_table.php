<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            // wa_id is now optional: omitted in webhooks when user adopts a username
            $table->string('wa_id')->nullable()->change();

            // BSUID: unique per (business portfolio, user). Always present in webhooks from 2026-03-31.
            // Format: "CC.ALPHANUMERIC" (e.g. US.13491208655302741918)
            $table->string('user_id')->nullable()->index()->after('wa_id');

            // WhatsApp username (optional feature for users, replaces phone in UI)
            $table->string('username')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'username']);
            $table->string('wa_id')->nullable(false)->change();
        });
    }
};
