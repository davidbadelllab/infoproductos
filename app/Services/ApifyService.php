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

        // Usar selectedKeywords si existe, si no, usar keywords
        $keywordsToSearch = !empty($params['selectedKeywords'])
            ? $params['selectedKeywords']
            : $params['keywords'];

        $limitedKeywords = array_slice($keywordsToSearch, 0, 3);

        // Buscar en TODOS los países seleccionados (máximo 3 para no saturar)
        $limitedCountries = array_slice($params['countries'], 0, 3);

        Log::info('🎯 Buscando en países: ' . implode(', ', $limitedCountries));
        Log::info('🔑 Keywords exactas: ' . implode(', ', $limitedKeywords));

        foreach ($limitedCountries as $country) {
            foreach ($limitedKeywords as $keyword) {
                Log::info("🔍 Ejecutando scraping para '{$keyword}' en {$country}");

                try {
                    $apifyResults = $this->runApifyActor($keyword, $country, $params);

                    // Formatear resultados
                    $formattedResults = array_map(
                        fn($item) => $this->formatApifyData($item, $keyword, $country),
                        $apifyResults
                    );

                    // FILTRO POST-BÚSQUEDA: Validar que los resultados sean relevantes
                    $filteredResults = $this->filterRelevantResults(
                        $formattedResults,
                        $keyword,
                        $country
                    );

                    Log::info(sprintf(
                        '📊 De %d anuncios de Apify, %d son relevantes para "%s" en %s',
                        count($formattedResults),
                        count($filteredResults),
                        $keyword,
                        $country
                    ));

                    // Contar cuántos tienen WhatsApp (para estadísticas)
                    $whatsappCount = count(array_filter(
                        $filteredResults,
                        fn($ad) => $ad['has_whatsapp'] ?? false
                    ));

                    Log::info(sprintf(
                        '💬 De los %d relevantes, %d contienen WhatsApp',
                        count($filteredResults),
                        $whatsappCount
                    ));

                    // Contar ganadores y potenciales
                    $winnersCount = count(array_filter(
                        $filteredResults,
                        fn($ad) => $ad['is_winner'] ?? false
                    ));

                    $potentialsCount = count(array_filter(
                        $filteredResults,
                        fn($ad) => $ad['is_potential'] ?? false
                    ));

                    Log::info(sprintf(
                        '🏆 Clasificación: %d GANADORES, %d POTENCIALES, %d normales',
                        $winnersCount,
                        $potentialsCount,
                        count($filteredResults) - $winnersCount - $potentialsCount
                    ));

                    $allResults = array_merge($allResults, $filteredResults);

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

        Log::info(sprintf('✅ Apify completado: %d anuncios REALES y RELEVANTES extraídos', count($allResults)));
        return $allResults;
    }

    /**
     * Filtrar resultados relevantes (post-búsqueda)
     * Valida que el anuncio realmente sea relevante a la keyword y país buscado
     */
    private function filterRelevantResults(array $results, string $keyword, string $country): array
    {
        return array_filter($results, function($ad) use ($keyword, $country) {
            // 1. VALIDACIÓN ESTRICTA DEL PAÍS
            $adCountry = $ad['country_code'] ?? '';

            // Log para debug: ver qué país viene en los datos
            if ($adCountry !== $country) {
                Log::debug("❌ Anuncio '{$ad['page_name']}' RECHAZADO: país incorrecto (tiene '{$adCountry}', esperado '{$country}')");
                return false;
            }

            // 1.5 VALIDACIÓN DE WHATSAPP: Si tiene WhatsApp, verificar que el código de país coincida
            $whatsappNumber = $ad['whatsapp_number'] ?? '';
            if (!empty($whatsappNumber) && $this->isInternationalPhoneNumber($whatsappNumber)) {
                $phoneCountry = $this->getCountryFromPhone($whatsappNumber);

                // Si detectamos que el número es de otro país, rechazar
                if ($phoneCountry && $phoneCountry !== $country) {
                    Log::debug("❌ Anuncio '{$ad['page_name']}' RECHAZADO: WhatsApp de otro país (número de '{$phoneCountry}', esperado '{$country}')");
                    return false;
                }
            }

            // 2. Validar que contenga la keyword en el texto del anuncio o nombre de página
            $searchText = $this->removeAccents(strtolower($ad['ad_text'] . ' ' . $ad['page_name']));

            // Tokenizar keyword en palabras individuales
            // "Curso de programacion" → ["curso", "programacion"]
            $keywordWords = $this->tokenizeKeyword($keyword);

            // VALIDACIÓN: Si la keyword no genera palabras significativas, rechazar
            if (empty($keywordWords)) {
                Log::debug("⚠️ Keyword '{$keyword}' no generó palabras significativas para buscar");
                return false;
            }

            // Buscar cada palabra en el texto (con variaciones)
            $matchedWords = [];
            foreach ($keywordWords as $word) {
                $variations = $this->getWordVariations($word);

                foreach ($variations as $variation) {
                    if (str_contains($searchText, $variation)) {
                        $matchedWords[] = $word;
                        break; // Ya encontramos una variación de esta palabra
                    }
                }
            }

            // Se requiere que al menos UNA palabra clave aparezca
            if (empty($matchedWords)) {
                Log::debug("❌ Anuncio '{$ad['page_name']}' RECHAZADO: no contiene ninguna palabra de '{$keyword}' (buscó: " . implode(', ', $keywordWords) . ")");
                return false;
            }

            Log::debug("✅ Anuncio '{$ad['page_name']}' ACEPTADO: país={$adCountry}, palabras=" . implode(', ', $matchedWords));

            // 3. Si pasa todas las validaciones, es relevante
            return true;
        });
    }

    /**
     * Tokenizar keyword en palabras significativas
     * "Curso de programacion" → ["curso", "programacion"]
     * "html, go, java" → ["html", "java"] (go es muy corta)
     */
    private function tokenizeKeyword(string $keyword): array
    {
        // Palabras a ignorar (artículos, preposiciones, etc.)
        $stopWords = ['de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'en', 'y', 'o', 'a', 'para'];

        // Limpiar puntuación y separar por espacios o comas
        $cleaned = preg_replace('/[,;:|]+/', ' ', $keyword); // Reemplazar puntuación por espacios
        $words = preg_split('/\s+/', strtolower(trim($cleaned)));

        // Filtrar palabras vacías y stop words
        $significantWords = array_filter($words, function($word) use ($stopWords) {
            $word = trim($this->removeAccents($word));
            // Permitir palabras de 2+ caracteres para incluir "go", "js", etc.
            return strlen($word) >= 2 && !in_array($word, $stopWords);
        });

        return array_values($significantWords);
    }

    /**
     * Generar variaciones de UNA palabra individual
     * "curso" → ["curso", "cursos"]
     * "programacion" → ["programacion", "programaciones"]
     */
    private function getWordVariations(string $word): array
    {
        $word = $this->removeAccents(strtolower($word));
        $variations = [$word];

        // Agregar plural simple (añadir 's' o 'es')
        if (!str_ends_with($word, 's')) {
            $variations[] = $word . 's';

            // Palabras terminadas en consonante pueden llevar 'es'
            if (!in_array(substr($word, -1), ['a', 'e', 'i', 'o', 'u'])) {
                $variations[] = $word . 'es';
            }
        }

        // Agregar singular (quitar 's' o 'es' final)
        if (str_ends_with($word, 'es') && strlen($word) > 4) {
            $variations[] = substr($word, 0, -2);
        } elseif (str_ends_with($word, 's') && strlen($word) > 3) {
            $variations[] = substr($word, 0, -1);
        }

        return array_unique($variations);
    }

    /**
     * Detectar si un número tiene formato internacional (+XX)
     */
    private function isInternationalPhoneNumber(string $phone): bool
    {
        return str_starts_with($phone, '+');
    }

    /**
     * Obtener código de país desde número de WhatsApp
     * Mapea los prefijos telefónicos internacionales a códigos de país ISO
     */
    private function getCountryFromPhone(string $phone): ?string
    {
        // Limpiar el número
        $phone = trim(str_replace([' ', '-', '(', ')'], '', $phone));

        // Mapa de códigos telefónicos a códigos ISO de país
        $phoneToCountry = [
            '+56' => 'CL',   // Chile
            '+51' => 'PE',   // Perú
            '+52' => 'MX',   // México
            '+54' => 'AR',   // Argentina
            '+57' => 'CO',   // Colombia
            '+593' => 'EC',  // Ecuador
            '+591' => 'BO',  // Bolivia
            '+34' => 'ES',   // España
            '+1' => 'US',    // USA/Canadá
        ];

        // Buscar el prefijo más largo que coincida
        foreach ($phoneToCountry as $prefix => $countryCode) {
            if (str_starts_with($phone, $prefix)) {
                return $countryCode;
            }
        }

        return null;
    }

    /**
     * Remover acentos de un texto
     */
    private function removeAccents(string $text): string
    {
        $search = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'];
        return str_replace($search, $replace, $text);
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
            'count' => $params['count'] ?? 200, // Usar parámetro del usuario, default 200 para traer más resultados
            'period' => '',
            'scrapePageAds.activeStatus' => 'all',
            'scrapePageAds.countryCode' => $country, // IMPORTANTE: Usar país específico, no 'ALL'
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
        $adStartDate = $apifyItem['ad_delivery_start_time'] ??
                       $apifyItem['ad_creation_time'] ??
                       $apifyItem['start_date'] ?? null;

        $daysRunning = $this->calculateDaysRunning($adStartDate);

        // Clasificar anuncio
        $hasWhatsApp = $this->checkForWhatsApp($adText) ||
                      $this->checkForWhatsApp($apifyItem['page_name'] ?? '');

        $isWinner = $daysRunning >= 30 && $hasWhatsApp;
        $isPotential = ($daysRunning >= 7 && $daysRunning < 30 && $hasWhatsApp) ||
                      ($daysRunning >= 30);

        // LOG TRANSPARENTE: Mostrar por qué es o no es ganador/potencial
        $pageName = $apifyItem['page_name'] ?? 'Sin nombre';
        $classification = $isWinner ? '🏆 GANADOR' : ($isPotential ? '⭐ POTENCIAL' : '📊 NORMAL');

        Log::debug("📋 Clasificación '{$pageName}': {$classification} | " .
                   "Días activo: {$daysRunning} | " .
                   "WhatsApp: " . ($hasWhatsApp ? 'SÍ' : 'NO') . " | " .
                   "Fecha inicio: " . ($adStartDate ?: 'NO DISPONIBLE'));

        // Explicar por qué NO es ganador si no lo es
        if (!$isWinner && !$isPotential) {
            if ($daysRunning < 7) {
                Log::debug("   ↳ Razón: Anuncio muy nuevo (necesita 7+ días para ser potencial)");
            } elseif ($daysRunning < 30 && !$hasWhatsApp) {
                Log::debug("   ↳ Razón: Necesita WhatsApp para ser potencial (tiene {$daysRunning} días)");
            }
        } elseif ($isPotential && !$isWinner) {
            if ($daysRunning >= 30 && !$hasWhatsApp) {
                Log::debug("   ↳ Potencial sin WhatsApp (para ser GANADOR necesita WhatsApp)");
            } elseif ($daysRunning < 30) {
                Log::debug("   ↳ Potencial joven (para ser GANADOR necesita 30+ días)");
            }
        }

        return [
            'page_name' => $apifyItem['page_name'] ?? $apifyItem['pageName'] ?? 'Página sin nombre',
            'page_url' => $apifyItem['page_url'] ?? ($apifyItem['page_id'] ?? null ? "https://facebook.com/{$apifyItem['page_id']}" : '#'),
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
    private function calculateDaysRunning($dateInput): int
    {
        if (!$dateInput) {
            return 0;
        }

        try {
            // Si es un timestamp Unix (número), convertir primero
            if (is_numeric($dateInput)) {
                $adDate = Carbon::createFromTimestamp($dateInput);
            } else {
                $adDate = Carbon::parse($dateInput);
            }

            // Calcular diferencia en días desde la fecha del anuncio hasta hoy
            return max(0, now()->diffInDays($adDate, false));
        } catch (\Exception $e) {
            Log::debug("⚠️ Error parseando fecha: " . $dateInput . " - " . $e->getMessage());
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
