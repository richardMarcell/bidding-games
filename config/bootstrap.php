<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = require __DIR__ . '/database.php';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok(array $data = [], int $statusCode = 200): void
{
    jsonResponse(array_merge(['success' => true], $data), $statusCode);
}

function fail(string $message, int $statusCode = 422, array $extra = []): void
{
    jsonResponse(array_merge(['success' => false, 'message' => $message], $extra), $statusCode);
}

function requireMethod(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        fail('Method not allowed.', 405);
    }
}

function getRequestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents('php://input');

        if ($rawInput === false || trim($rawInput) === '') {
            return [];
        }

        $decoded = json_decode($rawInput, true);

        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function lowerText(string $value): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function sanitizeUsername(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? '';

    return substr($value, 0, 30);
}

function sanitizeRoomCode(string $value): string
{
    return strtoupper(substr(trim($value), 0, 12));
}

function sanitizeTextAnswer(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? '';

    return substr($value, 0, 255);
}

function normalizeComparableAnswer(string $value): string
{
    $value = sanitizeTextAnswer($value);
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return trim(lowerText($value));
}

function randomRoomCode(PDO $pdo, int $length = 6): string
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $charactersLength = strlen($characters);

    do {
        $code = '';

        for ($index = 0; $index < $length; $index++) {
            $code .= $characters[random_int(0, $charactersLength - 1)];
        }

        $statement = $pdo->prepare('SELECT id FROM rooms WHERE code = ? LIMIT 1');
        $statement->execute([$code]);
    } while ($statement->fetch());

    return $code;
}

function getQuestionCount(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
}

function getQuestionByRound(PDO $pdo, int $round): ?array
{
    if ($round < 1) {
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM questions ORDER BY id ASC LIMIT 1 OFFSET ?');
    $statement->bindValue(1, $round - 1, PDO::PARAM_INT);
    $statement->execute();
    $question = $statement->fetch();

    return $question ?: null;
}

function fetchRoomById(PDO $pdo, int $roomId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM rooms WHERE id = ? LIMIT 1');
    $statement->execute([$roomId]);
    $room = $statement->fetch();

    return $room ?: null;
}

function fetchRoomByCode(PDO $pdo, string $code): ?array
{
    $statement = $pdo->prepare('SELECT * FROM rooms WHERE code = ? LIMIT 1');
    $statement->execute([$code]);
    $room = $statement->fetch();

    return $room ?: null;
}

function fetchUserById(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function clearUserSession(): void
{
    unset($_SESSION['user_id'], $_SESSION['room_id'], $_SESSION['role'], $_SESSION['username']);
}

function redirectTo(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function requireAuthenticatedPage(PDO $pdo, string $redirectPath = 'index.php'): array
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $roomId = (int) ($_SESSION['room_id'] ?? 0);

    if ($userId < 1 || $roomId < 1) {
        redirectTo($redirectPath);
    }

    $user = fetchUserById($pdo, $userId);
    $room = fetchRoomById($pdo, $roomId);

    if (!$user || !$room || (int) $user['room_id'] !== (int) $room['id']) {
        clearUserSession();
        redirectTo($redirectPath);
    }

    return ['user' => $user, 'room' => $room];
}

function requireAuthenticatedApi(PDO $pdo): array
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $roomId = (int) ($_SESSION['room_id'] ?? 0);

    if ($userId < 1 || $roomId < 1) {
        fail('Session tidak ditemukan. Silakan masuk lagi.', 401);
    }

    $user = fetchUserById($pdo, $userId);
    $room = fetchRoomById($pdo, $roomId);

    if (!$user || !$room || (int) $user['room_id'] !== (int) $room['id']) {
        clearUserSession();
        fail('Session tidak valid. Silakan masuk lagi.', 401);
    }

    return ['user' => $user, 'room' => $room];
}

function requireModerator(array $user): void
{
    if (($user['role'] ?? '') !== 'moderator') {
        fail('Hanya moderator yang dapat melakukan aksi ini.', 403);
    }
}

function requirePlayer(array $user): void
{
    if (($user['role'] ?? '') !== 'player') {
        fail('Hanya player yang dapat melakukan aksi ini.', 403);
    }
}

function getCurrentQuestion(PDO $pdo, array $room): ?array
{
    if (empty($room['current_question_id'])) {
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM questions WHERE id = ? LIMIT 1');
    $statement->execute([(int) $room['current_question_id']]);
    $question = $statement->fetch();

    return $question ?: null;
}

function getAcceptedAnswers(array $question): array
{
    $answers = [];
    $rawValues = array_filter([
        (string) ($question['correct_answer'] ?? ''),
        (string) ($question['answer_aliases'] ?? ''),
    ], static fn ($value): bool => trim($value) !== '');

    foreach ($rawValues as $rawValue) {
        $parts = explode('|', $rawValue);

        foreach ($parts as $part) {
            $normalized = normalizeComparableAnswer($part);

            if ($normalized !== '' && !in_array($normalized, $answers, true)) {
                $answers[] = $normalized;
            }
        }
    }

    return $answers;
}

function answerMatchesQuestion(array $question, string $submittedAnswer): bool
{
    $normalizedAnswer = normalizeComparableAnswer($submittedAnswer);

    if ($normalizedAnswer === '') {
        return false;
    }

    return in_array($normalizedAnswer, getAcceptedAnswers($question), true);
}

function getRankingData(PDO $pdo, int $roomId): array
{
    $statement = $pdo->prepare(
        "SELECT username, score
         FROM users
         WHERE room_id = ? AND role = 'player'
         ORDER BY score DESC, username ASC"
    );
    $statement->execute([$roomId]);
    $ranking = $statement->fetchAll();

    if (!$ranking) {
        return ['winners' => [], 'ranking' => []];
    }

    $topScore = (int) $ranking[0]['score'];
    $winners = [];
    $formattedRanking = [];

    foreach ($ranking as $row) {
        $formattedRow = [
            'username' => $row['username'],
            'score' => (int) $row['score'],
        ];

        $formattedRanking[] = $formattedRow;

        if ((int) $row['score'] === $topScore) {
            $winners[] = $formattedRow;
        }
    }

    return [
        'winners' => $winners,
        'ranking' => $formattedRanking,
    ];
}

function allPlayersHaveBid(PDO $pdo, int $roomId, int $questionId): bool
{
    $statement = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE room_id = ? AND role = 'player') AS player_total,
            (SELECT COUNT(*)
             FROM bids b
             INNER JOIN users u ON u.id = b.user_id
             WHERE b.room_id = ? AND b.question_id = ? AND u.role = 'player') AS bid_total"
    );
    $statement->execute([$roomId, $roomId, $questionId]);
    $result = $statement->fetch();

    if (!$result) {
        return false;
    }

    return (int) $result['player_total'] > 0 && (int) $result['bid_total'] >= (int) $result['player_total'];
}

