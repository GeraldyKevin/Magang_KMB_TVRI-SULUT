<?php

function ensureInstagramInsightsTable(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS ig_insights (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id VARCHAR(100) NOT NULL UNIQUE,
            media_url TEXT NULL,
            thumbnail_url TEXT NULL,
            media_type VARCHAR(30) NOT NULL DEFAULT 'IMAGE',
            caption TEXT NULL,
            likes INT NOT NULL DEFAULT 0,
            comments INT NOT NULL DEFAULT 0,
            posted_at DATETIME NULL,
            permalink TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $conn->query($sql);
}

function fetchInstagramMediaFromGraphApi(string $accountId, string $accessToken, int $limit = 12): array
{
    if ($accountId === '' || $accessToken === '' || $accountId === 'YOUR_INSTAGRAM_ACCOUNT_ID' || $accessToken === 'YOUR_ACCESS_TOKEN') {
        return [];
    }

    $endpoint = 'https://graph.facebook.com/v20.0/' . rawurlencode($accountId) . '/media';
    $params = http_build_query([
        'fields' => 'id,caption,media_type,media_url,thumbnail_url,timestamp,like_count,comments_count,permalink',
        'limit' => $limit,
        'access_token' => $accessToken,
    ]);

    $url = $endpoint . '?' . $params;

    $response = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    if ($response === false) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === null) {
        return [];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = $decoded['data'] ?? [];
    return is_array($items) ? $items : [];
}

function generateInstagramFallbackData(mysqli $conn): array
{
    $items = [];
    $result = $conn->query('SELECT id, nama_event, tanggal FROM kalender_event ORDER BY tanggal DESC, id DESC LIMIT 12');
    if (!$result) {
        return $items;
    }

    while ($row = $result->fetch_assoc()) {
        $postId = 'local_' . (string) ($row['id'] ?? '0');
        $caption = (string) ($row['nama_event'] ?? 'Konten TVRI Sulawesi Utara');
        $seed = abs((int) crc32($postId . $caption));

        $items[] = [
            'id' => $postId,
            'caption' => $caption,
            'media_type' => (($seed % 3) === 0) ? 'VIDEO' : ((($seed % 3) === 1) ? 'CAROUSEL_ALBUM' : 'IMAGE'),
            'media_url' => 'https://picsum.photos/seed/' . rawurlencode($postId) . '/800/500',
            'thumbnail_url' => 'https://picsum.photos/seed/thumb_' . rawurlencode($postId) . '/600/400',
            'timestamp' => (string) ($row['tanggal'] ?? date('Y-m-d')) . 'T18:00:00+08:00',
            'like_count' => 300 + ($seed % 1700),
            'comments_count' => 25 + (($seed >> 2) % 260),
            'permalink' => 'https://www.instagram.com/tvri_sulut_official',
        ];
    }

    return $items;
}

function upsertInstagramInsights(mysqli $conn, array $items): int
{
    if (count($items) === 0) {
        return 0;
    }

    $sql = 'INSERT INTO ig_insights (post_id, media_url, thumbnail_url, media_type, caption, likes, comments, posted_at, permalink)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                media_url = VALUES(media_url),
                thumbnail_url = VALUES(thumbnail_url),
                media_type = VALUES(media_type),
                caption = VALUES(caption),
                likes = VALUES(likes),
                comments = VALUES(comments),
                posted_at = VALUES(posted_at),
                permalink = VALUES(permalink),
                updated_at = CURRENT_TIMESTAMP';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $affected = 0;
    foreach ($items as $item) {
        $postId = trim((string) ($item['id'] ?? ''));
        if ($postId === '') {
            continue;
        }

        $mediaUrl = trim((string) ($item['media_url'] ?? ''));
        $thumbnailUrl = trim((string) ($item['thumbnail_url'] ?? ''));
        $mediaType = trim((string) ($item['media_type'] ?? 'IMAGE'));
        $caption = trim((string) ($item['caption'] ?? ''));
        $likes = (int) ($item['like_count'] ?? 0);
        $comments = (int) ($item['comments_count'] ?? 0);
        $permalink = trim((string) ($item['permalink'] ?? ''));

        $timestampRaw = trim((string) ($item['timestamp'] ?? ''));
        $postedAt = null;
        if ($timestampRaw !== '') {
            try {
                $dt = new DateTimeImmutable($timestampRaw);
                $postedAt = $dt->format('Y-m-d H:i:s');
            } catch (Throwable $e) {
                $postedAt = null;
            }
        }

        $stmt->bind_param(
            'sssssiiis',
            $postId,
            $mediaUrl,
            $thumbnailUrl,
            $mediaType,
            $caption,
            $likes,
            $comments,
            $postedAt,
            $permalink
        );
        $stmt->execute();
        $affected += max(0, $stmt->affected_rows);
    }

    $stmt->close();
    return $affected;
}

function syncInstagramInsights(mysqli $conn, string $accountId, string $accessToken): array
{
    ensureInstagramInsightsTable($conn);

    $items = fetchInstagramMediaFromGraphApi($accountId, $accessToken, 12);
    $mode = 'api';

    if (count($items) === 0) {
        $items = generateInstagramFallbackData($conn);
        $mode = 'fallback';
    }

    $affected = upsertInstagramInsights($conn, $items);

    return [
        'count' => count($items),
        'affected' => $affected,
        'mode' => $mode,
    ];
}
