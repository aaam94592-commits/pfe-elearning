<?php

const LEARNING_RESOURCE_MAX_BYTES = 52428800;
const LEARNING_RESOURCE_MAX_FILES = 5;

function learning_resource_extensions(): array
{
    return [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
        'mp3' => ['audio/mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'doc' => ['application/msword', 'application/vnd.ms-word'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];
}

function learning_resource_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        $number *= 1024;
        $unit = 'm';
    }
    if ($unit === 'm') {
        $number *= 1024;
        $unit = 'k';
    }
    if ($unit === 'k') {
        $number *= 1024;
    }

    return (int) $number;
}

function learning_resource_max_upload_bytes(): int
{
    $postMaximum = learning_resource_ini_bytes((string) ini_get('post_max_size'));
    $postFileMaximum = $postMaximum > 1048576 ? $postMaximum - 1048576 : $postMaximum;
    $limits = [
        LEARNING_RESOURCE_MAX_BYTES,
        learning_resource_ini_bytes((string) ini_get('upload_max_filesize')),
        $postFileMaximum,
    ];
    $limits = array_filter($limits, function ($limit) {
        return $limit > 0;
    });

    return $limits ? min($limits) : LEARNING_RESOURCE_MAX_BYTES;
}

function learning_resource_request_file_limit(): int
{
    $postMaximum = learning_resource_ini_bytes((string) ini_get('post_max_size'));
    if ($postMaximum === 0) {
        return LEARNING_RESOURCE_MAX_BYTES * LEARNING_RESOURCE_MAX_FILES;
    }

    return $postMaximum > 1048576 ? $postMaximum - 1048576 : $postMaximum;
}

function learning_resource_storage_directory(): string
{
    $configured = getenv('LEARNING_UPLOAD_DIR');
    if (is_string($configured) && trim($configured) !== '') {
        $directory = trim($configured);
    } else {
        $projectRoot = realpath(dirname(__DIR__));
        $documentRootValue = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $documentRoot = $documentRootValue !== '' ? realpath($documentRootValue) : false;
        if ($documentRoot === false && $projectRoot !== false) {
            $candidate = $projectRoot;
            while ($candidate !== dirname($candidate)) {
                if (in_array(strtolower(basename($candidate)), ['htdocs', 'www', 'public', 'public_html'], true)) {
                    $documentRoot = $candidate;
                    break;
                }
                $candidate = dirname($candidate);
            }
        }
        if ($projectRoot !== false && $documentRoot !== false
            && ($projectRoot === $documentRoot || strpos($projectRoot, $documentRoot . DIRECTORY_SEPARATOR) === 0)) {
            $directory = dirname($documentRoot) . DIRECTORY_SEPARATOR . basename($projectRoot) . '_private_uploads';
        } else {
            $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pfe_elearning_private_uploads';
        }
    }

    if (is_link($directory)) {
        throw new RuntimeException('Le dossier de stockage des fichiers ne peut pas être un lien symbolique.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de créer le dossier privé de stockage des fichiers.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Le dossier de stockage des fichiers n’est pas accessible en écriture.');
    }

    return $directory;
}

function learning_resource_original_name(string $name, string $extension): string
{
    $name = str_replace('\\', '/', $name);
    $name = basename($name);
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'UTF-8//IGNORE', $name);
        if ($converted !== false) {
            $name = $converted;
        }
    }
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
    if (function_exists('mb_strcut')) {
        $name = mb_strcut($name, 0, 240, 'UTF-8');
    } else {
        $name = substr($name, 0, 240);
    }

    return trim($name) !== '' ? $name : 'fichier.' . $extension;
}

