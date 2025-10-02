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

            // Paso 2: Generar imagen con DALL-E (OpenAI)
            $openaiKey = config('services.openai.api_key');

            if ($openaiKey) {
                try {
                    // Crear prompt simple en inglés para DALL-E (evita problemas con caracteres especiales)
                    $imagePrompt = sprintf(
                        'Create a professional Facebook ad image for a business called %s. Style: modern, eye-catching, vibrant colors, perfect for social media advertising',
                        $this->cleanForPrompt($validated['page_name'])
                    );

                    \Log::info('DALL-E Request', ['prompt' => $imagePrompt]);

                    $dalleResponse = $http
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $openaiKey,
                            'Content-Type' => 'application/json',
                        ])
                        ->timeout(60)
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

                        \Log::info('DALL-E Image URL', ['url' => $imageUrl]);

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

                                    return response()->json([
                                        'generated' => $text,
                                        'image' => $generatedImage,
                                    ]);
                                } else {
                                    \Log::error('Failed to download image from DALL-E URL', [
                                        'status' => $imageData->status(),
                                        'body_length' => strlen($imageData->body())
                                    ]);
                                }
                            } catch (\Exception $imageDownloadException) {
                                \Log::error('Exception downloading image from DALL-E URL', [
                                    'error' => $imageDownloadException->getMessage(),
                                    'url' => $imageUrl
                                ]);
                            }
                        } else {
                            \Log::error('No image URL in DALL-E response');
                        }
                    } else {
                        \Log::error('DALL-E API call failed');
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

            // Crear prompt simple en inglés para DALL-E con variación
            $styles = ['modern and minimalist', 'bold and colorful', 'elegant and professional', 'fun and creative', 'dynamic and energetic'];
            $randomStyle = $styles[array_rand($styles)];

            $imagePrompt = sprintf(
                'Create a professional Facebook ad image for a business called %s. Style: %s, eye-catching, vibrant colors, perfect for social media advertising',
                $this->cleanForPrompt($validated['page_name']),
                $randomStyle
            );

            \Log::info('DALL-E Regenerate Request', ['prompt' => $imagePrompt]);

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

                            return response()->json([
                                'image' => $generatedImage,
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
     * Generar video con Runway AI
     */
    public function generateVideo(\Illuminate\Http\Request $request)
    {
        set_time_limit(240); // 4 minutos

        $validated = $request->validate([
            'image' => ['required', 'string'], // Base64 image
            'prompt' => ['required', 'string'],
        ]);

        try {
            $runwayKey = config('services.runway.api_key');

            if (!$runwayKey) {
                return response()->json([
                    'message' => 'API key de Runway no configurada.',
                ], 500);
            }

            $http = \Illuminate\Support\Facades\Http::timeout(180);

            if (config('app.env') === 'local') {
                $http = $http->withOptions(['verify' => false]);
            }

            // Extraer base64 puro
            $imageBase64 = $validated['image'];
            if (strpos($imageBase64, 'base64,') !== false) {
                $imageBase64 = explode('base64,', $imageBase64)[1];
            }

            \Log::info('Runway Video Request', ['prompt' => $validated['prompt']]);

            // Paso 1: Iniciar generación del video con Runway
            $runwayResponse = $http
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $runwayKey,
                    'Content-Type' => 'application/json',
                    'X-Runway-Version' => '2024-09-13',
                ])
                ->post('https://api.runwayml.com/v1/image_to_video', [
                    'promptImage' => 'data:image/png;base64,' . $imageBase64,
                    'promptText' => $validated['prompt'],
                    'model' => 'gen3a_turbo',
                    'duration' => 5, // 5 segundos
                    'ratio' => '16:9',
                    'watermark' => false,
                ]);

            \Log::info('Runway Initial Response', [
                'status' => $runwayResponse->status(),
                'body' => $runwayResponse->json()
            ]);

            if (!$runwayResponse->successful()) {
                \Log::error('Runway API call failed', [
                    'status' => $runwayResponse->status(),
                    'body' => $runwayResponse->json()
                ]);

                return response()->json([
                    'message' => 'Error al iniciar generación de video: ' . ($runwayResponse->json('error') ?? 'Error desconocido'),
                ], 500);
            }

            $taskId = $runwayResponse->json('id');

            if (!$taskId) {
                return response()->json([
                    'message' => 'No se recibió ID de tarea de Runway',
                ], 500);
            }

            // Paso 2: Polling para verificar el estado (máximo 90 intentos = 3 minutos)
            $maxAttempts = 90;
            $attempt = 0;
            $videoUrl = null;

            while ($attempt < $maxAttempts) {
                sleep(2); // Esperar 2 segundos entre intentos

                $statusResponse = $http
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $runwayKey,
                        'X-Runway-Version' => '2024-09-13',
                    ])
                    ->get("https://api.runwayml.com/v1/tasks/{$taskId}");

                \Log::info('Runway Polling Attempt', [
                    'attempt' => $attempt + 1,
                    'status' => $statusResponse->json('status')
                ]);

                if ($statusResponse->successful()) {
                    $status = $statusResponse->json('status');

                    if ($status === 'SUCCEEDED') {
                        $videoUrl = $statusResponse->json('output.0') ?? $statusResponse->json('output');
                        break;
                    } elseif ($status === 'FAILED') {
                        return response()->json([
                            'message' => 'La generación del video falló: ' . ($statusResponse->json('failure') ?? 'Error desconocido'),
                        ], 500);
                    }
                }

                $attempt++;
            }

            if (!$videoUrl) {
                return response()->json([
                    'message' => 'Timeout esperando la generación del video. El video puede tardar más de lo esperado, intenta de nuevo en unos minutos.',
                ], 408);
            }

            \Log::info('Runway Video Generated', ['url' => $videoUrl]);

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
                \Log::error('Error downloading video from Runway URL', ['error' => $downloadException->getMessage()]);
            }

            // Si falla la descarga, devolver la URL directamente
            return response()->json([
                'video_url' => $videoUrl,
            ]);

        } catch (\Exception $e) {
            \Log::error('Runway video generation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Error al generar el video: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extraer palabras clave del texto generado
     */
    private function extractKeywords(string $text): string
    {
        // Palabras comunes a ignorar
        $stopWords = ['el', 'la', 'de', 'en', 'y', 'a', 'que', 'es', 'por', 'para', 'con', 'no', 'un', 'una', 'los', 'las', 'del', 'se', 'su', 'al', 'lo'];

        // Limpiar el texto y dividir en palabras
        $words = str_word_count(strtolower($text), 1, 'áéíóúñÁÉÍÓÚÑ');

        // Filtrar palabras comunes y contar frecuencia
        $filteredWords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });

        // Tomar las primeras 5 palabras únicas más relevantes
        $uniqueWords = array_unique($filteredWords);
        $keywords = array_slice($uniqueWords, 0, 5);

        return implode(', ', $keywords);
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
}
