<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$context = requireAuthenticatedPage($pdo, 'index.php');
$user = $context['user'];
$room = $context['room'];

if ($room['status'] !== 'waiting') {
    redirectTo('game.php');
}

$state = buildRoomState($pdo, $user, $room);
$stateJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby Room <?= escape($state['room']['code']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="lobby" data-base-path=".." data-role="<?= escape($user['role']) ?>">
    <div class="app-shell">
        <header class="page-header">
            <div>
                <span class="eyebrow">Lobby Room</span>
                <h1>Room <span id="roomCodeLabel"><?= escape($state['room']['code']) ?></span></h1>
                <p id="roomStatusText">Menunggu moderator memulai game.</p>
            </div>

            <div class="header-stats">
                <div class="stat-card">
                    <span>User</span>
                    <strong id="viewerName"><?= escape($state['viewer']['username']) ?></strong>
                </div>

                <div class="stat-card">
                    <span>Role</span>
                    <strong><?= escape($state['viewer']['role']) ?></strong>
                </div>

                <div class="stat-card">
                    <span>Total Soal</span>
                    <strong id="totalQuestionsLabel"><?= (int) $state['room']['total_questions'] ?></strong>
                </div>
            </div>
        </header>

        <div id="globalMessage" class="notice hidden"></div>

        <main class="lobby-layout">
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Peserta</span>
                        <h2>Daftar Player</h2>
                    </div>
                    <span class="pill" id="playerCountLabel"><?= (int) $state['summary']['players_total'] ?> player</span>
                </div>

                <div id="playerList" class="list-grid"></div>
            </section>

            <aside class="panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Kontrol Room</span>
                        <h2><?= $user['role'] === 'moderator' ? 'Panel Moderator' : 'Status Menunggu' ?></h2>
                    </div>
                </div>

                <p id="lobbyHint" class="soft-text">
                    <?= $user['role'] === 'moderator'
                        ? 'Mulai game ketika minimal satu player sudah masuk ke room.'
                        : 'Tunggu moderator memulai game. Halaman ini akan berpindah otomatis saat game dimulai.' ?>
                </p>

                <div class="control-stack">
                    <button
                        id="startGameBtn"
                        class="button button-primary full-width<?= $user['role'] !== 'moderator' ? ' hidden' : '' ?>"
                        type="button"
                    >
                        Start Game
                    </button>

                    <a href="index.php?reset=1" class="button button-ghost full-width">Kembali ke Halaman Awal</a>
                </div>

                <div class="divider"></div>

                <div class="mini-notes">
                    <p>Game sekarang dimulai dengan fase bidding bersama untuk semua player yang masih punya cukup poin.</p>
                    <p>Soal akan muncul otomatis hanya setelah semua player aktif selesai mengunci bid.</p>
                    <p>Player yang baru join akan muncul otomatis setiap polling.</p>
                    <p>Kode room ini bisa dibagikan ke peserta lain: <strong><?= escape($state['room']['code']) ?></strong></p>
                </div>
            </aside>
        </main>
    </div>

    <script>window.APP_INITIAL_STATE = <?= $stateJson ?: 'null' ?>;</script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
