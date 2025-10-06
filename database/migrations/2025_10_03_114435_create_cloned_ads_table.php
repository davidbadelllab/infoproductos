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
        Schema::create('cloned_ads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facebook_ad_id')->nullable()->constrained()->nullOnDelete();

            // Datos del anuncio
            $table->string('page_name');
            $table->string('country', 10);
            $table->decimal('price', 10, 2)->nullable();
            $table->text('original_copy');
            $table->text('generated_copy');

            // Archivos generados
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();

            // Metadata
            $table->json('replicate_images')->nullable(); // URLs de imágenes de Replicate
            $table->string('creatomate_render_id')->nullable();

            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cloned_ads');
    }
};
