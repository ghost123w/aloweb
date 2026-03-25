<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/config.php';
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if ($email) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("INSERT IGNORE INTO subscribers (email) VALUES (?)");
            $stmt->execute([$email]);

            echo "<script>alert('Subscribed successfully!'); window.location.href='index.php';</script>";
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    } else {
        header("Location: index.php");
    }
}
?>
