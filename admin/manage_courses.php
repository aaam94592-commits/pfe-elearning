<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/learning_resources.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = $_SESSION['admin_course_message'] ?? '';
$messageType = $_SESSION['admin_course_message_type'] ?? 'success';
unset($_SESSION['admin_course_message']);
unset($_SESSION['admin_course_message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (isset($_POST['delete_course'])) {
        $courseId = (int) ($_POST['delete_course'] ?? 0);
        if ($courseId > 0) {
            $resourceIds = $pdo->prepare('SELECT r.id
                FROM learning_resources r
                LEFT JOIN quizzes q ON q.id = r.quiz_id
                WHERE r.course_id = :course_id OR q.course_id = :quiz_course_id');
            $resourceIds->execute(['course_id' => $courseId, 'quiz_course_id' => $courseId]);
            foreach ($resourceIds->fetchAll(PDO::FETCH_COLUMN) as $resourceId) {
                delete_learning_resource($pdo, (int) $resourceId);
            }
            $pdo->prepare('DELETE FROM courses WHERE id = :id')->execute(['id' => $courseId]);
            $message = 'Cours supprimé avec succès.';
        }
    } elseif (isset($_POST['upload_course_resources'])) {
        $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $courseExists = $pdo->prepare('SELECT id FROM courses WHERE id = :course_id LIMIT 1');
        $courseExists->execute(['course_id' => $courseId ?: 0]);
        if (!$courseExists->fetchColumn()) {
            http_response_code(404);
            exit('Cours introuvable.');
        }
        try {
            $savedCount = save_learning_resources($pdo, $_FILES['resources'] ?? [], 'course', (int) $courseId, (int) $_SESSION['user_id']);
            if ($savedCount > 0) {
                $_SESSION['admin_course_message'] = $savedCount . ' ressource(s) ajoutée(s) au cours.';
                header('Location: manage_courses.php');
                exit;
            }
            $message = 'Sélectionnez au moins un fichier à ajouter.';
            $messageType = 'error';
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $messageType = 'error';
        }
    } elseif (isset($_POST['delete_resource'])) {
        $resourceId = filter_input(INPUT_POST, 'delete_resource', FILTER_VALIDATE_INT);
        $resourceExists = $pdo->prepare('SELECT id FROM learning_resources WHERE id = :id LIMIT 1');
        $resourceExists->execute(['id' => $resourceId ?: 0]);
        if (!$resourceExists->fetchColumn()) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }
        $resourceDeleted = delete_learning_resource($pdo, (int) $resourceId);
        $_SESSION['admin_course_message'] = $resourceDeleted
            ? 'Ressource supprimée.'
            : 'La ressource a été supprimée, mais son fichier doit être nettoyé par un administrateur.';
        $_SESSION['admin_course_message_type'] = $resourceDeleted ? 'success' : 'error';
        header('Location: manage_courses.php');
        exit;
    } elseif (isset($_POST['add_course'])) {
        $title = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);

        if ($title !== '' && $description !== '' && $teacherId > 0) {
            $pdo->prepare('INSERT INTO courses (teacher_id, titre, description) VALUES (:teacher_id, :titre, :description)')
                ->execute([
                    'teacher_id' => $teacherId,
                    'titre' => $title,
                    'description' => $description,
                ]);
            $message = 'Cours ajouté avec succès.';
        } else {
            $message = 'Veuillez remplir tous les champs.';
            $messageType = 'error';
        }
    }
}

$courses = $pdo->query('SELECT c.id, c.titre, c.description, c.created_at, u.name AS teacher_name
    FROM courses c
    LEFT JOIN teachers t ON t.id = c.teacher_id
    LEFT JOIN users u ON u.id = t.user_id
    ORDER BY c.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

$teachers = $pdo->query('SELECT t.id, u.name FROM teachers t JOIN users u ON u.id = t.user_id ORDER BY u.name ASC')->fetchAll(PDO::FETCH_ASSOC);
$resourcesQuery = $pdo->query('SELECT r.id, COALESCE(r.course_id, q.course_id) AS course_id,
        r.original_name, r.mime_type, r.file_size
    FROM learning_resources r
    LEFT JOIN quizzes q ON q.id = r.quiz_id
    ORDER BY r.id DESC');
$resourcesByCourse = [];
foreach ($resourcesQuery->fetchAll(PDO::FETCH_ASSOC) as $resource) {
    $resourcesByCourse[(int) $resource['course_id']][] = $resource;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des cours - E-Learning</title>
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
                <a class="btn btn-light" href="../dashboard_admin.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Retour</a>
            </div>
        </header>

        <main class="content" style="margin-top: 22px;">
            <section class="panel">
                <h3><i class="fa fa-circle-plus section-icon" aria-hidden="true"></i> Ajouter un cours</h3>
                <?php if ($message !== ''): ?>
                    <p class="<?= e($messageType); ?>" role="status"><?= e($message); ?></p>
                <?php endif; ?>

                <form method="POST" class="auth-form" style="max-width: 900px; margin-top: 18px;">
                    <?= csrf_field(); ?>
                    <div class="form-row">
                        <div class="form-field">
                            <label><i class="fa fa-book" aria-hidden="true"></i> Titre du cours</label>
                            <input class="form-control" type="text" name="titre" required>
                        </div>
                        <div class="form-field">
                            <label><i class="fa fa-chalkboard-user" aria-hidden="true"></i> Enseignant</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int) $teacher['id']; ?>"><?= e($teacher['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-field">
                        <label><i class="fa fa-align-left" aria-hidden="true"></i> Description</label>
                        <textarea class="form-control" rows="5" name="description" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" name="add_course" value="1"><i class="fa fa-circle-plus" aria-hidden="true"></i> Ajouter le cours</button>
                </form>
            </section>

            <section class="panel" style="margin-top: 22px;">
                <h3><i class="fa fa-book-open section-icon" aria-hidden="true"></i> Liste des cours</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fa fa-book" aria-hidden="true"></i> Titre</th>
                                <th><i class="fa fa-chalkboard-user" aria-hidden="true"></i> Enseignant</th>
                                <th><i class="fa fa-align-left" aria-hidden="true"></i> Description</th>
                                <th><i class="fa fa-paperclip" aria-hidden="true"></i> Supports</th>
                                <th><i class="fa fa-gear" aria-hidden="true"></i> Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?= e($course['titre']); ?></td>
                                    <td><?= e($course['teacher_name'] ?? 'Non assigné'); ?></td>
                                    <td><?= e($course['description']); ?></td>
                                    <td>
                                        <form method="POST" enctype="multipart/form-data" class="resource-upload-form">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="course_id" value="<?= (int) $course['id']; ?>">
                                            <label for="admin-course-resources-<?= (int) $course['id']; ?>">Ajouter des ressources</label>
                                            <input id="admin-course-resources-<?= (int) $course['id']; ?>" type="file" name="resources[]" multiple required
                                                accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.mp3,.wav,.ogg,.pdf,.txt,.csv,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                                            <small class="muted"><?= (int) floor(learning_resource_max_upload_bytes() / 1048576); ?> Mo maximum par fichier.</small>
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
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Supprimer ce cours ?');">
                                            <?= csrf_field(); ?>
                                            <button class="btn btn-secondary" type="submit" name="delete_course" value="<?= (int) $course['id']; ?>"><i class="fa fa-trash" aria-hidden="true"></i> Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <script src="../js/theme.js" defer></script>
</body>
</html>
