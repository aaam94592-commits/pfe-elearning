<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/learning_resources.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = $_SESSION['teacher_quiz_message'] ?? '';
$messageType = $_SESSION['teacher_quiz_message_type'] ?? 'success';
unset($_SESSION['teacher_quiz_message']);
unset($_SESSION['teacher_quiz_message_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_quiz_resources'])) {
    check_csrf();
    $quizId = filter_input(INPUT_POST, 'quiz_id', FILTER_VALIDATE_INT);
    $quizOwnership = $pdo->prepare('SELECT q.id
        FROM quizzes q
        JOIN courses c ON c.id = q.course_id
        JOIN teachers t ON t.id = c.teacher_id
        WHERE q.id = :quiz_id AND t.user_id = :user_id
        LIMIT 1');
    $quizOwnership->execute(['quiz_id' => $quizId ?: 0, 'user_id' => $userId]);
    if (!$quizOwnership->fetchColumn()) {
        http_response_code(404);
        exit('Quiz introuvable.');
    }

    try {
        $savedCount = save_learning_resources($pdo, $_FILES['resources'] ?? [], 'quiz', (int) $quizId, $userId);
        if ($savedCount === 0) {
            $message = 'Sélectionnez au moins un fichier à ajouter.';
            $messageType = 'error';
        } else {
            $_SESSION['teacher_quiz_message'] = $savedCount . ' ressource(s) ajoutée(s) au quiz.';
            header('Location: dashboard_teacher_quiz.php');
            exit;
        }
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resource'])) {
    check_csrf();
    $resourceId = filter_input(INPUT_POST, 'delete_resource', FILTER_VALIDATE_INT);
    $resourceOwnership = $pdo->prepare('SELECT r.id
        FROM learning_resources r
        JOIN quizzes q ON q.id = r.quiz_id
        JOIN courses c ON c.id = q.course_id
        JOIN teachers t ON t.id = c.teacher_id
        WHERE r.id = :resource_id AND t.user_id = :user_id
        LIMIT 1');
    $resourceOwnership->execute(['resource_id' => $resourceId ?: 0, 'user_id' => $userId]);
    if (!$resourceOwnership->fetchColumn()) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    $resourceDeleted = delete_learning_resource($pdo, (int) $resourceId);
    $_SESSION['teacher_quiz_message'] = $resourceDeleted
        ? 'Ressource supprimée.'
        : 'La ressource a été supprimée, mais son fichier doit être nettoyé par un administrateur.';
    $_SESSION['teacher_quiz_message_type'] = $resourceDeleted ? 'success' : 'error';
    header('Location: dashboard_teacher_quiz.php');
    exit;
}

$stmt = $pdo->prepare('SELECT q.id, q.titre, q.description, c.titre AS course_name
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    JOIN teachers t ON t.id = c.teacher_id
    WHERE t.user_id = :user_id
    ORDER BY q.id DESC');
$stmt->execute(['user_id' => (int) $_SESSION['user_id']]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$resourceQuery = $pdo->prepare('SELECT r.id, r.quiz_id, r.original_name, r.mime_type, r.file_size
    FROM learning_resources r
    JOIN quizzes q ON q.id = r.quiz_id
    JOIN courses c ON c.id = q.course_id
    JOIN teachers t ON t.id = c.teacher_id
    WHERE t.user_id = :user_id
    ORDER BY r.id DESC');
$resourceQuery->execute(['user_id' => $userId]);
$resourcesByQuiz = [];
foreach ($resourceQuery->fetchAll(PDO::FETCH_ASSOC) as $resource) {
    $resourcesByQuiz[(int) $resource['quiz_id']][] = $resource;
}
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
        <a class="site-brand" href="../dashboard_teacher.php"><div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div><span class="site-title">E-Learning</span></a>
        <div class="nav-actions">
            <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre" aria-label="Basculer le mode sombre"><i class="fa fa-moon"></i></button>
            <a class="btn btn-outline" href="../dashboard_teacher.php">Tableau de bord</a>
            <a class="btn btn-light" href="../logout.php">Déconnexion</a>
        </div>
    </header>

    <main class="quiz-shell">
        <section class="quiz-hero">
            <span class="eyebrow">Espace enseignant</span>
            <h1>Gestion des quiz</h1>
            <p>Créez des questions et consultez les résultats de vos apprenants.</p>
            <a class="btn btn-primary" href="../teachers/create_quiz.php"><i class="fa fa-circle-plus" aria-hidden="true"></i> Créer un quiz</a>
        </section>

        <?php if ($message !== ''): ?>
            <p class="<?= e($messageType); ?>" role="status"><?= e($message); ?></p>
        <?php endif; ?>

        <?php if ($quizzes): ?>
            <?php foreach ($quizzes as $quiz): ?>
                <article class="quiz-question">
                    <h2><i class="fa fa-clipboard-question card-title-icon" aria-hidden="true"></i> <?= e($quiz['titre']); ?></h2>
                    <p><strong>Cours :</strong> <?= e($quiz['course_name']); ?></p>
                    <?php if ($quiz['description']): ?><p><?= e($quiz['description']); ?></p><?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="resource-upload-form">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="quiz_id" value="<?= (int) $quiz['id']; ?>">
                        <label for="quiz-resources-<?= (int) $quiz['id']; ?>"><i class="fa fa-cloud-arrow-up" aria-hidden="true"></i> Supports du quiz</label>
                        <input id="quiz-resources-<?= (int) $quiz['id']; ?>" type="file" name="resources[]" multiple required
                            accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.webm,.mov,.mp3,.wav,.ogg,.pdf,.txt,.csv,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                        <small class="muted">Images, vidéos, audio et documents. Jusqu’à 5 fichiers par envoi, <?= (int) floor(learning_resource_max_upload_bytes() / 1048576); ?> Mo maximum par fichier (limite totale du serveur également appliquée).</small>
                        <button class="btn btn-secondary" type="submit" name="upload_quiz_resources" value="1"><i class="fa fa-upload" aria-hidden="true"></i> Importer</button>
                    </form>
                    <?php $quizResources = $resourcesByQuiz[(int) $quiz['id']] ?? []; ?>
                    <?php if ($quizResources): ?>
                        <ul class="resource-list teacher-resource-list">
                            <?php foreach ($quizResources as $resource): ?>
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
                    <div class="quiz-actions">
                        <a class="btn btn-secondary" href="add_question.php?quiz_id=<?= (int) $quiz['id']; ?>"><i class="fa fa-list-check" aria-hidden="true"></i> Gérer les questions</a>
                        <a class="btn btn-primary" href="view_results.php?quiz_id=<?= (int) $quiz['id']; ?>"><i class="fa fa-chart-column" aria-hidden="true"></i> Voir les résultats</a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <section class="quiz-question quiz-empty">
                Vous n’avez pas encore créé de quiz.
                <a class="text-link" href="../teachers/create_quiz.php">Créer votre premier quiz</a>
            </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer">© 2026 E-Learning</footer>
    <script src="../js/theme.js" defer></script>
</body>
</html>
