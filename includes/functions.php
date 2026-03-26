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
?>
