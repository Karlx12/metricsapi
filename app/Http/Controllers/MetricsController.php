<?php

namespace App\Http\Controllers;

use App\Services\MetaApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use IncadevUns\CoreDomain\Models\Metric;
use IncadevUns\CoreDomain\Models\Post;

/**
 * Controlador para manejar métricas de publicaciones de Facebook e Instagram.
 */
class MetricsController extends Controller
{
    protected MetaApiService $metaApi;

    public function __construct(MetaApiService $metaApi)
    {
        $this->metaApi = $metaApi;
    }

    /**
     * Obtiene métricas para una publicación específica.
     */
    public function getMetrics(int $postId): JsonResponse
    {
        $post = Post::find($postId);

        if (! $post) {
            return response()->json(['error' => 'Post not found'], 404);
        }

        $metrics = $post->metrics()->latest()->first();

        if (! $metrics) {
            return response()->json(['error' => 'No metrics found for this post'], 404);
        }

        return response()->json($metrics);
    }

    /**
     * Obtiene métricas consolidadas para una campaña.
     */
    public function getCampaignMetrics(int $campaignId): JsonResponse
    {
        $metrics = Metric::whereHas('post', function ($query) use ($campaignId) {
            $query->where('campaign_id', $campaignId);
        })
            ->latest('metric_date')
            ->get();

        if ($metrics->isEmpty()) {
            return response()->json(['error' => 'No metrics found for this campaign'], 404);
        }

        return response()->json($metrics);
    }

    /**
     * Refresca métricas para todos los posts de una campaña.
     */
    // NOTE: campaign-level refresh removed in favor of a global fetch endpoint.

    /**
     * Actualiza métricas para una publicación desde la API de Meta.
     */
    // Per-post update endpoint removed; UI now triggers global fetch that reads DB.

    /**
     * Método privado para actualizar métricas de un post.
     */
    private function updatePostMetrics(Post $post, string $platform): Metric
    {
        if (strtolower($platform) === 'facebook') {
            $data = $this->metaApi->getFacebookPostMetrics($post->meta_post_id ?? $post->id);
        } elseif (strtolower($platform) === 'instagram') {
            $data = $this->metaApi->getInstagramPostMetrics($post->meta_post_id ?? $post->id);
        } else {
            throw new \Exception('Unsupported platform');
        }

        $data['post_id'] = $post->id;
        $data['platform'] = $platform;
        $data['metric_date'] = now()->toDateString();
        $data['metric_type'] = 'cumulative';

        return Metric::updateOrCreate(
            ['post_id' => $post->id, 'platform' => $platform, 'metric_date' => $data['metric_date']],
            $data
        );
    }

    /**
     * Obtener posts de Facebook desde Meta API y guardarlos en la base de datos.
     */
    // Facebook import endpoints removed — the metrics service no longer imports posts.

    /**
     * Obtener media de Instagram desde Meta API y guardarlos en la base de datos.
     */
    // Instagram import endpoint removed — no longer used.

    /**
     * Actualiza métricas en lote para una lista de posts.
     * Espera un payload: { items: [ { post_id: 123, platform: 'facebook' }, ... ] }
     */
    // Batch update removed; UI will call fetchAndUpdateAll() which reads DB (no params).

    /**
     * Fetch and update metrics for up to 20 posts that have a meta_post_id.
     * The endpoint accepts no parameters — it selects posts from the database.
     */
    public function fetchAndUpdateAll(): JsonResponse
    {
        $posts = Post::whereNotNull('meta_post_id')
            ->where('meta_post_id', '!=', '')
            ->limit(20)
            ->get();

        if ($posts->isEmpty()) {
            return response()->json(['message' => 'No posts with meta_post_id found'], 200);
        }

        $results = [];
        $processed = 0;
        $updated = 0;

        foreach ($posts as $post) {
            $processed++;
            try {
                $metric = $this->updatePostMetrics($post, $post->platform);
                $results[] = [
                    'post_id' => $post->id,
                    'meta_post_id' => $post->meta_post_id,
                    'status' => 'updated',
                    'metric_id' => $metric->id ?? null,
                ];
                $updated++;
            } catch (\Exception $e) {
                $results[] = [
                    'post_id' => $post->id,
                    'meta_post_id' => $post->meta_post_id,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Fetch completed',
            'posts_processed' => $processed,
            'posts_updated' => $updated,
            'results' => $results,
        ]);
    }
}
