<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/koneksi.php';

$source = trim((string) ($_GET['source'] ?? 'dashboard'));
$period = trim((string) ($_GET['period'] ?? 'monthly'));
$format = trim((string) ($_GET['format'] ?? 'excel'));

$allowedSource = ['dashboard', 'rencana'];
$allowedPeriod = ['weekly', 'monthly'];
$allowedFormat = ['excel', 'pdf'];

if (!in_array($source, $allowedSource, true)) {
    $source = 'dashboard';
}

if (!in_array($period, $allowedPeriod, true)) {
    $period = 'monthly';
}

if (!in_array($format, $allowedFormat, true)) {
    $format = 'excel';
}

$today = new DateTimeImmutable('today');

if ($period === 'weekly') {
    $rangeStart = $today->modify('monday this week');
    $rangeEnd = $rangeStart->modify('+6 days');
    $periodLabel = 'Mingguan';
} else {
    $rangeStart = new DateTimeImmutable($today->format('Y-m-01'));
    $rangeEnd = $rangeStart->modify('last day of this month');
    $periodLabel = 'Bulanan';
}

$startDate = $rangeStart->format('Y-m-d');
$endDate = $rangeEnd->format('Y-m-d');

$events = [];
$stmtEvents = $conn->prepare('SELECT id, nama_event, tanggal, jenis_event FROM kalender_event WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC');
$stmtEvents->bind_param('ss', $startDate, $endDate);
$stmtEvents->execute();
$resultEvents = $stmtEvents->get_result();
while ($row = $resultEvents->fetch_assoc()) {
    $events[] = $row;
}
$stmtEvents->close();

$rencana = [];
$stmtRencana = $conn->prepare('SELECT id, judul_konten, tanggal_rencana, status, pic, catatan FROM rencana_konten WHERE tanggal_rencana BETWEEN ? AND ? ORDER BY tanggal_rencana ASC, id ASC');
$stmtRencana->bind_param('ss', $startDate, $endDate);
$stmtRencana->execute();
$resultRencana = $stmtRencana->get_result();
while ($row = $resultRencana->fetch_assoc()) {
    $rencana[] = $row;
}
$stmtRencana->close();

$totalEvent = count($events);
$totalRencana = count($rencana);
$followersBase = 15720;
$totalInteractions = max(1, ($totalEvent * 145) + ($totalRencana * 80));
$engagementRate = ($followersBase > 0) ? (($totalInteractions / $followersBase) * 100) : 0;
$reach = 12000 + ($totalInteractions * 3);

$fileStamp = date('Ymd_His');
$fileName = 'laporan_' . $source . '_' . $period . '_' . $fileStamp;

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileName . '.xls');
} else {
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan <?= htmlspecialchars(strtoupper($source), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 20px; color: #1f2937; }
        h1, h2 { margin: 0 0 8px; }
        p { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 12px; text-align: left; }
        th { background: #e5edff; }
        .section { margin-top: 18px; }
        .meta { margin-top: 10px; padding: 10px; background: #f3f6ff; border: 1px solid #dbe5ff; }
        .print-note { font-size: 12px; color: #6b7280; margin-top: 12px; }
        @media print {
            .print-note { display: none; }
        }
    </style>
</head>
<body>
    <h1>Laporan <?= htmlspecialchars($source === 'dashboard' ? 'Dashboard Monitoring Insight' : 'Rencana Konten', ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>Periode: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?></p>
    <p>Rentang Tanggal: <?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?> s/d <?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?></p>

    <div class="meta">
        <p><strong>Total Event Kalender:</strong> <?= $totalEvent; ?></p>
        <p><strong>Total Rencana Konten:</strong> <?= $totalRencana; ?></p>
        <p><strong>Estimasi Engagement Rate:</strong> <?= number_format($engagementRate, 2, ',', '.'); ?>%</p>
        <p><strong>Estimasi Reach:</strong> <?= number_format($reach, 0, ',', '.'); ?></p>
    </div>

    <div class="section">
        <h2>Data Event Kalender</h2>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Event</th>
                    <th>Tanggal</th>
                    <th>Jenis Event</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($events) === 0): ?>
                    <tr><td colspan="4">Tidak ada data event pada rentang ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($events as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td><?= htmlspecialchars((string) ($item['nama_event'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['tanggal'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['jenis_event'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Data Rencana Konten</h2>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Judul Konten</th>
                    <th>Tanggal Rencana</th>
                    <th>Status</th>
                    <th>PIC</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rencana) === 0): ?>
                    <tr><td colspan="6">Tidak ada data rencana konten pada rentang ini.</td></tr>
                <?php else: ?>
                    <?php foreach ($rencana as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td><?= htmlspecialchars((string) ($item['judul_konten'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['tanggal_rencana'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['pic'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) ($item['catatan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($format === 'pdf'): ?>
        <p class="print-note">Mode Cetak PDF aktif. Gunakan Print dan pilih Save as PDF untuk menyimpan file laporan.</p>
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    <?php endif; ?>
</body>
</html>
