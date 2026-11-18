<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/learning_resources.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Connexion requise.');
}

$resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$resourceId || $resourceId <= 0) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$resourceQuery = $pdo->prepare('SELECT r.id, r.course_id, r.quiz_id, r.original_name, r.stored_name, r.mime_type, r.file_size,
        course_teacher.user_id AS course_teacher_user_id,
        quiz_course.id AS quiz_course_id,
        quiz_teacher.user_id AS quiz_teacher_user_id
    FROM learning_resources r
    LEFT JOIN courses course ON course.id = r.course_id
    LEFT JOIN teachers course_teacher ON course_teacher.id = course.teacher_id
    LEFT JOIN quizzes q ON q.id = r.quiz_id
    LEFT JOIN courses quiz_course ON quiz_course.id = q.course_id
    LEFT JOIN teachers quiz_teacher ON quiz_teacher.id = quiz_course.teacher_id
    WHERE r.id = :resource_id
    LIMIT 1');
$resourceQuery->execute(['resource_id' => $resourceId]);
$resource = $resourceQuery->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$userId = (int) $_SESSION['user_id'];
$authorized = ($_SESSION['role'] ?? '') === 'admin';

if (!$authorized && ($_SESSION['role'] ?? '') === 'teacher') {
    $authorized = ((int) $resource['course_teacher_user_id'] === $userId)
        || ((int) $resource['quiz_teacher_user_id'] === $userId);
}

if (!$authorized && ($_SESSION['role'] ?? '') === 'student') {
    $courseId = $resource['course_id'] !== null
        ? (int) $resource['course_id']
        : (int) $resource['quiz_course_id'];
    $enrollment = $pdo->prepare('SELECT 1
        FROM course_enrollments ce
        JOIN students s ON s.id = ce.student_id
        WHERE ce.course_id = :course_id AND s.user_id = :user_id
        LIMIT 1');
    $enrollment->execute(['course_id' => $courseId, 'user_id' => $userId]);
    $authorized = (bool) $enrollment->fetchColumn();
}

if (!$authorized) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

session_write_close();

if (!preg_match('/\A[a-f0-9]{48}\.[a-z0-9]{2,5}\z/', (string) $resource['stored_name'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$filePath = learning_resource_storage_directory() . DIRECTORY_SEPARATOR . $resource['stored_name'];
if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$size = (int) $resource['file_size'];
$start = 0;
$end = $size - 1;
$status = 200;
$range = $_SERVER['HTTP_RANGE'] ?? '';
$supportsRange = strpos((string) $resource['mime_type'], 'video/') === 0
    || strpos((string) $resource['mime_type'], 'audio/') === 0;

if ($supportsRange && $range !== '') {
    if (!preg_match('/\Abytes=(\d*)-(\d*)\z/', $range, $matches) || ($matches[1] === '' && $matches[2] === '')) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '') {
        $suffixLength = (int) $matches[2];
        $start = max(0, $size - $suffixLength);
    } else {
        $start = (int) $matches[1];
        if ($matches[2] !== '') {
            $end = min($end, (int) $matches[2]);
        }
    }

    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    http_response_code(404);
    exit('Fichier introuvable.');
}
if ($start > 0 && fseek($handle, $start) !== 0) {
    fclose($handle);
    http_response_code(500);
    exit('Impossible de lire le fichier.');
}

http_response_code($status);
header('Content-Type: ' . $resource['mime_type']);
header('Content-Length: ' . ($end - $start + 1));
header('Content-Disposition: ' . (strpos($resource['mime_type'], 'image/') === 0 || strpos($resource['mime_type'], 'video/') === 0 || strpos($resource['mime_type'], 'audio/') === 0 ? 'inline' : 'attachment')
    . "; filename*=UTF-8''" . rawurlencode($resource['original_name']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if ($supportsRange) {
    header('Accept-Ranges: bytes');
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
}

$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($handle);
