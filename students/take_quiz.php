<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../login.php');
    exit;
}

$quizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
if (!$quizId) {
    header('Location: ../dashboard_student.php');
    exit;
}

header('Location: ../quiz/take_quiz.php?quiz_id=' . $quizId);
exit;
