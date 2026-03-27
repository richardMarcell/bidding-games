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

    $nextStatus = $freshRoom['status'] === 'paused' ? 'playing' : 'paused';

    if ($nextStatus === 'paused'
        && ($freshRoom['round_phase'] ?? '') === 'answering'
        && !empty($freshRoom['answer_deadline_at'])) {
        $updateStatement = $pdo->prepare(
            "UPDATE rooms
             SET status = 'paused',
                 answer_time_remaining_seconds = GREATEST(
                     CEILING(TIMESTAMPDIFF(MICROSECOND, CURRENT_TIMESTAMP, answer_deadline_at) / 1000000),
                     1
                 ),
                 answer_deadline_at = NULL
             WHERE id = ?"
        );
        $updateStatement->execute([(int) $freshRoom['id']]);
    } elseif ($nextStatus === 'playing'
        && ($freshRoom['round_phase'] ?? '') === 'answering'
        && $freshRoom['answer_time_remaining_seconds'] !== null) {
        $secondsLeft = max(1, (int) $freshRoom['answer_time_remaining_seconds']);
        $updateStatement = $pdo->prepare(
            "UPDATE rooms
             SET status = 'playing',
                 answer_deadline_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL " . $secondsLeft . " SECOND),
                 answer_time_remaining_seconds = NULL
             WHERE id = ?"
        );
        $updateStatement->execute([(int) $freshRoom['id']]);
    } else {
        $updateStatement = $pdo->prepare('UPDATE rooms SET status = ? WHERE id = ?');
        $updateStatement->execute([$nextStatus, (int) $freshRoom['id']]);
    }

    $pdo->commit();

    ok([
        'message' => $nextStatus === 'paused'
            ? 'Game dihentikan sementara oleh host.'
            : 'Game dilanjutkan kembali.',
        'status' => $nextStatus,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal mengubah status pause game.', 500);
}
