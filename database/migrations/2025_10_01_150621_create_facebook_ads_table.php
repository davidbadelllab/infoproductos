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
        Schema::create('facebook_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_search_id')->nullable()->constrained()->onDelete('cascade');

            // Información básica de la página
            $table->string('page_name');
            $table->string('page_url')->nullable();
            $table->text('ads_library_url')->nullable();

            // Contenido del anuncio
            $table->text('ad_text')->nullable();
            $table->text('ad_image_url')->nullable();
            $table->text('ad_video_url')->nullable();

            // Métricas
            $table->integer('ads_count')->default(1);
            $table->integer('days_running')->default(0);

            // Targeting
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->json('platforms')->nullable();
            $table->json('demographics')->nullable();

            // Análisis de contenido
            $table->boolean('has_whatsapp')->default(false);
            $table->string('whatsapp_number')->nullable();
            $table->json('matched_keywords')->nullable();
            $table->string('search_keyword')->nullable();

            // Clasificación
            $table->boolean('is_winner')->default(false);
            $table->boolean('is_potential')->default(false);

            // IDs únicos de Facebook
            $table->string('ad_id')->nullable()->unique();
            $table->string('page_id')->nullable();
            $table->string('library_id')->nullable();

            // Fechas de Facebook
            $table->timestamp('ad_start_date')->nullable();
            $table->timestamp('ad_end_date')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('creation_date')->nullable();

            // Información técnica de Facebook
            $table->timestamp('ad_delivery_start_time')->nullable();
            $table->timestamp('ad_delivery_stop_time')->nullable();
            $table->integer('total_running_time')->nullable();
            $table->json('ad_spend')->nullable();
            $table->json('impressions')->nullable();
            $table->json('targeting_info')->nullable();
            $table->string('ad_type')->nullable();
            $table->string('ad_format')->nullable();
            $table->string('ad_status')->nullable();

            // Metadatos
            $table->boolean('is_real_data')->default(false);
            $table->boolean('is_apify_data')->default(false);
            $table->string('data_source')->nullable();
            $table->json('raw_data')->nullable();

            $table->timestamps();

            // Índices para búsqueda
            $table->index('page_name');
            $table->index('country_code');
            $table->index('is_winner');
            $table->index('is_potential');
            $table->index('has_whatsapp');
            $table->index('search_keyword');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facebook_ads');
    }
};
