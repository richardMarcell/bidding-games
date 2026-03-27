<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

if (isset($_GET['reset'])) {
    clearUserSession();
}

$existingUserId = (int) ($_SESSION['user_id'] ?? 0);
$existingRoomId = (int) ($_SESSION['room_id'] ?? 0);

if ($existingUserId > 0 && $existingRoomId > 0) {
    $existingUser = fetchUserById($pdo, $existingUserId);
    $existingRoom = fetchRoomById($pdo, $existingRoomId);

    if ($existingUser && $existingRoom && (int) $existingUser['room_id'] === (int) $existingRoom['id']) {
        if ($existingRoom['status'] === 'waiting') {
            redirectTo('lobby.php');
        }

        if ($existingRoom['status'] === 'finished') {
            redirectTo('leaderboard.php');
        }

        redirectTo('game.php');
    }

    clearUserSession();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programming Quiz Bidding Game</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="index" data-base-path="..">
    <div class="app-shell">
        <header class="hero-grid">
            <section class="hero-copy">
                <span class="eyebrow">Realtime Quiz Room</span>
                <h1>Programming Quiz dengan sistem bidding poin.</h1>
                <p class="hero-text">
                    Moderator membuka room, player join dengan kode unik, lalu setiap soal dijawab
                    dengan taruhan poin yang menentukan risiko dan hadiah.
                </p>
                <div class="hero-tags">
                    <span class="pill">PHP + PDO</span>
                    <span class="pill">MySQL</span>
                    <span class="pill">AJAX Polling</span>
                    <span class="pill">Tanpa Framework Besar</span>
                </div>
            </section>

            <section class="panel hero-panel">
                <div id="globalMessage" class="notice hidden"></div>

                <div class="dual-form">
                    <article id="createRoomSection" class="form-card">
                        <div class="panel-heading">
                            <div>
                                <span class="eyebrow">Moderator</span>
                                <h2>Buat Room Baru</h2>
                            </div>
                        </div>

                        <form id="createRoomForm" class="stack-form">
                            <label>
                                <span>Username Moderator</span>
                                <input type="text" name="username" maxlength="30" placeholder="Contoh: Host Rina" required>
                            </label>

                            <button type="submit" class="button button-primary full-width">Buat Room</button>
                        </form>
                    </article>

                    <article class="form-card">
                        <div class="panel-heading">
                            <div>
                                <span class="eyebrow">Player</span>
                                <h2>Gabung ke Room</h2>
                            </div>
                        </div>

                        <form id="joinRoomForm" class="stack-form">
                            <label>
                                <span>Username Player</span>
                                <input type="text" name="username" maxlength="30" placeholder="Contoh: Dimas" required>
                            </label>

                            <label>
                                <span>Kode Room</span>
                                <input type="text" name="room_code" maxlength="12" placeholder="Masukkan kode room" required>
                            </label>

                            <button type="submit" class="button button-secondary full-width">Join Room</button>
                        </form>
                    </article>
                </div>
            </section>
        </header>

        <section class="info-grid">
            <article class="panel info-card">
                <span class="eyebrow">Alur Main</span>
                <h3>1. Bid dulu, baru jawab.</h3>
                <p>
                    Setiap player memulai dengan 1000 poin. Bid harus lebih dari 0 dan wajib menyisakan
                    minimal 1 poin. Jika saldo tinggal 1 atau 0, player akan melewati ronde bidding berikutnya.
                </p>
            </article>

            <article class="panel info-card">
                <span class="eyebrow">Scoring</span>
                <h3>Benar dapat poin, salah kehilangan poin.</h3>
                <p>
                    Jika jawaban benar maka skor bertambah sesuai bid. Jika salah, jumlah bid akan
                    dipotong dari saldo.
                </p>
            </article>

            <article class="panel info-card">
                <span class="eyebrow">Multiplayer</span>
                <h3>Polling sederhana, cocok untuk shared hosting.</h3>
                <p>
                    Lobby, status game, daftar player, dan soal aktif diperbarui otomatis tiap 2 detik.
                </p>
            </article>
        </section>

        <section class="panel notes-panel">
            <span class="eyebrow">Catatan Pakai</span>
            <p>
                Untuk simulasi banyak player di satu komputer, buka room moderator di browser utama
                lalu gunakan incognito atau browser lain untuk player.
            </p>
        </section>

        <section class="panel live-room-panel">
            <div class="panel-heading">
                <div>
                    <span class="eyebrow">Spectator</span>
                    <h2>Room Publik untuk Ditonton</h2>
                </div>
                <span class="pill">Tanpa kode join</span>
            </div>

            <p class="soft-text">
                Spectator tidak perlu join sebagai player. Pilih salah satu room aktif di bawah untuk
                melihat soal, leaderboard, progress jawaban, dan bid semua player secara live.
            </p>

            <div id="publicRoomList" class="room-grid"></div>
        </section>
    </div>

    <script>window.APP_INITIAL_STATE = null;</script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
