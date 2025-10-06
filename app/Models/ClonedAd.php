<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClonedAd extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'facebook_ad_id',
        'page_name',
        'country',
        'price',
        'original_copy',
        'generated_copy',
        'image_path',
        'video_path',
        'replicate_images',
        'creatomate_render_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'replicate_images' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function facebookAd()
    {
        return $this->belongsTo(FacebookAd::class);
    }

    // Accesor para la URL de la imagen
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }

        return asset('storage/' . $this->image_path);
    }

    // Accesor para la URL del video
    public function getVideoUrlAttribute()
    {
        if (!$this->video_path) {
            return null;
        }

        return asset('storage/' . $this->video_path);
    }
}
