<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/learning_resources.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../login.php');
    exit;
}

$studentQuery = $pdo->prepare('SELECT id FROM students WHERE user_id = :user_id LIMIT 1');
$studentQuery->execute(['user_id' => (int) $_SESSION['user_id']]);
$studentId = (int) $studentQuery->fetchColumn();

if (!$studentId) {
    http_response_code(403);
    exit('Votre profil étudiant est incomplet. Contactez un administrateur.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    if (!$courseId) {
        $_SESSION['course_catalog_message'] = ['type' => 'error', 'text' => 'Le cours sélectionné est invalide.'];
    } else {
        $courseExists = $pdo->prepare('SELECT id FROM courses WHERE id = :course_id LIMIT 1');
        $courseExists->execute(['course_id' => $courseId]);

        if (!$courseExists->fetchColumn()) {
            $_SESSION['course_catalog_message'] = ['type' => 'error', 'text' => 'Ce cours n’est plus disponible.'];
        } else {
            $enroll = $pdo->prepare('INSERT INTO course_enrollments (student_id, course_id)
                VALUES (:student_id, :course_id)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
            $enroll->execute(['student_id' => $studentId, 'course_id' => $courseId]);
            $_SESSION['course_catalog_message'] = ['type' => 'success', 'text' => 'Inscription au cours effectuée. Il est maintenant disponible dans votre tableau de bord.'];
        }
    }

    $redirectUrl = 'courses.php';
    if (!empty($_POST['search'])) {
        $redirectUrl .= '?search=' . rawurlencode(trim((string) $_POST['search']));
    }
    header('Location: ' . $redirectUrl);
    exit;
}

$message = $_SESSION['course_catalog_message'] ?? null;
unset($_SESSION['course_catalog_message']);

$search = trim((string) ($_GET['search'] ?? ''));
$searchSql = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
$coursesQuery = $pdo->prepare('SELECT c.id, c.titre, c.description,
        u.name AS teacher_name,
        COUNT(DISTINCT q.id) AS quiz_count,
        ce.id AS enrollment_id
    FROM courses c
    LEFT JOIN teachers t ON t.id = c.teacher_id
    LEFT JOIN users u ON u.id = t.user_id
    LEFT JOIN quizzes q ON q.course_id = c.id
    LEFT JOIN course_enrollments ce ON ce.course_id = c.id AND ce.student_id = :student_id
    WHERE (:search_empty = 1 OR c.titre LIKE :search_title OR c.description LIKE :search_description OR u.name LIKE :search_teacher)
    GROUP BY c.id, c.titre, c.description, u.name, ce.id
    ORDER BY (ce.id IS NOT NULL) DESC, c.titre');
$coursesQuery->execute([
    'student_id' => $studentId,
    'search_empty' => $search === '' ? 1 : 0,
    'search_title' => $searchSql,
    'search_description' => $searchSql,
    'search_teacher' => $searchSql,
]);
$courses = $coursesQuery->fetchAll(PDO::FETCH_ASSOC);
$enrolledCount = count(array_filter($courses, function ($course) {
    return $course['enrollment_id'] !== null;
}));

$resourceQuery = $pdo->prepare('SELECT r.id,
        COALESCE(r.course_id, q.course_id) AS parent_course_id,
        r.original_name, r.mime_type, r.file_size
    FROM learning_resources r
    LEFT JOIN quizzes q ON q.id = r.quiz_id
    WHERE (r.course_id IS NOT NULL AND EXISTS (
            SELECT 1
            FROM course_enrollments ce
            JOIN students s ON s.id = ce.student_id
            WHERE ce.course_id = r.course_id AND s.user_id = :course_user_id
        ))
        OR (q.course_id IS NOT NULL AND EXISTS (
            SELECT 1
            FROM course_enrollments ce
            JOIN students s ON s.id = ce.student_id
            WHERE ce.course_id = q.course_id AND s.user_id = :quiz_user_id
        ))
    ORDER BY r.id DESC');
$resourceQuery->execute([
    'course_user_id' => (int) $_SESSION['user_id'],
    'quiz_user_id' => (int) $_SESSION['user_id'],
]);
$resourcesByCourse = [];
foreach ($resourceQuery->fetchAll(PDO::FETCH_ASSOC) as $resource) {
    $resourcesByCourse[(int) $resource['parent_course_id']][] = $resource;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorer les cours - E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="../dashboard_student.php">
                <div class="brand-mark"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
                <span>E-Learning</span>
            </a>
            <div class="topbar-actions">
                <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre" aria-label="Basculer le mode sombre"><i class="fa fa-moon" aria-hidden="true"></i></button>
                <a class="btn btn-light" href="../dashboard_student.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Mon espace</a>
            </div>
        </header>

        <main class="content" style="margin-top: 22px;">
            <section class="hero">
                <h2><i class="fa fa-compass" aria-hidden="true"></i> Explorer les cours</h2>
                <div class="subtext">Trouvez un parcours, inscrivez-vous et retrouvez ses quiz dans votre espace.</div>
            </section>

            <?php if ($message): ?>
                <p class="<?= $message['type'] === 'success' ? 'success' : 'error'; ?>" role="status"><?= e($message['text']); ?></p>
            <?php endif; ?>

            <section class="panel catalog-toolbar">
                <form method="GET" class="catalog-search">
                    <label class="sr-only" for="course-search">Rechercher un cours ou un enseignant</label>
                    <i class="fa fa-magnifying-glass" aria-hidden="true"></i>
                    <input class="form-control" id="course-search" type="search" name="search" value="<?= e($search); ?>" placeholder="Rechercher un cours ou un enseignant">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-magnifying-glass" aria-hidden="true"></i> Rechercher</button>
                    <?php if ($search !== ''): ?>
                        <a class="btn btn-secondary" href="courses.php"><i class="fa fa-xmark" aria-hidden="true"></i> Effacer</a>
                    <?php endif; ?>
                </form>
                <p class="muted catalog-summary">
                    <i class="fa fa-book-open" aria-hidden="true"></i>
                    <?= count($courses); ?> cours affiché(s) · <?= $enrolledCount; ?> déjà rejoint(s) dans ces résultats
                </p>
            </section>

            <?php if ($courses): ?>
                <section class="list-grid catalog-grid" aria-label="Catalogue des cours">
                    <?php foreach ($courses as $course): ?>
                        <?php $isEnrolled = $course['enrollment_id'] !== null; ?>
                        <article class="course-card course-image-card catalog-course" style="--course-image: url('<?= e(course_image_url($course['titre'])); ?>')">
                            <h3><i class="fa fa-book card-title-icon" aria-hidden="true"></i> <?= e($course['titre']); ?></h3>
                            <p><?= e($course['description'] ?: 'Aucune description pour le moment.'); ?></p>
                            <div class="catalog-course-meta">
                                <span><i class="fa fa-chalkboard-user" aria-hidden="true"></i> <?= e($course['teacher_name'] ?: 'Équipe pédagogique'); ?></span>
                                <span><i class="fa fa-clipboard-question" aria-hidden="true"></i> <?= (int) $course['quiz_count']; ?> quiz</span>
                            </div>
                            <?php if ($isEnrolled): ?>
                                <span class="badge catalog-enrolled"><i class="fa fa-circle-check" aria-hidden="true"></i> Déjà inscrit</span>
                                <?php $courseResources = $resourcesByCourse[(int) $course['id']] ?? []; ?>
                                <?php if ($courseResources): ?>
                                    <section class="course-resource-section">
                                        <h4><i class="fa fa-folder-open" aria-hidden="true"></i> Ressources du cours</h4>
                                        <ul class="resource-list">
                                            <?php foreach ($courseResources as $resource): ?>
                                                <li class="catalog-resource">
                                                    <?php if (strpos($resource['mime_type'], 'image/') === 0): ?>
                                                        <a class="resource-preview-link" href="../resources/download.php?id=<?= (int) $resource['id']; ?>" target="_blank" rel="noopener">
                                                            <img class="resource-image-preview" src="../resources/download.php?id=<?= (int) $resource['id']; ?>" alt="<?= e($resource['original_name']); ?>" loading="lazy">
                                                        </a>
                                                    <?php elseif (strpos($resource['mime_type'], 'video/') === 0): ?>
                                                        <video class="resource-video-preview" controls preload="none">
                                                            <source src="../resources/download.php?id=<?= (int) $resource['id']; ?>" type="<?= e($resource['mime_type']); ?>">
                                                            Votre navigateur ne peut pas lire cette vidéo.
                                                        </video>
                                                    <?php elseif (strpos($resource['mime_type'], 'audio/') === 0): ?>
                                                        <audio class="resource-audio-preview" controls preload="none">
                                                            <source src="../resources/download.php?id=<?= (int) $resource['id']; ?>" type="<?= e($resource['mime_type']); ?>">
                                                            Votre navigateur ne peut pas lire cet audio.
                                                        </audio>
                                                    <?php endif; ?>
                                                    <a href="../resources/download.php?id=<?= (int) $resource['id']; ?>" target="_blank" rel="noopener">
                                                        <i class="fa <?= e(learning_resource_icon($resource['mime_type'])); ?>" aria-hidden="true"></i>
                                                        <?= e($resource['original_name']); ?>
                                                        <span class="muted">(<?= e(learning_resource_size((int) $resource['file_size'])); ?>)</span>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </section>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" class="catalog-enroll-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                    <input type="hidden" name="search" value="<?= e($search); ?>">
                                    <button class="btn btn-primary" type="submit"><i class="fa fa-circle-plus" aria-hidden="true"></i> Rejoindre le cours</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <section class="panel catalog-empty">
                    <i class="fa fa-book-open" aria-hidden="true"></i>
                    <h3><?= $search !== '' ? 'Aucun cours trouvé' : 'Aucun cours disponible'; ?></h3>
                    <p class="muted"><?= $search !== '' ? 'Essayez un autre titre ou nom d’enseignant.' : 'Les cours apparaîtront ici dès qu’ils seront publiés.'; ?></p>
                    <?php if ($search !== ''): ?><a class="btn btn-secondary" href="courses.php">Voir tous les cours</a><?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <footer class="site-footer">© 2026 E-Learning</footer>
    <script src="../js/theme.js" defer></script>
</body>
</html>
