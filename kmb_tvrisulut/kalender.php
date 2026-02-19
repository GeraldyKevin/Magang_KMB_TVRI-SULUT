<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/sidebar_shortcuts.php';

$namaAdmin = $_SESSION['nama_lengkap'] ?? 'Admin';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$message = '';
$error = '';
$editData = null;
$sidebarShortcutLinks = kmbSidebarGetShortcutLinks($conn);

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

    $holidayDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateKey);
    if (!$holidayDate || $holidayDate->format('Y-m-d') !== $dateKey) {
        return null;
    }

    return [
        'date' => $dateKey,
        'event' => $eventName,
        'is_national_holiday' => $isNationalHoliday,
    ];
}

$today = new DateTimeImmutable('today');
$viewYear = (int) ($_GET['year'] ?? $today->format('Y'));
$viewMonth = (int) ($_GET['month'] ?? $today->format('n'));

if ($viewYear < 2020 || $viewYear > 2035) {
    $viewYear = (int) $today->format('Y');
}

if ($viewMonth < 1 || $viewMonth > 12) {
    $viewMonth = (int) $today->format('n');
}

$selectedDate = ($viewYear === (int) $today->format('Y') && $viewMonth === (int) $today->format('n'))
    ? $today->format('Y-m-d')
    : sprintf('%04d-%02d-01', $viewYear, $viewMonth);

$allowedPerPage = [10, 50, 100];
$perPage = (int) ($_GET['per_page'] ?? 10);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 10;
}

$page = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}

$monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $viewYear, $viewMonth));
$prevMonthDate = $monthStart->modify('-1 month');
$nextMonthDate = $monthStart->modify('+1 month');
$daysInMonth = (int) $monthStart->format('t');
$startDayOffset = (int) $monthStart->format('N') - 1;
$todayStr = $today->format('Y-m-d');
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

$eventByDate = [];

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {
        $stmtDelete = $conn->prepare('DELETE FROM kalender_event WHERE id = ?');
        $stmtDelete->bind_param('i', $deleteId);
        $stmtDelete->execute();
        $stmtDelete->close();
        header('Location: kalender.php?msg=hapus_berhasil');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaEvent = trim($_POST['nama_event'] ?? '');
    $tanggal = trim($_POST['tanggal'] ?? '');
    $jenisEvent = trim($_POST['jenis_event'] ?? 'Perayaan Spesial');
    $eventId = (int) ($_POST['event_id'] ?? 0);

    if ($namaEvent === '' || $tanggal === '') {
        $error = 'Nama event dan tanggal wajib diisi.';
    } else {
        if ($eventId > 0) {
            $stmtUpdate = $conn->prepare('UPDATE kalender_event SET nama_event = ?, tanggal = ?, jenis_event = ? WHERE id = ?');
            $stmtUpdate->bind_param('sssi', $namaEvent, $tanggal, $jenisEvent, $eventId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            header('Location: kalender.php?msg=update_berhasil');
            exit;
        } else {
            $stmtInsert = $conn->prepare('INSERT INTO kalender_event (nama_event, tanggal, jenis_event, created_by) VALUES (?, ?, ?, ?)');
            $stmtInsert->bind_param('sssi', $namaEvent, $tanggal, $jenisEvent, $userId);
            $stmtInsert->execute();
            $stmtInsert->close();
            header('Location: kalender.php?msg=tambah_berhasil');
            exit;
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmtEdit = $conn->prepare('SELECT id, nama_event, tanggal, jenis_event FROM kalender_event WHERE id = ? LIMIT 1');
        $stmtEdit->bind_param('i', $editId);
        $stmtEdit->execute();
        $resultEdit = $stmtEdit->get_result();
        $editData = $resultEdit->fetch_assoc();
        $stmtEdit->close();
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'tambah_berhasil') {
        $message = 'Event berhasil ditambahkan.';
    } elseif ($_GET['msg'] === 'update_berhasil') {
        $message = 'Event berhasil diperbarui.';
    } elseif ($_GET['msg'] === 'hapus_berhasil') {
        $message = 'Event berhasil dihapus.';
    }
}

$localEventList = [];
$result = $conn->query('SELECT id, nama_event, tanggal, jenis_event, created_at FROM kalender_event ORDER BY tanggal ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $localEventList[] = $row;
    }
}

$stmtMonthEvent = $conn->prepare('SELECT id, nama_event, tanggal, jenis_event FROM kalender_event WHERE YEAR(tanggal) = ? AND MONTH(tanggal) = ? ORDER BY tanggal ASC, id ASC');
$stmtMonthEvent->bind_param('ii', $viewYear, $viewMonth);
$stmtMonthEvent->execute();
$resultMonthEvent = $stmtMonthEvent->get_result();
while ($row = $resultMonthEvent->fetch_assoc()) {
    $dateKey = $row['tanggal'];
    if (!isset($eventByDate[$dateKey])) {
        $eventByDate[$dateKey] = [];
    }

    $eventByDate[$dateKey][] = [
        'id' => (int) $row['id'],
        'title' => $row['nama_event'],
        'type' => 'lokal',
        'jenis' => $row['jenis_event'],
    ];
}
$stmtMonthEvent->close();

$hariLiburItems = fetchHariLiburApi();
$apiEventList = [];
foreach ($hariLiburItems as $item) {
    $normalizedItem = normalizeHariLiburItem($item);
    if ($normalizedItem === null) {
        continue;
    }

    $dateKey = $normalizedItem['date'];
    $eventName = $normalizedItem['event'];
    $isNationalHoliday = (bool) $normalizedItem['is_national_holiday'];

    $apiEventList[] = [
        'id' => 0,
        'nama_event' => $eventName,
        'tanggal' => $dateKey,
        'jenis_event' => $isNationalHoliday ? 'Hari Libur Nasional' : 'Hari Penting',
        'is_api' => true,
    ];

    $holidayDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateKey);
    if (!$holidayDate || (int) $holidayDate->format('Y') !== $viewYear || (int) $holidayDate->format('n') !== $viewMonth) {
        continue;
    }

    if (!isset($eventByDate[$dateKey])) {
        $eventByDate[$dateKey] = [];
    }

    $eventByDate[$dateKey][] = [
        'id' => 0,
        'title' => $eventName,
        'type' => $isNationalHoliday ? 'nasional' : 'penting',
        'jenis' => $isNationalHoliday ? 'Hari Libur Nasional' : 'Hari Penting',
    ];
}

