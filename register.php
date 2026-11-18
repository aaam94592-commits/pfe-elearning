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

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';

    $date_naissance = $_POST['date_naissance'] ?? null;
    $filiere = isset($_POST['filiere']) ? trim($_POST['filiere']) : null;
    $niveau = isset($_POST['niveau']) ? trim($_POST['niveau']) : null;
    $specialite = isset($_POST['specialite']) ? trim($_POST['specialite']) : null;

    if ($name !== '' && $email !== '' && $password !== '' && $role !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)');
            $insert->execute([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'role' => $role,
            ]);

            $userId = (int) $pdo->lastInsertId();

            if ($role === 'student') {
                $pdo->prepare('INSERT INTO students (user_id, date_naissance, filiere, niveau) VALUES (:user_id, :date_naissance, :filiere, :niveau)')
                    ->execute([
                        'user_id' => $userId,
                        'date_naissance' => $date_naissance,
                        'filiere' => $filiere,
                        'niveau' => $niveau,
                    ]);
            } elseif ($role === 'teacher') {
                $pdo->prepare('INSERT INTO teachers (user_id, specialite) VALUES (:user_id, :specialite)')
                    ->execute([
                        'user_id' => $userId,
                        'specialite' => $specialite,
                    ]);
            }

            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['role'] = $role;
            session_regenerate_id(true);

            switch ($role) {
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
                    $error = 'Rôle invalide.';
            }
        }
    } else {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        function toggleFields() {
            const role = document.getElementById('role').value;
            document.getElementById('studentFields').style.display = (role === 'student') ? 'block' : 'none';
            document.getElementById('teacherFields').style.display = (role === 'teacher') ? 'block' : 'none';
        }
    </script>
</head>
<body>
    <header class="site-header container">
        <a class="site-brand" href="index.php" aria-label="Retour à l’accueil">
            <div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
            <div class="site-title">E-Learning</div>
        </a>
        <div class="nav-actions">
            <button id="darkToggle" class="btn btn-outline" type="button" title="Basculer le mode sombre" aria-label="Basculer le mode sombre"><i class="fa fa-moon" aria-hidden="true"></i></button>
            <a class="btn btn-outline" href="login.php"><i class="fa fa-right-to-bracket" aria-hidden="true"></i> Connexion</a>
        </div>
    </header>

    <main class="auth-shell">
        <div class="auth-layout auth-layout-register">
            <div class="auth-visual register-visual" aria-label="Étudiants et enseignante dans une salle de classe">
                <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=1200&q=85" data-fallback="assets/classroom-fallback.svg" alt="Étudiants suivant un cours avec leur enseignante" loading="lazy">
                <div class="auth-visual-caption">
                    <span class="eyebrow">Un premier pas vers la suite</span>
                    <h2>Faites de votre curiosité une force.</h2>
                    <p>Rejoignez un espace qui vous aide à apprendre, enseigner et progresser.</p>
                </div>
            </div>
            <div class="auth-card registration-card">
                <div class="auth-brand">
                    <div class="logo"><i class="fa fa-graduation-cap" aria-hidden="true"></i></div>
                    <h1>Créer un compte</h1>
                </div>

                <div class="auth-panel">
                        <?php if (!empty($error)): ?>
                            <p class="error"><?= e($error); ?></p>
                        <?php endif; ?>

                        <form class="auth-form" method="POST" action="">
                            <?= csrf_field(); ?>

                            <div class="form-row">
                                <div class="form-field">
                                    <label for="name"><i class="fa fa-user" aria-hidden="true"></i> Nom complet</label>
                                    <input class="form-control" id="name" type="text" name="name" value="<?= e($_POST['name'] ?? ''); ?>" required>
                                </div>

                                <div class="form-field">
                                    <label for="role"><i class="fa fa-id-badge" aria-hidden="true"></i> Rôle</label>
                                    <select class="form-select" id="role" name="role" onchange="toggleFields()" required>
                                        <option value="">-- Choisir --</option>
                                        <option value="student" <?= (($_POST['role'] ?? '') === 'student') ? 'selected' : ''; ?>>Étudiant</option>
                                        <option value="teacher" <?= (($_POST['role'] ?? '') === 'teacher') ? 'selected' : ''; ?>>Enseignant</option>
                                        <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-field">
                                    <label for="email"><i class="fa fa-envelope" aria-hidden="true"></i> Email</label>
                                    <input class="form-control" id="email" type="email" name="email" value="<?= e($_POST['email'] ?? ''); ?>" required>
                                </div>

                                <div class="form-field">
                                    <label for="password"><i class="fa fa-lock" aria-hidden="true"></i> Mot de passe</label>
                                    <input class="form-control" id="password" type="password" name="password" required>
                                </div>
                            </div>

                            <div id="studentFields" style="display: <?= (($_POST['role'] ?? '') === 'student') ? 'block' : 'none'; ?>;">
                                <div class="form-row">
                                    <div class="form-field">
                                        <label for="date_naissance"><i class="fa fa-calendar-days" aria-hidden="true"></i> Date de naissance</label>
                                        <input class="form-control" id="date_naissance" type="date" name="date_naissance" value="<?= e($_POST['date_naissance'] ?? ''); ?>">
                                    </div>
                                    <div class="form-field">
                                        <label for="filiere"><i class="fa fa-book-open" aria-hidden="true"></i> Filière</label>
                                        <input class="form-control" id="filiere" type="text" name="filiere" value="<?= e($_POST['filiere'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-field" style="margin-top: 14px;">
                                    <label for="niveau"><i class="fa fa-layer-group" aria-hidden="true"></i> Niveau</label>
                                    <input class="form-control" id="niveau" type="text" name="niveau" value="<?= e($_POST['niveau'] ?? ''); ?>">
                                </div>
                            </div>

                            <div id="teacherFields" style="display: <?= (($_POST['role'] ?? '') === 'teacher') ? 'block' : 'none'; ?>; margin-top: 14px;">
                                <div class="form-field">
                                    <label for="specialite"><i class="fa fa-lightbulb" aria-hidden="true"></i> Spécialité</label>
                                    <input class="form-control" id="specialite" type="text" name="specialite" value="<?= e($_POST['specialite'] ?? ''); ?>">
                                </div>
                            </div>

                            <button class="btn btn-primary" type="submit"><i class="fa fa-user-plus" aria-hidden="true"></i> S'inscrire</button>
                        </form>
                    </div>

                <div class="link-row">Déjà inscrit ? <a href="login.php">Se connecter</a></div>
            </div>
        </div>
    </main>

    <footer class="site-footer">© 2026 E-Learning • Designed with ❤️</footer>

    <script src="js/theme.js" defer></script>
</body>
</html>
