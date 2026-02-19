<?php

function kmbSidebarGetCustomLimit(): int
{
    return 7;
}

function kmbSidebarEnsureSettingsTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS app_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

function kmbSidebarNormalizeUrl(string $rawUrl): ?string
{
    $url = trim($rawUrl);
    if ($url === '') {
        return null;
    }

    if (!preg_match('/^https?:\/\//i', $url)) {
        $url = 'https://' . $url;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '') {
        return null;
    }

    $path = (string) ($parts['path'] ?? '');
    $query = isset($parts['query']) ? ('?' . $parts['query']) : '';

    return $scheme . '://' . $host . $path . $query;
}

function kmbSidebarNormalizeLabel(string $rawLabel, string $fallback = 'Link Baru'): string
{
    $label = trim($rawLabel);
    $label = preg_replace('/\s+/', ' ', $label) ?: '';

    if ($label === '') {
        $label = $fallback;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($label, 0, 30);
    }

    return substr($label, 0, 30);
}

function kmbSidebarDefaultLinks(): array
{
    return [
        [
            'label' => 'MyInstants',
            'url' => 'https://www.myinstants.com/en/index/id/',
            'is_default' => true,
        ],
        [
            'label' => 'Pinterest',
            'url' => 'https://id.pinterest.com/',
            'is_default' => true,
        ],
        [
            'label' => 'Pixabay',
            'url' => 'https://pixabay.com/',
            'is_default' => true,
        ],
    ];
}

function kmbSidebarReadCustomLinks(mysqli $conn): array
{
    kmbSidebarEnsureSettingsTable($conn);

    $stmt = $conn->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        return [];
    }

    $key = 'sidebar_custom_urls';
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || !isset($row['setting_value'])) {
        return [];
    }

    $decoded = json_decode((string) $row['setting_value'], true);
    if (!is_array($decoded)) {
        return [];
    }

    $links = [];
    foreach ($decoded as $item) {
        $rawUrl = null;
        $rawLabel = null;

        if (is_string($item)) {
            $rawUrl = $item;
            $rawLabel = '';
        } elseif (is_array($item)) {
            $rawUrl = (string) ($item['url'] ?? '');
            $rawLabel = (string) ($item['label'] ?? '');
        }

        if (!is_string($rawUrl) || $rawUrl === '') {
            continue;
        }

        $normalized = kmbSidebarNormalizeUrl($rawUrl);
        if ($normalized === null) {
            continue;
        }

        $links[] = [
            'label' => kmbSidebarNormalizeLabel((string) $rawLabel, kmbSidebarLabelFromUrl($normalized)),
            'url' => $normalized,
            'is_default' => false,
        ];
    }

    $seen = [];
    $result = [];
    foreach ($links as $link) {
        $normalizedUrl = (string) ($link['url'] ?? '');
        if ($normalizedUrl === '' || isset($seen[$normalizedUrl])) {
            continue;
        }

        $seen[$normalizedUrl] = true;
        $result[] = $link;
    }

    return $result;
}

function kmbSidebarWriteCustomLinks(mysqli $conn, array $entries): bool
{
    kmbSidebarEnsureSettingsTable($conn);

    $normalizedList = [];
    foreach ($entries as $item) {
        if (!is_array($item)) {
            continue;
        }

        $rawUrl = (string) ($item['url'] ?? '');
        $rawLabel = (string) ($item['label'] ?? '');

        $normalized = kmbSidebarNormalizeUrl($rawUrl);
        if ($normalized === null) {
            continue;
        }

        $normalizedList[] = [
            'label' => kmbSidebarNormalizeLabel($rawLabel, kmbSidebarLabelFromUrl($normalized)),
            'url' => $normalized,
        ];
    }

    $seen = [];
    $deduped = [];
    foreach ($normalizedList as $item) {
        $itemUrl = (string) ($item['url'] ?? '');
        if ($itemUrl === '' || isset($seen[$itemUrl])) {
            continue;
        }

        $seen[$itemUrl] = true;
        $deduped[] = $item;
    }

    $normalizedList = $deduped;
    $payload = json_encode($normalizedList, JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
    if (!$stmt) {
        return false;
    }

    $key = 'sidebar_custom_urls';
    $stmt->bind_param('ss', $key, $payload);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function kmbSidebarLabelFromUrl(string $url): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    if ($host === '') {
        return 'Link Baru';
    }

    $host = preg_replace('/^www\./i', '', $host) ?: $host;
    $segments = explode('.', $host);
    $name = $segments[0] ?? $host;

    return ucfirst($name);
}

function kmbSidebarBadgeFromLabel(string $label): string
{
    $clean = trim($label);
    if ($clean === '') {
        return '•';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($clean, 0, 1));
    }

    return strtoupper(substr($clean, 0, 1));
}

