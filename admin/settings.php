<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once '../includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $keywords = $_POST['meta_keywords'];
        $description = $_POST['meta_description'];
        $smtp_host = $_POST['smtp_host'];
        $smtp_port = $_POST['smtp_port'];
        $smtp_user = $_POST['smtp_user'];
        $smtp_pass = $_POST['smtp_pass'];
        $smtp_enc = $_POST['smtp_enc'];
        $site_signature = $_POST['site_signature'];

        $stmt = $pdo->prepare("SELECT id FROM settings LIMIT 1");
        $stmt->execute();
        $setting = $stmt->fetch();

        if ($setting) {
            $stmt = $pdo->prepare("UPDATE settings SET meta_keywords = ?, meta_description = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_enc = ?, site_signature = ? WHERE id = ?");
            $stmt->execute([$keywords, $description, $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_enc, $site_signature, $setting['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO settings (meta_keywords, meta_description, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_enc, site_signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$keywords, $description, $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_enc, $site_signature]);
        }
        $message = "Settings updated successfully.";
    }

    $stmt = $pdo->prepare("SELECT * FROM settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<h1>SEO & SMTP Settings</h1>
<?php if (isset($message)) echo "<p style='color: green;'>$message</p>"; ?>
<form method="post">
    <h3>SEO Settings</h3>
    <label>Meta Keywords:<br>
    <textarea name="meta_keywords" rows="4" cols="50"><?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?></textarea></label><br>
    <label>Meta Description:<br>
    <textarea name="meta_description" rows="4" cols="50"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea></label><br>

    <h3>SMTP Settings</h3>
    <label>SMTP Host: <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"></label><br>
    <label>SMTP Port: <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>"></label><br>
    <label>SMTP User: <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>"></label><br>
    <label>SMTP Pass: <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>"></label><br>
    <label>Encryption:
        <select name="smtp_enc">
            <option value="" <?php if (($settings['smtp_enc'] ?? '') === '') echo 'selected'; ?>>None</option>
            <option value="tls" <?php if (($settings['smtp_enc'] ?? '') === 'tls') echo 'selected'; ?>>TLS</option>
            <option value="ssl" <?php if (($settings['smtp_enc'] ?? '') === 'ssl') echo 'selected'; ?>>SSL</option>
        </select>
    </label><br>

    <h3>Site Signature</h3>
    <label>Signature (HTML):<br>
    <textarea name="site_signature" rows="4" cols="50"><?php echo htmlspecialchars($settings['site_signature'] ?? ''); ?></textarea></label><br>

    <button type="submit">Save Settings</button>
</form>
<a href="index.php">Back to Dashboard</a>
