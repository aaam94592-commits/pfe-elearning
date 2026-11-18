<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$quizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
if (!$quizId) {
    header('Location: dashboard_teacher_quiz.php');
    exit;
}

$quizQuery = $pdo->prepare('SELECT q.id, q.titre, c.titre AS course_name
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    JOIN teachers t ON t.id = c.teacher_id
    WHERE q.id = :quiz_id AND t.user_id = :user_id
    LIMIT 1');
$quizQuery->execute(['quiz_id' => $quizId, 'user_id' => (int) $_SESSION['user_id']]);
$quiz = $quizQuery->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    http_response_code(404);
    exit('Quiz introuvable ou accès non autorisé.');
}

$resultQuery = $pdo->prepare('SELECT u.name, qr.score, qr.date_passed
    FROM quiz_results qr
    JOIN users u ON u.id = qr.student_id
    WHERE qr.quiz_id = :quiz_id
    ORDER BY qr.date_passed DESC');
$resultQuery->execute(['quiz_id' => $quizId]);
$results = $resultQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats - <?= e($quiz['titre']); ?> | E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="site-header container">
        <a class="site-brand" href="../dashboard_teacher.php"><div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div><span class="site-title">E-Learning</span></a>
        <div class="nav-actions">
            <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre" aria-label="Basculer le mode sombre"><i class="fa fa-moon"></i></button>
            <a class="btn btn-outline" href="dashboard_teacher_quiz.php"><i class="fa fa-clipboard-question" aria-hidden="true"></i> Mes quiz</a>
        </div>
    </header>

    <main class="quiz-shell">
        <section class="quiz-hero">
            <span class="eyebrow"><i class="fa fa-book-open" aria-hidden="true"></i> <?= e($quiz['course_name']); ?></span>
            <h1><i class="fa fa-chart-column" aria-hidden="true"></i> Résultats du quiz</h1>
            <p><?= e($quiz['titre']); ?> · <?= count($results); ?> tentative(s)</p>
        </section>

        <section class="quiz-question">
            <?php if ($results): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fa fa-user-graduate" aria-hidden="true"></i> Étudiant</th>
                                <th><i class="fa fa-chart-simple" aria-hidden="true"></i> Score</th>
                                <th><i class="fa fa-calendar-days" aria-hidden="true"></i> Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td><?= e($result['name']); ?></td>
                                    <td><?= e($result['score']); ?> %</td>
                                    <td><?= e(date('d/m/Y H:i', strtotime($result['date_passed']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="quiz-empty">Aucun étudiant n’a encore passé ce quiz.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">© 2026 E-Learning</footer>
    <script src="../js/theme.js" defer></script>
</body>
</html>
