<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/learning_resources.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = $_SESSION['teacher_course_message'] ?? '';
$messageType = $_SESSION['teacher_course_message_type'] ?? 'success';
unset($_SESSION['teacher_course_message']);
unset($_SESSION['teacher_course_message_type']);

$teacherQuery = $pdo->prepare('SELECT id FROM teachers WHERE user_id = :user_id LIMIT 1');
$teacherQuery->execute(['user_id' => $userId]);
$teacher = $teacherQuery->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    $courses = [];
} else {
    $teacherId = (int) $teacher['id'];
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE teacher_id = :teacher_id ORDER BY id DESC');
    $stmt->execute(['teacher_id' => $teacherId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    check_csrf();

    $title = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($teacher && $title !== '' && $description !== '') {
        $pdo->prepare('INSERT INTO courses (teacher_id, titre, description) VALUES (:teacher_id, :titre, :description)')
            ->execute([
                'teacher_id' => (int) $teacher['id'],
                'titre' => $title,
                'description' => $description,
            ]);
        $_SESSION['teacher_course_message'] = 'Cours ajouté. Vous pouvez maintenant ajouter des vidéos, images, fichiers audio ou documents depuis sa carte.';
        header('Location: manage_courses.php');
        exit;
    } else {
        $message = 'Veuillez remplir le titre et la description du cours.';
        $messageType = 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_course_resources'])) {
    check_csrf();
    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    if (!$teacher || !$courseId) {
        $message = 'Le cours sélectionné est invalide.';
        $messageType = 'error';
    } else {
        $courseOwnership = $pdo->prepare('SELECT id FROM courses WHERE id = :course_id AND teacher_id = :teacher_id LIMIT 1');
        $courseOwnership->execute(['course_id' => $courseId, 'teacher_id' => (int) $teacher['id']]);
        if (!$courseOwnership->fetchColumn()) {
            http_response_code(404);
            exit('Cours introuvable.');
        }

        try {
            $savedCount = save_learning_resources($pdo, $_FILES['resources'] ?? [], 'course', $courseId, $userId);
            if ($savedCount === 0) {
                $message = 'Sélectionnez au moins un fichier à ajouter.';
                $messageType = 'error';
            } else {
                $_SESSION['teacher_course_message'] = $savedCount . ' ressource(s) ajoutée(s) au cours.';
                header('Location: manage_courses.php');
                exit;
            }
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $messageType = 'error';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resource'])) {
    check_csrf();
    $resourceId = filter_input(INPUT_POST, 'delete_resource', FILTER_VALIDATE_INT);
    $resourceOwnership = $pdo->prepare('SELECT r.id
        FROM learning_resources r
        JOIN courses c ON c.id = r.course_id
        WHERE r.id = :resource_id AND c.teacher_id = :teacher_id
        LIMIT 1');
    $resourceOwnership->execute([
        'resource_id' => $resourceId ?: 0,
        'teacher_id' => $teacher ? (int) $teacher['id'] : 0,
    ]);
    if (!$resourceOwnership->fetchColumn()) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    $resourceDeleted = delete_learning_resource($pdo, (int) $resourceId);
    $_SESSION['teacher_course_message'] = $resourceDeleted
        ? 'Ressource supprimée.'
        : 'La ressource a été supprimée, mais son fichier doit être nettoyé par un administrateur.';
    $_SESSION['teacher_course_message_type'] = $resourceDeleted ? 'success' : 'error';
    header('Location: manage_courses.php');
    exit;
}

if ($teacher) {
    $resourcesQuery = $pdo->prepare('SELECT r.id, r.course_id, r.original_name, r.mime_type, r.file_size
        FROM learning_resources r
        JOIN courses c ON c.id = r.course_id
        WHERE c.teacher_id = :teacher_id
        ORDER BY r.id DESC');
    $resourcesQuery->execute(['teacher_id' => (int) $teacher['id']]);
    $resourcesByCourse = [];
    foreach ($resourcesQuery->fetchAll(PDO::FETCH_ASSOC) as $resource) {
        $resourcesByCourse[(int) $resource['course_id']][] = $resource;
    }
} else {
    $resourcesByCourse = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes cours - E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
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
                <a class="btn btn-light" href="../dashboard_teacher.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Retour</a>
            </div>
        </header>

        <main class="content" style="margin-top: 22px;">
            <section class="panel">
                <h3><i class="fa fa-circle-plus section-icon" aria-hidden="true"></i> Ajouter un cours</h3>
                <?php if ($message !== ''): ?>
                    <p class="<?= e($messageType); ?>" role="status"><?= e($message); ?></p>
                <?php endif; ?>
                <p class="muted">Après sa création, ajoutez des supports directement depuis la carte du cours.</p>

                <form method="POST" class="auth-form" style="max-width: 760px; margin-top: 18px;">
                    <?= csrf_field(); ?>
                    <div class="form-field">
                        <label><i class="fa fa-book" aria-hidden="true"></i> Titre du cours</label>
                        <input class="form-control" type="text" name="titre" required>
                    </div>
                    <div class="form-field">
                        <label><i class="fa fa-align-left" aria-hidden="true"></i> Description</label>
                        <textarea class="form-control" rows="5" name="description" required></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit" name="add_course" value="1"><i class="fa fa-circle-plus" aria-hidden="true"></i> Créer le cours</button>
                </form>
            </section>

            <section class="panel" style="margin-top: 22px;">
                <h3><i class="fa fa-book-open section-icon" aria-hidden="true"></i> Mes cours</h3>
                <div class="list-grid">
                    <?php foreach ($courses as $course): ?>
                        <article class="course-card course-image-card" style="--course-image: url('<?= e(course_image_url($course['titre'])); ?>')">
                            <h4><i class="fa fa-book card-title-icon" aria-hidden="true"></i> <?= e($course['titre']); ?></h4>
                            <p><?= e($course['description']); ?></p>
                            <div class="course-meta">
                                <span class="badge"><i class="fa fa-book" aria-hidden="true"></i> Cours</span>
                                <a class="btn btn-secondary" href="create_quiz.php?course_id=<?= (int) $course['id']; ?>"><i class="fa fa-circle-plus" aria-hidden="true"></i> Créer un quiz</a>
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="resource-upload-form">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                <label for="course-resources-<?= (int) $course['id']; ?>"><i class="fa fa-cloud-arrow-up" aria-hidden="true"></i> Ajouter des supports</label>
                                <input id="course-resources-<?= (int) $course['id']; ?>" type="file" name="resources[]" multiple required
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.mp3,.wav,.ogg,.pdf,.txt,.csv,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                                <small class="muted">Images, vidéos, audio et documents. Jusqu’à 5 fichiers par envoi, <?= (int) floor(learning_resource_max_upload_bytes() / 1048576); ?> Mo maximum par fichier (limite totale du serveur également appliquée).</small>
                                <button class="btn btn-secondary" type="submit" name="upload_course_resources" value="1"><i class="fa fa-upload" aria-hidden="true"></i> Importer</button>
                            </form>
                            <?php $courseResources = $resourcesByCourse[(int) $course['id']] ?? []; ?>
                            <?php if ($courseResources): ?>
                                <ul class="resource-list teacher-resource-list">
                                    <?php foreach ($courseResources as $resource): ?>
                                        <li>
                                            <a href="../resources/download.php?id=<?= (int) $resource['id']; ?>" target="_blank" rel="noopener">
                                                <i class="fa <?= e(learning_resource_icon($resource['mime_type'])); ?>" aria-hidden="true"></i>
                                                <?= e($resource['original_name']); ?>
                                                <span class="muted">(<?= e(learning_resource_size((int) $resource['file_size'])); ?>)</span>
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Supprimer cette ressource ?');">
                                                <?= csrf_field(); ?>
                                                <button class="btn btn-secondary resource-delete-button" type="submit" name="delete_resource" value="<?= (int) $resource['id']; ?>" aria-label="Supprimer <?= e($resource['original_name']); ?>">
                                                    <i class="fa fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
    <script src="../js/theme.js" defer></script>
</body>
</html>
