<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');

$context = requireAuthenticatedApi($pdo);
$user = $context['user'];
$room = $context['room'];

requireModerator($user);

$data = getRequestData();
$targetUserId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);

if ($targetUserId === false || $targetUserId < 1) {
    fail('Player yang dipilih tidak valid.');
}

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

    if (($freshRoom['status'] ?? '') === 'finished') {
        $pdo->rollBack();
        fail('Game sudah selesai. Player tidak bisa di-kick lagi.');
    }

    $targetStatement = $pdo->prepare(
        "SELECT id, username
         FROM users
         WHERE id = ? AND room_id = ? AND role = 'player'
         LIMIT 1
         FOR UPDATE"
    );
    $targetStatement->execute([
        $targetUserId,
        (int) $freshRoom['id'],
    ]);
    $targetUser = $targetStatement->fetch();

    if (!$targetUser) {
        $pdo->rollBack();
        fail('Player tidak ditemukan atau sudah keluar dari room.', 404);
    }

    $deleteStatement = $pdo->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
    $deleteStatement->execute([(int) $targetUser['id']]);

    $freshRoom = synchronizeRoomAfterPlayerRemoval($pdo, $freshRoom);

    $pdo->commit();

    ok([
        'message' => 'Player ' . $targetUser['username'] . ' berhasil dikeluarkan dari room.',
        'room_status' => $freshRoom['status'] ?? null,
        'round_phase' => $freshRoom['round_phase'] ?? null,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal mengeluarkan player dari room.', 500);
}
