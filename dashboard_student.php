<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT s.id, u.name, s.date_naissance, s.filiere, s.niveau
    FROM students s
    JOIN users u ON u.id = s.user_id
    WHERE s.user_id = :user_id LIMIT 1');
$stmt->execute(['user_id' => $userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $student = ['id' => 0, 'name' => $_SESSION['user_name'] ?? 'Étudiant', 'date_naissance' => null, 'filiere' => null, 'niveau' => null];
}

$stmt = $pdo->prepare('SELECT c.id, c.titre, c.description, u.name AS teacher_name
    FROM course_enrollments ce
    JOIN students s ON s.id = ce.student_id
    JOIN courses c ON c.id = ce.course_id
    JOIN teachers t ON t.id = c.teacher_id
    JOIN users u ON u.id = t.user_id
    WHERE s.user_id = :user_id');
$stmt->execute(['user_id' => $userId]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT q.id, q.titre, q.description, c.titre AS course_name
    FROM course_enrollments ce
    JOIN students s ON s.id = ce.student_id
    JOIN courses c ON c.id = ce.course_id
    JOIN quizzes q ON q.course_id = c.id
    WHERE s.user_id = :user_id');
$stmt->execute(['user_id' => $userId]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT qr.score, q.titre
    FROM quiz_results qr
    JOIN quizzes q ON q.id = qr.quiz_id
    WHERE qr.student_id = :user_id');
$stmt->execute(['user_id' => $userId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Étudiant - E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark"><i class="fa fa-graduation-cap"></i></div>
                <span>E-Learning</span>
            </div>
            <div class="topbar-actions">
                <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre"><i class="fa fa-moon"></i></button>
                <span class="user-pill"><i class="fa fa-user-graduate ui-icon" aria-hidden="true"></i> Étudiant</span>
                <a class="btn btn-light" href="logout.php"><i class="fa fa-right-from-bracket ui-icon" aria-hidden="true"></i> Déconnexion</a>
            </div>
        </header>

        <div class="dashboard-grid">
            <aside class="sidebar">
                <div class="sidebar-brand">Étudiant</div>
                <nav class="nav-group">
                    <a class="nav-link" href="students/profile.php"><i class="fa fa-user" aria-hidden="true"></i> Profil</a>
                    <a class="nav-link" href="students/courses.php"><i class="fa fa-compass" aria-hidden="true"></i> Explorer les cours</a>
                    <a class="nav-link" href="#courses"><i class="fa fa-book" aria-hidden="true"></i> Mes cours</a>
                    <a class="nav-link" href="#quizzes"><i class="fa fa-clipboard-question" aria-hidden="true"></i> Quiz</a>
                    <a class="nav-link" href="#results"><i class="fa fa-chart-bar" aria-hidden="true"></i> Résultats</a>
                </nav>
            </aside>

            <main class="content">
                <section class="hero">
                    <h2>Bienvenue, <?= e($student['name']); ?></h2>
                    <div class="subtext">Suivez votre progression et accédez à vos cours et évaluations.</div>
                </section>

                <section class="stats-grid">
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-book-open stat-label-icon" aria-hidden="true"></i> Cours</span>
                        <span class="value"><?= count($courses); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-clipboard-question stat-label-icon" aria-hidden="true"></i> Quiz</span>
                        <span class="value"><?= count($quizzes); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-chart-column stat-label-icon" aria-hidden="true"></i> Résultats</span>
                        <span class="value"><?= count($results); ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-id-card stat-label-icon" aria-hidden="true"></i> Profil</span>
                        <span class="value">OK</span>
                    </div>
                </section>

                <section class="panel" id="profile">
                    <h3><i class="fa fa-id-card section-icon" aria-hidden="true"></i> Profil</h3>
                    <div class="summary-row">
                        <span class="inline-pill"><strong>Nom:</strong> <?= e($student['name']); ?></span>
                        <span class="inline-pill"><strong>Date:</strong> <?= e($student['date_naissance'] ?? '-'); ?></span>
                        <span class="inline-pill"><strong>Filière:</strong> <?= e($student['filiere'] ?? '-'); ?></span>
                        <span class="inline-pill"><strong>Niveau:</strong> <?= e($student['niveau'] ?? '-'); ?></span>
                    </div>
                </section>

                <section class="panel" id="courses">
                    <div class="panel-heading-row">
                        <h3><i class="fa fa-book-open section-icon" aria-hidden="true"></i> Mes cours</h3>
                        <a class="btn btn-primary" href="students/courses.php"><i class="fa fa-compass" aria-hidden="true"></i> Explorer le catalogue</a>
                    </div>
                    <?php if ($courses): ?>
                        <div class="list-grid">
                            <?php foreach ($courses as $course): ?>
                                <article class="course-card course-image-card" style="--course-image: url('<?= e(course_image_url($course['titre'])); ?>')">
                                    <h4><i class="fa fa-book card-title-icon" aria-hidden="true"></i> <?= e($course['titre']); ?></h4>
                                    <p><?= e($course['description']); ?></p>
                                    <div class="course-meta">
                                        <span class="muted">Prof: <?= e($course['teacher_name']); ?></span>
                                        <span class="badge"><i class="fa fa-book" aria-hidden="true"></i> Cours</span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">Vous n'êtes inscrit à aucun cours pour le moment. Explorez le catalogue pour trouver votre premier cours.</p>
                    <?php endif; ?>
                </section>

                <section class="panel" id="quizzes">
                    <h3><i class="fa fa-clipboard-question section-icon" aria-hidden="true"></i> Mes quiz</h3>
                    <?php if ($quizzes): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th><i class="fa fa-clipboard-question" aria-hidden="true"></i> Quiz</th>
                                        <th><i class="fa fa-book-open" aria-hidden="true"></i> Cours</th>
                                        <th><i class="fa fa-wand-magic-sparkles" aria-hidden="true"></i> Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <tr>
                                            <td><?= e($quiz['titre']); ?></td>
                                            <td><?= e($quiz['course_name']); ?></td>
                                            <td><a class="btn btn-secondary" href="quiz/take_quiz.php?quiz_id=<?= (int) $quiz['id']; ?>"><i class="fa fa-play" aria-hidden="true"></i> Passer</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="muted">Aucun quiz disponible pour l'instant.</p>
                    <?php endif; ?>
                </section>

                <section class="panel" id="results">
                    <h3><i class="fa fa-chart-column section-icon" aria-hidden="true"></i> Mes résultats</h3>
                    <?php if ($results): ?>
                        <div style="max-width: 700px; margin: 0 auto;">
                            <canvas id="resultsChart"></canvas>
                        </div>
                        <script>
                            const labels = [<?php foreach ($results as $result): ?>'<?= addslashes(e($result['titre'])); ?>',<?php endforeach; ?>];
                            const scoreData = [<?php foreach ($results as $result): ?><?= (float) $result['score']; ?>,<?php endforeach; ?>];

                            new Chart(document.getElementById('resultsChart'), {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Score (%)',
                                        data: scoreData,
                                        backgroundColor: ['#4f46e5', '#2563eb', '#0ea5e9', '#10b981'],
                                        borderRadius: 10
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 100,
                                            ticks: { color: '#475569' }
                                        },
                                        x: {
                                            ticks: { color: '#475569' }
                                        }
                                    },
                                    plugins: {
                                        legend: { display: false }
                                    }
                                }
                            });
                        </script>
                    <?php else: ?>
                        <p class="muted">Aucun résultat pour le moment.</p>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <footer class="site-footer">© 2026 E-Learning • Designed with ❤️</footer>
    <script src="js/theme.js" defer></script>
</body>
</html>
