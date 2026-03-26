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

    if ($freshRoom['status'] === 'waiting') {
        $pdo->rollBack();
        fail('Game belum dimulai.');
    }

    if ($freshRoom['status'] === 'finished') {
        $pdo->rollBack();
        fail('Game sudah selesai.');
    }

    $nextStatus = $freshRoom['status'] === 'paused' ? 'playing' : 'paused';

    $updateStatement = $pdo->prepare('UPDATE rooms SET status = ? WHERE id = ?');
    $updateStatement->execute([$nextStatus, (int) $freshRoom['id']]);

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
