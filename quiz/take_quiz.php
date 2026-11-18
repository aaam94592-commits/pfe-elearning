<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/learning_resources.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../login.php');
    exit;
}

$quizId = filter_input(INPUT_GET, 'quiz_id', FILTER_VALIDATE_INT);
if (!$quizId) {
    header('Location: ../dashboard_student.php');
    exit;
}

$quizQuery = $pdo->prepare('SELECT q.id, q.titre, q.description
    FROM quizzes q
    JOIN courses c ON c.id = q.course_id
    JOIN course_enrollments ce ON ce.course_id = c.id
    JOIN students s ON s.id = ce.student_id
    WHERE q.id = :quiz_id AND s.user_id = :user_id
    LIMIT 1');
$quizQuery->execute(['quiz_id' => $quizId, 'user_id' => (int) $_SESSION['user_id']]);
$quiz = $quizQuery->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    http_response_code(404);
    $questions = [];
} else {
    $questionQuery = $pdo->prepare('SELECT id, question_text FROM questions WHERE quiz_id = :quiz_id ORDER BY id');
    $questionQuery->execute(['quiz_id' => $quizId]);
    $questions = $questionQuery->fetchAll(PDO::FETCH_ASSOC);
    $resourceQuery = $pdo->prepare('SELECT id, original_name, mime_type, file_size
        FROM learning_resources
        WHERE quiz_id = :quiz_id
        ORDER BY id DESC');
    $resourceQuery->execute(['quiz_id' => $quizId]);
    $quizResources = $resourceQuery->fetchAll(PDO::FETCH_ASSOC);
}

$quizResources = $quizResources ?? [];
$answersByQuestion = [];
if ($questions) {
    $questionIds = array_map('intval', array_column($questions, 'id'));
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $answerQuery = $pdo->prepare("SELECT id, question_id, answer_text FROM answers WHERE question_id IN ($placeholders) ORDER BY question_id, id");
    $answerQuery->execute($questionIds);

    foreach ($answerQuery->fetchAll(PDO::FETCH_ASSOC) as $answer) {
        $answersByQuestion[(int) $answer['question_id']][] = $answer;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $quiz ? e($quiz['titre']) . ' - E-Learning' : 'Quiz introuvable - E-Learning'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="site-header container">
        <a class="site-brand" href="../dashboard_student.php">
            <div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
            <span class="site-title">E-Learning</span>
        </a>
        <div class="nav-actions">
            <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre" aria-label="Basculer le mode sombre"><i class="fa fa-moon"></i></button>
            <a class="btn btn-outline" href="../dashboard_student.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Retour au tableau de bord</a>
        </div>
    </header>

    <main class="quiz-shell">
        <?php if (!$quiz): ?>
            <section class="quiz-hero">
                <h1>Quiz introuvable</h1>
                <p>Ce quiz n’est plus disponible. Retournez à votre espace étudiant pour choisir un autre quiz.</p>
                <a class="btn btn-primary" href="../dashboard_student.php">Retour au tableau de bord</a>
            </section>
        <?php else: ?>
            <section class="quiz-hero">
                <span class="eyebrow"><i class="fa fa-graduation-cap" aria-hidden="true"></i> Évaluation</span>
                <h1><?= e($quiz['titre']); ?></h1>
                <p><?= e($quiz['description'] ?: 'Répondez aux questions puis envoyez vos réponses pour découvrir votre résultat.'); ?></p>
                <?php if ($questions): ?>
                    <div class="quiz-progress" role="progressbar" aria-label="Progression du quiz" aria-valuemin="0" aria-valuemax="<?= count($questions); ?>" aria-valuenow="0">
                        <div id="progressBar" class="quiz-progress-bar"></div>
                    </div>
                    <p id="progressText">0 sur <?= count($questions); ?> question(s) répondue(s)</p>
                <?php endif; ?>
            </section>

            <?php if ($quizResources): ?>
                <section class="quiz-question course-resource-section">
                    <h2><i class="fa fa-folder-open section-icon" aria-hidden="true"></i> Supports du quiz</h2>
                    <ul class="resource-list">
                        <?php foreach ($quizResources as $resource): ?>
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

            <?php if (!$questions): ?>
                <section class="quiz-question quiz-empty">
                    Ce quiz ne contient pas encore de questions.
                </section>
            <?php else: ?>
                <form id="quizForm">
                    <?php foreach ($questions as $index => $question): ?>
                        <?php $answers = $answersByQuestion[(int) $question['id']] ?? []; ?>
                        <fieldset class="quiz-question">
                            <legend class="sr-only">Question <?= $index + 1; ?></legend>
                            <h2><?= $index + 1; ?>. <?= e($question['question_text']); ?></h2>
                            <?php if ($answers): ?>
                                <?php foreach ($answers as $answer): ?>
                                    <label class="quiz-option" for="answer-<?= (int) $answer['id']; ?>">
                                        <input id="answer-<?= (int) $answer['id']; ?>" type="radio" name="answer_<?= (int) $question['id']; ?>" value="<?= (int) $answer['id']; ?>">
                                        <span><?= e($answer['answer_text']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Aucune réponse n’a encore été ajoutée à cette question.</p>
                            <?php endif; ?>
                        </fieldset>
                    <?php endforeach; ?>

                    <div class="quiz-actions">
                        <span class="quiz-timer"><i class="fa fa-stopwatch" aria-hidden="true"></i> Temps restant : <span id="timer" aria-live="polite">10:00</span></span>
                        <button type="button" id="submitQuizBtn" class="btn btn-primary"><i class="fa fa-paper-plane" aria-hidden="true"></i> Envoyer mes réponses</button>
                    </div>
                </form>
                <div id="resultBox" aria-live="polite"></div>

                <script>
                (function () {
                    const questionIds = <?= json_encode(array_map('intval', array_column($questions, 'id'))); ?>;
                    const form = document.getElementById('quizForm');
                    const progress = document.querySelector('.quiz-progress');
                    const progressBar = document.getElementById('progressBar');
                    const progressText = document.getElementById('progressText');
                    const timer = document.getElementById('timer');
                    const submitButton = document.getElementById('submitQuizBtn');
                    const resultBox = document.getElementById('resultBox');
                    let isSubmitting = false;
                    let secondsLeft = 600;

                    function updateProgress() {
                        const answered = questionIds.filter(function (id) {
                            return form.querySelector('input[name="answer_' + id + '"]:checked');
                        }).length;
                        progressBar.style.width = (questionIds.length ? answered / questionIds.length * 100 : 0) + '%';
                        progress.setAttribute('aria-valuenow', answered);
                        progressText.textContent = answered + ' sur ' + questionIds.length + ' question(s) répondue(s)';
                    }

                    form.addEventListener('change', updateProgress);

                    function submitQuiz() {
                        if (isSubmitting) return;
                        isSubmitting = true;
                        submitButton.disabled = true;
                        resultBox.className = 'quiz-result';
                        resultBox.textContent = 'Enregistrement de vos réponses…';

                        const payload = new URLSearchParams();
                        payload.set('quiz_id', <?= json_encode((int) $quizId); ?>);
                        payload.set('csrf_token', <?= json_encode(csrf_token()); ?>);
                        questionIds.forEach(function (id) {
                            const selected = form.querySelector('input[name="answer_' + id + '"]:checked');
                            if (selected) payload.set('answers[' + id + ']', selected.value);
                        });

                        fetch('save_result.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: payload.toString()
                        })
                            .then(function (response) {
                                return response.json().then(function (data) {
                                    if (!response.ok || data.status !== 'success') {
                                        throw new Error(data.message || 'Impossible d’enregistrer le résultat.');
                                    }
                                    return data;
                                });
                            })
                            .then(function (data) {
                                resultBox.textContent = 'Résultat enregistré : ' + data.score + ' % (' + data.correct + ' / ' + data.total + ' bonnes réponses).';
                                form.querySelectorAll('input').forEach(function (input) { input.disabled = true; });
                                submitButton.textContent = 'Quiz terminé';
                            })
                            .catch(function (error) {
                                resultBox.textContent = error.message || 'Une erreur est survenue. Réessayez.';
                                submitButton.disabled = false;
                                isSubmitting = false;
                            });
                    }

                    submitButton.addEventListener('click', submitQuiz);
                    const timerInterval = window.setInterval(function () {
                        secondsLeft -= 1;
                        timer.textContent = String(Math.floor(secondsLeft / 60)).padStart(2, '0') + ':' + String(secondsLeft % 60).padStart(2, '0');
                        if (secondsLeft <= 0) {
                            window.clearInterval(timerInterval);
                            submitQuiz();
                        }
                    }, 1000);
                    updateProgress();
                }());
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="site-footer">© 2026 E-Learning</footer>
    <script src="../js/theme.js" defer></script>
</body>
</html>
