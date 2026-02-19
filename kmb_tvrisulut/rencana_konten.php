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

$statusOptions = ['Ide', 'Proses', 'Siap Tayang', 'Selesai'];

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {
        $stmtDelete = $conn->prepare('DELETE FROM rencana_konten WHERE id = ?');
        $stmtDelete->bind_param('i', $deleteId);
        $stmtDelete->execute();
        $stmtDelete->close();
        header('Location: rencana_konten.php?msg=hapus_berhasil');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judulKonten = trim($_POST['judul_konten'] ?? '');
    $tanggalRencana = trim($_POST['tanggal_rencana'] ?? '');
    $status = trim($_POST['status'] ?? 'Ide');
    $pic = trim($_POST['pic'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    $rencanaId = (int) ($_POST['rencana_id'] ?? 0);

    if ($judulKonten === '' || $tanggalRencana === '') {
        $error = 'Judul konten dan tanggal rencana wajib diisi.';
    } elseif (!in_array($status, $statusOptions, true)) {
        $error = 'Status tidak valid.';
    } else {
        if ($rencanaId > 0) {
            $stmtUpdate = $conn->prepare('UPDATE rencana_konten SET judul_konten = ?, tanggal_rencana = ?, status = ?, catatan = ?, pic = ? WHERE id = ?');
            $stmtUpdate->bind_param('sssssi', $judulKonten, $tanggalRencana, $status, $catatan, $pic, $rencanaId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            header('Location: rencana_konten.php?msg=update_berhasil');
            exit;
        } else {
            $stmtInsert = $conn->prepare('INSERT INTO rencana_konten (judul_konten, tanggal_rencana, status, catatan, pic, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmtInsert->bind_param('sssssi', $judulKonten, $tanggalRencana, $status, $catatan, $pic, $userId);
            $stmtInsert->execute();
            $stmtInsert->close();
            header('Location: rencana_konten.php?msg=tambah_berhasil');
            exit;
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmtEdit = $conn->prepare('SELECT id, judul_konten, tanggal_rencana, status, catatan, pic FROM rencana_konten WHERE id = ? LIMIT 1');
        $stmtEdit->bind_param('i', $editId);
        $stmtEdit->execute();
        $resultEdit = $stmtEdit->get_result();
        $editData = $resultEdit->fetch_assoc();
        $stmtEdit->close();
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'tambah_berhasil') {
        $message = 'Rencana konten berhasil ditambahkan.';
    } elseif ($_GET['msg'] === 'update_berhasil') {
        $message = 'Rencana konten berhasil diperbarui.';
    } elseif ($_GET['msg'] === 'hapus_berhasil') {
        $message = 'Rencana konten berhasil dihapus.';
    }
}

$rencanaList = [];
$result = $conn->query('SELECT id, judul_konten, tanggal_rencana, status, catatan, pic, created_at FROM rencana_konten ORDER BY tanggal_rencana ASC, id DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rencanaList[] = $row;
    }
}

function statusClass(string $status): string
{
    if ($status === 'Ide') {
        return 'status-ide';
    }

    if ($status === 'Proses') {
        return 'status-proses';
    }

    if ($status === 'Siap Tayang') {
        return 'status-siap-tayang';
    }

    return 'status-selesai';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rencana Konten - KMB TVRI Sulut</title>
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
                <a href="kalender.php" data-icon="📅"><span class="nav-text">Kalender Konten</span></a>
                <a class="active" href="rencana_konten.php" data-icon="📝"><span class="nav-text">Rencana Konten</span></a>
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
                    <h1>Rencana Konten KMB</h1>
                    <p>Halo, <?= htmlspecialchars($namaAdmin, ENT_QUOTES, 'UTF-8'); ?>!</p>
                </div>
            </header>

            <main class="main-content">
                <section class="panel">
                    <h2><?= $editData ? 'Edit Rencana Konten' : 'Tambah Rencana Konten'; ?></h2>

                    <?php if ($message !== ''): ?>
                        <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="form-grid" action="rencana_konten.php">
                        <input type="hidden" name="rencana_id" value="<?= (int) ($editData['id'] ?? 0); ?>">

                        <div>
                            <label for="judul_konten">Judul Konten</label>
                            <input
                                type="text"
                                id="judul_konten"
                                name="judul_konten"
                                required
                                value="<?= htmlspecialchars($editData['judul_konten'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>

                        <div>
                            <label for="tanggal_rencana">Tanggal Rencana</label>
                            <input
                                type="date"
                                id="tanggal_rencana"
                                name="tanggal_rencana"
                                required
                                value="<?= htmlspecialchars($editData['tanggal_rencana'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>

                        <div>
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php foreach ($statusOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?= (($editData['status'] ?? 'Ide') === $option) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="pic">PIC</label>
                            <input
                                type="text"
                                id="pic"
                                name="pic"
                                value="<?= htmlspecialchars($editData['pic'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="catatan">Catatan</label>
                            <input
                                type="text"
                                id="catatan"
                                name="catatan"
                                value="<?= htmlspecialchars($editData['catatan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>

                        <div class="form-actions">
                            <button type="submit"><?= $editData ? 'Update Rencana' : 'Simpan Rencana'; ?></button>
                            <?php if ($editData): ?>
                                <a class="btn-secondary" href="rencana_konten.php">Batal Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <h2>Daftar Rencana Konten</h2>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul Konten</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>PIC</th>
                                    <th>Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rencanaList) === 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Belum ada rencana konten.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rencanaList as $index => $item): ?>
                                        <tr>
                                            <td><?= $index + 1; ?></td>
                                            <td><?= htmlspecialchars($item['judul_konten'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($item['tanggal_rencana'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="status-badge <?= statusClass($item['status']); ?>">
                                                    <?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($item['pic'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($item['catatan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <a href="rencana_konten.php?edit=<?= (int) $item['id']; ?>">Edit</a>
                                                <a href="rencana_konten.php?delete=<?= (int) $item['id']; ?>" onclick="return confirm('Hapus rencana konten ini?');">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel export-report-panel">
                    <div class="export-report-head">
                        <h2>Download Laporan</h2>
                        <p>Ekspor data untuk bahan rapat evaluasi.</p>
                    </div>
                    <div class="export-report-actions">
                        <a class="export-btn" href="laporan_export.php?source=rencana&period=weekly&format=pdf" target="_blank">Cetak PDF Mingguan</a>
                        <a class="export-btn" href="laporan_export.php?source=rencana&period=weekly&format=excel">Download Excel Mingguan</a>
                        <a class="export-btn" href="laporan_export.php?source=rencana&period=monthly&format=pdf" target="_blank">Cetak PDF Bulanan</a>
                        <a class="export-btn" href="laporan_export.php?source=rencana&period=monthly&format=excel">Download Excel Bulanan</a>
                    </div>
                </section>
            </main>
        </section>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
