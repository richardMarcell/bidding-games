<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$context = requireAuthenticatedPage($pdo, 'index.php');
$user = $context['user'];
$room = $context['room'];

if ($room['status'] === 'waiting') {
    redirectTo('lobby.php');
}

if ($room['status'] === 'finished') {
    redirectTo('leaderboard.php');
}

$state = buildRoomState($pdo, $user, $room);
$stateJson = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$isModerator = $user['role'] === 'moderator';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Room <?= escape($state['room']['code']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="game" data-base-path=".." data-role="<?= escape($user['role']) ?>">
    <div class="app-shell">
        <header class="page-header">
            <div>
                <span class="eyebrow"><?= $isModerator ? 'Host Control Board' : 'Player Arena' ?></span>
                <h1>Room <span id="roomCodeLabel"><?= escape($state['room']['code']) ?></span></h1>
                <p id="gameStatusText">Status room dan fase ronde diperbarui otomatis tanpa refresh penuh.</p>
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
                    <span>Skor</span>
                    <strong id="scoreLabel"><?= (int) $state['viewer']['score'] ?></strong>
                </div>
            </div>
        </header>

        <div id="globalMessage" class="notice hidden"></div>

        <?php if ($isModerator): ?>
            <main class="moderator-shell">
                <section class="panel moderator-stage-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow" id="phaseBadge">Fase Ronde</span>
                            <h2 id="questionNumberLabel">Ronde Aktif</h2>
                        </div>

                        <div class="panel-meta">
                            <span class="pill" id="progressLabel">Progress</span>
                            <span class="pill hidden" id="roundTimerLabel">Timer</span>
                        </div>
                    </div>

                    <div id="questionContent" class="question-copy question-copy-large"></div>
                    <div id="questionFeedback" class="status-card"></div>
                </section>

                <aside id="moderatorPanel" class="panel moderator-side-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Moderator</span>
                            <h2>Kontrol Ronde</h2>
                        </div>
                    </div>

                    <p id="moderatorSummary" class="soft-text"></p>
                    <div class="control-stack moderator-action-stack">
                        <button id="pauseGameBtn" type="button" class="button button-secondary full-width">Hentikan Sementara</button>
                        <button id="finishGameBtn" type="button" class="button button-danger full-width">Akhiri Game</button>
                    </div>
                    <div class="divider"></div>
                    <button id="nextQuestionBtn" type="button" class="button button-primary full-width">Lanjut ke Ronde Berikutnya</button>

                    <div class="mini-notes">
                        <p>Hanya host yang bisa melihat isi jawaban semua player.</p>
                        <p>Host bisa pause kapan saja tanpa menghilangkan progres ronde.</p>
                        <p>Fase review selesai dulu sebelum pindah ronde.</p>
                    </div>
                </aside>

                <section class="panel moderator-score-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Leaderboard</span>
                            <h2>Skor dan Bid Player</h2>
                        </div>
                    </div>

                    <div id="playerList" class="list-grid"></div>
                </section>

                <section class="panel host-answer-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Host View</span>
                            <h2>Jawaban Masuk</h2>
                        </div>
                    </div>

                    <div id="responseList" class="host-answer-grid"></div>
                </section>

                <section id="rankingPanel" class="panel hidden">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Hasil Akhir</span>
                            <h2>Leaderboard Final</h2>
                        </div>
                    </div>

                    <div id="winnerHighlight" class="status-card"></div>
                    <div id="rankingList" class="ranking-list"></div>
                </section>
            </main>
        <?php else: ?>
            <main class="player-shell">
                <div id="resultFlash" class="result-flash hidden" aria-live="polite"></div>
                <section class="player-stage-grid">
                    <section class="panel player-question-panel">
                        <div class="panel-heading">
                            <div>
                                <span class="eyebrow" id="phaseBadge">Fase Ronde</span>
                                <h2 id="questionNumberLabel">Ronde Aktif</h2>
                            </div>

                            <div class="panel-meta">
                                <span class="pill" id="progressLabel">Progress</span>
                                <span class="pill hidden" id="roundTimerLabel">Timer</span>
                            </div>
                        </div>

                        <div id="questionContent" class="question-copy question-copy-large"></div>
                        <div id="questionFeedback" class="status-card"></div>
                    </section>

                    <aside id="playerControlsPanel" class="panel player-control-panel">
                        <div class="panel-heading">
                            <div>
                                <span class="eyebrow">Player Action</span>
                                <h2>Bid dan Jawab</h2>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>1. Kunci Bid</h3>
                            <p id="bidLimitText" class="soft-text"></p>

                            <form id="bidForm" class="stack-form">
                                <label>
                                    <span>Jumlah Bid</span>
                                    <input id="bidAmount" type="number" name="bid_amount" min="1" step="1" required>
                                </label>

                                <button id="bidSubmitBtn" type="submit" class="button button-secondary full-width">Kunci Bid</button>
                            </form>

                            <div id="bidStatus" class="inline-note"></div>
                        </div>

                        <div class="divider"></div>

                        <div class="form-section">
                            <h3>2. Jawab Soal</h3>
                            <form id="answerForm" class="stack-form">
                                <label>
                                    <span>Jawaban Teks</span>
                                    <textarea id="answerText" name="answer" rows="5" maxlength="255" placeholder="Ketik jawaban singkat kamu di sini"></textarea>
                                </label>

                                <button id="answerSubmitBtn" type="submit" class="button button-primary full-width">Kirim Jawaban</button>
                            </form>

                            <div id="answerStatus" class="inline-note"></div>
                        </div>
                    </aside>
                </section>

                <section class="panel player-score-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Leaderboard</span>
                            <h2>Skor dan Bid Saat Ini</h2>
                        </div>
                    </div>

                    <div id="playerList" class="list-grid"></div>
                </section>

                <section class="panel audience-feed-panel">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Progress</span>
                            <h2>Bid dan Status Jawaban</h2>
                        </div>
                    </div>

                    <div id="responseList" class="response-list"></div>
                </section>

                <section id="rankingPanel" class="panel hidden">
                    <div class="panel-heading">
                        <div>
                            <span class="eyebrow">Hasil Akhir</span>
                            <h2>Leaderboard Final</h2>
                        </div>
                    </div>

                    <div id="winnerHighlight" class="status-card"></div>
                    <div id="rankingList" class="ranking-list"></div>
                </section>
            </main>
        <?php endif; ?>
    </div>

    <script>window.APP_INITIAL_STATE = <?= $stateJson ?: 'null' ?>;</script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
