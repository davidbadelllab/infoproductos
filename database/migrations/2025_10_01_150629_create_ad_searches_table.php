<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ad_searches')) {
            return;
        }

        Schema::create('ad_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            // Parámetros de búsqueda
            $table->json('keywords');
            $table->json('countries');
            $table->json('selected_keywords')->nullable();
            $table->integer('days_back')->default(30);

            // Configuración de filtros
            $table->integer('min_ads')->default(10);
            $table->integer('min_days_running')->default(30);
            $table->integer('min_ads_for_long_running')->default(5);

            // Resultados
            $table->integer('total_results')->default(0);
            $table->integer('winners_count')->default(0);
            $table->integer('potential_count')->default(0);

            // Metadatos
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->string('data_source')->nullable(); // simulated, facebook_api, apify
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time')->nullable(); // segundos

            $table->timestamps();

            // Índices
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_searches');
    }
};
