<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $admin_email = ADMIN_EMAIL;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            $bio = $_POST['bio'];
            $profile_picture = "";

            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                if (!is_writable($upload_dir)) {
                    $error = "Upload directory is not writable.";
                } else {
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                    $file_name = $_FILES['profile_pic']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (in_array($file_ext, $allowed_ext)) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime_type = $finfo->file($_FILES['profile_pic']['tmp_name']);
                        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif'];

                        if (in_array($mime_type, $allowed_mime)) {
                            $new_file_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                            $target_file = $upload_dir . $new_file_name;

                            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                                $profile_picture = $new_file_name;
                            } else {
                                $error = "Failed to move uploaded file.";
                            }
                        } else {
                            $error = "Invalid MIME type.";
                        }
                    } else {
                        $error = "Invalid file extension.";
                    }
                }
            }

            if (!isset($error)) {
                if ($profile_picture !== "") {
                    $stmt = $pdo->prepare("UPDATE users SET bio = ?, profile_picture = ? WHERE email = ?");
                    $stmt->execute([$bio, $profile_picture, $admin_email]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE email = ?");
                    $stmt->execute([$bio, $admin_email]);
                }
                $message = "Profile updated successfully.";
            }
        } elseif (isset($_POST['change_password'])) {
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];

            if ($new_pass === $confirm_pass) {
                $hashed_pass = password_hash($new_pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_pass, $admin_email]);

                sendNotification($admin_email, "Password Changed", "Your admin password was changed at " . date('Y-m-d H:i'));
                $message = "Password updated successfully.";
            } else {
                $error = "Passwords do not match.";
            }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    $user = $stmt->fetch();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<h1>Profile Management</h1>
<?php if (isset($message)) echo "<p style='color: green;'>$message</p>"; ?>
<?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>

<h3>Update Bio & Picture</h3>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="update_profile" value="1">
    <p>Email: <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
    <label>Bio:<br>
    <textarea name="bio" rows="4" cols="50"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea></label><br>
    <label>Profile Picture:<br>
    <?php if (!empty($user['profile_picture'])): ?>
        <img src="../uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" width="100"><br>
    <?php endif; ?>
    <input type="file" name="profile_pic"></label><br>
    <button type="submit">Update Profile</button>
</form>

<hr>

<h3>Change Password</h3>
<form method="post">
    <input type="hidden" name="change_password" value="1">
    <label>New Password: <input type="password" name="new_password" required></label><br>
    <label>Confirm Password: <input type="password" name="confirm_password" required></label><br>
    <button type="submit">Change Password</button>
</form>

<a href="index.php">Back to Dashboard</a>
