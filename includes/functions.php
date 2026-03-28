<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendNotification($to, $subject, $message) {
    require_once __DIR__ . '/config.php';

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM settings LIMIT 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        if (!$settings) $settings = [];

        $mail = new PHPMailer(true);

        if (!empty($settings['smtp_host'])) {
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_user'];
            $mail->Password   = $settings['smtp_pass'];
            $mail->Port       = $settings['smtp_port'];

            if ($settings['smtp_enc'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($settings['smtp_enc'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
        } else {
            $mail->isMail();
        }

        $mail->setFrom($settings['smtp_user'] ?? 'no-reply@yourstoryline.com', 'Storyline System');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $signature = $settings['site_signature'] ?? '';
        $mail->Body    = "<html><body>$message<br><br>$signature</body></html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    } catch (PDOException $e) {
        error_log("Database Error in notification: " . $e->getMessage());
        return false;
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

function repairDatabase($pdo) {
    try {
        // 0. Users Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            bio TEXT NULL,
            profile_picture VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // 1. Categories Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            image VARCHAR(255) NULL
        )");

        // Ensure image column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'image'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL AFTER slug");
        }

        // 2. Password Resets Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (email),
            INDEX (token)
        )");

        // 3. Subscribers Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // 4. Posts Table columns
        $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL
        )");

        $columnsToAdd = [
            'seo_title' => "VARCHAR(255) AFTER content",
            'featured_image' => "VARCHAR(255) AFTER seo_title",
            'youtube_url' => "VARCHAR(255) AFTER featured_image",
            'category_id' => "INT NULL AFTER youtube_url",
            'meta_title' => "VARCHAR(255) AFTER category_id",
            'meta_keywords' => "TEXT AFTER meta_title",
            'meta_description' => "TEXT AFTER meta_keywords",
            'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ];

        $existingColumns = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM posts");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['Field'];
        }

        foreach ($columnsToAdd as $col => $definition) {
            if (!in_array($col, $existingColumns)) {
                $pdo->exec("ALTER TABLE posts ADD COLUMN $col $definition");
            }
        }

        // 5. Settings Table columns
        $settingsCols = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settingsCols[] = $row['Field'];
        }

        if (!in_array('logo', $settingsCols)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN logo VARCHAR(255) NULL");
        }
        if (!in_array('custom_header_code', $settingsCols)) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN custom_header_code TEXT NULL");
        }

        // 6. Initialize default content if empty
        $postCount = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        if ($postCount == 0) {
            $pdo->exec("INSERT INTO posts (title, content, seo_title) VALUES
                ('Welcome to NEWS5', 'This is your first story. You can manage your content through the admin dashboard.', 'welcome-to-news5'),
                ('Architecture of Narrative', 'Exploring the structural elements of storytelling in the digital age.', 'architecture-of-narrative'),
                ('Digital Connectivity', 'How technology is bridging the gap between storytellers and audiences.', 'digital-connectivity')
            ");
        }

        return true;
    } catch (PDOException $e) {
        error_log("Database Repair Error: " . $e->getMessage());
        return false;
    }
}
?>
