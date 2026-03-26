<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$roomCode = sanitizeRoomCode((string) ($_GET['room'] ?? ''));
$room = $roomCode !== '' ? fetchRoomByCode($pdo, $roomCode) : null;

if (!$room) {
    redirectTo('index.php');
}

$viewer = [
    'id' => 0,
    'username' => 'Spectator',
    'role' => 'spectator',
    'score' => 0,
];

$state = buildRoomState($pdo, $viewer, $room);
$stateJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spectate Room <?= escape($state['room']['code']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="spectate" data-base-path=".." data-room-code="<?= escape($roomCode) ?>">
    <div class="app-shell">
        <header class="page-header">
            <div>
                <span class="eyebrow">Public Spectator View</span>
                <h1>Watching Room <span id="roomCodeLabel"><?= escape($state['room']['code']) ?></span></h1>
                <p id="gameStatusText">Spectator hanya melihat soal, skor, bid, dan progress ronde.</p>
            </div>

            <div class="header-stats">
                <div class="stat-card">
                    <span>Viewer</span>
                    <strong>Spectator</strong>
                </div>

                <div class="stat-card">
                    <span>Ronde</span>
                    <strong id="spectateRoundLabel"><?= (int) $state['room']['current_round'] ?></strong>
                </div>

                <div class="stat-card">
                    <span>Status</span>
                    <strong id="spectateStatusLabel"><?= escape($state['room']['status']) ?></strong>
                </div>
            </div>
        </header>

        <div id="globalMessage" class="notice hidden"></div>

        <main class="spectator-shell">
            <section class="panel spectator-stage-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow" id="phaseBadge">Fase Ronde</span>
                        <h2 id="questionNumberLabel">Room Spectate</h2>
                    </div>

                    <span class="pill" id="progressLabel">Progress</span>
                </div>

                <div id="questionContent" class="question-copy question-copy-large"></div>
                <div id="questionFeedback" class="status-card"></div>
            </section>

            <section class="panel spectator-score-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Leaderboard</span>
                        <h2>Skor dan Bid Player</h2>
                    </div>
                </div>

                <div id="playerList" class="list-grid"></div>
            </section>

            <section class="panel spectator-feed-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Progress Publik</span>
                        <h2>Bid dan Status Jawaban</h2>
                    </div>
                </div>

                <div id="responseList" class="response-list"></div>
            </section>

            <section id="rankingPanel" class="panel hidden">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Final</span>
                        <h2>Leaderboard Akhir</h2>
                    </div>
                </div>

                <div id="winnerHighlight" class="status-card"></div>
                <div id="rankingList" class="ranking-list"></div>
            </section>

            <section class="panel spectator-nav-panel">
                <div class="panel-heading">
                    <div>
                        <span class="eyebrow">Navigasi</span>
                        <h2>Kembali</h2>
                    </div>
                </div>

                <a href="index.php" class="button button-ghost full-width">Kembali ke Daftar Room</a>
            </section>
        </main>
    </div>

    <script>window.APP_INITIAL_STATE = <?= $stateJson ?: 'null' ?>;</script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
