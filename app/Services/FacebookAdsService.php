<?php

namespace App\Services;

use App\Models\AdSearch;
use App\Models\FacebookAd;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacebookAdsService
{
    private const COUNTRY_NAMES = [
        'CL' => 'Chile',
        'PE' => 'Perú',
        'MX' => 'México',
        'AR' => 'Argentina',
        'CO' => 'Colombia',
        'EC' => 'Ecuador',
        'BO' => 'Bolivia',
    ];

    public function __construct(
        private ApifyService $apifyService
    ) {}

    /**
     * Ejecutar búsqueda de anuncios
     */
    public function searchAds(array $params, ?int $userId = null): AdSearch
    {
        // Crear registro de búsqueda
        $adSearch = AdSearch::create([
            'user_id' => $userId,
            'keywords' => $params['keywords'],
            'countries' => $params['countries'],
            'selected_keywords' => $params['selectedKeywords'] ?? null,
            'days_back' => $params['daysBack'] ?? 30,
            'min_ads' => $params['minAds'] ?? 10,
            'min_days_running' => $params['minDaysRunning'] ?? 30,
            'min_ads_for_long_running' => $params['minAdsForLongRunning'] ?? 5,
            'status' => 'pending',
        ]);

        try {
            $adSearch->markAsProcessing();

            // Determinar fuente de datos
            $dataSource = $params['dataSource'] ?? 'apify';

            $results = match ($dataSource) {
                'apify' => $this->scrapeWithApify($params),
                'simulated' => $this->generateSimulatedData($params),
                default => throw new \Exception("Fuente de datos no válida: {$dataSource}"),
            };

            // Analizar y clasificar resultados
            $analyzedResults = $this->analyzeResults($results, $params);

            // Guardar resultados en la base de datos
            $this->saveResults($adSearch, $analyzedResults, $dataSource);

            // Contar ganadores y potenciales
            $winnersCount = count(array_filter($analyzedResults, fn($r) => $r['is_winner']));
            $potentialCount = count(array_filter($analyzedResults, fn($r) => $r['is_potential']));

            $adSearch->markAsCompleted(
                count($analyzedResults),
                $winnersCount,
                $potentialCount
            );

            return $adSearch->load('facebookAds');

        } catch (\Exception $e) {
            Log::error('Error en búsqueda de anuncios: ' . $e->getMessage());
            $adSearch->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Scraping con Apify
     */
    private function scrapeWithApify(array $params): array
    {
        // Configurar credenciales del usuario si existen
        $user = auth()->user();
        if ($user && $user->apify_api_token && $user->apify_actor_id) {
            $this->apifyService->setCredentials(
                $user->apify_api_token,
                $user->apify_actor_id
            );
        }

        return $this->apifyService->getAdsData($params);
    }

    /**
     * Generar datos simulados
     */
    private function generateSimulatedData(array $params): array
    {
        $allResults = [];

        foreach ($params['countries'] as $country) {
            foreach ($params['keywords'] as $keyword) {
                $numResults = rand(5, 15);

                for ($i = 0; $i < $numResults; $i++) {
                    $allResults[] = $this->generateRealisticData($keyword, $country, $i);
                }

                usleep(500000); // 0.5 segundos
            }
        }

        return $allResults;
    }

    /**
     * Generar datos realistas para simulación
     */
    private function generateRealisticData(string $keyword, string $country, int $index): array
    {
        $baseNames = [
            'Cursos Online', 'Pack Digital', 'Ebooks Premium', 'Recetas Saludables',
            'Fitness Digital', 'Educativo Pro', 'Kit Descargables', 'Megapack',
        ];

        $suffixes = ['Pro', 'Premium', 'Digital', 'Online', 'Plus'];
        $randomName = $baseNames[array_rand($baseNames)];
        $randomSuffix = $suffixes[array_rand($suffixes)];
        $countryName = self::COUNTRY_NAMES[$country];

        $pageName = "{$randomName} {$randomSuffix} {$countryName}";
        $hasWhatsApp = rand(1, 10) > 3;
        $adsCount = rand(5, 30);
        $daysRunning = rand(1, 60);

        return [
            'page_name' => $pageName,
            'page_url' => "https://facebook.com/" . strtolower(str_replace(' ', '', $pageName)),
            'ads_library_url' => "https://www.facebook.com/ads/library/?country={$country}&q=" . urlencode($keyword),
            'ad_text' => $this->generateAdText($keyword),
            'ad_image_url' => "https://via.placeholder.com/600x400?text=" . urlencode($pageName),
            'ad_video_url' => rand(1, 10) > 7 ? "https://example.com/video{$index}.mp4" : null,
            'ads_count' => $adsCount,
            'days_running' => $daysRunning,
            'country' => $countryName,
            'country_code' => $country,
            'platforms' => ['Facebook', 'Instagram'],
            'demographics' => null,
            'has_whatsapp' => $hasWhatsApp,
            'whatsapp_number' => $hasWhatsApp ? $this->generateWhatsAppNumber($country) : null,
            'matched_keywords' => ["simulated | {$keyword}"],
            'search_keyword' => $keyword,
            'ad_id' => 'sim_' . time() . '_' . $index,
            'page_id' => 'page_' . time() . '_' . $index,
            'is_real_data' => false,
            'is_apify_data' => false,
            'data_source' => 'simulated',
        ];
    }

    /**
     * Generar texto de anuncio
     */
    private function generateAdText(string $keyword): string
    {
        $templates = [
            "🎓 CURSO COMPLETO: Aprende {$keyword} desde cero",
            "🔥 OFERTA: {$keyword} con 70% OFF",
            "📚 DESCARGA GRATIS: {$keyword} completo",
            "💎 Material premium sobre {$keyword}",
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * Generar número de WhatsApp
     */
    private function generateWhatsAppNumber(string $country): string
    {
        $phoneCodes = [
            'CL' => '+569',
            'PE' => '+519',
            'MX' => '+521',
            'AR' => '+549',
            'CO' => '+573',
        ];

        $code = $phoneCodes[$country] ?? '+569';
        $number = rand(10000000, 99999999);

        return "{$code}{$number}";
    }

    /**
     * Analizar resultados
     */
    private function analyzeResults(array $results, array $params): array
    {
        return array_map(function ($result) use ($params) {
            $analysis = $this->analyzePageForWinners($result, $params);
            return array_merge($result, $analysis);
        }, $results);
    }

    /**
     * Analizar página para determinar si es ganadora
     */
    private function analyzePageForWinners(array $page, array $params): array
    {
        $minAds = $params['minAds'] ?? 10;
        $minDaysRunning = $params['minDaysRunning'] ?? 30;
        $minAdsForLongRunning = $params['minAdsForLongRunning'] ?? 5;

        // Regla principal: 10 o más anuncios
        if ($page['ads_count'] >= $minAds) {
            return ['is_winner' => true, 'is_potential' => false];
        }

        // Excepción: productos con más días pero menos anuncios
        if ($page['days_running'] >= $minDaysRunning && $page['ads_count'] >= $minAdsForLongRunning) {
            return ['is_winner' => true, 'is_potential' => false];
        }

        // Potencial
        if ($page['ads_count'] >= floor($minAds * 0.7) ||
            ($page['days_running'] >= floor($minDaysRunning * 0.7) && $page['ads_count'] >= 2)) {
            return ['is_winner' => false, 'is_potential' => true];
        }

        return ['is_winner' => false, 'is_potential' => false];
    }

    /**
     * Guardar resultados
     */
    private function saveResults(AdSearch $adSearch, array $results, string $dataSource): void
    {
        DB::transaction(function () use ($adSearch, $results, $dataSource) {
            foreach ($results as $result) {
                FacebookAd::create([
                    'ad_search_id' => $adSearch->id,
                    'page_name' => $result['page_name'],
                    'page_url' => $result['page_url'] ?? null,
                    'ads_library_url' => $result['ads_library_url'] ?? null,
                    'ad_text' => $result['ad_text'] ?? null,
                    'ad_image_url' => $result['ad_image_url'] ?? null,
                    'ad_video_url' => $result['ad_video_url'] ?? null,
                    'ads_count' => $result['ads_count'] ?? 1,
                    'days_running' => $result['days_running'] ?? 0,
                    'country' => $result['country'] ?? null,
                    'country_code' => $result['country_code'] ?? null,
                    'platforms' => $result['platforms'] ?? null,
                    'demographics' => $result['demographics'] ?? null,
                    'has_whatsapp' => $result['has_whatsapp'] ?? false,
                    'whatsapp_number' => $result['whatsapp_number'] ?? null,
                    'matched_keywords' => $result['matched_keywords'] ?? null,
                    'search_keyword' => $result['search_keyword'] ?? null,
                    'is_winner' => $result['is_winner'] ?? false,
                    'is_potential' => $result['is_potential'] ?? false,
                    'ad_id' => $result['ad_id'] ?? null,
                    'page_id' => $result['page_id'] ?? null,
                    'library_id' => $result['library_id'] ?? null,
                    'ad_start_date' => $result['ad_start_date'] ?? null,
                    'ad_end_date' => $result['ad_end_date'] ?? null,
                    'last_seen' => $result['last_seen'] ?? now(),
                    'creation_date' => $result['creation_date'] ?? null,
                    'ad_delivery_start_time' => $result['ad_delivery_start_time'] ?? null,
                    'ad_delivery_stop_time' => $result['ad_delivery_stop_time'] ?? null,
                    'total_running_time' => $result['total_running_time'] ?? null,
                    'ad_spend' => $result['ad_spend'] ?? null,
                    'impressions' => $result['impressions'] ?? null,
                    'targeting_info' => $result['targeting_info'] ?? null,
                    'ad_type' => $result['ad_type'] ?? null,
                    'ad_format' => $result['ad_format'] ?? null,
                    'ad_status' => $result['ad_status'] ?? null,
                    'is_real_data' => $result['is_real_data'] ?? false,
                    'is_apify_data' => $result['is_apify_data'] ?? false,
                    'data_source' => $dataSource,
                    'raw_data' => $result['raw_data'] ?? $result,
                ]);
            }
        });

        $adSearch->update(['data_source' => $dataSource]);
    }

    /**
     * Obtener búsquedas de un usuario
     */
    public function getUserSearches(?int $userId, int $perPage = 10)
    {
        return AdSearch::with(['facebookAds' => function ($query) {
            $query->orderBy('is_winner', 'desc')
                  ->orderBy('is_potential', 'desc')
                  ->orderBy('days_running', 'desc');
        }])
        ->when($userId, fn($q) => $q->where('user_id', $userId))
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);
    }

    /**
     * Obtener detalles de una búsqueda
     */
    public function getSearchDetails(int $searchId, ?int $userId = null): ?AdSearch
    {
        return AdSearch::with(['facebookAds' => function ($query) {
            $query->orderBy('is_winner', 'desc')
                  ->orderBy('is_potential', 'desc')
                  ->orderBy('days_running', 'desc');
        }])
        ->when($userId, fn($q) => $q->where('user_id', $userId))
        ->find($searchId);
    }
}
