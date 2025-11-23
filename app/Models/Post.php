<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para almacenar publicaciones en redes sociales para campañas, con métricas asociadas.
 *
 * Reglas:
 * - campaign_id: ID de la campaña asociada
 * - title: Título de la publicación
 * - platform: Plataforma (e.g., Facebook, Instagram, TikTok)
 * - content: Contenido de la publicación
 * - image_url: URL de la imagen (nullable)
 */
class Post extends Model
{
    protected $fillable = [
        'campaign_id',
        'title',
        'platform',
        'content',
        'image_url',
    ];

    protected $casts = [
        //
    ];

    /**
     * Relación con métricas.
     */
    public function metrics()
    {
        return $this->hasMany(Metric::class);
    }
}