function allPlayersHaveAnswered(PDO $pdo, int $roomId, int $questionId): bool
{
    $statement = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE room_id = ? AND role = 'player') AS player_total,
            (SELECT COUNT(*)
             FROM bids b
             INNER JOIN users u ON u.id = b.user_id
             WHERE b.room_id = ? AND b.question_id = ? AND u.role = 'player'
               AND b.answer_text IS NOT NULL AND b.answer_text <> '') AS answer_total"
    );
    $statement->execute([$roomId, $roomId, $questionId]);
    $result = $statement->fetch();

    if (!$result) {
        return false;
    }

    return (int) $result['player_total'] > 0 && (int) $result['answer_total'] >= (int) $result['player_total'];
}

function evaluateCurrentRound(PDO $pdo, array $room): void
{
    $question = getCurrentQuestion($pdo, $room);

    if (!$question) {
        throw new RuntimeException('Soal aktif tidak ditemukan.');
    }

    $bidsStatement = $pdo->prepare(
        "SELECT b.id, b.user_id, b.bid_amount, b.answer_text
         FROM bids b
         INNER JOIN users u ON u.id = b.user_id
         WHERE b.room_id = ? AND b.question_id = ? AND u.role = 'player'
         ORDER BY b.id ASC
         FOR UPDATE"
    );
    $bidsStatement->execute([(int) $room['id'], (int) $question['id']]);
    $bids = $bidsStatement->fetchAll();

    $updateBidStatement = $pdo->prepare(
        'UPDATE bids SET is_correct = ?, score_delta = ?, evaluated_at = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $updateUserStatement = $pdo->prepare(
        'UPDATE users SET score = score + ? WHERE id = ?'
    );

    foreach ($bids as $bid) {
        $isCorrect = answerMatchesQuestion($question, (string) $bid['answer_text']);
        $scoreDelta = $isCorrect ? (int) $bid['bid_amount'] : -1 * (int) $bid['bid_amount'];

        $updateBidStatement->execute([
            $isCorrect ? 1 : 0,
            $scoreDelta,
            (int) $bid['id'],
        ]);

        $updateUserStatement->execute([
            $scoreDelta,
            (int) $bid['user_id'],
        ]);
    }
}

