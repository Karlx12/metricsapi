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
    public function refreshCampaignMetrics(int $campaignId): JsonResponse
    {
        $posts = Post::where('campaign_id', $campaignId)->get();

        if ($posts->isEmpty()) {
            return response()->json(['error' => 'No posts found for this campaign'], 404);
        }

        $updatedMetrics = [];
        foreach ($posts as $post) {
            try {
                $metric = $this->updatePostMetrics($post, $post->platform);
                $updatedMetrics[] = $metric;
            } catch (\Exception $e) {
                // Log error, but continue
            }
        }

        return response()->json([
            'message' => 'Metrics refreshed successfully',
            'campaign_id' => $campaignId,
            'posts_processed' => $posts->count(),
            'metrics_updated' => count($updatedMetrics),
            'metrics' => $updatedMetrics,
        ]);
    }

    /**
     * Actualiza métricas para una publicación desde la API de Meta.
     */
    public function updateMetrics(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'platform' => 'required|string|in:facebook,instagram',
        ]);

        $post = Post::find($postId);

        if (! $post) {
            return response()->json(['error' => 'Post not found'], 404);
        }

        try {
            $metric = $this->updatePostMetrics($post, $request->platform);

            return response()->json($metric);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

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
    public function getFacebookPosts(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $request->limit ?? 10;

        $pageId = env('META_PAGE_ID');
        $url = "{$this->metaApi->baseUrl}/{$pageId}/posts";
        $params = [
            'fields' => 'id,created_time,message,shares.summary(true).limit(0),comments.summary(true).limit(0),reactions.type(LIKE).summary(true).limit(0)',
            'access_token' => $this->metaApi->accessToken,
            'limit' => $limit,
        ];

        $response = Http::get($url, $params);

        if ($response->failed()) {
            return response()->json(['error' => 'Error fetching Facebook posts: '.$response->body()], 500);
        }

        $data = $response->json();

        // Guardar posts en la base de datos
        $savedPosts = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $postData) {
                $post = Post::updateOrCreate(
                    ['meta_post_id' => $postData['id']],
                    [
                        'campaign_id' => $request->campaign_id,
                        'title' => substr($postData['message'] ?? 'Facebook Post', 0, 255),
                        'platform' => 'facebook',
                        'content' => $postData['message'] ?? null,
                        'content_type' => 'text',
                        'status' => 'published',
                        'published_at' => $postData['created_time'],
                        'created_by' => auth()->id(),
                    ]
                );
                $savedPosts[] = $post;
                $this->updatePostMetrics($post, 'facebook');
            }
        }

        return response()->json([
            'message' => 'Posts and metrics saved successfully',
            'saved_count' => count($savedPosts),
            'data' => $data,
        ]);
    }

    /**
     * Obtener media de Instagram desde Meta API y guardarlos en la base de datos.
     */
    public function getInstagramPosts(Request $request): JsonResponse
    {
        $request->validate([
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $request->limit ?? 10;

        $igUserId = env('META_IG_USER_ID');
        $url = "{$this->metaApi->baseUrl}/{$igUserId}/media";
        $params = [
            'fields' => 'id,timestamp,media_type,like_count,comments_count,video_view_count',
            'access_token' => $this->metaApi->accessToken,
            'limit' => $limit,
        ];

        $response = Http::get($url, $params);

        if ($response->failed()) {
            return response()->json(['error' => 'Error fetching Instagram media: '.$response->body()], 500);
        }

        $data = $response->json();

        // Guardar posts en la base de datos
        $savedPosts = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $postData) {
                $post = Post::updateOrCreate(
                    ['meta_post_id' => $postData['id']],
                    [
                        'campaign_id' => $request->campaign_id,
                        'title' => 'Instagram Post',
                        'platform' => 'instagram',
                        'content' => null,
                        'content_type' => strtolower($postData['media_type']),
                        'status' => 'published',
                        'published_at' => $postData['timestamp'],
                        'created_by' => auth()->id(),
                    ]
                );
                $savedPosts[] = $post;
                $this->updatePostMetrics($post, 'instagram');
            }
        }

        return response()->json([
            'message' => 'Posts and metrics saved successfully',
            'saved_count' => count($savedPosts),
            'data' => $data,
        ]);
    }
}