function kmbSidebarGetCustomLinksWithLabels(mysqli $conn): array
{
    return kmbSidebarReadCustomLinks($conn);
}

function kmbSidebarGetShortcutLinks(mysqli $conn): array
{
    $defaultLinks = kmbSidebarDefaultLinks();
    $customLinks = kmbSidebarGetCustomLinksWithLabels($conn);

    $seen = [];
    $all = [];

    foreach (array_merge($defaultLinks, $customLinks) as $item) {
        $url = (string) ($item['url'] ?? '');
        $normalizedUrl = kmbSidebarNormalizeUrl($url);
        if ($normalizedUrl === null) {
            continue;
        }

        if (isset($seen[$normalizedUrl])) {
            continue;
        }

        $seen[$normalizedUrl] = true;
        $item['url'] = $normalizedUrl;
        $all[] = $item;
    }

    return $all;
}

function kmbSidebarAddCustomLink(mysqli $conn, string $rawLabel, string $rawUrl, ?string &$errorMessage = null): bool
{
    $normalized = kmbSidebarNormalizeUrl($rawUrl);
    if ($normalized === null) {
        $errorMessage = 'URL tidak valid. Gunakan format link yang benar.';
        return false;
    }

    $label = kmbSidebarNormalizeLabel($rawLabel);

    $existingCustom = kmbSidebarGetCustomLinksWithLabels($conn);
    $allLinks = array_merge(kmbSidebarDefaultLinks(), $existingCustom);

    foreach ($allLinks as $existingItem) {
        $existingUrl = kmbSidebarNormalizeUrl((string) ($existingItem['url'] ?? ''));
        if ($existingUrl !== null && $existingUrl === $normalized) {
            $errorMessage = 'URL tersebut sudah ada di daftar pintasan.';
            return false;
        }
    }

    if (count($existingCustom) >= kmbSidebarGetCustomLimit()) {
        $errorMessage = 'Maksimal ' . kmbSidebarGetCustomLimit() . ' URL tambahan untuk pintasan.';
        return false;
    }

    $existingCustom[] = [
        'label' => $label,
        'url' => $normalized,
        'is_default' => false,
    ];

    $saved = kmbSidebarWriteCustomLinks($conn, $existingCustom);
    if (!$saved) {
        $errorMessage = 'Gagal menyimpan URL baru. Silakan coba lagi.';
        return false;
    }

    return true;
}

function kmbSidebarDeleteCustomLink(mysqli $conn, string $rawUrl, ?string &$errorMessage = null): bool
{
    $normalized = kmbSidebarNormalizeUrl($rawUrl);
    if ($normalized === null) {
        $errorMessage = 'URL pintasan tidak valid.';
        return false;
    }

    $existingCustom = kmbSidebarGetCustomLinksWithLabels($conn);
    $updated = [];
    $found = false;

    foreach ($existingCustom as $item) {
        $itemUrl = kmbSidebarNormalizeUrl((string) ($item['url'] ?? ''));
        if (!$found && $itemUrl !== null && $itemUrl === $normalized) {
            $found = true;
            continue;
        }

        $updated[] = $item;
    }

    if (!$found) {
        $errorMessage = 'Pintasan tidak ditemukan atau sudah dihapus.';
        return false;
    }

    if (!kmbSidebarWriteCustomLinks($conn, $updated)) {
        $errorMessage = 'Gagal menghapus pintasan. Silakan coba lagi.';
        return false;
    }

    return true;
}
