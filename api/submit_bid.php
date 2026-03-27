<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');

$context = requireAuthenticatedApi($pdo);
$user = $context['user'];
$room = $context['room'];

requirePlayer($user);

$data = getRequestData();
$bidAmount = filter_var($data['bid_amount'] ?? null, FILTER_VALIDATE_INT);

if ($bidAmount === false) {
    fail('Bid harus berupa angka bulat.');
}

try {
    $pdo->beginTransaction();

    $userStatement = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
    $userStatement->execute([(int) $user['id']]);
    $freshUser = $userStatement->fetch();

    $roomStatement = $pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1 FOR UPDATE');
    $roomStatement->execute([(int) $room['id']]);
    $freshRoom = $roomStatement->fetch();

    if (!$freshUser || !$freshRoom) {
        $pdo->rollBack();
        fail('Data room atau user tidak ditemukan.', 404);
    }

    if ($freshRoom['status'] === 'paused') {
        $pdo->rollBack();
        fail('Game sedang dihentikan sementara oleh host.');
    }

    if ($freshRoom['status'] !== 'playing' || empty($freshRoom['current_question_id'])) {
        $pdo->rollBack();
        fail('Belum ada ronde aktif untuk bidding.');
    }

    if (($freshRoom['round_phase'] ?? '') !== 'bidding') {
        $pdo->rollBack();
        fail('Fase bidding untuk ronde ini sudah ditutup.');
    }

    $score = (int) $freshUser['score'];

    if (!canBidWithScore($score)) {
        $pdo->rollBack();
        fail('Saldo poin tidak cukup. Anda harus memiliki minimal 2 poin untuk bidding.');
    }

    if ($bidAmount < 1) {
        $pdo->rollBack();
        fail('Bid minimal adalah 1 poin.');
    }

    if ($bidAmount >= $score) {
        $pdo->rollBack();
        fail('Bid harus menyisakan minimal 1 poin. All-in tidak diperbolehkan.');
    }

    $existingStatement = $pdo->prepare(
        'SELECT id FROM bids WHERE user_id = ? AND room_id = ? AND question_id = ? LIMIT 1 FOR UPDATE'
    );
    $existingStatement->execute([
        (int) $freshUser['id'],
        (int) $freshRoom['id'],
        (int) $freshRoom['current_question_id'],
    ]);

    if ($existingStatement->fetch()) {
        $pdo->rollBack();
        fail('Bid untuk soal ini sudah dikirim.');
    }

    $insertStatement = $pdo->prepare(
        'INSERT INTO bids (user_id, room_id, question_id, bid_amount, answer_text, is_correct, score_delta, evaluated_at, answered_at) VALUES (?, ?, ?, ?, NULL, NULL, 0, NULL, NULL)'
    );
    $insertStatement->execute([
        (int) $freshUser['id'],
        (int) $freshRoom['id'],
        (int) $freshRoom['current_question_id'],
        $bidAmount,
    ]);

    $allPlayersReady = allPlayersHaveBid($pdo, (int) $freshRoom['id'], (int) $freshRoom['current_question_id']);

    if ($allPlayersReady) {
        $updateRoomStatement = $pdo->prepare(
            "UPDATE rooms SET round_phase = 'answering' WHERE id = ?"
        );
        $updateRoomStatement->execute([(int) $freshRoom['id']]);
    }

    $pdo->commit();

    ok([
        'message' => $allPlayersReady
            ? 'Bid berhasil disimpan. Semua player sudah bid, soal sekarang dibuka.'
            : 'Bid berhasil disimpan. Menunggu player lain menyelesaikan bidding.',
        'bid_amount' => $bidAmount,
        'all_players_bid' => $allPlayersReady,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal menyimpan bid.', 500);
}
