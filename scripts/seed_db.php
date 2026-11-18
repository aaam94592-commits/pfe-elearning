<?php
// scripts/seed_db.php
// Run via browser or CLI to create schema and seed sample data.
require_once __DIR__ . '/../includes/db.php';

function execSql($pdo, $sql) {
    $pdo->exec($sql);
}

try {
    // Users
    execSql($pdo, "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('student','teacher','admin') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Students, Teachers
    execSql($pdo, "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date_naissance DATE NULL,
        filiere VARCHAR(100) NULL,
        niveau VARCHAR(50) NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    execSql($pdo, "CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        specialite VARCHAR(150) NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Courses, Quizzes
    execSql($pdo, "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NULL,
        titre VARCHAR(255) NOT NULL,
        description TEXT NULL,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    execSql($pdo, "CREATE TABLE IF NOT EXISTS course_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_course_enrollment_student_course (student_id, course_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $enrollmentIndex = $pdo->query("SHOW INDEX FROM course_enrollments WHERE Key_name = 'uq_course_enrollment_student_course'")->fetch();
    if (!$enrollmentIndex) {
        $duplicateEnrollments = (int) $pdo->query('SELECT COUNT(*) FROM (
            SELECT student_id, course_id
            FROM course_enrollments
            GROUP BY student_id, course_id
            HAVING COUNT(*) > 1
        ) duplicate_rows')->fetchColumn();

        if ($duplicateEnrollments > 0) {
            throw new RuntimeException('Duplicate course enrollments must be resolved before adding the unique enrollment index.');
        }

        execSql($pdo, 'ALTER TABLE course_enrollments
            ADD UNIQUE KEY uq_course_enrollment_student_course (student_id, course_id)');
    }

    execSql($pdo, "CREATE TABLE IF NOT EXISTS quizzes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        titre VARCHAR(255) NOT NULL,
        description TEXT NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    execSql($pdo, "CREATE TABLE IF NOT EXISTS learning_resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NULL,
        quiz_id INT NULL,
        uploaded_by INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL UNIQUE,
        mime_type VARCHAR(127) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_learning_resources_course (course_id),
        INDEX idx_learning_resources_quiz (quiz_id),
        CONSTRAINT chk_learning_resources_parent CHECK (
            (course_id IS NOT NULL AND quiz_id IS NULL) OR
            (course_id IS NULL AND quiz_id IS NOT NULL)
        ),
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Questions & Answers
    execSql($pdo, "CREATE TABLE IF NOT EXISTS questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        question_text TEXT NOT NULL,
        type VARCHAR(20) NOT NULL,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    execSql($pdo, "CREATE TABLE IF NOT EXISTS answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        answer_text TEXT NOT NULL,
        is_correct TINYINT(1) DEFAULT 0,
        FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Results
    execSql($pdo, "CREATE TABLE IF NOT EXISTS quiz_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        quiz_id INT NOT NULL,
        score FLOAT DEFAULT 0,
        date_passed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY(student_id, quiz_id),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed sample users
    $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute(['email' => 'admin@example.com']);
    if (!$check->fetch()) {
        $pwd = password_hash('Password123!', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (:name,:email,:password,:role)");
        $stmt->execute(['name'=>'Admin','email'=>'admin@example.com','password'=>$pwd,'role'=>'admin']);
        echo "Inserted admin@example.com / Password123!\n";
    } else echo "Admin already exists\n";

    $check->execute(['email' => 'student@example.com']);
    if (!$check->fetch()) {
        $pwd = password_hash('studentpass', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (:name,:email,:password,:role)");
        $stmt->execute(['name'=>'Student','email'=>'student@example.com','password'=>$pwd,'role'=>'student']);
        $student_user_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO students (user_id,filiere,niveau) VALUES (:uid,:f,:n)")->execute(['uid'=>$student_user_id,'f'=>'Informatique','n'=>'Licence 2']);
        echo "Inserted student@example.com / studentpass\n";
    } else echo "Student already exists\n";

    // Seed one teacher, course, quiz, question
    $check->execute(['email' => 'teacher@example.com']);
    if (!$check->fetch()) {
        $pwd = password_hash('teacherpass', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (:name,:email,:password,:role)");
        $stmt->execute(['name'=>'Teacher','email'=>'teacher@example.com','password'=>$pwd,'role'=>'teacher']);
        $teacher_user_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO teachers (user_id,specialite) VALUES (:uid,:s)")->execute(['uid'=>$teacher_user_id,'s'=>'Maths']);
        // create course
        $teacherRec = $pdo->prepare("SELECT id FROM teachers WHERE user_id = :uid"); $teacherRec->execute(['uid'=>$teacher_user_id]); $t = $teacherRec->fetch();
        $pdo->prepare("INSERT INTO courses (teacher_id,titre,description) VALUES (:tid,:t,:d)")->execute(['tid'=>$t['id'],'t'=>'Introduction to PHP','d'=>'Cours de base']);
        $course_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO quizzes (course_id,titre,description) VALUES (:cid,:t,:d)")->execute(['cid'=>$course_id,'t'=>'Quiz 1','d'=>'Test de base']);
        $quiz_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO questions (quiz_id,question_text,type) VALUES (:qid,:qt,:tp)")->execute(['qid'=>$quiz_id,'qt'=>'Quelle est la balise PHP?','tp'=>'qcm']);
        $question_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO answers (question_id,answer_text,is_correct) VALUES (:qid,:a, :c)")
            ->execute(['qid'=>$question_id,'a'=>'<?php ?>', 'c'=>1]);
        $pdo->prepare("INSERT INTO answers (question_id,answer_text,is_correct) VALUES (:qid,:a, :c)")
            ->execute(['qid'=>$question_id,'a'=>'<script>', 'c'=>0]);
        echo "Inserted teacher, course, quiz, and one question\n";
    } else echo "Teacher already exists\n";

    echo "Done. You can now login with admin@example.com (Password123!) or student@example.com (studentpass)\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>