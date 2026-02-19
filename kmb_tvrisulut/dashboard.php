<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/instagram_sync.php';
require_once __DIR__ . '/config/sidebar_shortcuts.php';

$namaAdmin = $_SESSION['nama_lengkap'] ?? 'Admin';
$syncMessage = '';
$playLoginSound = !empty($_SESSION['play_login_sound']);
unset($_SESSION['play_login_sound']);
$loginSoundSource = '';
$sidebarShortcutLinks = kmbSidebarGetShortcutLinks($conn);

if ($playLoginSound) {
    $welcomeDirFs = __DIR__ . '/Music/WELCOME';
    if (is_dir($welcomeDirFs)) {
        $soundFiles = array_values(array_filter(scandir($welcomeDirFs) ?: [], function ($fileName) use ($welcomeDirFs) {
            $fullPath = $welcomeDirFs . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($fullPath)) {
                return false;
            }

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            return in_array($extension, ['mp3', 'wav', 'ogg'], true);
        }));

        if (count($soundFiles) > 0) {
            $selectedFile = $soundFiles[array_rand($soundFiles)];
            $loginSoundSource = 'Music/WELCOME/' . rawurlencode($selectedFile);
        }
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$instagramAccessToken = 'YOUR_ACCESS_TOKEN';
$instagramAccountId = 'YOUR_INSTAGRAM_ACCOUNT_ID';

if (isset($_GET['sync_ig']) && $_GET['sync_ig'] === '1') {
    $syncResult = syncInstagramInsights($conn, $instagramAccountId, $instagramAccessToken);
    $syncMessage = 'Sinkronisasi selesai: ' . (int) ($syncResult['count'] ?? 0) . ' konten diproses (' . strtoupper((string) ($syncResult['mode'] ?? 'fallback')) . ').';
}

ensureInstagramInsightsTable($conn);

function fetchHariLiburApi(): array
{
    $url = 'https://hari-libur-api.vercel.app/api';
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    if ($response === false) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === null) {
        return [];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

function normalizeHariLiburItem(array $item): ?array
{
    $dateKey = trim((string) ($item['date'] ?? $item['event_date'] ?? ''));
    $eventNameRaw = trim((string) ($item['event'] ?? $item['event_name'] ?? ''));
    $eventName = preg_replace('/\s+/', ' ', $eventNameRaw) ?: '';
    $isNationalHolidayRaw = $item['is_national_holiday'] ?? false;
    $isNationalHoliday = filter_var($isNationalHolidayRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($isNationalHoliday === null) {
        $isNationalHoliday = (bool) $isNationalHolidayRaw;
    }

    if ($dateKey === '' || $eventName === '') {
        return null;
    }

    return [
        'date' => $dateKey,
        'event' => $eventName,
        'is_national_holiday' => $isNationalHoliday,
    ];
}

function percentChange(float $current, float $previous): float
{
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }

    return (($current - $previous) / $previous) * 100;
}

function classifyContentCategory(string $title): string
{
    $normalized = mb_strtolower($title, 'UTF-8');

    $map = [
        'Olahraga' => ['bola', 'liga', 'timnas', 'sport', 'olahraga', 'turnamen'],
        'Hiburan' => ['hiburan', 'musik', 'konser', 'artis', 'film', 'show'],
        'Edukasi' => ['edukasi', 'belajar', 'tips', 'sekolah', 'kampus', 'literasi'],
        'Berita' => ['berita', 'breaking', 'update', 'peristiwa', 'politik', 'daerah'],
    ];

    foreach ($map as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($normalized, $keyword, 0, 'UTF-8') !== false) {
                return $category;
            }
        }
    }

    return 'Berita';
}

$totalEvent = 0;
$totalRencana = 0;

$resultEvent = $conn->query('SELECT COUNT(*) AS total FROM kalender_event');
if ($resultEvent) {
    $totalEvent = (int) ($resultEvent->fetch_assoc()['total'] ?? 0);
}

$resultRencana = $conn->query('SELECT COUNT(*) AS total FROM rencana_konten');
if ($resultRencana) {
    $totalRencana = (int) ($resultRencana->fetch_assoc()['total'] ?? 0);
}

$today = new DateTimeImmutable('today');
$todayStr = $today->format('Y-m-d');
$todayLiveText = (new DateTimeImmutable('now'))->format('d-m-Y H:i:s');
$monthStart = new DateTimeImmutable($today->format('Y-m-01'));
$daysInMonth = (int) $monthStart->format('t');
$startDayOffset = (int) $monthStart->format('N') - 1;

$monthNames = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];

$dayNames = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu',
];

