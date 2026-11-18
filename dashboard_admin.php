<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalTeachers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalCourses = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$recentUsers = $pdo->query('SELECT id, name, email, role FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - E-Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
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
                <span class="user-pill"><i class="fa fa-user-shield ui-icon" aria-hidden="true"></i> Admin</span>
                <a class="btn btn-light" href="logout.php"><i class="fa fa-right-from-bracket ui-icon" aria-hidden="true"></i> Déconnexion</a>
            </div>
        </header>

        <div class="dashboard-grid">
            <aside class="sidebar">
                <div class="sidebar-brand"><i class="fa fa-shield-halved" aria-hidden="true"></i> Admin</div>
                <nav class="nav-group">
                    <a class="nav-link active" href="dashboard_admin.php"><i class="fa fa-chart-line"></i> Tableau de bord</a>
                    <a class="nav-link" href="admin/manage_users.php"><i class="fa fa-users"></i> Utilisateurs</a>
                    <a class="nav-link" href="admin/manage_courses.php"><i class="fa fa-book"></i> Cours</a>
                </nav>
            </aside>

            <main class="content">
                <section class="hero">
                    <h2>Bienvenue, <?= e($_SESSION['user_name']); ?></h2>
                    <div class="subtext">Vue d’ensemble de la plateforme E-Learning.</div>
                </section>

                <section class="stats-grid">
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-users stat-label-icon" aria-hidden="true"></i> Utilisateurs</span>
                        <span class="value"><?= $totalUsers; ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-user-graduate stat-label-icon" aria-hidden="true"></i> Étudiants</span>
                        <span class="value"><?= $totalStudents; ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-chalkboard-user stat-label-icon" aria-hidden="true"></i> Enseignants</span>
                        <span class="value"><?= $totalTeachers; ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="label"><i class="fa fa-book-open stat-label-icon" aria-hidden="true"></i> Cours</span>
                        <span class="value"><?= $totalCourses; ?></span>
                    </div>
                </section>

                <section class="panel">
                    <h3><i class="fa fa-bolt section-icon" aria-hidden="true"></i> Actions rapides</h3>
                    <div class="list-grid">
                        <div class="course-card">
                            <h4><i class="fa fa-users card-title-icon" aria-hidden="true"></i> Gestion des utilisateurs</h4>
                            <p>Consultez, modifiez et gérez les comptes des étudiants, enseignants et admins.</p>
                            <div class="course-meta">
                                <span class="badge">Utilisateurs</span>
                                <a class="btn btn-secondary" href="admin/manage_users.php"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i> Ouvrir</a>
                            </div>
                        </div>

                        <div class="course-card">
                            <h4><i class="fa fa-book-open card-title-icon" aria-hidden="true"></i> Gestion des cours</h4>
                            <p>Publiez et organisez les supports pédagogiques, quiz et parcours de formation.</p>
                            <div class="course-meta">
                                <span class="badge">Cours</span>
                                <a class="btn btn-secondary" href="admin/manage_courses.php"><i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i> Ouvrir</a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <h3><i class="fa fa-clock-rotate-left section-icon" aria-hidden="true"></i> Utilisateurs récents</h3>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fa fa-user" aria-hidden="true"></i> Nom</th>
                                    <th><i class="fa fa-envelope" aria-hidden="true"></i> Email</th>
                                    <th><i class="fa fa-id-badge" aria-hidden="true"></i> Rôle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?= e($user['name']); ?></td>
                                        <td><?= e($user['email']); ?></td>
                                        <td><span class="inline-pill"><?= e($user['role']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <footer class="site-footer">© 2026 E-Learning • Designed with ❤️</footer>
    <script src="js/theme.js" defer></script>
</body>
</html>
