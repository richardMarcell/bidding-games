<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');

$context = requireAuthenticatedApi($pdo);
$user = $context['user'];
$room = $context['room'];

requireModerator($user);

try {
    $pdo->beginTransaction();

    $roomStatement = $pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1 FOR UPDATE');
    $roomStatement->execute([(int) $room['id']]);
    $room = $roomStatement->fetch();

    if (!$room) {
        $pdo->rollBack();
        fail('Room tidak ditemukan.', 404);
    }

    if ($room['status'] !== 'waiting') {
        $pdo->rollBack();
        fail('Game sudah dimulai atau selesai.');
    }

    $playerCountStatement = $pdo->prepare(
        "SELECT COUNT(*) FROM users WHERE room_id = ? AND role = 'player'"
    );
    $playerCountStatement->execute([(int) $room['id']]);
    $playerCount = (int) $playerCountStatement->fetchColumn();

    if ($playerCount < 1) {
        $pdo->rollBack();
        fail('Minimal harus ada 1 player untuk memulai game.');
    }

    if (countBidEligiblePlayers($pdo, (int) $room['id']) < 1) {
        $pdo->rollBack();
        fail('Belum ada player yang punya minimal 2 poin untuk ikut bidding.');
    }

    $question = getQuestionByRound($pdo, 1);

    if (!$question) {
        $pdo->rollBack();
        fail('Belum ada soal di database.');
    }

    $updateStatement = $pdo->prepare(
        "UPDATE rooms
         SET status = 'playing',
             round_phase = 'bidding',
             current_round = 1,
             current_question_id = ?,
             answer_deadline_at = NULL,
             answer_time_remaining_seconds = NULL
         WHERE id = ?"
    );
    $updateStatement->execute([(int) $question['id'], (int) $room['id']]);

    $pdo->commit();

    ok([
        'message' => 'Game dimulai.',
        'redirect' => 'game.php',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal memulai game.', 500);
}
