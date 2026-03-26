<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');

$data = getRequestData();
$username = sanitizeUsername((string) ($data['username'] ?? ''));
$roomCode = sanitizeRoomCode((string) ($data['room_code'] ?? ''));

if (strlen($username) < 3) {
    fail('Username minimal 3 karakter.');
}

if (strlen($roomCode) < 4) {
    fail('Kode room tidak valid.');
}

try {
    $pdo->beginTransaction();

    $roomStatement = $pdo->prepare('SELECT * FROM rooms WHERE code = ? LIMIT 1 FOR UPDATE');
    $roomStatement->execute([$roomCode]);
    $room = $roomStatement->fetch();

    if (!$room) {
        $pdo->rollBack();
        fail('Room tidak ditemukan.', 404);
    }

    if ($room['status'] !== 'waiting') {
        $pdo->rollBack();
        fail('Room sudah dimulai atau selesai. Player baru tidak bisa join.', 409);
    }

    $nameCheck = $pdo->prepare(
        'SELECT id FROM users WHERE room_id = ? AND LOWER(username) = LOWER(?) LIMIT 1'
    );
    $nameCheck->execute([(int) $room['id'], $username]);

    if ($nameCheck->fetch()) {
        $pdo->rollBack();
        fail('Username sudah dipakai di room ini.');
    }

    $userStatement = $pdo->prepare(
        "INSERT INTO users (username, role, room_id, score)
         VALUES (?, 'player', ?, 1000)"
    );
    $userStatement->execute([$username, (int) $room['id']]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['room_id'] = (int) $room['id'];
    $_SESSION['role'] = 'player';
    $_SESSION['username'] = $username;

    ok([
        'message' => 'Berhasil join ke room.',
        'room_code' => $room['code'],
        'redirect' => 'lobby.php',
    ], 201);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($exception instanceof PDOException && $exception->getCode() === '23000') {
        fail('Username sudah dipakai di room ini.', 409);
    }

    fail('Gagal join room. Silakan coba lagi.', 500);
}