$todayInfoLabel = $dayNames[(int) $today->format('N')] . ', ' . $today->format('d-m-Y');
$todayDateNumberDisplay = $today->format('d');
$monthNameEnglish = strtoupper($today->format('F'));
$calendarTagline = $today->format('Y') . ' TVRI SULAWESI UTARA';
$todayHighlightItems = [];
$miniMap = [];

$startOfWeek = $today->modify('monday this week');
$endOfWeek = $startOfWeek->modify('+6 days');
$startOfPrevWeek = $startOfWeek->modify('-7 days');
$endOfPrevWeek = $startOfWeek->modify('-1 day');

$thisWeekStartStr = $startOfWeek->format('Y-m-d');
$thisWeekEndStr = $endOfWeek->format('Y-m-d');
$prevWeekStartStr = $startOfPrevWeek->format('Y-m-d');
$prevWeekEndStr = $endOfPrevWeek->format('Y-m-d');

$hariLiburItems = fetchHariLiburApi();
foreach ($hariLiburItems as $item) {
    $normalized = normalizeHariLiburItem($item);
    if ($normalized === null) {
        continue;
    }

    $dateKey = $normalized['date'];
    $eventName = $normalized['event'];
    $isNationalHoliday = (bool) $normalized['is_national_holiday'];

    $eventDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateKey);
    if (!$eventDate) {
        continue;
    }

    if ((int) $eventDate->format('Y') === (int) $today->format('Y') && (int) $eventDate->format('n') === (int) $today->format('n')) {
        if (!isset($miniMap[$dateKey])) {
            $miniMap[$dateKey] = [];
        }
        $miniMap[$dateKey][] = $eventName;
    }

    if ($dateKey === $todayStr) {
        $todayHighlightItems[] = ($isNationalHoliday ? 'Libur Nasional' : 'Hari Penting') . ': ' . $eventName;
    }
}

$stmtMonthLocal = $conn->prepare('SELECT tanggal, nama_event FROM kalender_event WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ? ORDER BY tanggal ASC');
$currentYear = (int) $today->format('Y');
$currentMonth = (int) $today->format('n');
$stmtMonthLocal->bind_param('ii', $currentYear, $currentMonth);
$stmtMonthLocal->execute();
$resultMonthLocal = $stmtMonthLocal->get_result();
while ($row = $resultMonthLocal->fetch_assoc()) {
    $dateKey = (string) ($row['tanggal'] ?? '');
    $eventName = trim((string) ($row['nama_event'] ?? ''));
    if ($dateKey === '' || $eventName === '') {
        continue;
    }

    if (!isset($miniMap[$dateKey])) {
        $miniMap[$dateKey] = [];
    }
    $miniMap[$dateKey][] = $eventName;
}
$stmtMonthLocal->close();

$stmtTodayLocal = $conn->prepare('SELECT nama_event, jenis_event FROM kalender_event WHERE tanggal = ? ORDER BY id ASC');
$stmtTodayLocal->bind_param('s', $todayStr);
$stmtTodayLocal->execute();
$resultTodayLocal = $stmtTodayLocal->get_result();
while ($row = $resultTodayLocal->fetch_assoc()) {
    $todayHighlightItems[] = 'Acara: ' . trim((string) ($row['nama_event'] ?? ''));
}
$stmtTodayLocal->close();

$todayHighlightItems = array_values(array_unique(array_filter($todayHighlightItems)));
$todayDisplayItems = array_slice($todayHighlightItems, 0, 5);
$todayRemainingCount = max(0, count($todayHighlightItems) - count($todayDisplayItems));

$thisWeekPostCount = 0;
$prevWeekPostCount = 0;

$stmtWeekPosts = $conn->prepare('SELECT COUNT(*) AS total FROM kalender_event WHERE tanggal BETWEEN ? AND ?');
$stmtWeekPosts->bind_param('ss', $thisWeekStartStr, $thisWeekEndStr);
$stmtWeekPosts->execute();
$resultWeekPosts = $stmtWeekPosts->get_result();
if ($resultWeekPosts) {
    $thisWeekPostCount = (int) ($resultWeekPosts->fetch_assoc()['total'] ?? 0);
}
$stmtWeekPosts->close();

$stmtPrevWeekPosts = $conn->prepare('SELECT COUNT(*) AS total FROM kalender_event WHERE tanggal BETWEEN ? AND ?');
$stmtPrevWeekPosts->bind_param('ss', $prevWeekStartStr, $prevWeekEndStr);
$stmtPrevWeekPosts->execute();
$resultPrevWeekPosts = $stmtPrevWeekPosts->get_result();
if ($resultPrevWeekPosts) {
    $prevWeekPostCount = (int) ($resultPrevWeekPosts->fetch_assoc()['total'] ?? 0);
}
$stmtPrevWeekPosts->close();

$dailyLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$dailyInteractions = [];
for ($i = 0; $i < 7; $i++) {
    $dayDate = $startOfWeek->modify('+' . $i . ' day');
    $seed = (int) $dayDate->format('N') + ((int) $dayDate->format('d') * 7) + ($thisWeekPostCount * 9);
    $dailyInteractions[] = 95 + ($seed % 140);
}

$totalInteractions = array_sum($dailyInteractions);

$followersCurrent = 15720 + ($thisWeekPostCount * 4);
$followersPrevious = 15480 + ($prevWeekPostCount * 4);
$followerGrowthCount = $followersCurrent - $followersPrevious;

$reachCurrent = 21200 + ($totalInteractions * 8);
$reachPrevious = 19800 + (array_sum(array_slice($dailyInteractions, 0, 5)) * 7);

$engagementRate = ($followersCurrent > 0) ? (($totalInteractions / $followersCurrent) * 100) : 0;
$engagementRatePrevious = ($followersPrevious > 0)
    ? (((int) round($totalInteractions * 0.9) / $followersPrevious) * 100)
    : 0;

$engagementChange = percentChange($engagementRate, $engagementRatePrevious);
$reachChange = percentChange((float) $reachCurrent, (float) $reachPrevious);
$followerGrowthChange = percentChange((float) $followerGrowthCount, (float) max(1, $followersPrevious - 15300));
$postingChange = percentChange((float) $thisWeekPostCount, (float) $prevWeekPostCount);

$platformProfiles = [
    ['key' => 'youtube', 'label' => 'YouTube', 'handle' => '@tvrisulut_streaming', 'reach_factor' => 1.18, 'er_factor' => 1.12, 'growth_factor' => 1.05],
    ['key' => 'tiktok', 'label' => 'TikTok', 'handle' => '@tvrisulut', 'reach_factor' => 1.08, 'er_factor' => 1.22, 'growth_factor' => 1.11],
    ['key' => 'facebook', 'label' => 'Facebook', 'handle' => 'TvriSulawesiUtaraOfficial', 'reach_factor' => 0.92, 'er_factor' => 0.88, 'growth_factor' => 0.84],
];

$platformEngagementCards = [];
foreach ($platformProfiles as $profile) {
    $label = (string) ($profile['label'] ?? 'Platform');
    $reachFactor = (float) ($profile['reach_factor'] ?? 1);
    $erFactor = (float) ($profile['er_factor'] ?? 1);
    $growthFactor = (float) ($profile['growth_factor'] ?? 1);

    $platformReachCurrent = (int) round($reachCurrent * $reachFactor);
    $platformReachPrev = (int) round($reachPrevious * max(0.65, $reachFactor - 0.05));
    $platformInteractionsCurrent = max(1, (int) round($totalInteractions * $erFactor));
    $platformInteractionsPrev = max(1, (int) round($platformInteractionsCurrent * 0.9));

    $platformFollowersCurrent = max(1, (int) round($followersCurrent * max(0.6, $growthFactor)));
    $platformFollowersPrev = max(1, (int) round($followersPrevious * max(0.58, $growthFactor - 0.03)));

    $platformER = ($platformFollowersCurrent > 0)
        ? (($platformInteractionsCurrent / $platformFollowersCurrent) * 100)
        : 0;
    $platformERPrev = ($platformFollowersPrev > 0)
        ? (($platformInteractionsPrev / $platformFollowersPrev) * 100)
        : 0;

    $platformEngagementCards[] = [
        'label' => $label,
        'handle' => (string) ($profile['handle'] ?? ''),
        'engagement_rate' => $platformER,
        'engagement_change' => percentChange($platformER, $platformERPrev),
        'reach' => $platformReachCurrent,
        'reach_change' => percentChange((float) $platformReachCurrent, (float) $platformReachPrev),
        'interactions' => $platformInteractionsCurrent,
    ];
}

$topPosts = [];
$resultRecentPosts = $conn->query('SELECT id, nama_event, tanggal FROM kalender_event ORDER BY tanggal DESC, id DESC LIMIT 30');
if ($resultRecentPosts) {
    while ($row = $resultRecentPosts->fetch_assoc()) {
        $seed = abs((int) crc32((string) ($row['nama_event'] ?? '') . (string) ($row['tanggal'] ?? '') . (string) ($row['id'] ?? 0)));
        $likes = 160 + ($seed % 1200);
        $comments = 25 + (($seed >> 2) % 190);
        $shares = 18 + (($seed >> 4) % 140);
        $saves = 12 + (($seed >> 6) % 170);
        $score = $likes + ($comments * 2) + ($shares * 3) + ($saves * 3);

        $topPosts[] = [
            'judul' => (string) ($row['nama_event'] ?? ''),
            'tanggal' => (string) ($row['tanggal'] ?? ''),
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
            'saves' => $saves,
            'score' => $score,
        ];
    }
}

