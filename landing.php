<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'student': header('Location: dashboard_student.php'); exit;
        case 'teacher': header('Location: dashboard_teacher.php'); exit;
        case 'admin': header('Location: dashboard_admin.php'); exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Learning | Plateforme d'apprentissage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="landing-page">
    <header class="landing-hero">
        <div class="landing-shell">
            <nav class="landing-nav">
                <div class="brand-badge">
                    <span class="mini-mark"><i class="fa fa-graduation-cap" aria-hidden="true"></i></span>
                    <span>E-Learning</span>
                </div>
                <div class="landing-links">
                    <a class="btn btn-outline" href="login.php"><i class="fa fa-right-to-bracket" aria-hidden="true"></i> Connexion</a>
                    <a class="btn btn-primary" href="register.php"><i class="fa fa-user-plus" aria-hidden="true"></i> Créer un compte</a>
                </div>
            </nav>

            <div class="hero-layout">
                <div class="hero-copy">
                    <span class="hero-kicker"><i class="fa-solid fa-sparkles"></i> Une nouvelle façon d’apprendre</span>
                    <h1>Le savoir ouvre de nouvelles <em>perspectives.</em></h1>
                    <p>Des cours inspirants, des enseignants passionnés et un espace simple pour faire grandir vos compétences — à votre rythme.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="register.php">Découvrir la plateforme <i class="fa-solid fa-arrow-right"></i></a>
                        <a class="btn btn-outline" href="#learning">Explorer les parcours</a>
                    </div>
                    <div class="hero-stats">
                        <div class="hero-stat"><i class="fa-solid fa-circle-check"></i><span>Apprendre à votre rythme</span></div>
                        <div class="hero-stat"><i class="fa-solid fa-circle-check"></i><span>Progresser avec confiance</span></div>
                    </div>
                </div>

                <div class="hero-card">
                    <img class="hero-image" src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80" data-fallback="assets/classroom-fallback.svg" alt="Étudiants apprenant ensemble">
                    <div class="floating-badge">
                        <span class="floating-badge-icon"><i class="fa-solid fa-chalkboard-user"></i></span>
                        <span class="floating-badge-copy"><strong>Apprendre ensemble</strong><small>Étudiants & enseignants</small></span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <section class="section" id="learning">
            <div class="section-heading">
                <span class="eyebrow">Une plateforme, plusieurs chemins</span>
                <h2>Tout ce qu’il faut pour <em>aller plus loin.</em></h2>
                <p>Un espace pensé pour accompagner chaque étape de l’apprentissage, de la première leçon aux nouveaux objectifs.</p>
            </div>
            <div class="landing-shell feature-grid">
                <article class="feature-card" data-reveal>
                    <div class="feature-icon"><i class="fa fa-user-graduate"></i></div>
                    <h3>Pour les étudiants</h3>
                    <p>Retrouvez vos cours et quiz au même endroit, avancez à votre rythme et gardez un œil sur votre progression.</p>
                    <a class="text-link" href="register.php">Commencer à apprendre <i class="fa-solid fa-arrow-right"></i></a>
                </article>
                <article class="feature-card" data-reveal>
                    <div class="feature-icon"><i class="fa fa-chalkboard-teacher"></i></div>
                    <h3>Pour les enseignants</h3>
                    <p>Créez vos cours, préparez des évaluations et accompagnez vos apprenants depuis un espace dédié.</p>
                    <a class="text-link" href="register.php">Enseigner sur la plateforme <i class="fa-solid fa-arrow-right"></i></a>
                </article>
                <article class="feature-card" data-reveal>
                    <div class="feature-icon"><i class="fa fa-chart-line"></i></div>
                    <h3>Pour l’administration</h3>
                    <p>Une vue claire pour gérer les comptes, organiser les cours et garder la plateforme en mouvement.</p>
                    <a class="text-link" href="login.php">Accéder à mon espace <i class="fa-solid fa-arrow-right"></i></a>
                </article>
            </div>
        </section>

        <section class="section pathways-section">
            <div class="landing-shell">
                <div class="section-heading">
                    <span class="eyebrow">Explorez vos centres d’intérêt</span>
                    <h2>Apprenez des compétences <em>qui comptent.</em></h2>
                    <p>Explorez des domaines variés et construisez votre propre parcours, une étape à la fois.</p>
                </div>
                <div class="course-grid">
                    <article class="course-card-large" data-reveal>
                        <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80" data-fallback="assets/classroom-fallback.svg" alt="Formation web design" loading="lazy">
                        <div class="content">
                            <span class="course-category">Créativité & numérique</span>
                            <h3>Développement web</h3>
                            <p>Imaginez et construisez des expériences web utiles, accessibles et élégantes.</p>
                            <div class="meta">
                                <span class="badge">Créer</span>
                                <a class="course-link" href="register.php" aria-label="Explorer le parcours développement web"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                            </div>
                        </div>
                    </article>
                    <article class="course-card-large" data-reveal>
                        <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=80" data-fallback="assets/classroom-fallback.svg" alt="Formation business" loading="lazy">
                        <div class="content">
                            <span class="course-category">Stratégie & collaboration</span>
                            <h3>Gestion & marketing</h3>
                            <p>Développez des idées, collaborez efficacement et donnez vie à vos projets.</p>
                            <div class="meta">
                                <span class="badge">Développer</span>
                                <a class="course-link" href="register.php" aria-label="Explorer le parcours gestion et marketing"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                            </div>
                        </div>
                    </article>
                    <article class="course-card-large" data-reveal>
                        <img src="https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=1200&q=80" data-fallback="assets/classroom-fallback.svg" alt="Formation informatique" loading="lazy">
                        <div class="content">
                            <span class="course-category">Technologie & innovation</span>
                            <h3>Informatique & IA</h3>
                            <p>Explorez les technologies qui façonnent le monde et les outils de demain.</p>
                            <div class="meta">
                                <span class="badge">Découvrir</span>
                                <a class="course-link" href="register.php" aria-label="Explorer le parcours informatique et IA"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="section story-section">
            <div class="landing-shell">
                <div class="showcase-panel" data-reveal>
                    <div class="showcase-photo">
                        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=1400&q=85" data-fallback="assets/classroom-fallback.svg" alt="Enseignante échangeant avec des élèves dans une salle de classe" loading="lazy">
                    </div>
                    <div class="showcase-copy">
                        <span class="eyebrow">Apprendre ensemble</span>
                        <h3>La technologie rapproche. Les enseignants inspirent.</h3>
                        <p>Quand un bon accompagnement rencontre les bons outils, apprendre devient une expérience vivante. Posez vos questions, pratiquez et transformez votre curiosité en compétences.</p>
                        <ul>
                            <li><i class="fa-solid fa-check"></i> Un espace organisé pour apprendre et enseigner</li>
                            <li><i class="fa-solid fa-check"></i> Des quiz pour pratiquer et vérifier ses acquis</li>
                            <li><i class="fa-solid fa-check"></i> Une progression visible à chaque étape</li>
                        </ul>
                        <a class="btn btn-primary" href="register.php">Trouver mon parcours <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="section learning-scene">
            <div class="landing-shell">
                <div class="scene-copy">
                    <span class="eyebrow">Votre prochain chapitre commence ici</span>
                    <h2>Un bon cours peut changer votre <em>façon de voir le monde.</em></h2>
                    <p>Créez un compte pour découvrir les outils, parcourir vos cours et prendre votre apprentissage en main.</p>
                    <a class="btn btn-light" href="register.php">Créer mon compte <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="scene-photo">
                    <img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=1400&q=85" data-fallback="assets/classroom-fallback.svg" alt="Étudiants participant à un cours en classe" loading="lazy">
                </div>
            </div>
        </section>

    </main>

    <footer class="site-footer">
        <div class="landing-shell footer-inner">
            <a class="brand-badge" href="index.php"><span class="mini-mark"><i class="fa fa-graduation-cap" aria-hidden="true"></i></span><span>E-Learning</span></a>
            <p>Apprendre aujourd’hui. Aller plus loin demain.</p>
            <div><a href="login.php">Connexion</a><a href="register.php">Créer un compte</a></div>
            <small>© <?= date('Y'); ?> E-Learning. Tous droits réservés.</small>
        </div>
    </footer>
    <script src="js/site.js" defer></script>
</body>
</html>