function buildRoomState(PDO $pdo, array $viewer, array $room): array
{
    $viewerRole = $viewer['role'] ?? 'spectator';
    $viewerId = (int) ($viewer['id'] ?? 0);
    $isSpectator = $viewerRole === 'spectator';

    $playersStatement = $pdo->prepare(
        "SELECT id, username, role, score
         FROM users
         WHERE room_id = ?
         ORDER BY CASE WHEN role = 'moderator' THEN 0 ELSE 1 END, score DESC, username ASC"
    );
    $playersStatement->execute([(int) $room['id']]);
    $players = $playersStatement->fetchAll();

    $questionCount = getQuestionCount($pdo);
    $currentQuestion = getCurrentQuestion($pdo, $room);
    $rankingData = getRankingData($pdo, (int) $room['id']);

    $playerTotal = 0;
    $playersBid = 0;
    $playersAnswered = 0;
    $playerMap = [];
    $viewerBid = null;
    $responses = [];

    foreach ($players as $player) {
        if ($player['role'] === 'player') {
            $playerTotal++;
        }
    }

    if ($currentQuestion) {
        $responseStatement = $pdo->prepare(
            "SELECT b.user_id, u.username, u.role, b.bid_amount, b.answer_text, b.is_correct, b.score_delta, b.evaluated_at
             FROM bids b
             INNER JOIN users u ON u.id = b.user_id
             WHERE b.room_id = ? AND b.question_id = ?
             ORDER BY u.username ASC"
        );
        $responseStatement->execute([(int) $room['id'], (int) $currentQuestion['id']]);
        $rows = $responseStatement->fetchAll();

        foreach ($rows as $row) {
            if ($row['role'] !== 'player') {
                continue;
            }

            $userId = (int) $row['user_id'];
            $hasAnswer = $row['answer_text'] !== null && trim((string) $row['answer_text']) !== '';
            $canSeeAnswerText = $viewerRole === 'moderator'
                || ($viewerRole === 'player' && $viewerId === $userId);
            $canSeeJudgement = $viewerRole === 'moderator'
                || ($viewerRole === 'player' && $viewerId === $userId);

            $playerMap[$userId] = [
                'bid_amount' => (int) $row['bid_amount'],
                'answer_text' => $row['answer_text'],
                'is_correct' => $row['is_correct'] === null ? null : (bool) $row['is_correct'],
                'score_delta' => (int) $row['score_delta'],
                'has_answer' => $hasAnswer,
            ];

            $playersBid++;

            if ($hasAnswer) {
                $playersAnswered++;
            }

            if ($userId === $viewerId) {
                $viewerBid = $playerMap[$userId];
            }

            $responses[] = [
                'user_id' => $userId,
                'username' => $row['username'],
                'bid_amount' => (int) $row['bid_amount'],
                'has_answer' => $hasAnswer,
                'answer_text' => $canSeeAnswerText ? $row['answer_text'] : null,
                'is_correct' => $canSeeJudgement ? ($row['is_correct'] === null ? null : (bool) $row['is_correct']) : null,
                'score_delta' => $canSeeJudgement ? (int) $row['score_delta'] : null,
                'is_evaluated' => $row['evaluated_at'] !== null,
            ];
        }
    }

    $formattedPlayers = [];

    foreach ($players as $player) {
        $playerId = (int) $player['id'];
        $bidData = $playerMap[$playerId] ?? null;

        $formattedPlayers[] = [
            'id' => $playerId,
            'username' => $player['username'],
            'role' => $player['role'],
            'score' => (int) $player['score'],
            'has_bid' => $bidData !== null,
            'has_answer' => $bidData['has_answer'] ?? false,
            'current_bid' => $bidData['bid_amount'] ?? null,
        ];
    }

    $viewerHasBid = $viewerBid !== null;
    $viewerHasAnswer = $viewerHasBid && ($viewerBid['has_answer'] ?? false);
    $questionVisible = $currentQuestion
        && in_array($room['status'], ['playing', 'paused'], true)
        && $room['round_phase'] !== 'bidding';
    $correctVisible = $currentQuestion && ($room['round_phase'] === 'review' || $room['status'] === 'finished');

    $questionPayload = null;

    if ($currentQuestion) {
        $questionPayload = [
            'id' => (int) $currentQuestion['id'],
            'number' => (int) $room['current_round'],
            'is_visible' => (bool) $questionVisible,
            'category' => $questionVisible ? $currentQuestion['category'] : null,
            'question' => $questionVisible ? $currentQuestion['question'] : null,
            'correct_answer' => $correctVisible ? $currentQuestion['correct_answer'] : null,
        ];
    }

    $everyoneBidded = $playerTotal > 0 && $playersBid >= $playerTotal;
    $everyoneAnswered = $playerTotal > 0 && $playersAnswered >= $playerTotal;
    $canStart = $viewerRole === 'moderator' && $room['status'] === 'waiting' && $playerTotal >= 1 && $questionCount > 0;
    $canNext = $viewerRole === 'moderator' && $room['status'] === 'playing' && $room['round_phase'] === 'review';
    $canPause = $viewerRole === 'moderator' && $room['status'] === 'playing';
    $canResume = $viewerRole === 'moderator' && $room['status'] === 'paused';
    $canFinish = $viewerRole === 'moderator' && in_array($room['status'], ['playing', 'paused'], true);

    return [
        'room' => [
            'id' => (int) $room['id'],
            'code' => $room['code'],
            'status' => $room['status'],
            'round_phase' => $room['round_phase'],
            'current_round' => (int) $room['current_round'],
            'total_questions' => $questionCount,
            'can_start' => $canStart,
            'can_next' => $canNext,
            'can_pause' => $canPause,
            'can_resume' => $canResume,
            'can_finish' => $canFinish,
        ],
        'viewer' => [
            'id' => $viewerId,
            'username' => $viewer['username'] ?? 'Spectator',
            'role' => $viewerRole,
            'score' => $isSpectator ? null : (int) ($viewer['score'] ?? 0),
            'has_bid' => $viewerHasBid,
            'has_answer' => $viewerHasAnswer,
            'current_bid' => $viewerBid['bid_amount'] ?? null,
            'current_answer' => $viewerBid['answer_text'] ?? null,
            'current_is_correct' => $viewerBid['is_correct'] ?? null,
            'current_score_delta' => $viewerBid['score_delta'] ?? null,
            'can_bid' => $viewerRole === 'player'
                && $room['status'] === 'playing'
                && $room['round_phase'] === 'bidding'
                && !$viewerHasBid
                && (int) ($viewer['score'] ?? 0) > 1,
            'can_answer' => $viewerRole === 'player'
                && $room['status'] === 'playing'
                && $room['round_phase'] === 'answering'
                && $viewerHasBid
                && !$viewerHasAnswer,
        ],
        'players' => $formattedPlayers,
        'current_question' => $questionPayload,
        'responses' => $responses,
        'summary' => [
            'players_total' => $playerTotal,
            'players_bid' => $playersBid,
            'players_answered' => $playersAnswered,
            'everyone_bidded' => $everyoneBidded,
            'everyone_answered' => $everyoneAnswered,
            'waiting_for_bid' => max(0, $playerTotal - $playersBid),
            'waiting_for_answer' => max(0, $playerTotal - $playersAnswered),
            'winners' => $rankingData['winners'],
            'ranking' => $rankingData['ranking'],
        ],
    ];
}

function getPublicRooms(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT r.id, r.code, r.status, r.round_phase, r.current_round, r.updated_at,
                COUNT(CASE WHEN u.role = 'player' THEN 1 END) AS player_total
         FROM rooms r
         LEFT JOIN users u ON u.room_id = r.id
         GROUP BY r.id, r.code, r.status, r.round_phase, r.current_round, r.updated_at
         HAVING player_total > 0 OR r.status <> 'waiting'
         ORDER BY FIELD(r.status, 'playing', 'paused', 'waiting', 'finished'), r.updated_at DESC, r.id DESC
         LIMIT 24"
    );
    $rooms = $statement->fetchAll();
    $questionCount = getQuestionCount($pdo);

    $formatted = [];

    foreach ($rooms as $room) {
        $formatted[] = [
            'id' => (int) $room['id'],
            'code' => $room['code'],
            'status' => $room['status'],
            'round_phase' => $room['round_phase'],
            'current_round' => (int) $room['current_round'],
            'total_questions' => $questionCount,
            'player_total' => (int) $room['player_total'],
        ];
    }

    return $formatted;
}