if (count($topPosts) === 0) {
    $topPosts = [
        ['judul' => 'Reels Liputan Banjir Manado', 'tanggal' => $today->format('Y-m-d'), 'likes' => 1360, 'comments' => 184, 'shares' => 211, 'saves' => 165, 'score' => 0],
        ['judul' => 'Breaking News Sulut Malam', 'tanggal' => $today->modify('-1 day')->format('Y-m-d'), 'likes' => 1242, 'comments' => 158, 'shares' => 196, 'saves' => 121, 'score' => 0],
        ['judul' => 'Info Cuaca Sulawesi Utara', 'tanggal' => $today->modify('-2 day')->format('Y-m-d'), 'likes' => 980, 'comments' => 94, 'shares' => 143, 'saves' => 90, 'score' => 0],
        ['judul' => 'Highlight Program Hiburan', 'tanggal' => $today->modify('-3 day')->format('Y-m-d'), 'likes' => 915, 'comments' => 88, 'shares' => 127, 'saves' => 85, 'score' => 0],
        ['judul' => 'Edukasi Cegah Hoaks', 'tanggal' => $today->modify('-4 day')->format('Y-m-d'), 'likes' => 872, 'comments' => 79, 'shares' => 118, 'saves' => 82, 'score' => 0],
    ];
}

usort($topPosts, function (array $a, array $b): int {
    return (int) (($b['score'] ?? 0) <=> ($a['score'] ?? 0));
});
$topPosts = array_slice($topPosts, 0, 5);

$categoryTotals = [
    'Berita' => 0,
    'Hiburan' => 0,
    'Olahraga' => 0,
    'Edukasi' => 0,
];

$statusWeight = [
    'Ide' => 1,
    'Proses' => 2,
    'Siap Tayang' => 3,
    'Selesai' => 4,
];

$resultKategori = $conn->query('SELECT judul_konten, status FROM rencana_konten ORDER BY id DESC LIMIT 300');
if ($resultKategori) {
    while ($row = $resultKategori->fetch_assoc()) {
        $title = (string) ($row['judul_konten'] ?? '');
        if ($title === '') {
            continue;
        }

        $category = classifyContentCategory($title);
        $seed = abs((int) crc32($title));
        $weight = $statusWeight[(string) ($row['status'] ?? 'Ide')] ?? 1;
        $engagementValue = 12 + ($seed % 34) + ($weight * 6);
        $categoryTotals[$category] += $engagementValue;
    }
}

if (array_sum($categoryTotals) === 0) {
    $categoryTotals = [
        'Berita' => 120,
        'Hiburan' => 95,
        'Olahraga' => 80,
        'Edukasi' => 70,
    ];
}

$dashboardPayload = [
    'dailyLabels' => $dailyLabels,
    'dailyInteractions' => $dailyInteractions,
    'categoryLabels' => array_keys($categoryTotals),
    'categoryValues' => array_values($categoryTotals),
];

$igCards = [];
$resultIg = $conn->query('SELECT post_id, media_url, thumbnail_url, media_type, caption, likes, comments, posted_at, permalink FROM ig_insights ORDER BY posted_at DESC, id DESC LIMIT 12');
if ($resultIg) {
    while ($row = $resultIg->fetch_assoc()) {
        $igCards[] = $row;
    }
}

$igBarItems = array_slice($igCards, 0, 5);
$igBarLabels = [];
$igBarValues = [];
foreach ($igBarItems as $idx => $item) {
    $label = 'Konten ' . ($idx + 1);
    $caption = trim((string) ($item['caption'] ?? ''));
    if ($caption !== '') {
        $label = mb_substr($caption, 0, 16, 'UTF-8');
    }

    $likes = (int) ($item['likes'] ?? 0);
    $comments = (int) ($item['comments'] ?? 0);
    $igBarLabels[] = $label;
    $igBarValues[] = $likes + $comments;
}