function validate_learning_resource_uploads(array $files): array
{
    if (!isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])
        || !is_array($files['name']) || !is_array($files['tmp_name'])
        || !is_array($files['error']) || !is_array($files['size'])) {
        throw new RuntimeException('Les fichiers envoyés sont invalides.');
    }

    $fileCount = count($files['name']);
    if ($fileCount > LEARNING_RESOURCE_MAX_FILES) {
        throw new RuntimeException('Vous pouvez ajouter jusqu’à ' . LEARNING_RESOURCE_MAX_FILES . ' fichiers à la fois.');
    }

    $allowed = learning_resource_extensions();
    $maximumBytes = learning_resource_max_upload_bytes();
    $totalBytes = 0;
    $validated = [];

    foreach ($files['name'] as $index => $submittedName) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                ? 'Un fichier dépasse la limite autorisée par le serveur.'
                : 'Un fichier n’a pas pu être envoyé. Vérifiez la configuration d’envoi du serveur.';
            throw new RuntimeException($message);
        }

        $temporaryPath = $files['tmp_name'][$index] ?? '';
        $size = (int) ($files['size'][$index] ?? 0);
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath) || $size <= 0) {
            throw new RuntimeException('Un fichier envoyé est vide ou invalide.');
        }
        if ($size > $maximumBytes) {
            $maximumMegabytes = max(1, (int) floor($maximumBytes / 1048576));
            throw new RuntimeException('Chaque fichier doit respecter la limite du serveur (' . $maximumMegabytes . ' Mo maximum).');
        }
        $totalBytes += $size;
        if ($totalBytes > learning_resource_request_file_limit()) {
            throw new RuntimeException('La taille totale des fichiers dépasse la limite de réception du serveur.');
        }

        $originalName = is_string($submittedName) ? $submittedName : '';
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset($allowed[$extension])) {
            throw new RuntimeException('Format non accepté. Utilisez une image, une vidéo ou un audio courant, un PDF, un document Office, un fichier TXT ou CSV.');
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($temporaryPath);
        if (!is_string($mimeType) || !in_array($mimeType, $allowed[$extension], true)) {
            throw new RuntimeException('Le contenu du fichier ne correspond pas à son extension.');
        }

        $validated[] = [
            'temporary_path' => $temporaryPath,
            'original_name' => learning_resource_original_name($originalName, $extension),
            'extension' => $extension,
            'mime_type' => $mimeType,
            'file_size' => $size,
        ];
    }

    return $validated;
}

function save_learning_resources(PDO $pdo, array $files, string $parentType, int $parentId, int $uploadedBy): int
{
    if (!in_array($parentType, ['course', 'quiz'], true) || $parentId <= 0 || $uploadedBy <= 0) {
        throw new InvalidArgumentException('La destination des fichiers est invalide.');
    }

    $validatedFiles = validate_learning_resource_uploads($files);
    if (!$validatedFiles) {
        return 0;
    }

    $directory = learning_resource_storage_directory();
    $column = $parentType . '_id';
    $insert = $pdo->prepare('INSERT INTO learning_resources
        (' . $column . ', uploaded_by, original_name, stored_name, mime_type, file_size)
        VALUES (:parent_id, :uploaded_by, :original_name, :stored_name, :mime_type, :file_size)');
    $storedPaths = [];

    try {
        $pdo->beginTransaction();
        foreach ($validatedFiles as $file) {
            do {
                $storedName = bin2hex(random_bytes(24)) . '.' . $file['extension'];
                $destination = $directory . DIRECTORY_SEPARATOR . $storedName;
            } while (file_exists($destination));

            if (!move_uploaded_file($file['temporary_path'], $destination)) {
                throw new RuntimeException('Impossible d’enregistrer un des fichiers envoyés.');
            }
            $storedPaths[] = $destination;

            $insert->execute([
                'parent_id' => $parentId,
                'uploaded_by' => $uploadedBy,
                'original_name' => $file['original_name'],
                'stored_name' => $storedName,
                'mime_type' => $file['mime_type'],
                'file_size' => $file['file_size'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($storedPaths as $path) {
            if (is_file($path) && !unlink($path)) {
                error_log('Unable to remove a rolled-back learning resource: ' . $path);
            }
        }
        throw $exception;
    }

    return count($validatedFiles);
}

function delete_learning_resource(PDO $pdo, int $resourceId): bool
{
    $query = $pdo->prepare('SELECT stored_name FROM learning_resources WHERE id = :id LIMIT 1');
    $query->execute(['id' => $resourceId]);
    $storedName = $query->fetchColumn();
    if (!is_string($storedName)) {
        return false;
    }

    $path = preg_match('/\A[a-f0-9]{48}\.[a-z0-9]{2,5}\z/', $storedName)
        ? learning_resource_storage_directory() . DIRECTORY_SEPARATOR . $storedName
        : null;
    $delete = $pdo->prepare('DELETE FROM learning_resources WHERE id = :id');
    $delete->execute(['id' => $resourceId]);

    if ($path !== null && is_file($path) && !unlink($path)) {
        error_log('Unable to remove deleted learning resource file: ' . $path);
        return false;
    }

    return true;
}

function learning_resource_icon(string $mimeType): string
{
    if (strpos($mimeType, 'image/') === 0) {
        return 'fa-image';
    }
    if (strpos($mimeType, 'video/') === 0) {
        return 'fa-video';
    }
    if (strpos($mimeType, 'audio/') === 0) {
        return 'fa-headphones';
    }
    if ($mimeType === 'application/pdf') {
        return 'fa-file-pdf';
    }

    return 'fa-file-lines';
}

function learning_resource_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' Ko';
    }

    return $bytes . ' o';
}
