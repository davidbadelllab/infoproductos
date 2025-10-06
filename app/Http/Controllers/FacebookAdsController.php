<?php

namespace App\Http\Controllers;

use App\Services\FacebookAdsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacebookAdsController extends Controller
{
    public function __construct(
        private FacebookAdsService $facebookAdsService
    ) {}

    /**
     * Mostrar formulario de búsqueda
     */
    public function index()
    {
        $searches = $this->facebookAdsService->getUserSearches(
            auth()->id(),
            10
        );

        return Inertia::render('facebook-ads/index', [
            'searches' => $searches,
        ]);
    }

    /**
     * Ejecutar búsqueda de anuncios
     */
    public function search(Request $request)
    {
        // Aumentar tiempo de ejecución para Apify
        set_time_limit(300); // 5 minutos
        ini_set('max_execution_time', '300');

        $validated = $request->validate([
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'required|string|max:255',
            'countries' => 'required|array|min:1',
            'countries.*' => 'required|string|size:2',
            'selectedKeywords' => 'nullable|array',
            'selectedKeywords.*' => 'string',
            'daysBack' => 'nullable|integer|min:1|max:365',
            'minAds' => 'nullable|integer|min:1',
            'minDaysRunning' => 'nullable|integer|min:1',
            'minAdsForLongRunning' => 'nullable|integer|min:1',
            'dataSource' => 'required|in:apify,simulated',
        ]);

        try {
            $adSearch = $this->facebookAdsService->searchAds(
                $validated,
                auth()->id()
            );

            return redirect()->route('facebook-ads.show', $adSearch->id)
                ->with('success', 'Búsqueda completada exitosamente');
        } catch (\Exception $e) {
            return back()->withErrors([
                'search' => 'Error al realizar la búsqueda: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Ver detalles de una búsqueda
     */
    public function show(int $id)
    {
        $search = $this->facebookAdsService->getSearchDetails(
            $id,
            auth()->id()
        );

        if (!$search) {
            abort(404, 'Búsqueda no encontrada');
        }

        // Debug: verificar que los anuncios tengan IDs
        foreach ($search->facebookAds as $ad) {
            if (!$ad->id) {
                \Log::error('FacebookAd sin ID:', ['ad' => $ad->toArray()]);
            }
        }

        return Inertia::render('facebook-ads/show', [
            'search' => $search,
        ]);
    }

    /**
     * Ver detalles de un anuncio específico
     */
    public function showAd(int $searchId, int $adId)
    {
        $ad = \App\Models\FacebookAd::where('id', $adId)
            ->whereHas('adSearch', function ($q) use ($searchId) {
                $q->where('id', $searchId)
                  ->where('user_id', auth()->id());
            })
            ->with('adSearch')
            ->first();

        if (!$ad) {
            abort(404, 'Anuncio no encontrado');
        }

        return Inertia::render('facebook-ads/show-ad', [
            'ad' => $ad,
            'search' => $ad->adSearch,
        ]);
    }

    /**
     * Generar texto para post usando Gemini
     */
    public function generateCopy(\Illuminate\Http\Request $request)
    {
        // Aumentar tiempo de ejecución para generación de imagen
        set_time_limit(120); // 2 minutos

        $validated = $request->validate([
            'page_name' => ['required', 'string'],
            'ad_text' => ['required', 'string'],
            'country' => ['required', 'string', 'size:2'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $user = auth()->user();
        $user->makeVisible('gemini_api_key');
        $apiKey = $user->gemini_api_key ?? config('services.gemini.key');
        if (!$apiKey) {
            return response()->json([
                'message' => 'Falta configurar la API key de Gemini en tus Settings',
            ], 422);
        }

        $prompt = sprintf(
            'Genera un copy de venta en español para Facebook basándote en este texto original (ajústalo, mejora la persuasión, añade emojis adecuados y CTA), manteniendo el nombre de la marca/página. País objetivo: %s. Precio de oferta: %s. Texto original:\n---\n%s\n---\nNombre de la página: %s. Devuelve solo el texto final, sin explicaciones.',
            strtoupper($validated['country']),
            number_format($validated['price'], 2),
            $validated['ad_text'],
            $validated['page_name']
        );

        try {
            $http = \Illuminate\Support\Facades\Http::acceptJson()
                ->timeout(60);

            // Deshabilitar verificación SSL en desarrollo (para solucionar problemas de certificados en Windows)
            if (config('app.env') === 'local') {
                $http = $http->withOptions([
                    'verify' => false,
                ]);
            }

            // La API de Gemini requiere la key como parámetro de query, no como header
            // Usar v1beta con gemini-2.5-flash
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

            $payload = [
                'contents' => [[
                    'parts' => [[ 'text' => $prompt ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 8192,  // Aumentado significativamente
                    'topP' => 0.95,
                    'topK' => 40,
                    'responseModalities' => ['TEXT'],  // Solo texto
                ],
            ];

            \Log::info('Gemini API Request', [
                'url' => str_replace($apiKey, 'HIDDEN', $url),
                'payload' => $payload
            ]);

            $response = $http->post($url, $payload);

            if ($response->failed()) {
                \Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => str_replace($apiKey, 'HIDDEN', $url)
                ]);

                // Mensaje específico para sobrecarga (503)
                if ($response->status() === 503) {
                    return response()->json([
                        'message' => 'El sistema de IA está sobrecargado. Por favor, espera 2 minutos y vuelve a intentar clonar este anuncio.',
                    ], 503);
                }

                return response()->json([
                    'message' => 'No se pudo generar el copy con Gemini: ' . $response->json('error.message', 'Error desconocido'),
                ], 500);
            }

            $data = $response->json();
            $text = data_get($data, 'candidates.0.content.parts.0.text');

            if (!$text) {
                \Log::error('Gemini response without text', [
                    'full_response' => $data,
                    'status' => $response->status()
                ]);

                return response()->json([
                    'message' => 'No se pudo generar el texto. Verifica tu API key de Gemini en Settings.',
                ], 500);
            }

            // Paso 2: Generar imagen con DALL-E 3 basada en el copy y las guías de diseño
            $openAiKey = config('services.openai.api_key');

            if ($openAiKey) {
                try {
                    // Crear prompt estructurado siguiendo FACEBOOK_ADS_PROMPT_GUIDE.md
                    $imagePrompt = $this->generateFacebookAdImagePrompt($text, $validated);

                    \Log::info('DALL-E Request', ['prompt' => $imagePrompt]);

                    $dalleResponse = $http
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $openAiKey,
                            'Content-Type' => 'application/json',
                        ])
                        ->timeout(90)
                        ->post('https://api.openai.com/v1/images/generations', [
                            'model' => 'dall-e-3',
                            'prompt' => $imagePrompt,
                            'n' => 1,
                            'size' => '1024x1024',
                            'quality' => 'standard',
                        ]);

                    \Log::info('DALL-E Response', [
                        'status' => $dalleResponse->status(),
                        'body' => $dalleResponse->json()
                    ]);

                    if ($dalleResponse->successful()) {
                        $imageUrl = $dalleResponse->json('data.0.url');

                        if ($imageUrl) {
                            // Descargar la imagen y convertirla a base64
                            try {
                                // Crear cliente HTTP limpio sin Authorization header para descargar desde Azure
                                $imageHttp = \Illuminate\Support\Facades\Http::withHeaders([
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                ])
                                ->timeout(90);

                                if (config('app.env') === 'local') {
                                    $imageHttp = $imageHttp->withOptions(['verify' => false]);
                                }

                                $imageData = $imageHttp->get($imageUrl);

                                \Log::info('Image Download Response', [
                                    'status' => $imageData->status(),
                                    'size' => strlen($imageData->body()),
                                    'successful' => $imageData->successful()
                                ]);

                                if ($imageData->successful() && strlen($imageData->body()) > 0) {
                                    $imageBase64 = base64_encode($imageData->body());
                                    $generatedImage = 'data:image/png;base64,' . $imageBase64;

                                    \Log::info('Image successfully converted to base64', [
                                        'base64_length' => strlen($imageBase64)
                                    ]);

                                    // Guardar imagen en disco
                                    $imagePath = $this->saveImageToStorage($imageData->body(), 'cloned-ads');

                                    // Crear registro de anuncio clonado
                                    $clonedAd = \App\Models\ClonedAd::create([
                                        'user_id' => auth()->id(),
                                        'page_name' => $validated['page_name'],
                                        'country' => $validated['country'],
                                        'price' => $validated['price'],
                                        'original_copy' => $validated['ad_text'],
                                        'generated_copy' => $text,
                                        'image_path' => $imagePath,
                                    ]);

                                    return response()->json([
                                        'generated' => $text,
                                        'image' => $generatedImage,
                                        'cloned_ad_uuid' => $clonedAd->uuid,
                                        'image_url' => $clonedAd->image_url,
                                    ]);
                                }
                            } catch (\Exception $imageDownloadException) {
                                \Log::error('Exception downloading image from DALL-E URL', [
                                    'error' => $imageDownloadException->getMessage(),
                                    'url' => $imageUrl
                                ]);
                            }
                        }
                    } else {
                        \Log::error('DALL-E API call failed', [
                            'status' => $dalleResponse->status(),
                            'body' => $dalleResponse->json()
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('DALL-E generation failed', ['error' => $e->getMessage()]);
                }
            }

            // Si falla la imagen o no hay clave de OpenAI, devolver solo texto
            return response()->json([
                'generated' => $text,
                'image' => null,
                'warning' => 'Texto generado correctamente. La generación de imagen no está disponible en este momento.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Gemini API exception', [ 'error' => $e->getMessage() ]);
            return response()->json([
                'message' => 'Error al comunicarse con Gemini',
            ], 500);
        }
    }

    /**
     * Regenerar solo la imagen
     */
    public function regenerateImage(\Illuminate\Http\Request $request)
    {
        // Aumentar tiempo de ejecución para generación de imagen
        set_time_limit(120); // 2 minutos

        $validated = $request->validate([
            'page_name' => ['required', 'string'],
            'ad_text' => ['required', 'string'],
            'country' => ['required', 'string', 'size:2'],
            'price' => ['required', 'numeric', 'min:0'],
            'cloned_ad_uuid' => ['nullable', 'string'],
        ]);

        try {
            $http = \Illuminate\Support\Facades\Http::acceptJson()->timeout(60);

            if (config('app.env') === 'local') {
                $http = $http->withOptions(['verify' => false]);
            }

            $openaiKey = config('services.openai.api_key');

            if (!$openaiKey) {
                return response()->json([
                    'message' => 'API key de OpenAI no configurada.',
                ], 500);
            }

            // Usar el copy generado (ad_text) para crear un prompt estructurado
            // siguiendo FACEBOOK_ADS_PROMPT_GUIDE.md
            $imagePrompt = $this->generateFacebookAdImagePrompt($validated['ad_text'], $validated);

            \Log::info('DALL-E Regenerate Request', [
                'prompt' => $imagePrompt,
                'based_on_copy' => substr($validated['ad_text'], 0, 100)
            ]);

            $dalleResponse = $http
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $openaiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => 'dall-e-3',
                    'prompt' => $imagePrompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'standard',
                ]);

            \Log::info('DALL-E Regenerate Response', [
                'status' => $dalleResponse->status(),
                'body' => $dalleResponse->json()
            ]);

            if ($dalleResponse->successful()) {
                $imageUrl = $dalleResponse->json('data.0.url');

                \Log::info('DALL-E Regenerate Image URL', ['url' => $imageUrl]);

                if ($imageUrl) {
                    // Descargar la imagen y convertirla a base64
                    try {
                        // Crear un cliente HTTP limpio (sin headers de autorización previos)
                        $imageHttp = \Illuminate\Support\Facades\Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        ])
                        ->timeout(90);

                        if (config('app.env') === 'local') {
                            $imageHttp = $imageHttp->withOptions(['verify' => false]);
                        }

                        $imageData = $imageHttp->get($imageUrl);

                        \Log::info('Image Regenerate Download Response', [
                            'status' => $imageData->status(),
                            'size' => strlen($imageData->body()),
                            'successful' => $imageData->successful()
                        ]);

                        if ($imageData->successful() && strlen($imageData->body()) > 0) {
                            $imageBase64 = base64_encode($imageData->body());
                            $generatedImage = 'data:image/png;base64,' . $imageBase64;

                            \Log::info('Image regenerate successfully converted to base64', [
                                'base64_length' => strlen($imageBase64)
                            ]);

                            // Guardar imagen en disco
                            $imagePath = $this->saveImageToStorage($imageData->body(), 'cloned-ads');

                            // Actualizar o crear registro de anuncio clonado
                            if (!empty($validated['cloned_ad_uuid'])) {
                                $clonedAd = \App\Models\ClonedAd::where('uuid', $validated['cloned_ad_uuid'])
                                    ->where('user_id', auth()->id())
                                    ->first();

                                if ($clonedAd) {
                                    // Eliminar imagen anterior si existe
                                    if ($clonedAd->image_path && \Storage::disk('public')->exists($clonedAd->image_path)) {
                                        \Storage::disk('public')->delete($clonedAd->image_path);
                                    }

                                    $clonedAd->update(['image_path' => $imagePath]);
                                    $imageUrl = $clonedAd->image_url;
                                } else {
                                    $imageUrl = asset('storage/' . $imagePath);
                                }
                            } else {
                                $imageUrl = asset('storage/' . $imagePath);
                            }

                            return response()->json([
                                'image' => $generatedImage,
                                'image_url' => $imageUrl,
                            ]);
                        } else {
                            \Log::error('Failed to download regenerated image', [
                                'status' => $imageData->status(),
                                'body_length' => strlen($imageData->body())
                            ]);
                        }
                    } catch (\Exception $imageDownloadException) {
                        \Log::error('Exception downloading regenerated image', [
                            'error' => $imageDownloadException->getMessage(),
                            'url' => $imageUrl
                        ]);
                    }
                } else {
                    \Log::error('No image URL in DALL-E regenerate response');
                }
            } else {
                \Log::error('DALL-E regenerate API call failed', [
                    'status' => $dalleResponse->status(),
                    'body' => $dalleResponse->json()
                ]);
            }

            return response()->json([
                'message' => 'No se pudo generar la imagen con DALL-E. Inténtalo de nuevo.',
            ], 500);

        } catch (\Exception $e) {
            \Log::error('DALL-E regeneration failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Error al generar la imagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar video con Creatomate
     */
    public function generateVideo(\Illuminate\Http\Request $request)
    {
        set_time_limit(300); // 5 minutos

        $validated = $request->validate([
            'image' => ['required', 'string'], // Base64 image (usado como referencia)
            'text1' => ['nullable', 'string'],
            'text2' => ['nullable', 'string'],
            'page_name' => ['nullable', 'string'], // Para generar prompts descriptivos
        ]);

        try {
            $replicateToken = config('services.replicate.api_token');
            $creatomateApiKey = config('services.creatomate.api_key');
            $templateId = config('services.creatomate.template_id');

            if (!$replicateToken) {
                return response()->json([
                    'message' => 'API token de Replicate no configurado.',
                ], 500);
            }

            if (!$creatomateApiKey) {
                return response()->json([
                    'message' => 'API key de Creatomate no configurada.',
                ], 500);
            }

            $http = \Illuminate\Support\Facades\Http::timeout(180);

            if (config('app.env') === 'local') {
                $http = $http->withOptions(['verify' => false]);
            }

            \Log::info('Video Generation Started', [
                'text1' => $validated['text1'] ?? 'Default Text',
                'text2' => $validated['text2'] ?? 'Default Text 2'
            ]);

            // Paso 1: Generar hasta 6 imágenes con Replicate (minimax/image-01)
            $numImages = 6;
            $images = [];

            $basePrompt = sprintf(
                'Professional marketing image for %s. Modern design, vibrant colors, eye-catching composition. High quality, commercial photography style.',
                $this->cleanForPrompt($validated['page_name'] ?? $validated['text1'] ?? 'product')
            );

            \Log::info('Starting Replicate image generation', ['num_images' => $numImages]);

            // Generar múltiples imágenes secuencialmente con delay
            $predictionIds = [];
            for ($i = 0; $i < $numImages; $i++) {
                try {
                    // Delay de 1 segundo entre cada solicitud para respetar rate limit
                    if ($i > 0) {
                        sleep(1);
                    }

                    // Variar el prompt para cada imagen
                    $prompt = $basePrompt . " Variation " . ($i + 1) . ". Dynamic, engaging, marketing focused.";

                    $replicateResponse = $http
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $replicateToken,
                            'Content-Type' => 'application/json',
                        ])
                        ->timeout(60)
                        ->post('https://api.replicate.com/v1/models/minimax/image-01/predictions', [
                            'input' => [
                                'prompt' => $prompt,
                                'aspect_ratio' => '3:4',
                            ]
                        ]);

                    if ($replicateResponse->successful()) {
                        $predictionIds[] = [
                            'id' => $replicateResponse->json('id'),
                            'url' => $replicateResponse->json('urls.get'),
                        ];
                        \Log::info("Replicate prediction {$i} started", ['id' => $replicateResponse->json('id')]);
                    } else {
                        \Log::error("Replicate prediction {$i} failed to start", [
                            'status' => $replicateResponse->status(),
                            'body' => $replicateResponse->json()
                        ]);
                        // Si falla, no seguir intentando más imágenes
                        break;
                    }
                } catch (\Exception $e) {
                    \Log::error("Exception starting Replicate prediction {$i}", ['error' => $e->getMessage()]);
                    // Si hay excepción, no seguir intentando
                    break;
                }
            }

            // Polling para esperar que todas las predicciones terminen
            $maxAttempts = 60; // 2 minutos máximo
            $attempt = 0;

            while ($attempt < $maxAttempts && count($images) < count($predictionIds)) {
                sleep(2);

                foreach ($predictionIds as $index => $prediction) {
                    // Si ya tenemos esta imagen, skip
                    if (isset($images[$index])) {
                        continue;
                    }

                    try {
                        $statusResponse = $http
                            ->withHeaders([
                                'Authorization' => 'Bearer ' . $replicateToken,
                            ])
                            ->get($prediction['url']);

                        $status = $statusResponse->json('status');

                        if ($status === 'succeeded') {
                            $output = $statusResponse->json('output');
                            $imageUrl = is_array($output) ? $output[0] : $output;

                            if ($imageUrl) {
                                // Descargar y convertir a base64
                                $imageData = $http->withHeaders([
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                ])->get($imageUrl);

                                if ($imageData->successful()) {
                                    $imageBase64 = base64_encode($imageData->body());
                                    $images[$index] = 'data:image/jpeg;base64,' . $imageBase64;
                                    \Log::info("Image {$index} downloaded successfully");
                                }
                            }
                        } elseif ($status === 'failed') {
                            \Log::error("Replicate prediction {$index} failed", ['error' => $statusResponse->json('error')]);
                            // Marcar como fallida para no intentar de nuevo
                            $images[$index] = null;
                        }
                    } catch (\Exception $e) {
                        \Log::error("Exception polling Replicate prediction {$index}", ['error' => $e->getMessage()]);
                    }
                }

                $attempt++;
            }

            // Filtrar imágenes nulas
            $images = array_filter($images);

            if (empty($images)) {
                \Log::warning('No Replicate images generated, using DALL-E image as fallback');
                // Fallback: usar la imagen original de DALL-E
                $images = [$validated['image']];
            } else {
                \Log::info('Replicate images generated', ['count' => count($images)]);
            }

            // Paso 2: Crear el render en Creatomate con las imágenes generadas
            $modifications = [
                'Video.source' => $images[0] ?? $validated['image'], // Primera imagen o fallback
                'Text-1.text' => $validated['text1'] ?? 'Your Text And Video Here',
                'Text-2.text' => $validated['text2'] ?? 'Create & Automate
[size 150%]Video[/size]',
            ];

            // Si el template soporta múltiples imágenes, agregarlas
            foreach ($images as $i => $imageData) {
                $modifications["Image-{$i}.source"] = $imageData;
            }

            $creatomateResponse = $http
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $creatomateApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.creatomate.com/v2/renders', [
                    'template_id' => $templateId,
                    'modifications' => $modifications,
                ]);

            \Log::info('Creatomate Initial Response', [
                'status' => $creatomateResponse->status(),
                'body' => $creatomateResponse->json()
            ]);

            if (!$creatomateResponse->successful()) {
                \Log::error('Creatomate API call failed', [
                    'status' => $creatomateResponse->status(),
                    'body' => $creatomateResponse->json()
                ]);

                return response()->json([
                    'message' => 'Error al iniciar generación de video: ' . ($creatomateResponse->json('error') ?? 'Error desconocido'),
                ], 500);
            }

            $renders = $creatomateResponse->json();
            $renderId = is_array($renders) && isset($renders[0]['id']) ? $renders[0]['id'] : null;

            if (!$renderId) {
                return response()->json([
                    'message' => 'No se recibió ID de render de Creatomate',
                ], 500);
            }

            \Log::info('Creatomate Render Started', ['id' => $renderId]);

            // Paso 2: Polling para verificar el estado (máximo 90 intentos = 3 minutos)
            $maxAttempts = 90;
            $attempt = 0;
            $videoUrl = null;

            while ($attempt < $maxAttempts) {
                sleep(2); // Esperar 2 segundos entre intentos

                $statusResponse = $http
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $creatomateApiKey,
                    ])
                    ->get("https://api.creatomate.com/v2/renders/{$renderId}");

                $renderData = $statusResponse->json();
                $status = $renderData['status'] ?? 'unknown';

                \Log::info('Creatomate Polling Attempt', [
                    'attempt' => $attempt + 1,
                    'status' => $status
                ]);

                if ($status === 'succeeded') {
                    $videoUrl = $renderData['url'] ?? null;
                    break;
                } elseif ($status === 'failed') {
                    return response()->json([
                        'message' => 'La generación del video falló: ' . ($renderData['error_message'] ?? 'Error desconocido'),
                    ], 500);
                }

                $attempt++;
            }

            if (!$videoUrl) {
                return response()->json([
                    'message' => 'Timeout esperando la generación del video. El video puede tardar más de lo esperado, intenta de nuevo en unos minutos.',
                ], 408);
            }

            \Log::info('Creatomate Video Generated', ['url' => $videoUrl]);

            // Descargar el video y convertirlo a base64
            try {
                $videoHttp = \Illuminate\Support\Facades\Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->timeout(120);

                if (config('app.env') === 'local') {
                    $videoHttp = $videoHttp->withOptions(['verify' => false]);
                }

                $videoDownload = $videoHttp->get($videoUrl);

                if ($videoDownload->successful() && strlen($videoDownload->body()) > 0) {
                    $videoBase64 = base64_encode($videoDownload->body());

                    \Log::info('Video successfully downloaded and converted to base64', [
                        'size' => strlen($videoDownload->body()),
                        'base64_length' => strlen($videoBase64)
                    ]);

                    return response()->json([
                        'video_data' => 'data:video/mp4;base64,' . $videoBase64,
                    ]);
                }
            } catch (\Exception $downloadException) {
                \Log::error('Error downloading video from Creatomate URL', ['error' => $downloadException->getMessage()]);
            }

            // Si falla la descarga, devolver la URL directamente
            return response()->json([
                'video_url' => $videoUrl,
            ]);

        } catch (\Exception $e) {
            \Log::error('Creatomate video generation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Error al generar el video: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generar prompt para DALL-E basado en el copy y las guías de diseño de Facebook Ads
     */
    private function generateFacebookAdImagePrompt(string $generatedCopy, array $validated): string
    {
        // Extraer información clave SOLO del copy generado (NO del nombre de página)
        $keywords = $this->extractKeywords($generatedCopy);
        $price = isset($validated['price']) ? '$' . number_format($validated['price'], 2) : '';
        $country = $validated['country'] ?? 'CO';

        // Analizar el copy para identificar tema, beneficios y CTA
        $copyLines = explode("\n", $generatedCopy);
        $mainTheme = '';
        $mainBenefit = '';
        $cta = '';
        $urgencyText = '';

        foreach ($copyLines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 10) continue;

            // Eliminar emojis para análisis limpio
            $cleanLine = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $line);
            $cleanLine = trim($cleanLine);

            // Primera línea significativa como tema principal
            if (empty($mainTheme) && strlen($cleanLine) > 15) {
                $mainTheme = $cleanLine;
            }

            // Buscar CTAs comunes
            if (empty($cta) && preg_match('/(compra|comprar|inscr[ií]b|obt[eé]n|consigue|aprovecha|descubre|unete|registrate)/i', $cleanLine)) {
                $cta = substr($cleanLine, 0, 60);
            }

            // Buscar elementos de urgencia
            if (empty($urgencyText) && preg_match('/(ahora|hoy|limitado|exclusivo|solo|oferta|descuento|gratis)/i', $cleanLine)) {
                $urgencyText = substr($cleanLine, 0, 60);
            }

            // Buscar beneficio (líneas con palabras clave de valor)
            if (empty($mainBenefit) && preg_match('/(aprende|descubre|transforma|mejora|gana|consigue|logra|alcanza)/i', $cleanLine)) {
                $mainBenefit = substr($cleanLine, 0, 100);
            }
        }

        // Construir prompt para flat design limpio y profesional
        $prompt = "Senior graphic designer: Create award-winning FLAT DESIGN illustration (1024x1024px).\n\n";

        $prompt .= "STYLE - MANDATORY:\n";
        $prompt .= "- FLAT DESIGN/ILLUSTRATION only (NO gradients/shadows/3D)\n";
        $prompt .= "- Vector-style, solid colors, geometric shapes, clean lines\n";
        $prompt .= "- Use a DYNAMIC color palette (never fixed). Select 2–3 primary hues + neutral (white/black/gray) based on the product category, emotions, and cultural context.\n";
        $prompt .= "- Rule of thirds, minimalist, premium aesthetic\n\n";

        // Sugerencia de paleta basada en el copy y el país
        $countryForPalette = $country;
        $paletteSuggestion = $this->getDynamicColorPaletteSuggestion($generatedCopy, $countryForPalette);
        $prompt .= "COLOR PALETTE (GUIDANCE - NOT FIXED):\n";
        $prompt .= "- Choose colors according to the copy context. Example hues: {$paletteSuggestion['names']} (examples: {$paletteSuggestion['hexes']}).\n";
        $prompt .= "- You MUST adapt hues to the product and mood; do not reuse the same palette across outputs. Favor accessibility and high contrast for legibility.\n\n";

        $prompt .= "PRODUCT/SERVICE TO VISUALIZE:\n";

        // Analizar el copy completo para extraer el producto
        $copyLower = strtolower($generatedCopy);
        $productDescription = '';

        // Detectar categorías de productos comunes en el copy
        if (preg_match('/(portabebé|porta\s*bebé|cargador|mochila|fulares?)\s*(ergonómic[ao]s?|de\s*bebé)?/i', $generatedCopy, $match)) {
            $productDescription = "modern ergonomic baby carrier being used by happy parent with infant, flat design illustration style";
        } elseif (preg_match('/(curso|taller|clase|capacitación|formación|entrenamiento)\s+(?:de\s+)?([a-záéíóúñ\s]{5,40})/i', $generatedCopy, $match)) {
            $subject = trim($match[2]);
            $productDescription = "online learning concept, digital course platform, person successfully learning {$subject}, flat design illustration";
        } elseif (preg_match('/(tarot|carta|oráculo|lectura|espiritual|holístic[ao]|meditación|yoga)/i', $generatedCopy)) {
            $productDescription = "spiritual wellness scene, tarot or oracle cards in mystical setting, flat design illustration with calming colors";
        } elseif (preg_match('/(ropa|vestido|camisa|pantalón|zapatos|calzado|moda)/i', $generatedCopy)) {
            $productDescription = "fashionable clothing product, modern style apparel, flat design illustration";
        } elseif (preg_match('/(tecnología|electrónic[ao]|dispositivo|gadget|app|aplicación|software)/i', $generatedCopy)) {
            $productDescription = "modern technology product, sleek electronic device, flat design illustration";
        } elseif (preg_match('/(comida|alimento|receta|cocina|restaurante|delivery)/i', $generatedCopy)) {
            $productDescription = "delicious food illustration, appetizing meal in flat design style, culinary product";
        } elseif (preg_match('/(fitness|gym|ejercicio|entrenamiento|deporte|salud)/i', $generatedCopy)) {
            $productDescription = "fitness and wellness lifestyle, healthy active person, flat design illustration";
        } elseif (preg_match('/(belleza|cosmético|skincare|maquillaje|cuidado)/i', $generatedCopy)) {
            $productDescription = "beauty and skincare product, cosmetics in elegant flat design illustration, self-care concept";
        } elseif (preg_match('/(hogar|casa|decoración|mueble|organización)/i', $generatedCopy)) {
            $productDescription = "home decor product, modern interior design element, flat design illustration";
        } else {
            // Fallback: usar el tema principal
            $cleanTheme = $this->cleanForPrompt($mainTheme);
            $productDescription = "professional product or service related to: {$cleanTheme}, flat design illustration, commercial advertising style";
        }

        $prompt .= "SUBJECT: {$productDescription}\n\n";

        // Contexto étnico y cultural según el país objetivo
        $ethnicContext = $this->getEthnicContext($country);

        if (!empty($mainBenefit)) {
            $emotionalContext = $this->cleanForPrompt($mainBenefit);
            $prompt .= "MOOD: {$emotionalContext}. Happy people if human interaction.\n\n";
        }

        $prompt .= "CULTURAL CONTEXT (MANDATORY):\n";
        $prompt .= "Market: {$country}. People MUST be: {$ethnicContext}\n";
        $prompt .= "Setting: {$this->getCulturalSetting($country)}\n\n";

        $prompt .= "COMPOSITION:\n";
        $prompt .= "- Product as hero, in use, aspirational context\n";
        $prompt .= "- Happy people using product (geometric figures if applicable)\n";
        $prompt .= "- Clean background, solid colors, central 20% safe zone\n\n";

        $prompt .= "DO NOT INCLUDE:\n";
        $prompt .= "- ANY text, letters, numbers, symbols, logos, brand marks\n";
        $prompt .= "- Price tags, labels, badges, timers, URLs, contact info\n";
        $prompt .= "- Gradients, shadows, 3D effects, realistic photos\n\n";

        $prompt .= "DIRECTION: Visual masterpiece in FLAT DESIGN style. Award-winning product in lifestyle context. Solid colors, geometric shapes, emotional storytelling. NO text/numbers. Culture of {$country} authentically represented (geometric characters). Style: app icons, landing pages, infographics.";

        return $prompt;
    }

    /**
     * Construir sugerencia de paleta de colores dinámica a partir del copy y el país
     * Devuelve nombres legibles y ejemplos de hex para orientar el modelo sin fijarlo
     */
    private function getDynamicColorPaletteSuggestion(string $copy, string $country): array
    {
        $copyLower = strtolower($copy);

        // Categorías y paletas ejemplo (no restrictivas)
        $palettes = [
            'urgency_sale' => [
                'names' => 'warm energetic reds/oranges with sunny yellows, neutral charcoal/white',
                'hexes' => ['#E11D48', '#F97316', '#F59E0B', '#111827', '#FFFFFF']
            ],
            'premium_luxury' => [
                'names' => 'black and white with gold accents, deep purple/navy',
                'hexes' => ['#0B0B0B', '#FFFFFF', '#C9A227', '#4C1D95', '#0F172A']
            ],
            'tech' => [
                'names' => 'cool blues/teals with cyan accents and soft gray',
                'hexes' => ['#1D4ED8', '#0EA5E9', '#14B8A6', '#334155', '#E5E7EB']
            ],
            'wellness_spiritual' => [
                'names' => 'calming teals/aquas, lavender, soft purples, plenty of white',
                'hexes' => ['#06B6D4', '#60A5FA', '#A78BFA', '#7C3AED', '#FFFFFF']
            ],
            'baby_kids' => [
                'names' => 'soft pastels: baby blue, soft pink, mint, light yellow',
                'hexes' => ['#93C5FD', '#F9A8D4', '#99F6E4', '#FDE68A', '#FFFFFF']
            ],
            'food' => [
                'names' => 'appetizing warm reds/oranges/yellows with fresh green accents',
                'hexes' => ['#EF4444', '#FB923C', '#FACC15', '#22C55E', '#111827']
            ],
            'beauty' => [
                'names' => 'elegant pinks/rose, beige, gold, soft purple',
                'hexes' => ['#EC4899', '#F5D0C5', '#D4AF37', '#C084FC', '#111827']
            ],
            'fitness' => [
                'names' => 'energetic lime/orange/red on dark charcoal backgrounds',
                'hexes' => ['#84CC16', '#F97316', '#DC2626', '#0F172A', '#FFFFFF']
            ],
            'finance_business' => [
                'names' => 'trustworthy greens/blues with navy and white',
                'hexes' => ['#16A34A', '#2563EB', '#0F172A', '#0EA5E9', '#FFFFFF']
            ],
            'education' => [
                'names' => 'professional blues/teals with orange accent for CTA',
                'hexes' => ['#1D4ED8', '#14B8A6', '#EAB308', '#F97316', '#FFFFFF']
            ],
            'fashion' => [
                'names' => 'high-contrast black/white with one bold accent color',
                'hexes' => ['#111827', '#FFFFFF', '#9333EA', '#F43F5E', '#22C55E']
            ],
            'default' => [
                'names' => 'balanced palette chosen from context; 2–3 primaries + neutral',
                'hexes' => ['#3B82F6', '#10B981', '#F59E0B', '#111827', '#FFFFFF']
            ],
        ];

        $key = 'default';
        if (preg_match('/(oferta|descuento|aprovecha|hoy|ahora|limitad)/i', $copyLower)) {
            $key = 'urgency_sale';
        } elseif (preg_match('/(premium|exclusiv|lujo|elegant)/i', $copyLower)) {
            $key = 'premium_luxury';
        } elseif (preg_match('/(tecno|software|app|digital|gadget)/i', $copyLower)) {
            $key = 'tech';
        } elseif (preg_match('/(tarot|yoga|meditaci[oó]n|espiritual|hol[ií]stic)/i', $copyLower)) {
            $key = 'wellness_spiritual';
        } elseif (preg_match('/(beb[eé]|ni[nñ]o|infantil|mam[aá]|pap[aá])/i', $copyLower)) {
            $key = 'baby_kids';
        } elseif (preg_match('/(comida|alimento|receta|cocina|restaurante)/i', $copyLower)) {
            $key = 'food';
        } elseif (preg_match('/(belleza|cosm[eé]tico|maquillaje|skincare)/i', $copyLower)) {
            $key = 'beauty';
        } elseif (preg_match('/(fitness|gimnasio|deporte|entrenamiento)/i', $copyLower)) {
            $key = 'fitness';
        } elseif (preg_match('/(finanza|dinero|ingreso|negocio|empresa|marketing)/i', $copyLower)) {
            $key = 'finance_business';
        } elseif (preg_match('/(curso|clase|taller|aprende|estudia)/i', $copyLower)) {
            $key = 'education';
        } elseif (preg_match('/(moda|ropa|fashion|vestido)/i', $copyLower)) {
            $key = 'fashion';
        }

        $selected = $palettes[$key] ?? $palettes['default'];

        // Ajuste cultural simple: para mercados con preferencia alta por colores cálidos
        if (in_array(strtoupper($country), ['MX', 'CO', 'PE', 'VE', 'BR']) && $key === 'default') {
            $selected = $palettes['urgency_sale'];
        }

        return [
            'names' => $selected['names'],
            'hexes' => implode(', ', $selected['hexes'])
        ];
    }

    /**
     * Extraer conceptos clave y producto del texto generado
     */
    private function extractKeywords(string $text): string
    {
        // Palabras comunes a ignorar
        $stopWords = ['el', 'la', 'de', 'en', 'y', 'a', 'que', 'es', 'por', 'para', 'con', 'no', 'un', 'una', 'los', 'las', 'del', 'se', 'su', 'al', 'lo', 'tu', 'te', 'ya', 'más', 'como', 'pero', 'solo', 'ahora', 'hoy', 'aquí', 'está', 'este', 'esta'];

        // Limpiar emojis
        $cleanText = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $text);

        // Buscar sustantivos importantes (productos, servicios)
        $productPatterns = [
            '/(?:curso|cursos|clase|clases|taller|talleres|programa|programas|servicio|servicios)\s+(?:de\s+)?([a-záéíóúñ\s]+)/i',
            '/(?:producto|productos|artículo|artículos)\s+(?:de\s+)?([a-záéíóúñ\s]+)/i',
            '/([a-záéíóúñ]+(?:bebé|bebés|niño|niños|adulto|adultos|persona|personas|casa|hogar|auto|carro)s?)/i',
            '/([a-záéíóúñ]+(?:ergonómico|terapéutico|profesional|premium|exclusivo|digital|online)s?)/i',
        ];

        $products = [];
        foreach ($productPatterns as $pattern) {
            if (preg_match_all($pattern, $cleanText, $matches)) {
                $products = array_merge($products, $matches[1] ?? $matches[0]);
            }
        }

        // Limpiar y dividir en palabras
        $words = str_word_count(strtolower($cleanText), 1, 'áéíóúñÁÉÍÓÚÑ');

        // Filtrar palabras comunes
        $filteredWords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 4 && !in_array($word, $stopWords);
        });

        // Combinar productos encontrados con palabras relevantes
        $keywords = array_merge($products, array_slice(array_unique($filteredWords), 0, 3));
        $keywords = array_unique($keywords);
        $keywords = array_slice($keywords, 0, 5);

        return implode(', ', $keywords);
    }

    /**
     * Obtener contexto étnico según el país objetivo
     */
    private function getEthnicContext(string $countryCode): string
    {
        $contexts = [
            // América Latina
            'CL' => 'Chilean people - primarily mestizo (mixed European and indigenous Chilean/Mapuche ancestry), Latin American appearance, medium to light brown skin tones, dark hair',
            'AR' => 'Argentine people - predominantly European descent (Italian, Spanish), lighter skin tones, European features, Latin American styling',
            'CO' => 'Colombian people - diverse mestizo population, mixed indigenous and European ancestry, medium to tan skin tones, Latin American appearance',
            'MX' => 'Mexican people - mestizo and indigenous Mexican heritage, medium to brown skin tones, Latin American features',
            'PE' => 'Peruvian people - mestizo and indigenous Peruvian (Quechua, Aymara) heritage, medium to brown skin tones, Andean features',
            'BR' => 'Brazilian people - diverse mixed ancestry (European, African, indigenous), various skin tones, Brazilian appearance',
            'EC' => 'Ecuadorian people - mestizo and indigenous heritage, medium skin tones, Latin American features',
            'UY' => 'Uruguayan people - predominantly European descent, lighter skin tones, Latin American styling',
            'VE' => 'Venezuelan people - mestizo and mixed heritage, medium to tan skin tones, Latin American appearance',

            // España y Europa
            'ES' => 'Spanish people - European Caucasian, Mediterranean features, light to olive skin tones',
            'PT' => 'Portuguese people - European Caucasian, Mediterranean features, light to olive skin tones',
            'IT' => 'Italian people - European Caucasian, Mediterranean features, olive skin tones',
            'FR' => 'French people - European Caucasian, light skin tones, European features',
            'DE' => 'German people - European Caucasian, light skin tones, Northern European features',
            'GB' => 'British people - European Caucasian, light skin tones, British/UK appearance',

            // Estados Unidos y Canadá
            'US' => 'American people - diverse multicultural (Caucasian, Hispanic/Latino, African American, Asian American), various ethnicities typical of US demographics',
            'CA' => 'Canadian people - diverse multicultural population, various ethnicities',

            // Centroamérica y Caribe
            'CR' => 'Costa Rican people - mestizo heritage, medium skin tones, Latin American appearance',
            'PA' => 'Panamanian people - mixed Afro-Caribbean and mestizo heritage, various skin tones',
            'GT' => 'Guatemalan people - indigenous Maya and mestizo heritage, medium to brown skin tones',
            'DO' => 'Dominican people - mixed African, European, and Taino heritage, medium to brown skin tones, Caribbean appearance',
            'PR' => 'Puerto Rican people - mixed heritage (Spanish, African, Taino), medium to tan skin tones, Caribbean Latin American',
        ];

        return $contexts[$countryCode] ?? 'diverse Latin American or local population appropriate for the target market';
    }

    /**
     * Obtener contexto cultural y de escenario según el país
     */
    private function getCulturalSetting(string $countryCode): string
    {
        $settings = [
            // América Latina
            'CL' => 'Chilean urban lifestyle, modern South American city setting, contemporary Latin American aesthetic',
            'AR' => 'Argentine cosmopolitan lifestyle, Buenos Aires modern urban setting, sophisticated South American style',
            'CO' => 'Colombian modern lifestyle, vibrant Latin American urban setting, colorful contemporary Colombian aesthetic',
            'MX' => 'Mexican contemporary lifestyle, modern Mexican urban/suburban setting, vibrant Mexican aesthetic',
            'PE' => 'Peruvian modern lifestyle, Lima urban setting or Andean-influenced contemporary aesthetic',
            'BR' => 'Brazilian contemporary lifestyle, modern Brazilian urban setting, vibrant Brazilian aesthetic',
            'EC' => 'Ecuadorian modern lifestyle, contemporary Latin American urban setting',
            'UY' => 'Uruguayan modern lifestyle, Montevideo cosmopolitan setting, refined South American aesthetic',
            'VE' => 'Venezuelan modern lifestyle, contemporary Latin American urban aesthetic',

            // España y Europa
            'ES' => 'Spanish contemporary lifestyle, modern European Mediterranean setting',
            'PT' => 'Portuguese modern lifestyle, contemporary European setting',
            'IT' => 'Italian contemporary lifestyle, modern European Mediterranean aesthetic',
            'FR' => 'French modern lifestyle, sophisticated European urban setting',
            'DE' => 'German contemporary lifestyle, modern Northern European setting',
            'GB' => 'British modern lifestyle, contemporary UK urban setting',

            // Estados Unidos y Canadá
            'US' => 'American contemporary lifestyle, modern US suburban or urban setting',
            'CA' => 'Canadian modern lifestyle, contemporary North American setting',

            // Centroamérica y Caribe
            'CR' => 'Costa Rican modern lifestyle, contemporary Central American setting',
            'PA' => 'Panamanian modern lifestyle, contemporary Latin American urban aesthetic',
            'GT' => 'Guatemalan contemporary lifestyle, modern Central American setting',
            'DO' => 'Dominican modern lifestyle, contemporary Caribbean Latin American aesthetic',
            'PR' => 'Puerto Rican modern lifestyle, contemporary Caribbean setting',
        ];

        return $settings[$countryCode] ?? 'modern contemporary lifestyle appropriate for the target market';
    }

    /**
     * Limpiar texto para usar en prompts de IA (evita problemas con caracteres especiales)
     */
    private function cleanForPrompt(string $text): string
    {
        // Remover emojis y caracteres especiales problemáticos
        $text = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // Emoticons
        $text = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $text); // Símbolos y pictogramas
        $text = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $text); // Transporte y mapas
        $text = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $text); // Banderas
        $text = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $text);   // Símbolos varios
        $text = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $text);   // Dingbats

        // Reemplazar caracteres acentuados por sus equivalentes sin acento
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', '¡', '¿'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N', '', ''],
            $text
        );

        // Limpiar espacios múltiples
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Exportar resultados
     */
    public function export(int $id, Request $request)
    {
        $search = $this->facebookAdsService->getSearchDetails(
            $id,
            auth()->id()
        );

        if (!$search) {
            abort(404, 'Búsqueda no encontrada');
        }

        $format = $request->query('format', 'json');

        return match ($format) {
            'csv' => $this->exportToCsv($search),
            'excel' => $this->exportToExcel($search),
            default => response()->json($search),
        };
    }

    /**
     * Exportar a CSV
     */
    private function exportToCsv($search)
    {
        $filename = "facebook_ads_search_{$search->id}_" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($search) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'Página',
                'URL',
                'Texto del Anuncio',
                'País',
                'Días Activo',
                'Cantidad de Anuncios',
                'WhatsApp',
                'Número de WhatsApp',
                'Es Ganador',
                'Es Potencial',
            ]);

            // Data
            foreach ($search->facebookAds as $ad) {
                fputcsv($file, [
                    $ad->page_name,
                    $ad->page_url,
                    $ad->ad_text,
                    $ad->country,
                    $ad->days_running,
                    $ad->ads_count,
                    $ad->has_whatsapp ? 'Sí' : 'No',
                    $ad->whatsapp_number,
                    $ad->is_winner ? 'Sí' : 'No',
                    $ad->is_potential ? 'Sí' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Exportar a Excel (requiere instalación de maatwebsite/excel)
     */
    private function exportToExcel($search)
    {
        // TODO: Implementar exportación a Excel usando maatwebsite/excel
        return response()->json([
            'message' => 'Exportación a Excel no implementada aún',
        ], 501);
    }

    /**
     * Obtener estadísticas
     */
    public function stats()
    {
        $userId = auth()->id();

        $stats = [
            'total_searches' => \App\Models\AdSearch::where('user_id', $userId)->count(),
            'completed_searches' => \App\Models\AdSearch::where('user_id', $userId)->completed()->count(),
            'total_ads_found' => \App\Models\FacebookAd::whereHas('adSearch', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->count(),
            'winners_count' => \App\Models\FacebookAd::whereHas('adSearch', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->winners()->count(),
            'potential_count' => \App\Models\FacebookAd::whereHas('adSearch', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->potential()->count(),
            'with_whatsapp' => \App\Models\FacebookAd::whereHas('adSearch', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->withWhatsApp()->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Guardar imagen en storage y retornar path relativo
     */
    private function saveImageToStorage($imageContent, $folder = 'cloned-ads')
    {
        $fileName = uniqid('img_') . '_' . time() . '.png';
        $path = $folder . '/' . $fileName;

        \Storage::disk('public')->put($path, $imageContent);

        return $path;
    }
}
