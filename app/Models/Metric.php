<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para almacenar métricas de publicaciones de Facebook e Instagram.
 *
 * Reglas:
 * - post_id: ID de la publicación relacionada
 * - views: Número de vistas
 * - likes: Número de likes
 * - comments: Número de comentarios
 * - shares: Número de compartidos
 */
class Metric extends Model
{
    protected $fillable = [
        'post_id',
        'views',
        'likes',
        'comments',
        'shares',
    ];

    /**
     * Relación con la publicación.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
