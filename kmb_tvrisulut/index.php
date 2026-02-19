<?php
session_start();
require_once __DIR__ . '/config/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$loginThemeMusicSource = '';

$loginThemeDir = __DIR__ . '/Music/Login theme';
if (is_dir($loginThemeDir)) {
    $musicFiles = array_values(array_filter(scandir($loginThemeDir) ?: [], function ($fileName) use ($loginThemeDir) {
        $filePath = $loginThemeDir . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($filePath)) {
            return false;
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return in_array($extension, ['mp3', 'wav', 'ogg'], true);
    }));

    if (count($musicFiles) > 0) {
        $selectedMusic = $musicFiles[array_rand($musicFiles)];
        $loginThemeMusicSource = 'Music/Login%20theme/' . rawurlencode($selectedMusic);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password, nama_lengkap FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['play_login_sound'] = 1;

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard KMB TVRI Sulut</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">
    <?php if ($loginThemeMusicSource !== ''): ?>
        <audio id="loginThemeAudio" preload="auto" autoplay loop>
            <source src="<?= htmlspecialchars($loginThemeMusicSource, ENT_QUOTES, 'UTF-8'); ?>" type="audio/mpeg">
        </audio>
        <button id="loginMuteToggle" class="login-audio-toggle" type="button" aria-label="Mute musik login" aria-pressed="false">🔊 Mute</button>
    <?php endif; ?>

    <div class="login-bg-slider" aria-hidden="true">
        <div class="login-bg-track">
            <article class="login-bg-item"><img src="Gallery/LOGIN THEME/login theme.jpg" alt=""></article>
            <article class="login-bg-item"><img src="Gallery/LOGIN THEME/login theme 2.jpg" alt=""></article>
            <article class="login-bg-item"><img src="Gallery/LOGIN THEME/login theme 3.jpg" alt=""></article>

            <article class="login-bg-item"><img src="Gallery/LOGIN THEME/login theme.jpg" alt=""></article>
            <article class="login-bg-item"><img src="Gallery/LOGIN THEME/login theme 2.jpg" alt=""></article>
            <article class="login-bg-item"><img src="Gallery/LOGIN THEME/login theme 3.jpg" alt=""></article>
        </div>
    </div>

    <main class="login-wrapper">
        <section class="login-card">
            <h1>DASHBOARD KMB TVRI SULAWESI UTARA</h1>
            <p>Silakan masuk untuk melanjutkan.</p>

            <?php if ($error !== ''): ?>
                <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" required placeholder="Masukkan username">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="Masukkan password">

                <button type="submit">Masuk</button>
            </form>
            <small>HALO SOBAT TVRI !!!</small>
            <p class="login-copyright">&copy; <span id="copyrightYear"></span> KMB TVRI Sulawesi Utara. All rights reserved.</p>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Dynamic copyright year
            var yearEl = document.getElementById('copyrightYear');
            if (yearEl) { yearEl.textContent = new Date().getFullYear(); }

            const loginThemeAudio = document.getElementById('loginThemeAudio');
            const loginMuteToggle = document.getElementById('loginMuteToggle');

            if (loginThemeAudio) {
                loginThemeAudio.volume = 0.7;
                const autoPlayPromise = loginThemeAudio.play();

                if (autoPlayPromise && typeof autoPlayPromise.catch === 'function') {
                    autoPlayPromise.catch(function () {
                        const playOnFirstInteraction = function () {
                            loginThemeAudio.play().catch(function () {});
                            document.removeEventListener('click', playOnFirstInteraction);
                            document.removeEventListener('keydown', playOnFirstInteraction);
                        };

                        document.addEventListener('click', playOnFirstInteraction);
                        document.addEventListener('keydown', playOnFirstInteraction);
                    });
                }
            }

            if (loginThemeAudio && loginMuteToggle) {
                const refreshMuteButton = function () {
                    const isMuted = loginThemeAudio.muted;
                    loginMuteToggle.textContent = isMuted ? '🔇 Unmute' : '🔊 Mute';
                    loginMuteToggle.setAttribute('aria-pressed', isMuted ? 'true' : 'false');
                    loginMuteToggle.setAttribute('aria-label', isMuted ? 'Unmute musik login' : 'Mute musik login');
                };

                loginMuteToggle.addEventListener('click', function () {
                    loginThemeAudio.muted = !loginThemeAudio.muted;
                    refreshMuteButton();
                });

                refreshMuteButton();
            }

            const slider = document.querySelector('.login-bg-slider');
            const track = document.querySelector('.login-bg-track');
            const loginWrapper = document.querySelector('.login-wrapper');

            if (!slider || !track || !loginWrapper) {
                return;
            }

            let singleSetWidth = Math.max(1, track.scrollWidth / 2);
            let currentX = 0;
            let lastTime = 0;

            const baseSpeed = 48;
            const fastSpeed = 108;
            const slowSpeed = 18;
            let activeSpeed = baseSpeed;

            function updateTrackPosition() {
                track.style.transform = `translateX(${currentX}px)`;
            }

            function recalcWidth() {
                singleSetWidth = Math.max(1, track.scrollWidth / 2);
            }

            function step(timestamp) {
                if (!lastTime) {
                    lastTime = timestamp;
                }

                const deltaSeconds = (timestamp - lastTime) / 1000;
                lastTime = timestamp;

                currentX -= activeSpeed * deltaSeconds;

                while (currentX <= -singleSetWidth) {
                    currentX += singleSetWidth;
                }

                updateTrackPosition();
                window.requestAnimationFrame(step);
            }

            slider.addEventListener('mouseenter', function () {
                if (!loginWrapper.matches(':hover')) {
                    activeSpeed = fastSpeed;
                }
            });

            slider.addEventListener('mouseleave', function () {
                activeSpeed = baseSpeed;
            });

            loginWrapper.addEventListener('mouseenter', function () {
                activeSpeed = slowSpeed;
            });

            loginWrapper.addEventListener('mouseleave', function () {
                activeSpeed = slider.matches(':hover') ? fastSpeed : baseSpeed;
            });

            window.addEventListener('resize', function () {
                recalcWidth();
            });

            recalcWidth();
            updateTrackPosition();
            window.requestAnimationFrame(step);
        });
    </script>
</body>
</html>
