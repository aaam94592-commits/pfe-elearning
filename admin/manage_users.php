<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (isset($_POST['delete_user'])) {
        $userId = (int) ($_POST['delete_user'] ?? 0);
        if ($userId > 0) {
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
            $message = 'Utilisateur supprimé avec succès.';
        }
    } elseif (isset($_POST['add_user'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? '';

        if ($name !== '' && $email !== '' && $password !== '' && in_array($role, ['student', 'teacher', 'admin'], true)) {
            $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
            $exists->execute(['email' => $email]);
            if (!$exists->fetch()) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)');
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => $hash,
                    'role' => $role,
                ]);

                $userId = (int) $pdo->lastInsertId();

                if ($role === 'student') {
                    $pdo->prepare('INSERT INTO students (user_id) VALUES (:user_id)')->execute(['user_id' => $userId]);
                } elseif ($role === 'teacher') {
                    $pdo->prepare('INSERT INTO teachers (user_id) VALUES (:user_id)')->execute(['user_id' => $userId]);
                }

                $message = 'Utilisateur ajouté avec succès.';
            } else {
                $message = 'Cet email est déjà utilisé.';
            }
        } else {
            $message = 'Veuillez remplir tous les champs obligatoires.';
        }
    }
}

$users = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - E-Learning</title>
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
                <h3><i class="fa fa-user-plus section-icon" aria-hidden="true"></i> Ajouter un utilisateur</h3>
                <?php if ($message !== ''): ?>
                    <p class="success"><?= e($message); ?></p>
                <?php endif; ?>

                <form method="POST" class="auth-form" style="max-width: 900px; margin-top: 18px;">
                    <?= csrf_field(); ?>
                    <div class="form-row">
                        <div class="form-field">
                            <label><i class="fa fa-user" aria-hidden="true"></i> Nom</label>
                            <input class="form-control" type="text" name="name" required>
                        </div>
                        <div class="form-field">
                            <label><i class="fa fa-envelope" aria-hidden="true"></i> Email</label>
                            <input class="form-control" type="email" name="email" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label><i class="fa fa-lock" aria-hidden="true"></i> Mot de passe</label>
                            <input class="form-control" type="password" name="password" required>
                        </div>
                        <div class="form-field">
                            <label><i class="fa fa-id-badge" aria-hidden="true"></i> Rôle</label>
                            <select class="form-select" name="role" required>
                                <option value="student">Étudiant</option>
                                <option value="teacher">Enseignant</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" name="add_user" value="1"><i class="fa fa-user-plus" aria-hidden="true"></i> Ajouter</button>
                </form>
            </section>

            <section class="panel" style="margin-top: 22px;">
                <h3><i class="fa fa-users section-icon" aria-hidden="true"></i> Liste des utilisateurs</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fa fa-user" aria-hidden="true"></i> Nom</th>
                                <th><i class="fa fa-envelope" aria-hidden="true"></i> Email</th>
                                <th><i class="fa fa-id-badge" aria-hidden="true"></i> Rôle</th>
                                <th><i class="fa fa-calendar-days" aria-hidden="true"></i> Créé le</th>
                                <th><i class="fa fa-gear" aria-hidden="true"></i> Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= e($user['name']); ?></td>
                                    <td><?= e($user['email']); ?></td>
                                    <td><span class="inline-pill"><?= e($user['role']); ?></span></td>
                                    <td><?= e($user['created_at']); ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                            <?= csrf_field(); ?>
                                            <button class="btn btn-secondary" type="submit" name="delete_user" value="<?= (int) $user['id']; ?>"><i class="fa fa-trash" aria-hidden="true"></i> Supprimer</button>
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
