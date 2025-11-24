# Metrics API

This microservice provides endpoints to fetch, update, and manage metrics for social media posts (Facebook and Instagram) using the Meta Graph API. It integrates with the core-domain package for data persistence.

Base route prefix: `/api/v1/marketing/metrics`

All endpoints require authentication via Bearer token (`Authorization: Bearer <token>`).

## Environment Variables

- `META_PAGE_ACCESS_TOKEN`: Access token for Meta API.
- `META_PAGE_ID`: Facebook Page ID.
- `META_IG_USER_ID`: Instagram User ID.
- `META_IG_ACCESS_TOKEN`: Instagram Access Token (optional, uses page token if not provided).

## Endpoints

### Get Metrics for a Post

- **Method**: GET
- **URL**: `/api/v1/marketing/metrics/post/{postId}`
- **Parameters**:
  - `postId` (path, integer): ID of the post in the database.
- **Purpose**: Retrieves the latest metrics for a specific post.

  ```bash
  curl -X GET "http://localhost:8001/api/v1/marketing/metrics/post/12" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Accept: application/json"
  ```

### Update Metrics for a Post

- **Method**: POST
- **URL**: `/api/v1/marketing/metrics/post/{postId}/update`
- **Parameters**:
  - `postId` (path, integer): ID of the post.
  - `platform` (body, required, string): "facebook" or "instagram".
- **Purpose**: Fetches and updates metrics for the post from Meta API.

  ```bash
  curl -X POST "http://localhost:8001/api/v1/marketing/metrics/post/12/update" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"platform": "facebook"}'
  ```

### Get Metrics for a Campaign

- **Method**: GET
- **URL**: `/api/v1/marketing/metrics/campaign/{campaignId}`
- **Parameters**:
  - `campaignId` (path, integer): ID of the campaign.
- **Purpose**: Retrieves consolidated metrics for all posts associated with the campaign.

  ```bash
  curl -X GET "http://localhost:8001/api/v1/marketing/metrics/campaign/1" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Accept: application/json"
  ```

### Refresh Metrics for a Campaign

- **Method**: POST
- **URL**: `/api/v1/marketing/metrics/campaign/{campaignId}/refresh`
- **Parameters**:
  - `campaignId` (path, integer): ID of the campaign.
- **Purpose**: Updates metrics for all posts in the campaign from Meta API.

  ```bash
  curl -X POST "http://localhost:8001/api/v1/marketing/metrics/campaign/1/refresh" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Accept: application/json"
  ```

### Fetch Facebook Posts

- **Method**: GET
- **URL**: `/api/v1/marketing/metrics/facebook/posts`
- **Parameters**:
  - `campaign_id` (query, optional, integer): Associate fetched posts with this campaign.
  - `limit` (query, optional, integer, 1-100): Number of posts to fetch (default 10).
- **Purpose**: Fetches posts from Facebook Page via Meta API, saves them to DB, and updates metrics.

  ```bash
  curl -X GET "http://localhost:8001/api/v1/marketing/metrics/facebook/posts?campaign_id=1&limit=5" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Accept: application/json"
  ```

### Fetch Instagram Posts

- **Method**: GET
- **URL**: `/api/v1/marketing/metrics/instagram/posts`
- **Parameters**:
  - `campaign_id` (query, optional, integer): Associate fetched media with this campaign.
  - `limit` (query, optional, integer, 1-100): Number of media to fetch (default 10).
- **Purpose**: Fetches media from Instagram User via Meta API, saves them to DB, and updates metrics.

  ```bash
  curl -X GET "http://localhost:8001/api/v1/marketing/metrics/instagram/posts?campaign_id=1&limit=5" \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -H "Accept: application/json"
  ```

## Notes

- Metrics include views, likes, comments, shares, engagement, reach, impressions, saves.
- Posts are saved to the database with associations to campaigns if provided.
- Requires valid Meta API tokens for fetching/updating metrics.
