<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSearch extends Model
{
    protected $fillable = [
        'user_id',
        'keywords',
        'countries',
        'selected_keywords',
        'days_back',
        'min_ads',
        'min_days_running',
        'min_ads_for_long_running',
        'total_results',
        'winners_count',
        'potential_count',
        'status',
        'error_message',
        'data_source',
        'started_at',
        'completed_at',
        'processing_time',
    ];

    protected $casts = [
        'keywords' => 'array',
        'countries' => 'array',
        'selected_keywords' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Relación con User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con FacebookAds
     */
    public function facebookAds(): HasMany
    {
        return $this->hasMany(FacebookAd::class);
    }

    /**
     * Scope para búsquedas completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope para búsquedas en proceso
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope para búsquedas fallidas
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Marcar como en proceso
     */
    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Marcar como completada
     */
    public function markAsCompleted($totalResults, $winnersCount, $potentialCount)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'processing_time' => now()->diffInSeconds($this->started_at),
            'total_results' => $totalResults,
            'winners_count' => $winnersCount,
            'potential_count' => $potentialCount,
        ]);
    }

    /**
     * Marcar como fallida
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
            'processing_time' => now()->diffInSeconds($this->started_at),
        ]);
    }
}