$dashboardPayload['igBarLabels'] = $igBarLabels;
$dashboardPayload['igBarValues'] = $igBarValues;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - KMB TVRI SULAWESI UTARA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dashboard-body">
    <div class="app-shell">
        <aside class="sidebar">
            <h2>KMB TVRI</h2>
            <nav>
                <a class="active" href="dashboard.php" data-icon="🏠"><span class="nav-text">Beranda</span></a>
                <a href="kalender.php" data-icon="📅"><span class="nav-text">Kalender Konten</span></a>
                <a href="rencana_konten.php" data-icon="📝"><span class="nav-text">Rencana Konten</span></a>
                <a href="admin_settings.php" data-icon="⚙️"><span class="nav-text">Pengaturan Admin</span></a>
                <a href="logout.php" data-icon="🚪"><span class="nav-text">Logout</span></a>
            </nav>
            <div class="sidebar-shortcuts" aria-label="Pintasan website">
                <p class="sidebar-shortcuts-title">Pintasan Web</p>
                <div class="sidebar-shortcuts-list">
                    <?php foreach ($sidebarShortcutLinks as $shortcut): ?>
                        <?php
                            $shortcutLabel = (string) ($shortcut['label'] ?? 'Pintasan');
                            $shortcutBubble = kmbSidebarBadgeFromLabel($shortcutLabel);
                        ?>
                        <a
                            class="shortcut-link"
                            aria-label="<?= htmlspecialchars($shortcutLabel, ENT_QUOTES, 'UTF-8'); ?>"
                            data-label="<?= htmlspecialchars($shortcutLabel, ENT_QUOTES, 'UTF-8'); ?>"
                            href="<?= htmlspecialchars((string) ($shortcut['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        ><span class="shortcut-badge-text"><?= htmlspecialchars($shortcutBubble, ENT_QUOTES, 'UTF-8'); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sidebar-social" aria-label="Akses media sosial KMB TVRI">
                <a class="social-link instagram" href="https://www.instagram.com/tvri_sulut_official" target="_blank" rel="noopener noreferrer" aria-label="Instagram TVRI Sulawesi Utara">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2Zm0 1.5A4.25 4.25 0 0 0 3.5 7.75v8.5a4.25 4.25 0 0 0 4.25 4.25h8.5a4.25 4.25 0 0 0 4.25-4.25v-8.5a4.25 4.25 0 0 0-4.25-4.25h-8.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 1.5A3.5 3.5 0 1 0 12 15.5 3.5 3.5 0 0 0 12 8.5Zm5.4-2.45a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1Z"/></svg>
                </a>
                <a class="social-link facebook" href="https://www.facebook.com/TvriSulawesiUtaraOfficial" target="_blank" rel="noopener noreferrer" aria-label="Facebook TVRI Sulawesi Utara">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.36 21v-8.2h2.76l.44-3.2h-3.2V7.56c0-.93.26-1.56 1.6-1.56h1.7V3.14c-.82-.09-1.65-.13-2.48-.12-2.46 0-4.15 1.5-4.15 4.24V9.6H7.24v3.2h2.79V21h3.33Z"/></svg>
                </a>
                <a class="social-link tiktok" href="https://www.tiktok.com/@tvrisulut" target="_blank" rel="noopener noreferrer" aria-label="TikTok TVRI Sulawesi Utara">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.76 3c.33 1.77 1.38 3.13 3.07 3.89 1.01.46 2.02.57 3.17.47v3.07a7.74 7.74 0 0 1-3.16-.63v5.47a6.81 6.81 0 1 1-5.83-6.74v3.16a3.69 3.69 0 1 0 2.75 3.58V3h3Z"/></svg>
                </a>
                <a class="social-link youtube" href="https://www.youtube.com/@tvrisulut_streaming" target="_blank" rel="noopener noreferrer" aria-label="YouTube TVRI Sulawesi Utara">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 12c0 3.46-.27 5.63-.72 6.68-.44 1.01-1.24 1.8-2.25 2.25C18.98 21.39 16.81 21.66 12 21.66s-6.98-.27-8.03-.73a3.34 3.34 0 0 1-2.25-2.25C1.27 17.63 1 15.46 1 12s.27-5.63.72-6.68A3.34 3.34 0 0 1 3.97 3.07C5.02 2.61 7.19 2.34 12 2.34s6.98.27 8.03.73a3.34 3.34 0 0 1 2.25 2.25C22.73 6.37 23 8.54 23 12Zm-14.5 4.2 7-4.2-7-4.2v8.4Z"/></svg>
                </a>
            </div>
        </aside>

        <section class="content-area">
            <header class="top-header">
                <div>
                    <h1>Dashboard Monitoring Insight</h1>
                    <p>Halo, <?= htmlspecialchars($namaAdmin, ENT_QUOTES, 'UTF-8'); ?>!</p>
                </div>

                <aside class="today-info-card">
                    <h3>Info Hari Ini</h3>
                    <div class="clock-widget" aria-hidden="true">
                        <div class="clock-face" id="clockFace">
                            <span class="clock-center-dot"></span>
                            <span id="clockHour" class="clock-hand hour"></span>
                            <span id="clockMinute" class="clock-hand minute"></span>
                            <span id="clockSecond" class="clock-hand second"></span>
                        </div>
                    </div>
                    <p id="liveDateTime" data-base-time="<?= htmlspecialchars($todayLiveText, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($todayLiveText, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><?= htmlspecialchars($todayInfoLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                    <ul>
                        <?php if (count($todayDisplayItems) === 0): ?>
                            <li>Tidak ada agenda khusus hari ini.</li>
                        <?php else: ?>
                            <?php foreach ($todayDisplayItems as $todayItem): ?>
                                <li><?= htmlspecialchars($todayItem, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                            <?php if ($todayRemainingCount > 0): ?>
                                <li>+<?= $todayRemainingCount; ?> agenda lainnya.</li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </aside>
            </header>

            <main class="main-content">
                <?php if ($syncMessage !== ''): ?>
                    <div class="alert success"><?= htmlspecialchars($syncMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <section class="panel dashboard-hero">
                    <div class="hero-main">
                        <h2>Ringkasan Kinerja KMB TVRI Sulawesi Utara</h2>
                        <p>Tampilan beranda difokuskan untuk membaca performa harian lebih cepat, rapi, dan mudah dipahami.</p>
                    </div>
                    <div class="hero-chips">
                        <span class="hero-chip">Interaksi Mingguan: <?= number_format($totalInteractions, 0, ',', '.'); ?></span>
                        <span class="hero-chip">Followers Aktif: <?= number_format($followersCurrent, 0, ',', '.'); ?></span>
                        <span class="hero-chip">Konten Minggu Ini: <?= number_format($thisWeekPostCount, 0, ',', '.'); ?></span>
                    </div>
                </section>

                <section class="dashboard-section">
                    <div class="section-headline">
                        <h2>Indikator Utama</h2>
                        <p>4 metrik inti untuk membaca performa akun secara cepat.</p>
                    </div>

                    <div class="summary-grid">
                        <article class="info-card kpi-card">
                            <h3>Engagement Rate (ER)</h3>
                            <p class="kpi-value"><?= number_format($engagementRate, 2, ',', '.'); ?>%</p>
                            <small class="metric-delta <?= $engagementChange >= 0 ? 'up' : 'down'; ?>">vs periode lalu <?= $engagementChange >= 0 ? '+' : ''; ?><?= number_format($engagementChange, 1, ',', '.'); ?>%</small>
                        </article>
                        <article class="info-card kpi-card">
                            <h3>Total Reach / Jangkauan</h3>
                            <p class="kpi-value"><?= number_format($reachCurrent, 0, ',', '.'); ?></p>
                            <small class="metric-delta <?= $reachChange >= 0 ? 'up' : 'down'; ?>">vs periode lalu <?= $reachChange >= 0 ? '+' : ''; ?><?= number_format($reachChange, 1, ',', '.'); ?>%</small>
                        </article>
                        <article class="info-card kpi-card">
                            <h3>Pertumbuhan Followers</h3>
                            <p class="kpi-value">+<?= number_format($followerGrowthCount, 0, ',', '.'); ?></p>
                            <small class="metric-delta <?= $followerGrowthChange >= 0 ? 'up' : 'down'; ?>">vs periode lalu <?= $followerGrowthChange >= 0 ? '+' : ''; ?><?= number_format($followerGrowthChange, 1, ',', '.'); ?>%</small>
                        </article>
                        <article class="info-card kpi-card">
                            <h3>Total Posting Terlaksana</h3>
                            <p class="kpi-value"><?= number_format($thisWeekPostCount, 0, ',', '.'); ?></p>
                            <small class="metric-delta <?= $postingChange >= 0 ? 'up' : 'down'; ?>">vs periode lalu <?= $postingChange >= 0 ? '+' : ''; ?><?= number_format($postingChange, 1, ',', '.'); ?>%</small>
                        </article>
                    </div>
                </section>

                <section class="panel platform-engagement-panel">
                    <div class="platform-headline">
                        <h2>Monitor Engagement Multi-Platform</h2>
                        <p>YouTube, TikTok, dan Facebook dengan format metrik yang setara Instagram.</p>
                    </div>

                    <div class="platform-grid">
                        <?php foreach ($platformEngagementCards as $platformCard): ?>
                            <?php
                                $platformName = (string) ($platformCard['label'] ?? '-');
                                $platformHandle = (string) ($platformCard['handle'] ?? '');
                                $platformER = (float) ($platformCard['engagement_rate'] ?? 0);
                                $platformERChange = (float) ($platformCard['engagement_change'] ?? 0);
                                $platformReach = (int) ($platformCard['reach'] ?? 0);
                                $platformReachChange = (float) ($platformCard['reach_change'] ?? 0);
                                $platformInteractions = (int) ($platformCard['interactions'] ?? 0);
                            ?>
                            <article class="platform-card">
                                <h3><?= htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="platform-handle"><?= htmlspecialchars($platformHandle, ENT_QUOTES, 'UTF-8'); ?></p>

                                <div class="platform-stats">
                                    <div>
                                        <small>ER</small>
                                        <strong><?= number_format($platformER, 2, ',', '.'); ?>%</strong>
                                        <span class="metric-delta <?= $platformERChange >= 0 ? 'up' : 'down'; ?>">
                                            <?= $platformERChange >= 0 ? '+' : ''; ?><?= number_format($platformERChange, 1, ',', '.'); ?>%
                                        </span>
                                    </div>
                                    <div>
                                        <small>Reach</small>
                                        <strong><?= number_format($platformReach, 0, ',', '.'); ?></strong>
                                        <span class="metric-delta <?= $platformReachChange >= 0 ? 'up' : 'down'; ?>">
                                            <?= $platformReachChange >= 0 ? '+' : ''; ?><?= number_format($platformReachChange, 1, ',', '.'); ?>%
                                        </span>
                                    </div>
                                    <div>
                                        <small>Interaksi</small>
                                        <strong><?= number_format($platformInteractions, 0, ',', '.'); ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="panel">
                    <div class="ig-monitor-head">
                        <div>
                            <h2>Monitor Performa Instagram @tvri_sulut_official</h2>
                            <p>Pantau konten terbaik, metrik, dan status performa dalam satu tampilan visual.</p>
                        </div>
                        <a class="sync-btn" href="dashboard.php?sync_ig=1">🔄 Sinkronisasi Data Terbaru</a>
                    </div>

                    <div class="ig-card-grid">
                        <?php if (count($igCards) === 0): ?>
                            <article class="ig-card empty">
                                <p>Belum ada data Instagram. Klik tombol sinkronisasi untuk memuat data terbaru.</p>
                            </article>
                        <?php else: ?>
                            <?php foreach ($igCards as $ig): ?>
                                <?php
                                    $mediaType = strtoupper((string) ($ig['media_type'] ?? 'IMAGE'));
                                    $mediaTypeLabel = $mediaType === 'VIDEO' ? 'Video' : ($mediaType === 'CAROUSEL_ALBUM' ? 'Carousel' : 'Foto');
                                    $mediaIcon = $mediaType === 'VIDEO' ? '🎬' : ($mediaType === 'CAROUSEL_ALBUM' ? '🧩' : '🖼️');

                                    $likes = (int) ($ig['likes'] ?? 0);
                                    $comments = (int) ($ig['comments'] ?? 0);
                                    $captionText = trim((string) ($ig['caption'] ?? ''));
                                    $captionPreview = $captionText !== '' ? $captionText : 'Konten tanpa caption.';

                                    $postedAt = trim((string) ($ig['posted_at'] ?? ''));
                                    $postedAtLabel = '-';
                                    if ($postedAt !== '') {
                                        try {
                                            $postedAtLabel = (new DateTimeImmutable($postedAt))->format('d M Y, H:i') . ' WITA';
                                        } catch (Throwable $e) {
                                            $postedAtLabel = $postedAt;
                                        }
                                    }

                                    $mediaSource = trim((string) ($ig['thumbnail_url'] ?? ''));
                                    if ($mediaSource === '') {
                                        $mediaSource = trim((string) ($ig['media_url'] ?? ''));
                                    }

                                    $isTrending = $likes >= 1000;
                                ?>
                                <article class="ig-card">
                                    <div class="ig-thumb-wrap">
                                        <?php if ($mediaSource !== ''): ?>
                                            <img src="<?= htmlspecialchars($mediaSource, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail konten Instagram">
                                        <?php else: ?>
                                            <div class="ig-thumb-fallback">Tanpa Thumbnail</div>
                                        <?php endif; ?>
                                        <span class="ig-type-badge"><?= $mediaIcon; ?> <?= htmlspecialchars($mediaTypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>

                                    <div class="ig-card-body">
                                        <p class="ig-post-time">⏱️ Diposting: <?= htmlspecialchars($postedAtLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="ig-caption"><?= htmlspecialchars($captionPreview, ENT_QUOTES, 'UTF-8'); ?></p>

                                        <hr>

                                        <p class="ig-performance-title">Kinerja Konten:</p>
                                        <div class="ig-metrics-row">
                                            <span>❤️ <?= number_format($likes, 0, ',', '.'); ?> Likes</span>
                                            <span>💬 <?= number_format($comments, 0, ',', '.'); ?> Komentar</span>
                                        </div>

                                        <span class="ig-status-badge <?= $isTrending ? 'trending' : 'normal'; ?>">
                                            <?= $isTrending ? '🔥 Trending' : '🟢 Stabil'; ?>
                                        </span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="panel mini-calendar-panel">
                    <div class="mini-calendar-head">
                        <h2>KALENDER</h2>
                        <a href="kalender.php">Buka Kalender</a>
                    </div>
                    <div class="desk-calendar-shell" aria-label="Kalender meja bulan berjalan">
                        <div class="desk-calendar-spiral" aria-hidden="true">
                            <?php for ($ring = 0; $ring < 10; $ring++): ?>
                                <span></span>
                            <?php endfor; ?>
                        </div>

                        <div class="desk-calendar-body">
                            <aside class="desk-calendar-left">
                                <p class="desk-month-number"><?= htmlspecialchars($todayDateNumberDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="desk-month-name"><?= htmlspecialchars($monthNameEnglish, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="desk-month-meta"><?= htmlspecialchars($calendarTagline, ENT_QUOTES, 'UTF-8'); ?></p>
                            </aside>

                            <div class="desk-calendar-right">
                                <div class="desk-weekdays">
                                    <div>MON</div>
                                    <div>TUE</div>
                                    <div>WED</div>
                                    <div>THU</div>
                                    <div>FRI</div>
                                    <div>SAT</div>
                                    <div>SUN</div>
                                </div>

                                <div class="desk-days">
                                    <?php for ($empty = 0; $empty < $startDayOffset; $empty++): ?>
                                        <div class="desk-day empty"></div>
                                    <?php endfor; ?>

                                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                        <?php
                                            $dateStr = sprintf('%04d-%02d-%02d', (int) $today->format('Y'), (int) $today->format('n'), $day);
                                            $isToday = $dateStr === $todayStr;
                                            $dayDate = new DateTimeImmutable($dateStr);
                                            $isWeekend = (int) $dayDate->format('N') >= 6;
                                            $hasAgenda = isset($miniMap[$dateStr]) && count($miniMap[$dateStr]) > 0;
                                        ?>
                                        <div class="desk-day<?= $isToday ? ' is-today' : ''; ?><?= $isWeekend ? ' is-weekend' : ''; ?><?= $hasAgenda ? ' has-agenda' : ''; ?>">
                                            <?= $day; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="chart-grid">
                    <section class="panel">
                        <h2>Trend Interactions Harian</h2>
                        <canvas id="interactionTrendChart" height="130"></canvas>
                    </section>

                    <section class="panel">
                        <h2>Analisis Pilar Konten</h2>
                        <canvas id="contentCategoryChart" height="130"></canvas>
                    </section>

                    <section class="panel">
                        <h2>Perbandingan Interaksi 5 Konten Terakhir</h2>
                        <canvas id="igInteractionBarChart" height="130"></canvas>
                    </section>
                </div>

                <section class="panel">
                    <h2>Status Integrasi Data</h2>
                    <p class="integration-note">Data dashboard saat ini menggunakan kombinasi data lokal dan simulasi terstruktur agar siap disambungkan ke API Instagram kantor. Saat API resmi tersedia, cukup ganti sumber dataset pada variabel payload tanpa ubah desain.</p>
                </section>

                <section class="panel export-report-panel">
                    <div class="export-report-head">
                        <h2>Download Laporan</h2>
                        <p>Siap untuk rapat evaluasi mingguan dan bulanan.</p>
                    </div>
                    <div class="export-report-actions">
                        <a class="export-btn" href="laporan_export.php?source=dashboard&period=weekly&format=pdf" target="_blank">Cetak PDF Mingguan</a>
                        <a class="export-btn" href="laporan_export.php?source=dashboard&period=weekly&format=excel">Download Excel Mingguan</a>
                        <a class="export-btn" href="laporan_export.php?source=dashboard&period=monthly&format=pdf" target="_blank">Cetak PDF Bulanan</a>
                        <a class="export-btn" href="laporan_export.php?source=dashboard&period=monthly&format=excel">Download Excel Bulanan</a>
                    </div>
                </section>
            </main>
        </section>
    </div>

    <script>
        window.dashboardPayload = <?= json_encode($dashboardPayload, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <?php if ($playLoginSound && $loginSoundSource !== ''): ?>
        <audio id="loginSuccessAudio" preload="auto">
            <source src="<?= htmlspecialchars($loginSoundSource, ENT_QUOTES, 'UTF-8'); ?>" type="audio/mpeg">
        </audio>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const loginAudio = document.getElementById('loginSuccessAudio');
                if (!loginAudio) {
                    return;
                }

                loginAudio.volume = 0.7;
                const playPromise = loginAudio.play();

                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () {
                        const playOnFirstInteraction = function () {
                            loginAudio.play().catch(function () {});
                            document.removeEventListener('click', playOnFirstInteraction);
                            document.removeEventListener('keydown', playOnFirstInteraction);
                        };

                        document.addEventListener('click', playOnFirstInteraction);
                        document.addEventListener('keydown', playOnFirstInteraction);
                    });
                }
            });
        </script>
    <?php endif; ?>
    <script src="js/script.js"></script>
</body>
</html>
