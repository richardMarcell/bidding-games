<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

requireMethod('POST');

$context = requireAuthenticatedApi($pdo);
$user = $context['user'];
$room = $context['room'];

requirePlayer($user);

$data = getRequestData();
$answer = sanitizeTextAnswer((string) ($data['answer'] ?? ''));

if ($answer === '') {
    fail('Jawaban tidak boleh kosong.');
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
        fail('Tidak ada ronde aktif.');
    }

    if (($freshRoom['round_phase'] ?? '') !== 'answering') {
        $pdo->rollBack();
        fail('Jawaban belum dibuka. Semua player aktif harus selesai bidding terlebih dahulu.');
    }

    $questionStatement = $pdo->prepare('SELECT * FROM questions WHERE id = ? LIMIT 1');
    $questionStatement->execute([(int) $freshRoom['current_question_id']]);
    $question = $questionStatement->fetch();

    if (!$question) {
        $pdo->rollBack();
        fail('Soal aktif tidak ditemukan.', 404);
    }

    $bidStatement = $pdo->prepare(
        'SELECT * FROM bids WHERE user_id = ? AND room_id = ? AND question_id = ? LIMIT 1 FOR UPDATE'
    );
    $bidStatement->execute([
        (int) $freshUser['id'],
        (int) $freshRoom['id'],
        (int) $question['id'],
    ]);
    $bid = $bidStatement->fetch();

    if (!$bid) {
        $pdo->rollBack();
        fail('Anda harus melakukan bid sebelum menjawab.');
    }

    if ($bid['answer_text'] !== null && trim((string) $bid['answer_text']) !== '') {
        $pdo->rollBack();
        fail('Jawaban untuk soal ini sudah dikirim.');
    }

    $updateBidStatement = $pdo->prepare(
        'UPDATE bids SET answer_text = ?, answered_at = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $updateBidStatement->execute([
        $answer,
        (int) $bid['id'],
    ]);

    $allPlayersAnswered = allPlayersHaveAnswered($pdo, (int) $freshRoom['id'], (int) $question['id']);

    if ($allPlayersAnswered) {
        evaluateCurrentRound($pdo, $freshRoom);

        $updateRoomStatement = $pdo->prepare(
            "UPDATE rooms SET round_phase = 'review' WHERE id = ?"
        );
        $updateRoomStatement->execute([(int) $freshRoom['id']]);
    }

    $pdo->commit();

    $updatedUser = fetchUserById($pdo, (int) $freshUser['id']);
    $resultStatement = $pdo->prepare(
        'SELECT is_correct, score_delta FROM bids WHERE id = ? LIMIT 1'
    );
    $resultStatement->execute([(int) $bid['id']]);
    $result = $resultStatement->fetch() ?: ['is_correct' => null, 'score_delta' => 0];

    ok([
        'message' => $allPlayersAnswered
            ? 'Jawaban berhasil disimpan. Semua player yang ikut ronde sudah menjawab dan ronde telah dinilai.'
            : 'Jawaban berhasil disimpan. Menunggu player lain menjawab.',
        'round_reviewed' => $allPlayersAnswered,
        'is_correct' => $result['is_correct'] === null ? null : (bool) $result['is_correct'],
        'score_delta' => (int) $result['score_delta'],
        'new_score' => (int) ($updatedUser['score'] ?? $freshUser['score']),
        'correct_answer' => $allPlayersAnswered ? (string) $question['correct_answer'] : null,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal menyimpan jawaban.', 500);
}
