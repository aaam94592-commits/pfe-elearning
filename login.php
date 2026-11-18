<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'student':
            header('Location: dashboard_student.php');
            exit;
        case 'teacher':
            header('Location: dashboard_teacher.php');
            exit;
        case 'admin':
            header('Location: dashboard_admin.php');
            exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email !== '' && $password !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            session_regenerate_id(true);

            switch ($user['role']) {
                case 'student':
                    header('Location: dashboard_student.php');
                    exit;
                case 'teacher':
                    header('Location: dashboard_teacher.php');
                    exit;
                case 'admin':
                    header('Location: dashboard_admin.php');
                    exit;
                default:
                    $error = 'Rôle inconnu.';
            }
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header container">
        <a class="site-brand" href="index.php" aria-label="Retour à l’accueil">
            <div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
            <div class="site-title">E-Learning</div>
        </a>
        <div class="nav-actions">
            <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre" aria-label="Basculer le mode sombre"><i class="fa fa-moon" aria-hidden="true"></i></button>
        </div>
    </header>

    <main class="auth-shell">
        <div class="auth-layout">
            <div class="auth-visual login-visual" aria-label="Classe avec des étudiants et leur enseignante">
                <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=1400&q=85" data-fallback="assets/classroom-fallback.svg" alt="Une enseignante accompagne ses élèves en classe">
                <div class="auth-visual-caption">
                    <span class="eyebrow">L’apprentissage vous attend</span>
                    <h2>Chaque nouvelle idée ouvre une porte.</h2>
                    <p>Retrouvez vos cours, votre progression et votre communauté d’apprentissage.</p>
                </div>
            </div>

            <div class="auth-card">
                <div class="auth-brand">
                    <div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
                    <h1>E-Learning</h1>
                </div>

                <div class="auth-panel">
                    <?php if (!empty($error)): ?>
                        <p class="error"><?= e($error); ?></p>
                    <?php endif; ?>

                    <form class="auth-form" method="POST" action="">
                        <?= csrf_field(); ?>

                        <div class="form-field">
                            <label for="email"><i class="fa fa-envelope" aria-hidden="true"></i> Email</label>
                            <input class="form-control" id="email" type="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" required>
                        </div>

                        <div class="form-field">
                            <label for="password"><i class="fa fa-lock" aria-hidden="true"></i> Mot de passe</label>
                            <input class="form-control" id="password" type="password" name="password" required>
                        </div>

                        <button class="btn btn-primary" type="submit"><i class="fa fa-right-to-bracket" aria-hidden="true"></i> Se connecter</button>
                    </form>
                </div>

                <div class="link-row">Pas encore inscrit ? <a href="register.php">Créer un compte</a></div>
            </div>
        </div>
    </main>

    <footer class="site-footer">© 2026 E-Learning • Designed with ❤️</footer>

    <script src="js/theme.js" defer></script>
</body>
</html>
