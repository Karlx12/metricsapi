<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Metric;
use App\Services\MetaApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
        $post = Post::findOrFail($postId);

        $metrics = $post->metrics()->latest()->first();

        if (!$metrics) {
            return response()->json(['error' => 'No metrics found for this post'], 404);
        }

        return response()->json($metrics);
    }

    /**
     * Actualiza métricas para una publicación desde la API de Meta.
     */
    public function updateMetrics(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'meta_post_id' => 'required|string',
            'platform' => 'required|string|in:facebook,instagram',
        ]);

        $post = Post::findOrFail($postId);

        try {
            if (strtolower($request->platform) === 'facebook') {
                $data = $this->metaApi->getFacebookPostMetrics($request->meta_post_id);
            } elseif (strtolower($request->platform) === 'instagram') {
                $data = $this->metaApi->getInstagramPostMetrics($request->meta_post_id);
            } else {
                return response()->json(['error' => 'Unsupported platform'], 400);
            }

            $metric = Metric::updateOrCreate(
                ['post_id' => $postId],
                $data
            );

            return response()->json($metric);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene métricas para todas las publicaciones de una campaña.
     */
    public function getCampaignMetrics(int $campaignId): JsonResponse
    {
        $posts = Post::where('campaign_id', $campaignId)->with('metrics')->get();

        $result = $posts->map(function ($post) {
            return [
                'post_id' => $post->id,
                'title' => $post->title,
                'platform' => $post->platform,
                'metrics' => $post->metrics->last(),
            ];
        });

        return response()->json($result);
    }
}
