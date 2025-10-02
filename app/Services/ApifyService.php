<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyService
{
    private const MAX_WAIT_TIME = 240; // 4 minutos
    private const CHECK_INTERVAL = 5; // 5 segundos

    private const COUNTRY_NAMES = [
        'CL' => 'Chile',
        'PE' => 'Perú',
        'MX' => 'México',
        'AR' => 'Argentina',
        'CO' => 'Colombia',
        'EC' => 'Ecuador',
        'BO' => 'Bolivia',
    ];

    private ?string $apiToken = null;
    private ?string $actorId = null;

    /**
     * Configurar credenciales de Apify
     */
    public function setCredentials(?string $apiToken, ?string $actorId): self
    {
        $this->apiToken = $apiToken;
        $this->actorId = $actorId;
        return $this;
    }

    /**
     * Obtener token API (del usuario o del config)
     */
    private function getApiToken(): string
    {
        return $this->apiToken ?? config('services.apify.token');
    }

    /**
     * Obtener Actor ID (del usuario o del config)
     */
    private function getActorId(): string
    {
        return $this->actorId ?? config('services.apify.actor_id');
    }

    /**
     * Obtener datos de anuncios usando Apify
     */
    public function getAdsData(array $params): array
    {
        Log::info('🔥 Iniciando scraping con Apify Facebook Ad Library Scraper');

        $allResults = [];
        $limitedKeywords = array_slice($params['keywords'], 0, 3);
        $limitedCountries = array_slice($params['countries'], 0, 1);

        foreach ($limitedCountries as $country) {
            foreach ($limitedKeywords as $keyword) {
                Log::info("🔍 Ejecutando scraping para '{$keyword}' en {$country}");

                try {
                    $apifyResults = $this->runApifyActor($keyword, $country, $params);
                    $formattedResults = array_map(
                        fn($item) => $this->formatApifyData($item, $keyword, $country),
                        $apifyResults
                    );

                    // Contar cuántos tienen WhatsApp (para estadísticas)
                    $whatsappCount = count(array_filter(
                        $formattedResults,
                        fn($ad) => $ad['has_whatsapp'] ?? false
                    ));

                    Log::info(sprintf(
                        '📊 De %d anuncios, %d contienen WhatsApp',
                        count($formattedResults),
                        $whatsappCount
                    ));

                    // IMPORTANTE: Agregar TODOS los resultados, no solo los que tienen WhatsApp
                    $allResults = array_merge($allResults, $formattedResults);

                    if (count($allResults) >= 50) {
                        Log::info('🛑 Límite de resultados alcanzado');
                        break 2;
                    }

                    sleep(1);
                } catch (\Exception $e) {
                    Log::error("❌ Error con keyword '{$keyword}': {$e->getMessage()}");
                    continue;
                }
            }
        }

        Log::info(sprintf('✅ Apify completado: %d anuncios REALES extraídos', count($allResults)));
        return $allResults;
    }

    /**
     * Ejecutar el Actor de Apify
     */
    private function runApifyActor(string $keyword, string $country, array $params): array
    {
        $searchUrl = sprintf(
            'https://www.facebook.com/ads/library/?active_status=all&ad_type=all&country=%s&q=%s&search_type=keyword_unordered&media_type=all',
            $country,
            urlencode($keyword)
        );

        $input = [
            'urls' => [['url' => $searchUrl]],
            'count' => 10, // Reducido de 20 a 10 para más velocidad
            'period' => '',
            'scrapePageAds.activeStatus' => 'all',
            'scrapePageAds.countryCode' => 'ALL',
        ];

        Log::info('📡 Llamando a Apify Actor: ' . $this->getActorId());
        Log::info('🔗 URL de búsqueda: ' . $searchUrl);
        Log::info('📝 Input para Apify: ' . json_encode($input, JSON_PRETTY_PRINT));

        $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $this->getApiToken(),
        ])
        ->withOptions(['verify' => false]) // Solo para desarrollo
        ->post("https://api.apify.com/v2/acts/" . $this->getActorId() . "/runs", $input);

        if (!$response->successful()) {
            Log::error("❌ Error al iniciar Apify Actor: {$response->status()}");
            Log::error("Respuesta: {$response->body()}");
            throw new \Exception("Apify API error: {$response->status()} - {$response->body()}");
        }

        $runData = $response->json();

        if (!isset($runData['data']['id'])) {
            Log::error("❌ No se encontró ID en la respuesta de Apify:");
            Log::error(json_encode($runData, JSON_PRETTY_PRINT));
            throw new \Exception("No se pudo iniciar el Actor de Apify");
        }

        $runId = $runData['data']['id'];

        Log::info("⏳ Esperando resultados de Apify (Run ID: {$runId})");

        return $this->waitForApifyResults($runId);
    }

    /**
     * Esperar resultados de Apify
     */
    private function waitForApifyResults(string $runId): array
    {
        $startTime = time();

        while ((time() - $startTime) < self::MAX_WAIT_TIME) {
            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiToken(),
            ])
            ->withOptions(['verify' => false])
            ->get("https://api.apify.com/v2/actor-runs/{$runId}");

            if (!$statusResponse->successful()) {
                Log::error("❌ Error al obtener estado de Apify: {$statusResponse->status()}");
                Log::error("Respuesta: {$statusResponse->body()}");
                throw new \Exception("Error al consultar estado de Apify: {$statusResponse->status()}");
            }

            $statusData = $statusResponse->json();

            if (!isset($statusData['data']) || !isset($statusData['data']['status'])) {
                Log::error("❌ Respuesta de Apify inválida:");
                Log::error(json_encode($statusData, JSON_PRETTY_PRINT));
                throw new \Exception('Respuesta de Apify no tiene la estructura esperada');
            }

            $status = $statusData['data']['status'];

            Log::info("📊 Estado de Apify: {$status}");

            if ($status === 'SUCCEEDED') {
                if (!isset($statusData['data']['defaultDatasetId'])) {
                    Log::error("❌ No se encontró defaultDatasetId en la respuesta");
                    Log::error(json_encode($statusData, JSON_PRETTY_PRINT));
                    throw new \Exception('No se encontró defaultDatasetId en la respuesta de Apify');
                }

                $datasetId = $statusData['data']['defaultDatasetId'];
                Log::info("📦 Dataset ID: {$datasetId}");

                $resultsResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->getApiToken(),
                ])
                ->withOptions(['verify' => false])
                ->get("https://api.apify.com/v2/datasets/{$datasetId}/items");

                if (!$resultsResponse->successful()) {
                    Log::error("❌ Error al obtener resultados del dataset: {$resultsResponse->status()}");
                    throw new \Exception("Error al obtener resultados del dataset");
                }

                $results = $resultsResponse->json();

                if (!is_array($results)) {
                    Log::error("❌ Los resultados no son un array");
                    Log::error("Tipo: " . gettype($results));
                    $results = [];
                }

                Log::info(sprintf('✅ Apify completado: %d resultados obtenidos', count($results)));

                return $results;
            } elseif ($status === 'FAILED') {
                $errorMessage = $statusData['data']['error'] ?? 'Error desconocido';
                Log::error("❌ Apify Actor falló: {$errorMessage}");
                throw new \Exception("Apify Actor falló: {$errorMessage}");
            }

            sleep(self::CHECK_INTERVAL);
        }

        throw new \Exception('Timeout esperando resultados de Apify');
    }

    /**
     * Formatear datos de Apify
     */
    private function formatApifyData(array $apifyItem, string $keyword, string $country): array
    {
        $snapshot = $apifyItem['snapshot'] ?? [];

        // Extraer texto del anuncio
        $adText = $snapshot['body'] ??
                 $snapshot['ad_creative_body'] ??
                 $snapshot['text'] ??
                 $snapshot['description'] ??
                 $apifyItem['body'] ??
                 'Sin texto disponible';

        // Manejar diferentes tipos de datos
        if (is_array($adText)) {
            $adText = $adText['text'] ?? $adText['body'] ?? json_encode($adText);
        } elseif (is_object($adText)) {
            $adText = $adText->text ?? $adText->body ?? json_encode($adText);
        }

        $adText = (string)$adText;

        // Extraer imagen
        $adImageUrl = $this->extractImageUrl($snapshot, $apifyItem);

        // Extraer video
        $adVideoUrl = $this->extractVideoUrl($snapshot);

        // Calcular días de ejecución
        $daysRunning = $this->calculateDaysRunning(
            $apifyItem['ad_creation_time'] ?? $apifyItem['start_date'] ?? null
        );

        // Clasificar anuncio
        $hasWhatsApp = $this->checkForWhatsApp($adText) ||
                      $this->checkForWhatsApp($apifyItem['page_name'] ?? '');

        $isWinner = $daysRunning >= 30 && $hasWhatsApp;
        $isPotential = ($daysRunning >= 7 && $daysRunning < 30 && $hasWhatsApp) ||
                      ($daysRunning >= 30);

        return [
            'page_name' => $apifyItem['page_name'] ?? $apifyItem['pageName'] ?? 'Página sin nombre',
            'page_url' => $apifyItem['page_url'] ?? "https://facebook.com/{$apifyItem['page_id']}",
            'ads_library_url' => $apifyItem['ad_snapshot_url'] ?? '',
            'ad_text' => $adText,
            'ad_image_url' => $adImageUrl,
            'ad_video_url' => $adVideoUrl,
            'ads_count' => 1,
            'days_running' => $daysRunning,
            'country' => self::COUNTRY_NAMES[$country] ?? $country,
            'country_code' => $country,
            'platforms' => $apifyItem['platforms'] ?? ['Facebook'],
            'demographics' => $apifyItem['demographics'] ?? null,
            'has_whatsapp' => $hasWhatsApp,
            'whatsapp_number' => $this->extractWhatsApp($adText),
            'matched_keywords' => ["apify | {$keyword}"],
            'search_keyword' => $keyword,
            'is_winner' => $isWinner,
            'is_potential' => $isPotential,
            'ad_id' => $apifyItem['ad_id'] ?? $apifyItem['id'] ?? null,
            'page_id' => $apifyItem['page_id'] ?? null,
            'library_id' => $apifyItem['ad_archive_id'] ?? $apifyItem['library_id'] ?? null,
            'ad_start_date' => $this->parseDate($apifyItem['ad_delivery_start_time'] ?? null),
            'ad_end_date' => $this->parseDate($apifyItem['ad_delivery_stop_time'] ?? null),
            'last_seen' => now(),
            'creation_date' => $this->parseDate($apifyItem['ad_creation_time'] ?? null),
            'ad_delivery_start_time' => $this->parseDate($apifyItem['ad_delivery_start_time'] ?? null),
            'ad_delivery_stop_time' => $this->parseDate($apifyItem['ad_delivery_stop_time'] ?? null),
            'total_running_time' => $apifyItem['total_time_running'] ?? null,
            'ad_spend' => $apifyItem['spend'] ?? $apifyItem['spend_range'] ?? null,
            'impressions' => $apifyItem['impressions'] ?? $apifyItem['impressions_range'] ?? null,
            'targeting_info' => $apifyItem['targeting'] ?? $apifyItem['target_audience'] ?? null,
            'ad_type' => $apifyItem['ad_type'] ?? null,
            'ad_format' => $apifyItem['format'] ?? null,
            'ad_status' => $apifyItem['status'] ?? null,
            'is_real_data' => true,
            'is_apify_data' => true,
            'data_source' => 'apify',
            'raw_data' => $apifyItem,
        ];
    }

    /**
     * Extraer URL de imagen
     */
    private function extractImageUrl(array $snapshot, array $apifyItem): ?string
    {
        // Intentar campos directos
        $imageUrl = $snapshot['image_url'] ??
                   $snapshot['original_image_url'] ??
                   $snapshot['resized_image_url'] ??
                   $apifyItem['image_url'] ??
                   null;

        // Buscar en cards
        if (!$imageUrl && isset($snapshot['cards']) && is_array($snapshot['cards'])) {
            foreach ($snapshot['cards'] as $card) {
                if (!empty($card['image_url'])) {
                    $imageUrl = $card['image_url'];
                    break;
                }
            }
        }

        // Buscar en arrays de imágenes
        if (!$imageUrl && isset($snapshot['images']) && is_array($snapshot['images'])) {
            $firstImage = $snapshot['images'][0] ?? null;
            $imageUrl = is_array($firstImage)
                ? ($firstImage['url'] ?? $firstImage['src'] ?? null)
                : $firstImage;
        }

        return $imageUrl;
    }

    /**
     * Extraer URL de video
     */
    private function extractVideoUrl(array $snapshot): ?string
    {
        // Buscar en cards
        if (isset($snapshot['cards']) && is_array($snapshot['cards'])) {
            foreach ($snapshot['cards'] as $card) {
                if (!empty($card['video_hd_url'])) {
                    return $card['video_hd_url'];
                }
                if (!empty($card['video_sd_url'])) {
                    return $card['video_sd_url'];
                }
            }
        }

        return $snapshot['video_url'] ??
               $snapshot['video_hd_url'] ??
               $snapshot['video_sd_url'] ??
               null;
    }

    /**
     * Calcular días de ejecución
     */
    private function calculateDaysRunning(?string $dateString): int
    {
        if (!$dateString) {
            return 0;
        }

        try {
            $adDate = Carbon::parse($dateString);
            return $adDate->diffInDays(now());
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Verificar si contiene WhatsApp
     */
    private function checkForWhatsApp(string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        $lowerText = strtolower($text);

        // Patrones de WhatsApp comunes
        $patterns = [
            'whatsapp', 'wa.me', 'api.whatsapp.com', 'chat.whatsapp.com',
            'whatssap', 'whassap', 'whats app', 'wsp', 'wsapp',
            'escríbeme', 'escribeme', 'escribe al', 'contáctame',
            'contactame', 'manda mensaje', 'envía mensaje', 'envia mensaje',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lowerText, $pattern)) {
                return true;
            }
        }

        // Buscar números de teléfono con formatos comunes
        // +56912345678, +1234567890, 912345678, etc.
        if (preg_match('/(\+?\d{1,3}[\s-]?\d{3,4}[\s-]?\d{3,4}[\s-]?\d{0,4})/', $text)) {
            return true;
        }

        // Buscar emojis de teléfono o WhatsApp
        if (preg_match('/📱|📞|💬|✉️/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Extraer número de WhatsApp
     */
    private function extractWhatsApp(string $text): ?string
    {
        // Buscar números con formato internacional (+56912345678)
        if (preg_match('/\+\d{1,3}[\s-]?\d{3,4}[\s-]?\d{3,4}[\s-]?\d{0,4}/', $text, $matches)) {
            return trim(str_replace([' ', '-'], '', $matches[0]));
        }

        // Buscar números en enlaces wa.me
        if (preg_match('/wa\.me\/(\+?\d+)/', $text, $matches)) {
            return $matches[1];
        }

        // Buscar números locales (912345678, 987654321)
        if (preg_match('/\b\d{9,12}\b/', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Parsear fecha
     */
    private function parseDate(?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Probar conexión con Apify
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiToken(),
            ])
            ->withOptions(['verify' => false])
            ->get("https://api.apify.com/v2/acts/" . $this->getActorId());

            if ($response->successful()) {
                Log::info('✅ Conexión con Apify OK');
                return true;
            }

            Log::error('❌ Error de conexión con Apify: ' . $response->status());
            return false;
        } catch (\Exception $e) {
            Log::error('❌ Error probando Apify: ' . $e->getMessage());
            return false;
        }
    }
}
