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

$error = '';
$success = '';
$questionText = '';
$type = 'qcm';
$answerValues = ['', '', '', ''];
$correctIndex = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $questionText = trim($_POST['question_text'] ?? '');
    $type = $_POST['type'] ?? '';
    $submittedAnswers = $_POST['answers'] ?? [];
    $answerValues = is_array($submittedAnswers)
        ? array_map(function ($answer) {
            return is_string($answer) ? trim($answer) : '';
        }, $submittedAnswers)
        : [];
    if ($type === 'true_false') {
        $answerValues = array_slice($answerValues, 0, 2);
    }
    $nonEmptyAnswers = array_filter($answerValues, function ($answer) {
        return $answer !== '';
    });
    $correctIndex = filter_var($_POST['correct'] ?? null, FILTER_VALIDATE_INT);

    if ($questionText === '' || !in_array($type, ['qcm', 'true_false'], true)) {
        $error = 'Renseignez la question et choisissez un type valide.';
    } elseif (count($nonEmptyAnswers) < 2) {
        $error = 'Ajoutez au moins deux réponses.';
    } elseif ($correctIndex === false || !isset($answerValues[$correctIndex]) || $answerValues[$correctIndex] === '') {
        $error = 'Choisissez une réponse correcte parmi les réponses renseignées.';
    } else {
        $pdo->beginTransaction();
        $insertQuestion = $pdo->prepare('INSERT INTO questions (quiz_id, question_text, type) VALUES (:quiz_id, :question_text, :type)');
        $insertQuestion->execute(['quiz_id' => $quizId, 'question_text' => $questionText, 'type' => $type]);
        $questionId = (int) $pdo->lastInsertId();

        $insertAnswer = $pdo->prepare('INSERT INTO answers (question_id, answer_text, is_correct) VALUES (:question_id, :answer_text, :is_correct)');
        foreach ($answerValues as $index => $answer) {
            if ($answer === '') {
                continue;
            }
            $insertAnswer->execute([
                'question_id' => $questionId,
                'answer_text' => $answer,
                'is_correct' => $index === $correctIndex ? 1 : 0,
            ]);
        }
        $pdo->commit();

        $success = 'Question ajoutée avec succès.';
        $questionText = '';
        $answerValues = ['', '', '', ''];
        $correctIndex = 0;
    }
}

$questionQuery = $pdo->prepare('SELECT id, question_text, type FROM questions WHERE quiz_id = :quiz_id ORDER BY id');
$questionQuery->execute(['quiz_id' => $quizId]);
$questions = $questionQuery->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions - <?= e($quiz['titre']); ?> | E-Learning</title>
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
            <h1><i class="fa fa-list-check" aria-hidden="true"></i> Questions : <?= e($quiz['titre']); ?></h1>
            <p>Ajoutez des questions à choix multiples ou vrai/faux. Choisissez une seule réponse correcte.</p>
        </section>

        <section class="quiz-question">
            <?php if ($error !== ''): ?><p class="error" role="alert"><?= e($error); ?></p><?php endif; ?>
            <?php if ($success !== ''): ?><p class="success" role="status"><?= e($success); ?></p><?php endif; ?>
            <form method="POST" class="auth-form">
                <?= csrf_field(); ?>
                <div class="form-field">
                    <label for="question_text"><i class="fa fa-circle-question" aria-hidden="true"></i> Texte de la question</label>
                    <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?= e($questionText); ?></textarea>
                </div>
                <div class="form-field">
                    <label for="type"><i class="fa fa-shapes" aria-hidden="true"></i> Type de question</label>
                    <select class="form-select" name="type" id="type" required>
                        <option value="qcm" <?= $type === 'qcm' ? 'selected' : ''; ?>>Choix multiple</option>
                        <option value="true_false" <?= $type === 'true_false' ? 'selected' : ''; ?>>Vrai / Faux</option>
                    </select>
                </div>
                <div class="form-field">
                    <span class="form-label"><i class="fa fa-list-ol" aria-hidden="true"></i> Réponses proposées</span>
                    <div class="quiz-answer-list" id="quizAnswerList">
                        <?php foreach ($answerValues as $index => $answer): ?>
                            <label class="quiz-option" for="answer-<?= $index; ?>" data-answer-index="<?= $index; ?>">
                                <input id="answer-<?= $index; ?>" type="radio" name="correct" value="<?= $index; ?>" <?= $correctIndex === $index ? 'checked' : ''; ?> aria-label="Définir la réponse <?= $index + 1; ?> comme correcte">
                                <input class="form-control" type="text" name="answers[]" value="<?= e($answer); ?>" placeholder="Réponse <?= $index + 1; ?>" aria-label="Réponse <?= $index + 1; ?>">
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="muted">Cochez le bouton à côté de la bonne réponse. Laissez les réponses inutilisées vides.</small>
                </div>
                <button class="btn btn-primary" type="submit"><i class="fa fa-circle-plus" aria-hidden="true"></i> Ajouter la question</button>
            </form>
        </section>

        <section class="quiz-question">
            <h2><i class="fa fa-list-check section-icon" aria-hidden="true"></i> Questions existantes (<?= count($questions); ?>)</h2>
            <?php if ($questions): ?>
                <ol class="quiz-question-list">
                    <?php foreach ($questions as $question): ?>
                        <li><strong><?= e($question['question_text']); ?></strong><span class="badge"><?= $question['type'] === 'true_false' ? 'Vrai / Faux' : 'Choix multiple'; ?></span></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="quiz-empty">Aucune question pour le moment.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer class="site-footer">© 2026 E-Learning</footer>
    <script src="../js/theme.js" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const typeSelect = document.getElementById('type');
        const answerRows = document.querySelectorAll('[data-answer-index]');
        function updateAnswerFields() {
            answerRows.forEach(function (row) {
                row.hidden = typeSelect.value === 'true_false' && Number(row.dataset.answerIndex) > 1;
            });
        }
        typeSelect.addEventListener('change', updateAnswerFields);
        updateAnswerFields();
    });
    </script>
</body>
</html>
