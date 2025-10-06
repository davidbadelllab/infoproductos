<?php

namespace App\Http\Controllers;

use App\Models\ClonedAd;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClonedAdsController extends Controller
{
    /**
     * Listar todos los anuncios clonados del usuario
     */
    public function index()
    {
        $clonedAds = ClonedAd::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ad) {
                return [
                    'id' => $ad->id,
                    'uuid' => $ad->uuid,
                    'page_name' => $ad->page_name,
                    'country' => $ad->country,
                    'price' => $ad->price,
                    'original_copy' => $ad->original_copy,
                    'generated_copy' => $ad->generated_copy,
                    'image_url' => $ad->image_url,
                    'video_url' => $ad->video_url,
                    'has_image' => !empty($ad->image_path),
                    'has_video' => !empty($ad->video_path),
                    'created_at' => $ad->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $ad->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('cloned-ads/index', [
            'clonedAds' => $clonedAds,
        ]);
    }

    /**
     * Ver/editar un anuncio clonado específico
     */
    public function show(string $uuid)
    {
        $clonedAd = ClonedAd::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Ya no convertimos a base64, usamos directamente la URL pública para mejor rendimiento
        return Inertia::render('cloned-ads/show', [
            'clonedAd' => [
                'id' => $clonedAd->id,
                'uuid' => $clonedAd->uuid,
                'page_name' => $clonedAd->page_name,
                'country' => $clonedAd->country,
                'price' => $clonedAd->price,
                'original_copy' => $clonedAd->original_copy,
                'generated_copy' => $clonedAd->generated_copy,
                'image_url' => $clonedAd->image_url,
                'video_url' => $clonedAd->video_url,
                'has_image' => !empty($clonedAd->image_path),
                'has_video' => !empty($clonedAd->video_path),
                'created_at' => $clonedAd->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $clonedAd->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Actualizar el copy de un anuncio clonado
     */
    public function update(Request $request, string $uuid)
    {
        $clonedAd = ClonedAd::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'generated_copy' => ['required', 'string'],
            'page_name' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $clonedAd->update($validated);

        return back()->with('success', 'Anuncio actualizado correctamente');
    }

    /**
     * Actualizar la imagen editada
     */
    public function updateImage(Request $request, string $uuid)
    {
        $clonedAd = ClonedAd::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'image' => ['required', 'string'], // base64
        ]);

        // Decodificar base64
        $imageData = $validated['image'];
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $imageData = base64_decode($imageData);
        } else {
            return response()->json(['message' => 'Formato de imagen inválido'], 400);
        }

        // Eliminar imagen anterior
        if ($clonedAd->image_path && \Storage::disk('public')->exists($clonedAd->image_path)) {
            \Storage::disk('public')->delete($clonedAd->image_path);
        }

        // Guardar nueva imagen
        $fileName = uniqid('img_') . '_' . time() . '.png';
        $path = 'cloned-ads/' . $fileName;
        \Storage::disk('public')->put($path, $imageData);

        $clonedAd->update(['image_path' => $path]);

        return response()->json([
            'success' => true,
            'image_url' => $clonedAd->image_url,
        ]);
    }

    /**
     * Actualizar o registrar el video generado/seleccionado
     */
    public function updateVideo(Request $request, string $uuid)
    {
        $clonedAd = ClonedAd::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'video' => ['nullable', 'string'], // base64 data URL
            'video_url' => ['nullable', 'string'],
        ]);

        // Si viene URL directa, solo guardarla y salir
        if (!empty($validated['video_url'])) {
            $clonedAd->update(['video_path' => null]);
            return response()->json([
                'success' => true,
                'video_url' => $validated['video_url'],
            ]);
        }

        if (empty($validated['video'])) {
            return response()->json(['message' => 'Se requiere video o video_url'], 422);
        }

        $videoData = $validated['video'];
        if (preg_match('/^data:video\/(\w+);base64,/', $videoData)) {
            $videoData = substr($videoData, strpos($videoData, ',') + 1);
            $videoData = base64_decode($videoData);
        } else {
            return response()->json(['message' => 'Formato de video inválido'], 400);
        }

        // Eliminar video anterior si existe
        if ($clonedAd->video_path && \Storage::disk('public')->exists($clonedAd->video_path)) {
            \Storage::disk('public')->delete($clonedAd->video_path);
        }

        $fileName = uniqid('vid_') . '_' . time() . '.mp4';
        $path = 'cloned-ads/' . $fileName;
        \Storage::disk('public')->put($path, $videoData);

        $clonedAd->update(['video_path' => $path]);

        return response()->json([
            'success' => true,
            'video_url' => $clonedAd->video_url,
        ]);
    }

    /**
     * Generar video directamente con Replicate (bytedance/seedance-1-pro)
     */
    public function generateVideo(Request $request, string $uuid)
    {
        set_time_limit(300); // 5 minutos

        $clonedAd = ClonedAd::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        try {
            $replicateToken = config('services.replicate.api_token');

            if (!$replicateToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'API token de Replicate no configurado.',
                ], 500);
            }

            $http = \Illuminate\Support\Facades\Http::timeout(180);

            if (config('app.env') === 'local') {
                $http = $http->withOptions(['verify' => false]);
            }

            \Log::info('🎬 Iniciando generación de video con Replicate', [
                'uuid' => $uuid,
                'page_name' => $clonedAd->page_name,
            ]);

            // Crear prompt para el video basado en el anuncio
            $videoPrompt = $this->generateVideoPrompt($clonedAd);

            \Log::info('📝 Prompt generado para video', ['prompt' => $videoPrompt]);

            // PASO 1: Iniciar generación de video con bytedance/seedance-1-pro
            $replicateResponse = $http
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $replicateToken,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(60)
                ->post('https://api.replicate.com/v1/models/bytedance/seedance-1-pro/predictions', [
                    'input' => [
                        'fps' => 24,
                        'prompt' => $videoPrompt,
                        'duration' => 5,
                        'resolution' => '1080p',
                        'aspect_ratio' => '16:9',
                        'camera_fixed' => false,
                    ]
                ]);

            if (!$replicateResponse->successful()) {
                \Log::error('❌ Replicate API call failed', [
                    'status' => $replicateResponse->status(),
                    'body' => $replicateResponse->json()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al iniciar generación de video: ' . ($replicateResponse->json('detail') ?? 'Error desconocido'),
                ], 500);
            }

            $predictionId = $replicateResponse->json('id');
            $predictionUrl = $replicateResponse->json('urls.get');

            \Log::info('⏳ Predicción iniciada en Replicate', [
                'prediction_id' => $predictionId,
                'url' => $predictionUrl
            ]);

            // PASO 2: Polling para verificar el estado (máximo 90 intentos = 3 minutos)
            $maxAttempts = 90;
            $attempt = 0;
            $videoUrl = null;

            while ($attempt < $maxAttempts) {
                sleep(3); // Aumentado a 3 segundos para dar más tiempo

                try {
                    // Crear un nuevo HTTP client para cada petición de polling
                    $pollingHttp = \Illuminate\Support\Facades\Http::timeout(30);

                    if (config('app.env') === 'local') {
                        $pollingHttp = $pollingHttp->withOptions(['verify' => false]);
                    }

                    $statusResponse = $pollingHttp
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $replicateToken,
                        ])
                        ->get($predictionUrl);

                    // Verificar si la respuesta HTTP fue exitosa
                    if (!$statusResponse->successful()) {
                        \Log::error('❌ HTTP request failed', [
                            'status_code' => $statusResponse->status(),
                            'body' => $statusResponse->body()
                        ]);
                        $attempt++;
                        continue;
                    }

                    $responseData = $statusResponse->json();
                    $status = $responseData['status'] ?? null;

                    // Log completo de la respuesta para debugging
                    \Log::info('🔄 Replicate polling', [
                        'attempt' => $attempt + 1,
                        'status' => $status,
                        'http_status' => $statusResponse->status(),
                        'response' => $responseData
                    ]);

                    if ($status === 'succeeded') {
                        $videoUrl = $responseData['output'] ?? null;
                        \Log::info('✅ Video generado exitosamente', [
                            'url' => $videoUrl,
                            'metrics' => $responseData['metrics'] ?? null
                        ]);
                        break;
                    } elseif ($status === 'failed') {
                        $error = $responseData['error'] ?? 'Error desconocido';
                        \Log::error('❌ Generación de video falló', ['error' => $error]);
                        return response()->json([
                            'success' => false,
                            'message' => 'La generación del video falló: ' . $error,
                        ], 500);
                    } elseif ($status === 'canceled') {
                        \Log::error('❌ Generación de video cancelada');
                        return response()->json([
                            'success' => false,
                            'message' => 'La generación del video fue cancelada',
                        ], 500);
                    }
                } catch (\Exception $e) {
                    \Log::error('❌ Excepción en polling', [
                        'attempt' => $attempt + 1,
                        'error' => $e->getMessage()
                    ]);
                }

                $attempt++;
            }

            if (!$videoUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timeout esperando la generación del video. Intenta de nuevo en unos minutos.',
                ], 408);
            }

            // PASO 3: Descargar y guardar el video
            \Log::info('💾 Descargando video generado', ['url' => $videoUrl]);

            $videoHttp = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->timeout(120);

            if (config('app.env') === 'local') {
                $videoHttp = $videoHttp->withOptions(['verify' => false]);
            }

            $videoDownload = $videoHttp->get($videoUrl);

            if ($videoDownload->successful() && strlen($videoDownload->body()) > 0) {
                // Eliminar video anterior si existe
                if ($clonedAd->video_path && \Storage::disk('public')->exists($clonedAd->video_path)) {
                    \Storage::disk('public')->delete($clonedAd->video_path);
                }

                // Guardar nuevo video
                $fileName = uniqid('vid_') . '_' . time() . '.mp4';
                $path = 'cloned-ads/' . $fileName;
                \Storage::disk('public')->put($path, $videoDownload->body());

                $clonedAd->update(['video_path' => $path]);

                \Log::info('✅ Video guardado exitosamente', [
                    'path' => $path,
                    'size' => strlen($videoDownload->body())
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Video generado y guardado correctamente',
                    'video_url' => $clonedAd->video_url,
                ]);
            }

            // Si falla la descarga, retornar error
            \Log::error('❌ No se pudo descargar el video');
            return response()->json([
                'success' => false,
                'message' => 'No se pudo descargar el video generado',
            ], 500);

        } catch (\Exception $e) {
            \Log::error('❌ Error en generación de video', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el video: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar prompt descriptivo para el video basado en el anuncio
     */
    private function generateVideoPrompt(ClonedAd $clonedAd): string
    {
        $pageName = $clonedAd->page_name;
        $copy = $clonedAd->generated_copy;
        $country = $clonedAd->country ?? 'international';

        // Extraer las primeras frases del copy (más contexto)
        $copyLines = explode("\n", $copy);
        $mainMessage = '';
        $lineCount = 0;

        foreach ($copyLines as $line) {
            $cleanLine = trim($line);
            if (!empty($cleanLine) && $lineCount < 3) {
                $mainMessage .= $cleanLine . ' ';
                $lineCount++;
            }
        }

        // Crear un prompt cinematográfico más detallado
        $prompt = sprintf(
            '[Cinematic commercial shot] Professional marketing video showcasing %s in %s. %s [Dynamic follow shots] Vibrant colors, modern aesthetic, smooth camera movements. High-quality commercial production with engaging transitions showing the business environment and atmosphere. [Wide establishing shot to close-up details]',
            $this->cleanForPrompt($pageName),
            $country,
            substr($this->cleanForPrompt($mainMessage), 0, 250)
        );

        return $prompt;
    }

    /**
     * Limpiar texto para usar en prompts (eliminar caracteres especiales)
     */
    private function cleanForPrompt(string $text): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
        return trim($cleaned);
    }

    /**
     * Eliminar un anuncio clonado
     */
    public function destroy(string $uuid)
    {
        $clonedAd = ClonedAd::where('uuid', $uuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Eliminar archivos asociados
        if ($clonedAd->image_path && \Storage::disk('public')->exists($clonedAd->image_path)) {
            \Storage::disk('public')->delete($clonedAd->image_path);
        }

        if ($clonedAd->video_path && \Storage::disk('public')->exists($clonedAd->video_path)) {
            \Storage::disk('public')->delete($clonedAd->video_path);
        }

        $clonedAd->delete();

        return redirect()->route('cloned-ads.index')
            ->with('success', 'Anuncio eliminado correctamente');
    }
}
