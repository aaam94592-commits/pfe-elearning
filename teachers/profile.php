<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT u.name, u.email, t.specialite
    FROM users u
    LEFT JOIN teachers t ON t.user_id = u.id
    WHERE u.id = :user_id LIMIT 1');
$stmt->execute(['user_id' => $userId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    $teacher = ['name' => $_SESSION['user_name'] ?? 'Enseignant', 'email' => '', 'specialite' => '-'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil enseignant - E-Learning</title>
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
            <section class="profile-card">
                <div class="profile-cover" style="background: linear-gradient(135deg, rgba(79,70,229,0.7), rgba(14,165,233,0.7)), url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat;"></div>
                <div class="profile-body">
                    <div class="profile-avatar" style="background: linear-gradient(135deg, #22c55e, #14b8a6);"><?= strtoupper(substr($teacher['name'], 0, 1)); ?></div>
                    <h2 style="margin: 18px 0 4px;"><i class="fa fa-chalkboard-user card-title-icon" aria-hidden="true"></i> <?= e($teacher['name']); ?></h2>
                    <p class="muted" style="margin: 0;"><i class="fa fa-graduation-cap" aria-hidden="true"></i> Enseignant E-Learning</p>

                    <div class="profile-info">
                        <div class="info-tile">
                            <span><i class="fa fa-envelope" aria-hidden="true"></i> Email</span>
                            <strong><?= e($teacher['email'] ?: 'Non renseigné'); ?></strong>
                        </div>
                        <div class="info-tile">
                            <span><i class="fa fa-lightbulb" aria-hidden="true"></i> Spécialité</span>
                            <strong><?= e($teacher['specialite'] ?: '-'); ?></strong>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="../js/theme.js" defer></script>
</body>
</html>
