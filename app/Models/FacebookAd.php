<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookAd extends Model
{
    protected $fillable = [
        'ad_search_id',
        'page_name',
        'page_url',
        'ads_library_url',
        'ad_text',
        'ad_image_url',
        'ad_video_url',
        'ads_count',
        'days_running',
        'country',
        'country_code',
        'platforms',
        'demographics',
        'has_whatsapp',
        'whatsapp_number',
        'matched_keywords',
        'search_keyword',
        'is_winner',
        'is_potential',
        'ad_id',
        'page_id',
        'library_id',
        'ad_start_date',
        'ad_end_date',
        'last_seen',
        'creation_date',
        'ad_delivery_start_time',
        'ad_delivery_stop_time',
        'total_running_time',
        'ad_spend',
        'impressions',
        'targeting_info',
        'ad_type',
        'ad_format',
        'ad_status',
        'is_real_data',
        'is_apify_data',
        'data_source',
        'raw_data',
    ];

    protected $casts = [
        'platforms' => 'array',
        'demographics' => 'array',
        'matched_keywords' => 'array',
        'has_whatsapp' => 'boolean',
        'is_winner' => 'boolean',
        'is_potential' => 'boolean',
        'is_real_data' => 'boolean',
        'is_apify_data' => 'boolean',
        'ad_spend' => 'array',
        'impressions' => 'array',
        'targeting_info' => 'array',
        'raw_data' => 'array',
        'ad_start_date' => 'datetime',
        'ad_end_date' => 'datetime',
        'last_seen' => 'datetime',
        'creation_date' => 'datetime',
        'ad_delivery_start_time' => 'datetime',
        'ad_delivery_stop_time' => 'datetime',
    ];

    /**
     * Relación con AdSearch
     */
    public function adSearch(): BelongsTo
    {
        return $this->belongsTo(AdSearch::class);
    }

    /**
     * Scope para anuncios ganadores
     */
    public function scopeWinners($query)
    {
        return $query->where('is_winner', true);
    }

    /**
     * Scope para anuncios potenciales
     */
    public function scopePotential($query)
    {
        return $query->where('is_potential', true);
    }

    /**
     * Scope para anuncios con WhatsApp
     */
    public function scopeWithWhatsApp($query)
    {
        return $query->where('has_whatsapp', true);
    }

    /**
     * Scope para anuncios por país
     */
    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope para anuncios con datos reales
     */
    public function scopeRealData($query)
    {
        return $query->where('is_real_data', true);
    }
}
