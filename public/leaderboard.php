<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$roomCode = sanitizeRoomCode((string) ($_GET['room'] ?? ''));
$isSpectatorView = $roomCode !== '';

if ($isSpectatorView) {
    $room = fetchRoomByCode($pdo, $roomCode);

    if (!$room) {
        redirectTo('index.php');
    }

    if ($room['status'] !== 'finished') {
        redirectTo('spectate.php?room=' . urlencode($roomCode));
    }

    $viewer = [
        'id' => 0,
        'username' => 'Spectator',
        'role' => 'spectator',
        'score' => 0,
    ];
} else {
    $context = requireAuthenticatedPage($pdo, 'index.php');
    $viewer = $context['user'];
    $room = $context['room'];

    if ($room['status'] === 'waiting') {
        redirectTo('lobby.php');
    }

    if ($room['status'] !== 'finished') {
        redirectTo('game.php');
    }
}

$state = buildRoomState($pdo, $viewer, $room);
$winnerNames = array_map(
    static fn (array $winner): string => $winner['username'] . ' (' . $winner['score'] . ' pts)',
    $state['summary']['winners'] ?? []
);
$homeUrl = $isSpectatorView ? 'index.php' : 'index.php?reset=1';
$newGameUrl = $isSpectatorView ? 'index.php#createRoomSection' : 'index.php?reset=1#createRoomSection';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard Room <?= escape($state['room']['code']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="leaderboard" data-base-path="..">
    <div class="app-shell">
        <header class="page-header">
            <div>
                <span class="eyebrow">Final Leaderboard</span>
                <h1>Room <span><?= escape($state['room']['code']) ?></span></h1>
                <p class="soft-text">Permainan sudah selesai. Semua pemain diarahkan ke halaman hasil akhir ini.</p>
            </div>

            <div class="header-stats">
                <div class="stat-card">
                    <span>Viewer</span>
                    <strong><?= escape($state['viewer']['username']) ?></strong>
                </div>

                <div class="stat-card">
                    <span>Status</span>
                    <strong>finished</strong>
                </div>

                <div class="stat-card">
                    <span>Total Soal</span>
                    <strong><?= (int) $state['room']['total_questions'] ?></strong>
                </div>
            </div>
        </header>

        <main class="player-shell">
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Pemenang</span>
                        <h2>Hasil Akhir</h2>
                    </div>
                    <span class="pill">Leaderboard Final</span>
                </div>

                <div class="status-card">
                    <?php if (!empty($state['summary']['ranking'])): ?>
                        <?php if (count($state['summary']['winners']) === 1): ?>
                            <p>
                                Pemenang: <strong><?= escape($state['summary']['winners'][0]['username']) ?></strong>
                                dengan skor <?= (int) $state['summary']['winners'][0]['score'] ?> poin.
                            </p>
                        <?php else: ?>
                            <p>Hasil seri di posisi pertama: <?= escape(implode(', ', $winnerNames)) ?>.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Belum ada data ranking.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Ranking</span>
                        <h2>Urutan Skor Player</h2>
                    </div>
                </div>

                <div class="ranking-list">
                    <?php if (!empty($state['summary']['ranking'])): ?>
                        <?php foreach ($state['summary']['ranking'] as $index => $item): ?>
                            <article class="ranking-card">
                                <span class="ranking-index">#<?= $index + 1 ?></span>
                                <strong><?= escape($item['username']) ?></strong>
                                <span class="score-chip"><?= (int) $item['score'] ?> pts</span>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">Belum ada data ranking.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Aksi</span>
                        <h2>Lanjut Setelah Game</h2>
                    </div>
                </div>

                <div class="control-stack">
                    <a href="<?= escape($homeUrl) ?>" class="button button-ghost full-width">Kembali ke Halaman Utama</a>
                    <a href="<?= escape($newGameUrl) ?>" class="button button-primary full-width">Mulai Game Baru</a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