$allEventList = [];
foreach ($localEventList as $localEvent) {
    $allEventList[] = [
        'id' => (int) ($localEvent['id'] ?? 0),
        'nama_event' => (string) ($localEvent['nama_event'] ?? ''),
        'tanggal' => (string) ($localEvent['tanggal'] ?? ''),
        'jenis_event' => (string) ($localEvent['jenis_event'] ?? ''),
        'is_api' => false,
    ];
}

foreach ($apiEventList as $apiEvent) {
    $allEventList[] = $apiEvent;
}

usort($allEventList, function (array $a, array $b): int {
    $dateCompare = strcmp((string) ($a['tanggal'] ?? ''), (string) ($b['tanggal'] ?? ''));
    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    $typeCompare = strcmp((string) ($a['jenis_event'] ?? ''), (string) ($b['jenis_event'] ?? ''));
    if ($typeCompare !== 0) {
        return $typeCompare;
    }

    return strcmp((string) ($a['nama_event'] ?? ''), (string) ($b['nama_event'] ?? ''));
});

$totalEvents = count($allEventList);
$totalPages = max(1, (int) ceil($totalEvents / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$pagedEventList = array_slice($allEventList, $offset, $perPage);
$rangeStart = $totalEvents === 0 ? 0 : ($offset + 1);
$rangeEnd = $totalEvents === 0 ? 0 : min($offset + $perPage, $totalEvents);

$baseQuery = [
    'year' => $viewYear,
    'month' => $viewMonth,
    'per_page' => $perPage,
];

$prevPageQuery = $baseQuery;
$prevPageQuery['page'] = max(1, $page - 1);

$nextPageQuery = $baseQuery;
$nextPageQuery['page'] = min($totalPages, $page + 1);

$todayQuery = [
    'year' => (int) $today->format('Y'),
    'month' => (int) $today->format('n'),
    'per_page' => $perPage,
    'page' => 1,
];

$prevMonthQuery = [
    'year' => (int) $prevMonthDate->format('Y'),
    'month' => (int) $prevMonthDate->format('n'),
    'per_page' => $perPage,
    'page' => 1,
];

$nextMonthQuery = [
    'year' => (int) $nextMonthDate->format('Y'),
    'month' => (int) $nextMonthDate->format('n'),
    'per_page' => $perPage,
    'page' => 1,
];

$todayDateNumberDisplay = $today->format('d');
$viewMonthEnglish = strtoupper($monthStart->format('F'));
$viewCalendarTagline = $viewYear . ' TVRI SULAWESI UTARA';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender Konten - KMB TVRI Sulut</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <h2>KMB TVRI</h2>
            <nav>
                <a href="dashboard.php" data-icon="🏠"><span class="nav-text">Beranda</span></a>
                <a class="active" href="kalender.php" data-icon="📅"><span class="nav-text">Kalender Konten</span></a>
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
                <a class="social-link x" href="https://x.com/tvrisulut" target="_blank" rel="noopener noreferrer" aria-label="X TVRI Sulawesi Utara">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.9 3h2.93l-6.4 7.32L23 21h-6l-4.7-6.15L6.9 21H4l6.84-7.82L1 3h6.15l4.24 5.6L18.9 3Zm-1.03 16.24h1.62L6.26 4.67H4.52l13.35 14.57Z"/></svg>
                </a>
                <a class="social-link youtube" href="https://www.youtube.com/@tvrisulut_streaming" target="_blank" rel="noopener noreferrer" aria-label="YouTube TVRI Sulawesi Utara">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 12c0 3.46-.27 5.63-.72 6.68-.44 1.01-1.24 1.8-2.25 2.25C18.98 21.39 16.81 21.66 12 21.66s-6.98-.27-8.03-.73a3.34 3.34 0 0 1-2.25-2.25C1.27 17.63 1 15.46 1 12s.27-5.63.72-6.68A3.34 3.34 0 0 1 3.97 3.07C5.02 2.61 7.19 2.34 12 2.34s6.98.27 8.03.73a3.34 3.34 0 0 1 2.25 2.25C22.73 6.37 23 8.54 23 12Zm-14.5 4.2 7-4.2-7-4.2v8.4Z"/></svg>
                </a>
            </div>
        </aside>

        <section class="content-area">
            <header class="top-header">
                <div>
                    <h1>Kalender Konten</h1>
                    <p>Halo, <?= htmlspecialchars($namaAdmin, ENT_QUOTES, 'UTF-8'); ?>!</p>
                </div>
            </header>

            <main class="main-content">
                <section class="panel">
                    <h2>Kalender Bulanan</h2>

                    <div class="calendar-toolbar-shell">
                        <aside class="calendar-toolbar-left">
                            <p class="calendar-toolbar-month-number"><?= htmlspecialchars($todayDateNumberDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="calendar-toolbar-month-name"><?= htmlspecialchars($viewMonthEnglish, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="calendar-toolbar-meta"><?= htmlspecialchars($viewCalendarTagline, ENT_QUOTES, 'UTF-8'); ?></p>
                        </aside>

                        <div class="calendar-toolbar-main">
                            <form id="calendar-filter-form" method="GET" action="kalender.php" class="calendar-toolbar">
                                <input type="hidden" name="per_page" value="<?= $perPage; ?>">
                                <input type="hidden" name="page" value="1">

                                <div>
                                    <label for="year">Bar Tahun</label>
                                    <select id="year" name="year">
                                        <?php for ($y = ((int) $today->format('Y')) - 5; $y <= ((int) $today->format('Y')) + 5; $y++): ?>
                                            <option value="<?= $y; ?>" <?= $viewYear === $y ? 'selected' : ''; ?>><?= $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="month">Bar Bulan</label>
                                    <select id="month" name="month">
                                        <?php foreach ($monthNames as $monthNumber => $monthLabel): ?>
                                            <option value="<?= $monthNumber; ?>" <?= $viewMonth === $monthNumber ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="calendar-toolbar-action">
                                    <label>&nbsp;</label>
                                    <button type="button" id="open-add-event" class="quick-add-button">Tambah Event</button>
                                </div>
                            </form>

                            <div class="calendar-headline">
                                <div class="calendar-nav">
                                    <a
                                        class="calendar-arrow"
                                        href="kalender.php?<?= htmlspecialchars(http_build_query($prevMonthQuery), ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-label="Bulan sebelumnya"
                                    >&lt;</a>
                                    <h3><?= htmlspecialchars($monthNames[$viewMonth] . ' ' . $viewYear, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <a
                                        class="calendar-today"
                                        href="kalender.php?<?= htmlspecialchars(http_build_query($todayQuery), ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-label="Kembali ke bulan hari ini"
                                    >Hari Ini</a>
                                    <a
                                        class="calendar-arrow"
                                        href="kalender.php?<?= htmlspecialchars(http_build_query($nextMonthQuery), ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-label="Bulan berikutnya"
                                    >&gt;</a>
                                </div>
                                <small>Klik tanggal untuk menampilkan tombol + pada kotak tanggal tersebut.</small>
                            </div>
                        </div>
                    </div>

                    <div class="calendar-grid calendar-weekday">
                        <div>Sen</div>
                        <div>Sel</div>
                        <div>Rab</div>
                        <div>Kam</div>
                        <div>Jum</div>
                        <div>Sab</div>
                        <div>Min</div>
                    </div>

                    <div class="calendar-grid calendar-days">
                        <?php for ($empty = 0; $empty < $startDayOffset; $empty++): ?>
                            <article class="calendar-cell empty"></article>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                            <?php
                                $dateStr = sprintf('%04d-%02d-%02d', $viewYear, $viewMonth, $day);
                                $items = $eventByDate[$dateStr] ?? [];
                                $isToday = $dateStr === $todayStr;
                                $isSelected = $dateStr === $selectedDate;
                                $dayDate = new DateTimeImmutable($dateStr);
                                $isSunday = (int) $dayDate->format('N') === 7;
                                $hasNationalHoliday = false;
                                $hasAnyEvent = count($items) > 0;

                                foreach ($items as $item) {
                                    if (($item['type'] ?? '') === 'nasional') {
                                        $hasNationalHoliday = true;
                                        break;
                                    }
                                }
                            ?>
                            <article class="calendar-cell<?= $isToday ? ' is-today' : ''; ?><?= $isSelected ? ' is-selected' : ''; ?><?= $hasAnyEvent ? ' has-events' : ''; ?><?= $isSunday ? ' is-sunday' : ''; ?><?= $hasNationalHoliday ? ' is-national-holiday' : ''; ?>">
                                <button
                                    type="button"
                                    class="calendar-day<?= $isSunday ? ' sunday-day' : ''; ?><?= $hasAnyEvent ? ' event-day' : ''; ?><?= $hasNationalHoliday ? ' holiday-day' : ''; ?>"
                                    data-date="<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="Pilih tanggal <?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <?= $day; ?>
                                </button>

                                <button
                                    type="button"
                                    class="calendar-add-button"
                                    data-date="<?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="Tambah event pada tanggal <?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8'); ?>"
                                >+</button>

                                <div class="calendar-events">
                                    <?php if (count($items) === 0): ?>
                                        <span class="calendar-empty">-</span>
                                    <?php else: ?>
                                        <?php foreach ($items as $item): ?>
                                            <div class="calendar-event <?= $item['type'] === 'nasional' ? 'event-national' : ($item['type'] === 'penting' ? 'event-important' : 'event-local'); ?>">
                                                <?php if ($item['type'] === 'nasional'): ?>
                                                    Libur Nasional: <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php elseif ($item['type'] === 'penting'): ?>
                                                    Hari Penting: <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php else: ?>
                                                    Event: <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endfor; ?>
                    </div>
                </section>

                <section class="panel">
                    <h2><?= $editData ? 'Edit Event' : 'Tambah Event Kalender'; ?></h2>

                    <?php if ($message !== ''): ?>
                        <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form id="event-form" method="POST" class="form-grid" action="kalender.php">
                        <input type="hidden" name="event_id" value="<?= (int) ($editData['id'] ?? 0); ?>">

                        <div>
                            <label for="nama_event">Nama Event</label>
                            <input
                                type="text"
                                id="nama_event"
                                name="nama_event"
                                required
                                value="<?= htmlspecialchars($editData['nama_event'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>

                        <div>
                            <label for="tanggal">Tanggal</label>
                            <input
                                type="date"
                                id="tanggal"
                                name="tanggal"
                                required
                                value="<?= htmlspecialchars($editData['tanggal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>

                        <div>
                            <label for="jenis_event">Jenis Event</label>
                            <input
                                type="text"
                                id="jenis_event"
                                name="jenis_event"
                                list="jenis_event_default"
                                value="<?= htmlspecialchars($editData['jenis_event'] ?? 'Perayaan Spesial', ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Pilih atau ketik jenis event"
                                required
                            >
                            <datalist id="jenis_event_default">
                                <option value="Libur Nasional"></option>
                                <option value="Perayaan Spesial"></option>
                            </datalist>
                        </div>

                        <div class="form-actions">
                            <button type="submit"><?= $editData ? 'Update Event' : 'Simpan Event'; ?></button>
                            <?php if ($editData): ?>
                                <a class="btn-secondary" href="kalender.php">Batal Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <h2>Daftar Event</h2>

                    <form method="GET" action="kalender.php" class="event-list-toolbar">
                        <input type="hidden" name="year" value="<?= $viewYear; ?>">
                        <input type="hidden" name="month" value="<?= $viewMonth; ?>">
                        <input type="hidden" name="page" value="1">

                        <div>
                            <label for="per_page">Jumlah List</label>
                            <select id="per_page" name="per_page">
                                <option value="10" <?= $perPage === 10 ? 'selected' : ''; ?>>1-10</option>
                                <option value="50" <?= $perPage === 50 ? 'selected' : ''; ?>>1-50</option>
                                <option value="100" <?= $perPage === 100 ? 'selected' : ''; ?>>1-100</option>
                            </select>
                        </div>
                    </form>

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Event</th>
                                    <th>Tanggal</th>
                                    <th>Jenis</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($pagedEventList) === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Belum ada event.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pagedEventList as $index => $event): ?>
                                        <tr>
                                            <td><?= $offset + $index + 1; ?></td>
                                            <td><?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($event['tanggal'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($event['jenis_event'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php if (!($event['is_api'] ?? false) && (int) ($event['id'] ?? 0) > 0): ?>
                                                    <a href="kalender.php?edit=<?= (int) $event['id']; ?>">Edit</a>
                                                    <a href="kalender.php?delete=<?= (int) $event['id']; ?>" onclick="return confirm('Hapus event ini?');">Hapus</a>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-bar">
                        <p>Menampilkan <?= $rangeStart; ?>-<?= $rangeEnd; ?> dari <?= $totalEvents; ?> data</p>
                        <div class="pagination-nav">
                            <a class="pagination-button<?= $page <= 1 ? ' disabled' : ''; ?>" href="kalender.php?<?= htmlspecialchars(http_build_query($prevPageQuery), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Halaman sebelumnya">&lt;</a>
                            <span>Halaman <?= $page; ?> / <?= $totalPages; ?></span>
                            <a class="pagination-button<?= $page >= $totalPages ? ' disabled' : ''; ?>" href="kalender.php?<?= htmlspecialchars(http_build_query($nextPageQuery), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Halaman berikutnya">&gt;</a>
                        </div>
                    </div>
                </section>
            </main>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarFilterForm = document.getElementById('calendar-filter-form');
            const yearSelect = document.getElementById('year');
            const monthSelect = document.getElementById('month');
            const openAddEventButton = document.getElementById('open-add-event');
            const dateInput = document.getElementById('tanggal');
            const form = document.getElementById('event-form');
            const perPageSelect = document.getElementById('per_page');
            const dayButtons = document.querySelectorAll('.calendar-day[data-date]');
            const addButtons = document.querySelectorAll('.calendar-add-button[data-date]');
            const initialSelectedDate = '<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>';

            function markSelectedCell(cellElement) {
                const selectedCells = document.querySelectorAll('.calendar-cell.is-selected');

                selectedCells.forEach(function (cell) {
                    cell.classList.remove('is-selected');
                });

                if (cellElement) {
                    cellElement.classList.add('is-selected');
                }
            }

            function showAddButtonForDate(selectedDate) {
                addButtons.forEach(function (button) {
                    button.classList.remove('is-visible');
                });

                if (!selectedDate) {
                    return;
                }

                const activeAddButton = document.querySelector(`.calendar-add-button[data-date="${selectedDate}"]`);
                if (activeAddButton) {
                    activeAddButton.classList.add('is-visible');
                }
            }

            function openEventFormByDate(selectedDate) {
                if (!selectedDate || !dateInput || !form) {
                    return;
                }

                dateInput.value = selectedDate;
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function submitCalendarFilter() {
                if (!calendarFilterForm) {
                    return;
                }

                calendarFilterForm.submit();
            }

            if (yearSelect) {
                yearSelect.addEventListener('change', submitCalendarFilter);
            }

            if (monthSelect) {
                monthSelect.addEventListener('change', submitCalendarFilter);
            }

            if (perPageSelect) {
                perPageSelect.addEventListener('change', function () {
                    const perPageForm = perPageSelect.closest('form');
                    if (perPageForm) {
                        perPageForm.submit();
                    }
                });
            }

            dayButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const selected = button.getAttribute('data-date');
                    if (!selected) {
                        return;
                    }

                    const selectedCell = button.closest('.calendar-cell');
                    markSelectedCell(selectedCell);
                    showAddButtonForDate(selected);

                    if (dateInput) {
                        dateInput.value = selected;
                    }
                });
            });

            addButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const selected = button.getAttribute('data-date');
                    openEventFormByDate(selected);
                });
            });

            if (openAddEventButton) {
                openAddEventButton.addEventListener('click', function () {
                    const selectedCell = document.querySelector('.calendar-add-button.is-visible[data-date]')
                        || document.querySelector('.calendar-cell.is-selected .calendar-add-button[data-date]');

                    if (selectedCell) {
                        openEventFormByDate(selectedCell.getAttribute('data-date'));
                        return;
                    }

                    openEventFormByDate('<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>');
                });
            }

            showAddButtonForDate(initialSelectedDate);
        });
    </script>
    <script src="js/script.js"></script>
</body>
</html>
