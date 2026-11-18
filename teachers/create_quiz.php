<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = $_SESSION['teacher_quiz_message'] ?? '';
unset($_SESSION['teacher_quiz_message']);
$selectedCourseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

$teacherQuery = $pdo->prepare('SELECT id FROM teachers WHERE user_id = :user_id LIMIT 1');
$teacherQuery->execute(['user_id' => $userId]);
$teacher = $teacherQuery->fetch(PDO::FETCH_ASSOC);

if ($teacher) {
    $teacherId = (int) $teacher['id'];
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE teacher_id = :teacher_id ORDER BY id DESC');
    $stmt->execute(['teacher_id' => $teacherId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $courses = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $courseId = (int) ($_POST['course_id'] ?? 0);
    $title = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($courseId > 0 && $title !== '' && $description !== '') {
        $courseOwnership = $pdo->prepare('SELECT id FROM courses WHERE id = :course_id AND teacher_id = :teacher_id LIMIT 1');
        $courseOwnership->execute([
            'course_id' => $courseId,
            'teacher_id' => $teacher ? (int) $teacher['id'] : 0,
        ]);
        if (!$courseOwnership->fetchColumn()) {
            $message = 'Veuillez sélectionner un de vos cours.';
        } else {
            $pdo->prepare('INSERT INTO quizzes (course_id, titre, description) VALUES (:course_id, :titre, :description)')
                ->execute([
                    'course_id' => $courseId,
                    'titre' => $title,
                    'description' => $description,
                ]);
            $_SESSION['teacher_quiz_message'] = 'Quiz créé. Ajoutez maintenant ses documents et gérez ses questions.';
            header('Location: ../quiz/dashboard_teacher_quiz.php');
            exit;
        }
    } elseif ($courseId <= 0) {
        $message = 'Veuillez sélectionner un cours.';
    } else {
        $message = 'Veuillez remplir le titre et la description du quiz.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un quiz - E-Learning</title>
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
                <h3><i class="fa fa-clipboard-question section-icon" aria-hidden="true"></i> Créer un quiz</h3>
                <?php if ($message !== ''): ?>
                    <p class="error" role="alert"><?= e($message); ?></p>
                <?php endif; ?>

                <form method="POST" class="auth-form" style="max-width: 760px; margin-top: 18px;">
                    <?= csrf_field(); ?>
                    <div class="form-field">
                        <label><i class="fa fa-book-open" aria-hidden="true"></i> Choisir un cours</label>
                        <select class="form-select" name="course_id" required <?= !$courses ? 'disabled' : ''; ?>>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) $course['id']; ?>" <?= $selectedCourseId === (int) $course['id'] ? 'selected' : ''; ?>><?= e($course['titre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label><i class="fa fa-heading" aria-hidden="true"></i> Titre du quiz</label>
                        <input class="form-control" type="text" name="titre" required>
                    </div>
                    <div class="form-field">
                        <label><i class="fa fa-align-left" aria-hidden="true"></i> Description</label>
                        <textarea class="form-control" rows="5" name="description" required></textarea>
                    </div>
                    <p class="muted">Après la création, importez ses ressources depuis la carte du quiz.</p>
                    <?php if ($courses): ?>
                        <button class="btn btn-primary" type="submit"><i class="fa fa-circle-plus" aria-hidden="true"></i> Créer</button>
                    <?php else: ?>
                        <p class="muted">Créez d’abord un cours pour pouvoir lui associer un quiz.</p>
                        <a class="btn btn-secondary" href="manage_courses.php"><i class="fa fa-book" aria-hidden="true"></i> Gérer mes cours</a>
                    <?php endif; ?>
                </form>
            </section>
        </main>
    </div>
    <script src="../js/theme.js" defer></script>
</body>
</html>
