<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');

$data = getRequestData();
$username = sanitizeUsername((string) ($data['username'] ?? ''));

if (strlen($username) < 3) {
    fail('Username minimal 3 karakter.');
}

try {
    $pdo->beginTransaction();

    $roomCode = randomRoomCode($pdo);

    $roomStatement = $pdo->prepare(
        "INSERT INTO rooms (code, status, current_round, current_question_id)
         VALUES (?, 'waiting', 0, NULL)"
    );
    $roomStatement->execute([$roomCode]);
    $roomId = (int) $pdo->lastInsertId();

    $userStatement = $pdo->prepare(
        "INSERT INTO users (username, role, room_id, score)
         VALUES (?, 'moderator', ?, 1000)"
    );
    $userStatement->execute([$username, $roomId]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['room_id'] = $roomId;
    $_SESSION['role'] = 'moderator';
    $_SESSION['username'] = $username;

    ok([
        'message' => 'Room berhasil dibuat.',
        'room_code' => $roomCode,
        'redirect' => 'lobby.php',
    ], 201);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal membuat room. Silakan coba lagi.', 500);
}
