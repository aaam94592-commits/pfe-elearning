<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT q.id AS quiz_id, q.titre AS quiz_title, c.titre AS course_title, qr.score
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    JOIN course_enrollments ce ON ce.course_id = c.id
    JOIN students s ON s.id = ce.student_id AND s.user_id = :enrolled_user_id
    LEFT JOIN quiz_results qr ON qr.quiz_id = q.id AND qr.student_id = :result_user_id
    ORDER BY q.id DESC');
$stmt->execute([
    'enrolled_user_id' => (int) $_SESSION['user_id'],
    'result_user_id' => (int) $_SESSION['user_id'],
]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes quiz - E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="site-header container">
        <a class="site-brand" href="../dashboard_student.php"><div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div><span class="site-title">E-Learning</span></a>
        <div class="nav-actions">
            <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre" aria-label="Basculer le mode sombre"><i class="fa fa-moon"></i></button>
            <a class="btn btn-outline" href="../dashboard_student.php">Tableau de bord</a>
            <a class="btn btn-light" href="../logout.php">Déconnexion</a>
        </div>
    </header>

    <main class="quiz-shell">
        <section class="quiz-hero">
            <span class="eyebrow">Espace étudiant</span>
            <h1>Mes quiz</h1>
            <p>Choisissez une évaluation et suivez votre progression.</p>
        </section>

        <section class="quiz-question">
            <?php if ($quizzes): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fa fa-clipboard-question" aria-hidden="true"></i> Quiz</th>
                                <th><i class="fa fa-book-open" aria-hidden="true"></i> Cours</th>
                                <th><i class="fa fa-chart-column" aria-hidden="true"></i> Résultat</th>
                                <th><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $quiz): ?>
                                <tr>
                                    <td><?= e($quiz['quiz_title']); ?></td>
                                    <td><?= e($quiz['course_title']); ?></td>
                                    <td><?= $quiz['score'] !== null ? e($quiz['score']) . ' %' : 'À faire'; ?></td>
                                    <td><a class="btn btn-secondary" href="take_quiz.php?quiz_id=<?= (int) $quiz['quiz_id']; ?>"><i class="fa fa-play" aria-hidden="true"></i> Passer le quiz</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="quiz-empty">Aucun quiz n’est disponible pour le moment.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">© 2026 E-Learning</footer>
    <script src="../js/theme.js" defer></script>
</body>
</html>
