<?php
session_start();
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['admin_email'] = $user['email'];

            require_once '../includes/functions.php';
            sendNotification($user['email'], "New Login Detected", "A login occurred at " . date('Y-m-d H:i'));

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<h1>Admin Login</h1>
<?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
<form method="post">
    <label>Email: <input type="email" name="email" required></label><br>
    <label>Password: <input type="password" name="password" required></label><br>
    <button type="submit">Login</button>
</form>
