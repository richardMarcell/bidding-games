<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('GET');

$context = requireAuthenticatedApi($pdo);
$user = $context['user'];
$room = $context['room'];

ok([
    'state' => buildRoomState($pdo, $user, $room),
]);
