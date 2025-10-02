<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'gemini_api_key')) {
                // Si apify_actor_id existe, colocar después; si no, al final
                if (Schema::hasColumn('users', 'apify_actor_id')) {
                    $table->string('gemini_api_key')->nullable()->after('apify_actor_id');
                } else {
                    $table->string('gemini_api_key')->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'gemini_api_key')) {
                $table->dropColumn('gemini_api_key');
            }
        });
    }
};


