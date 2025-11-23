<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Servicio para interactuar con la API Graph de Meta (Facebook e Instagram).
 */
class MetaApiService
{
    protected string $baseUrl = 'https://graph.facebook.com/v24.0';

    protected string $accessToken;

    public function __construct()
    {
        $this->accessToken = config('services.meta.page_access_token') ?? env('META_PAGE_ACCESS_TOKEN');
    }

    /**
     * Obtiene métricas de un post de Facebook.
     */
    public function getFacebookPostMetrics(string $postId): array
    {
        $url = "{$this->baseUrl}/{$postId}/insights";
        $params = [
            'metric' => 'post_impressions,post_engaged_users,post_reactions_by_type_total,post_comments,post_shares',
            'access_token' => $this->accessToken,
        ];

        $response = Http::get($url, $params);

        if ($response->failed()) {
            throw new \Exception('Error fetching Facebook metrics: ' . $response->body());
        }

        $data = $response->json()['data'] ?? [];

        return $this->parseFacebookMetrics($data);
    }

    /**
     * Obtiene métricas de un post de Instagram.
     */
    public function getInstagramPostMetrics(string $postId): array
    {
        $url = "{$this->baseUrl}/{$postId}/insights";
        $params = [
            'metric' => 'impressions,reach,likes,comments,shares',
            'access_token' => $this->accessToken,
        ];

        $response = Http::get($url, $params);

        if ($response->failed()) {
            throw new \Exception('Error fetching Instagram metrics: ' . $response->body());
        }

        $data = $response->json()['data'] ?? [];

        return $this->parseInstagramMetrics($data);
    }

    /**
     * Parsea métricas de Facebook.
     */
    protected function parseFacebookMetrics(array $data): array
    {
        $metrics = [
            'views' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
        ];

        foreach ($data as $metric) {
            switch ($metric['name']) {
                case 'post_impressions':
                    $metrics['views'] = $metric['values'][0]['value'] ?? 0;
                    break;
                case 'post_engaged_users':
                    // Approximate likes from engaged users, but actually need reactions
                    break;
                case 'post_reactions_by_type_total':
                    $reactions = $metric['values'][0]['value'] ?? [];
                    $metrics['likes'] = ($reactions['like'] ?? 0) + ($reactions['love'] ?? 0) + ($reactions['wow'] ?? 0) + ($reactions['haha'] ?? 0) + ($reactions['sad'] ?? 0) + ($reactions['angry'] ?? 0);
                    break;
                case 'post_comments':
                    $metrics['comments'] = $metric['values'][0]['value'] ?? 0;
                    break;
                case 'post_shares':
                    $metrics['shares'] = $metric['values'][0]['value'] ?? 0;
                    break;
            }
        }

        return $metrics;
    }

    /**
     * Parsea métricas de Instagram.
     */
    protected function parseInstagramMetrics(array $data): array
    {
        $metrics = [
            'views' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
        ];

        foreach ($data as $metric) {
            switch ($metric['name']) {
                case 'impressions':
                    $metrics['views'] = $metric['values'][0]['value'] ?? 0;
                    break;
                case 'reach':
                    // Reach is different from views, but for simplicity
                    break;
                case 'likes':
                    $metrics['likes'] = $metric['values'][0]['value'] ?? 0;
                    break;
                case 'comments':
                    $metrics['comments'] = $metric['values'][0]['value'] ?? 0;
                    break;
                case 'shares':
                    $metrics['shares'] = $metric['values'][0]['value'] ?? 0;
                    break;
            }
        }

        return $metrics;
    }
}
