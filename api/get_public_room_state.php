<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('GET');

$roomCode = sanitizeRoomCode((string) ($_GET['room_code'] ?? ''));

if ($roomCode === '') {
    fail('Kode room spectator tidak valid.', 422);
}

$room = fetchRoomByCode($pdo, $roomCode);

if (!$room) {
    fail('Room tidak ditemukan.', 404);
}

$viewer = [
    'id' => 0,
    'username' => 'Spectator',
    'role' => 'spectator',
    'score' => 0,
];

ok([
    'state' => buildRoomState($pdo, $viewer, $room),
]);
