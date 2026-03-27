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

    if ($freshRoom['status'] === 'paused') {
        $pdo->rollBack();
        fail('Game sedang dihentikan sementara. Lanjutkan dulu sebelum pindah ronde.');
    }

    if (($freshRoom['round_phase'] ?? '') !== 'review') {
        $pdo->rollBack();
        fail('Belum bisa pindah ke soal berikutnya. Tunggu sampai semua jawaban selesai dinilai.');
    }

    $nextRound = (int) $freshRoom['current_round'] + 1;
    $questionCount = getQuestionCount($pdo);

    if ($nextRound > $questionCount) {
        $finishStatement = $pdo->prepare(
            "UPDATE rooms
             SET status = 'finished', current_question_id = NULL
             WHERE id = ?"
        );
        $finishStatement->execute([(int) $freshRoom['id']]);

        $pdo->commit();

        ok([
            'message' => 'Game selesai. Semua soal telah dimainkan.',
            'finished' => true,
        ]);
    }

    $question = getQuestionByRound($pdo, $nextRound);

    if (!$question) {
        $pdo->rollBack();
        fail('Soal berikutnya tidak ditemukan.', 404);
    }

    if (countBidEligiblePlayers($pdo, (int) $freshRoom['id']) < 1) {
        $finishStatement = $pdo->prepare(
            "UPDATE rooms
             SET status = 'finished', current_question_id = NULL
             WHERE id = ?"
        );
        $finishStatement->execute([(int) $freshRoom['id']]);

        $pdo->commit();

        ok([
            'message' => 'Game selesai. Tidak ada player yang punya cukup poin untuk lanjut bidding.',
            'finished' => true,
        ]);
    }

    $updateStatement = $pdo->prepare(
        "UPDATE rooms
         SET current_round = ?, current_question_id = ?, round_phase = 'bidding'
         WHERE id = ?"
    );
    $updateStatement->execute([
        $nextRound,
        (int) $question['id'],
        (int) $freshRoom['id'],
    ]);

    $pdo->commit();

    ok([
        'message' => 'Berhasil pindah ke ronde bidding berikutnya.',
        'finished' => false,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Gagal memuat soal berikutnya.', 500);
}
