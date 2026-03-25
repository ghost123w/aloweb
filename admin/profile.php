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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex h-screen font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col p-6 space-y-8">
        <h2 class="text-2xl font-black text-blue-400">Storyline</h2>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">D</span><span>Dashboard</span></a>
            <a href="posts" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">P</span><span>Post Manager</span></a>
            <a href="settings" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">S</span><span>SEO Settings</span></a>
            <a href="profile" class="flex items-center space-x-3 text-lg bg-blue-600 p-3 rounded-xl font-bold transition shadow-lg shadow-blue-500/20"><span class="w-5 h-5 flex items-center justify-center bg-white/20 rounded">U</span><span>Profile</span></a>
        </nav>
        <div class="border-t border-slate-800 pt-6">
            <a href="logout" class="flex items-center space-x-3 text-lg text-red-400 hover:text-red-300 transition font-semibold"><span>Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-10 overflow-auto">
        <div class="max-w-4xl mx-auto">
            <header class="mb-10">
                <h1 class="text-3xl font-black text-slate-800">Admin Profile</h1>
                <p class="text-slate-500">Manage your bio, profile picture, and login credentials.</p>
            </header>

            <?php if (isset($message)): ?>
                <div class="bg-green-50 text-green-600 p-4 rounded-xl mb-8 border border-green-100 font-bold">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-8 border border-red-100 font-bold">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                <!-- Bio & Picture Section -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <h2 class="text-xl font-bold text-slate-800">Bio & Appearance</h2>
                    <form method="post" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="flex items-center space-x-6 pb-4">
                            <div class="relative">
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-lg">
                                <?php else: ?>
                                    <div class="w-24 h-24 rounded-2xl bg-blue-50 text-blue-400 flex items-center justify-center text-3xl font-bold border-4 border-white shadow-lg">A</div>
                                <?php endif; ?>
                                <label class="absolute -bottom-2 -right-2 bg-blue-600 p-2 rounded-xl text-white cursor-pointer hover:bg-blue-700 transition shadow-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <input type="file" name="profile_pic" class="hidden">
                                </label>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($user['email'] ?? ''); ?></h3>
                                <p class="text-sm text-slate-400">Primary Administrator</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">About Yourself (Bio)</label>
                            <textarea name="bio" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none" placeholder="Write something about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-500/10">
                            Update Bio
                        </button>
                    </form>
                </section>

                <!-- Password Section -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <h2 class="text-xl font-bold text-slate-800">Security</h2>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="change_password" value="1">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none" placeholder="••••••••">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none" placeholder="••••••••">
                        </div>
                        <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl font-bold hover:bg-slate-800 transition shadow-lg">
                            Change Password
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </main>

</body>
</html>
