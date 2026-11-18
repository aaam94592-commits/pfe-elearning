<?php
function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isTeacher() {
    return isLoggedIn() && $_SESSION['role'] === 'teacher';
}

function isStudent() {
    return isLoggedIn() && $_SESSION['role'] === 'student';
}

// HTML escape helper
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function course_image_url($title) {
    $title = function_exists('mb_strtolower')
        ? mb_strtolower((string) $title, 'UTF-8')
        : strtolower((string) $title);

    $images = [
        ['keywords' => ['web', 'php', 'programm', 'code', 'informatique', 'ia'], 'url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=900&q=80'],
        ['keywords' => ['gestion', 'marketing', 'business', 'entrepreneur'], 'url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=80'],
        ['keywords' => ['design', 'créa', 'crea', 'art', 'photo'], 'url' => 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&w=900&q=80'],
        ['keywords' => ['science', 'math', 'physique', 'chimie'], 'url' => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=900&q=80'],
        ['keywords' => ['langue', 'anglais', 'français', 'francais', 'communication'], 'url' => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=900&q=80'],
    ];

    foreach ($images as $image) {
        foreach ($image['keywords'] as $keyword) {
            if (strpos($title, $keyword) !== false) {
                return $image['url'];
            }
        }
    }

    return 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=900&q=80';
}

// Simple CSRF helpers
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="'.csrf_token().'">';
}

function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die('Invalid CSRF token');
        }
    }
}
?> 
