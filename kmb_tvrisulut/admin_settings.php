<?php
require_once __DIR__ . '/config/auth_check.php';
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/sidebar_shortcuts.php';

$namaAdmin = $_SESSION['nama_lengkap'] ?? 'Admin';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$usernameSession = (string) ($_SESSION['username'] ?? '');

$message = '';
$error = '';

$conn->query("CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

function getSetting(mysqli $conn, string $key, string $defaultValue = ''): string
{
    $stmt = $conn->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        return $defaultValue;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $defaultValue;
    }

    return (string) ($row['setting_value'] ?? $defaultValue);
}

function upsertSetting(mysqli $conn, string $key, string $value): void
{
    $stmt = $conn->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

$instagramAccountId = getSetting($conn, 'instagram_account_id', '');
$instagramAccessToken = getSetting($conn, 'instagram_access_token', '');
$sidebarShortcutLinks = kmbSidebarGetShortcutLinks($conn);
$customSidebarLinks = kmbSidebarGetCustomLinksWithLabels($conn);
$customShortcutRemaining = max(0, kmbSidebarGetCustomLimit() - count($customSidebarLinks));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = trim((string) ($_POST['form_type'] ?? ''));

    if ($formType === 'profile') {
        $namaBaru = trim((string) ($_POST['nama_lengkap'] ?? ''));
        $usernameBaru = trim((string) ($_POST['username'] ?? ''));
        $passwordBaru = trim((string) ($_POST['password_baru'] ?? ''));

        if ($namaBaru === '' || $usernameBaru === '') {
            $error = 'Nama lengkap dan username wajib diisi.';
        } else {
            $stmtCheck = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $stmtCheck->bind_param('si', $usernameBaru, $userId);
            $stmtCheck->execute();
            $checkResult = $stmtCheck->get_result();
            $isDuplicate = $checkResult && $checkResult->num_rows > 0;
            $stmtCheck->close();

            if ($isDuplicate) {
                $error = 'Username sudah digunakan pengguna lain.';
            } else {
                if ($passwordBaru !== '') {
                    $stmtUpdate = $conn->prepare('UPDATE users SET nama_lengkap = ?, username = ?, password = ? WHERE id = ?');
                    $stmtUpdate->bind_param('sssi', $namaBaru, $usernameBaru, $passwordBaru, $userId);
                } else {
                    $stmtUpdate = $conn->prepare('UPDATE users SET nama_lengkap = ?, username = ? WHERE id = ?');
                    $stmtUpdate->bind_param('ssi', $namaBaru, $usernameBaru, $userId);
                }

                $stmtUpdate->execute();
                $stmtUpdate->close();

                $_SESSION['nama_lengkap'] = $namaBaru;
                $_SESSION['username'] = $usernameBaru;
                $namaAdmin = $namaBaru;
                $usernameSession = $usernameBaru;
                $message = 'Profil admin berhasil diperbarui.';
            }
        }
    } elseif ($formType === 'instagram') {
        $instagramAccountId = trim((string) ($_POST['instagram_account_id'] ?? ''));
        $instagramAccessToken = trim((string) ($_POST['instagram_access_token'] ?? ''));

        upsertSetting($conn, 'instagram_account_id', $instagramAccountId);
        upsertSetting($conn, 'instagram_access_token', $instagramAccessToken);

        $message = 'Konfigurasi Instagram berhasil disimpan.';
    } elseif ($formType === 'shortcut_add') {
        $shortcutLabel = trim((string) ($_POST['shortcut_label'] ?? ''));
        $shortcutUrl = trim((string) ($_POST['shortcut_url'] ?? ''));
        $shortcutError = null;

        if (kmbSidebarAddCustomLink($conn, $shortcutLabel, $shortcutUrl, $shortcutError)) {
            $message = 'URL pintasan baru berhasil ditambahkan.';
            $sidebarShortcutLinks = kmbSidebarGetShortcutLinks($conn);
            $customSidebarLinks = kmbSidebarGetCustomLinksWithLabels($conn);
            $customShortcutRemaining = max(0, kmbSidebarGetCustomLimit() - count($customSidebarLinks));
        } else {
            $error = (string) ($shortcutError ?? 'Gagal menambahkan URL pintasan.');
        }
    } elseif ($formType === 'shortcut_delete') {
        $shortcutUrl = trim((string) ($_POST['shortcut_url'] ?? ''));
        $shortcutError = null;

        if (kmbSidebarDeleteCustomLink($conn, $shortcutUrl, $shortcutError)) {
            $message = 'Pintasan berhasil dihapus.';
            $sidebarShortcutLinks = kmbSidebarGetShortcutLinks($conn);
            $customSidebarLinks = kmbSidebarGetCustomLinksWithLabels($conn);
            $customShortcutRemaining = max(0, kmbSidebarGetCustomLimit() - count($customSidebarLinks));
        } else {
            $error = (string) ($shortcutError ?? 'Gagal menghapus pintasan.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Admin - KMB TVRI Sulut</title>
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
                <a href="rencana_konten.php" data-icon="📝"><span class="nav-text">Rencana Konten</span></a>
                <a class="active" href="admin_settings.php" data-icon="⚙️"><span class="nav-text">Pengaturan Admin</span></a>
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
                    <h1>Pengaturan Admin</h1>
                    <p>Kelola profil akun admin dan konfigurasi integrasi Instagram.</p>
                </div>
            </header>

            <main class="main-content">
                <?php if ($message !== ''): ?>
                    <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <section class="panel">
                    <h2>Profil Admin</h2>
                    <form method="POST" class="form-grid" action="admin_settings.php">
                        <input type="hidden" name="form_type" value="profile">

                        <div>
                            <label for="nama_lengkap">Nama Lengkap</label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" required value="<?= htmlspecialchars($namaAdmin, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div>
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required value="<?= htmlspecialchars($usernameSession, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div>
                            <label for="password_baru">Password Baru (opsional)</label>
                            <input type="password" id="password_baru" name="password_baru" placeholder="Kosongkan jika tidak diubah">
                        </div>

                        <div class="form-actions">
                            <button type="submit">Simpan Profil</button>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <h2>Konfigurasi Instagram API</h2>
                    <form method="POST" class="form-grid" action="admin_settings.php">
                        <input type="hidden" name="form_type" value="instagram">

                        <div>
                            <label for="instagram_account_id">Instagram Account ID</label>
                            <input type="text" id="instagram_account_id" name="instagram_account_id" value="<?= htmlspecialchars($instagramAccountId, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Contoh: 17841400000000000">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="instagram_access_token">Access Token</label>
                            <input type="text" id="instagram_access_token" name="instagram_access_token" value="<?= htmlspecialchars($instagramAccessToken, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Paste long-lived access token di sini">
                        </div>

                        <div class="form-actions">
                            <button type="submit">Simpan Konfigurasi API</button>
                        </div>
                    </form>
                </section>

                <section class="panel">
                    <h2>Pintasan Web Sidebar</h2>
                    <form method="POST" class="form-grid" action="admin_settings.php">
                        <input type="hidden" name="form_type" value="shortcut_add">

                        <div>
                            <label for="shortcut_label">Nama Custom</label>
                            <input type="text" id="shortcut_label" name="shortcut_label" placeholder="Contoh: Referensi Ide" maxlength="30" required>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="shortcut_url">Tambah URL Baru</label>
                            <input type="url" id="shortcut_url" name="shortcut_url" placeholder="https://contoh.com" required>
                            <small>Isi nama dan URL. Slot URL tambahan maksimal <?= kmbSidebarGetCustomLimit(); ?> (sisa <?= $customShortcutRemaining; ?>).</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit">Tambah Pintasan</button>
                        </div>
                    </form>

                    <div class="table-wrapper" style="margin-top: 14px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Label</th>
                                    <th>URL</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($customSidebarLinks) === 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Belum ada URL tambahan. URL default sudah aktif otomatis.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customSidebarLinks as $index => $shortcut): ?>
                                        <tr>
                                            <td><?= $index + 1; ?></td>
                                            <td><?= htmlspecialchars((string) ($shortcut['label'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <a href="<?= htmlspecialchars((string) ($shortcut['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?= htmlspecialchars((string) ($shortcut['url'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <form method="POST" action="admin_settings.php" onsubmit="return confirm('Hapus pintasan ini?');" style="display:inline-flex;">
                                                    <input type="hidden" name="form_type" value="shortcut_delete">
                                                    <input type="hidden" name="shortcut_url" value="<?= htmlspecialchars((string) ($shortcut['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </section>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
