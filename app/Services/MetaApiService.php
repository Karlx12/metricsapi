<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Servicio para interactuar con la API Graph de Meta (Facebook e Instagram).
 */
class MetaApiService
{
    public string $baseUrl = 'https://graph.facebook.com/v24.0';

    public string $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.meta.page_access_token') ?? env('META_PAGE_ACCESS_TOKEN');
    }

    /**
     * Obtiene métricas de un post de Facebook.
     */
    public function getFacebookPostMetrics(string $postId): array
    {
        // Obtener views de insights
        $insightsUrl = "{$this->baseUrl}/{$postId}/insights";
        $insightsParams = [
            'metric' => 'post_impressions',
            'access_token' => $this->accessToken,
        ];

        $insightsResponse = Http::get($insightsUrl, $insightsParams);

        $views = 0;
        if ($insightsResponse->successful()) {
            $insightsData = $insightsResponse->json()['data'] ?? [];
            foreach ($insightsData as $metric) {
                if ($metric['name'] === 'post_impressions') {
                    $views = $metric['values'][0]['value'] ?? 0;
                    break;
                }
            }
        }

        // Obtener likes, comments, shares del post
        $postUrl = "{$this->baseUrl}/{$postId}";
        $postParams = [
            'fields' => 'shares,comments.summary(true).limit(0),reactions.type(LIKE).summary(true).limit(0)',
            'access_token' => $this->accessToken,
        ];

        $postResponse = Http::get($postUrl, $postParams);

        if ($postResponse->failed()) {
            throw new \Exception('Error fetching Facebook post data: ' . $postResponse->body());
        }

        $postData = $postResponse->json();

        return [
            'views' => $views,
            'likes' => $postData['reactions']['summary']['total_count'] ?? 0,
            'comments' => $postData['comments']['summary']['total_count'] ?? 0,
            'shares' => $postData['shares']['count'] ?? 0,
        ];
    }

    /**
     * Obtiene métricas de un post de Instagram.
     */
    public function getInstagramPostMetrics(string $postId): array
    {
        // Obtener likes, comments, video_views
        $postUrl = "{$this->baseUrl}/{$postId}";
        $postParams = [
            'fields' => 'like_count,comments_count,video_view_count',
            'access_token' => $this->accessToken,
        ];

        $postResponse = Http::get($postUrl, $postParams);

        $likes = 0;
        $comments = 0;
        $videoViews = 0;

        if ($postResponse->successful()) {
            $postData = $postResponse->json();
            $likes = $postData['like_count'] ?? 0;
            $comments = $postData['comments_count'] ?? 0;
            $videoViews = $postData['video_view_count'] ?? 0;
        }

        // Obtener views (impressions), saved
        $insightsUrl = "{$this->baseUrl}/{$postId}/insights";
        $insightsParams = [
            'metric' => 'impressions,saved',
            'access_token' => $this->accessToken,
        ];

        $insightsResponse = Http::get($insightsUrl, $insightsParams);

        $views = $videoViews; // Default to video_views if available
        $saved = 0;

        if ($insightsResponse->successful()) {
            $insightsData = $insightsResponse->json()['data'] ?? [];
            foreach ($insightsData as $metric) {
                if ($metric['name'] === 'impressions') {
                    $views = $metric['values'][0]['value'] ?? $videoViews;
                } elseif ($metric['name'] === 'saved') {
                    $saved = $metric['values'][0]['value'] ?? 0;
                }
            }
        }

        return [
            'views' => $views,
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $saved,
        ];
    }
}
