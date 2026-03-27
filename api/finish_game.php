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
    $freshRoom = $roomStatement->fetch();

    if (!$freshRoom) {
        $pdo->rollBack();
        fail('Room tidak ditemukan.', 404);
    }

    $freshRoom = expireAnsweringRoundIfNeeded($pdo, $freshRoom);

    if ($freshRoom['status'] === 'waiting') {
        $pdo->rollBack();
        fail('Game belum dimulai.');
    }

    if ($freshRoom['status'] === 'finished') {
        $pdo->rollBack();
        fail('Game sudah selesai.');
    }

    $updateStatement = $pdo->prepare(
        "UPDATE rooms
         SET status = 'finished',
             current_question_id = NULL,
             answer_deadline_at = NULL,
             answer_time_remaining_seconds = NULL
         WHERE id = ?"
    );
    $updateStatement->execute([(int) $freshRoom['id']]);

    $pdo->commit();

    ok([
        'message' => 'Game diakhiri oleh host.',
        'finished' => true,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal mengakhiri game.', 500);
}
