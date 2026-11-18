<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json'); // JSON response

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès interdit']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Votre session a expiré. Rechargez la page puis réessayez.']);
        exit;
    }

    $student_id = (int) $_SESSION['user_id'];
    $quiz_id = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
    $submittedAnswers = $_POST['answers'] ?? [];

    if (!$quiz_id || !is_array($submittedAnswers)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Les réponses envoyées ne sont pas valides.']);
        exit;
    }

    $enrollmentQuery = $pdo->prepare('SELECT 1
        FROM quizzes q
        JOIN course_enrollments ce ON ce.course_id = q.course_id
        JOIN students s ON s.id = ce.student_id
        WHERE q.id = :quiz_id AND s.user_id = :student_id
        LIMIT 1');
    $enrollmentQuery->execute(['quiz_id' => $quiz_id, 'student_id' => $student_id]);
    if (!$enrollmentQuery->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Inscrivez-vous au cours associé avant de passer ce quiz.']);
        exit;
    }

    $questionQuery = $pdo->prepare('SELECT id FROM questions WHERE quiz_id = :quiz_id');
    $questionQuery->execute(['quiz_id' => $quiz_id]);
    $questionIds = array_map('intval', $questionQuery->fetchAll(PDO::FETCH_COLUMN));

    if (!$questionIds) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Ce quiz ne contient aucune question.']);
        exit;
    }

    $answerQuery = $pdo->prepare('SELECT COUNT(*)
        FROM answers
        WHERE question_id = :question_id AND id = :answer_id AND is_correct = 1');
    $correctCount = 0;
    foreach ($questionIds as $questionId) {
        $answerId = filter_var($submittedAnswers[$questionId] ?? null, FILTER_VALIDATE_INT);
        if ($answerId === false || $answerId === null) {
            continue;
        }

        $answerQuery->execute(['question_id' => $questionId, 'answer_id' => $answerId]);
        $correctCount += (int) $answerQuery->fetchColumn();
    }

    $totalQuestions = count($questionIds);
    $score = round(($correctCount / $totalQuestions) * 100, 2);
    $saveResult = $pdo->prepare('INSERT INTO quiz_results (student_id, quiz_id, score)
        VALUES (:student_id, :quiz_id, :score)
        ON DUPLICATE KEY UPDATE score = VALUES(score), date_passed = NOW()');
    $saveResult->execute([
        'student_id' => $student_id,
        'quiz_id' => $quiz_id,
        'score' => $score,
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Résultat enregistré.',
        'score' => $score,
        'correct' => $correctCount,
        'total' => $totalQuestions,
    ]);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit;
}
?>
