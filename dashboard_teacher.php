<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT c.id, c.titre, c.description
    FROM courses c
    JOIN teachers t ON t.id = c.teacher_id
    WHERE t.user_id = :user_id');
$stmt->execute(['user_id' => $userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCourses = count($courses);
$teacherStats = $pdo->prepare('SELECT
    (SELECT COUNT(*) FROM quizzes q JOIN courses c ON c.id = q.course_id WHERE c.teacher_id = t.id) AS quiz_count,
    (SELECT COUNT(*) FROM questions q
        JOIN quizzes z ON z.id = q.quiz_id
        JOIN courses c ON c.id = z.course_id
        WHERE c.teacher_id = t.id) AS question_count,
    (SELECT COUNT(DISTINCT ce.student_id)
        FROM course_enrollments ce
        JOIN courses c ON c.id = ce.course_id
        WHERE c.teacher_id = t.id) AS student_count
    FROM teachers t
    WHERE t.user_id = :user_id
    LIMIT 1');
$teacherStats->execute(['user_id' => $userId]);
$stats = $teacherStats->fetch(PDO::FETCH_ASSOC) ?: ['quiz_count' => 0, 'question_count' => 0, 'student_count' => 0];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Enseignant - E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark"><i class="fa fa-chalkboard-teacher"></i></div>
                <span>E-Learning</span>
            </div>
            <div class="topbar-actions">
                <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre"><i class="fa fa-moon"></i></button>
                <span class="user-pill"><i class="fa fa-chalkboard-user ui-icon" aria-hidden="true"></i> Enseignant</span>
                <a class="btn btn-light" href="logout.php"><i class="fa fa-right-from-bracket ui-icon" aria-hidden="true"></i> Déconnexion</a>
            </div>
        </header>

        <div class="dashboard-grid">
            <aside class="sidebar">
                <div class="sidebar-brand"><i class="fa fa-chalkboard-user" aria-hidden="true"></i> Enseignant</div>
                <nav class="nav-group">
                    <a class="nav-link active" href="dashboard_teacher.php"><i class="fa fa-grid-2"></i> Dashboard</a>
                    <a class="nav-link" href="teachers/manage_courses.php"><i class="fa fa-book"></i> Cours</a>
                    <a class="nav-link" href="teachers/create_quiz.php"><i class="fa fa-circle-plus"></i> Créer un quiz</a>
                    <a class="nav-link" href="quiz/dashboard_teacher_quiz.php"><i class="fa fa-clipboard-question"></i> Mes quiz</a>
                    <a class="nav-link" href="teachers/profile.php"><i class="fa fa-user"></i> Mon profil</a>
                </nav>
            </aside>

            <main class="content">
                <section class="hero">
                    <h2>Bienvenue, <?= e($_SESSION['user_name']); ?></h2>
                    <div class="subtext">Gérez vos cours, quiz et contenus pédagogiques.</div>
                </section>

                <section class="stats-grid">
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-book-open stat-label-icon" aria-hidden="true"></i> Cours</span>
                        <span class="value"><?= $totalCourses; ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-clipboard-question stat-label-icon" aria-hidden="true"></i> Quiz</span>
                        <span class="value"><?= (int) $stats['quiz_count']; ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-user-graduate stat-label-icon" aria-hidden="true"></i> Étudiants</span>
                        <span class="value"><?= (int) $stats['student_count']; ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-circle-question stat-label-icon" aria-hidden="true"></i> Questions</span>
                        <span class="value"><?= (int) $stats['question_count']; ?></span>
                    </div>
                </section>

                <section class="panel">
                    <h3><i class="fa fa-book-open section-icon" aria-hidden="true"></i> Mes cours</h3>
                    <?php if ($courses): ?>
                        <div class="list-grid">
                            <?php foreach ($courses as $course): ?>
                                <article class="course-card course-image-card" style="--course-image: url('<?= e(course_image_url($course['titre'])); ?>')">
                                    <h4><i class="fa fa-book card-title-icon" aria-hidden="true"></i> <?= e($course['titre']); ?></h4>
                                    <p><?= e($course['description']); ?></p>
                                    <div class="course-meta">
                                        <span class="badge"><i class="fa fa-book" aria-hidden="true"></i> Cours</span>
                                        <a class="btn btn-secondary" href="teachers/create_quiz.php?course_id=<?= (int) $course['id']; ?>"><i class="fa fa-circle-plus" aria-hidden="true"></i> Ajouter quiz</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">Aucun cours pour le moment. Commencez par créer votre premier cours.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <footer class="site-footer">© 2026 E-Learning • Designed with ❤️</footer>
    <script src="js/theme.js" defer></script>
</body>
</html>
