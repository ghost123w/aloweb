<?php
session_start();
if (!file_exists('../includes/config.php')) {
    header("Location: ../install/index.php");
    exit;
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $admin_email = ADMIN_EMAIL;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            die("CSRF token validation failed.");
        }
        if (isset($_POST['update_profile'])) {
            $bio = $_POST['bio'];
            $profile_picture = "";

            if (isset($_POST['remove_pic']) && $_POST['remove_pic'] == '1') {
                $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE email = ?");
                $stmt->execute([$admin_email]);
                $message = "Profile picture removed.";
            }

            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $file_name = $_FILES['profile_pic']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (in_array($file_ext, $allowed_ext)) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime_type = $finfo->file($_FILES['profile_pic']['tmp_name']);
                    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

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
            $current_pass = $_POST['current_password'];
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];

            // Fetch current password from DB
            $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
            $stmt->execute([$admin_email]);
            $user_data = $stmt->fetch();

            if ($user_data && password_verify($current_pass, $user_data['password'])) {
                if ($new_pass === $confirm_pass) {
                    $hashed_pass = password_hash($new_pass, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->execute([$hashed_pass, $admin_email]);

                    sendNotification($admin_email, "Password Changed", "Your admin password was changed at " . date('Y-m-d H:i'));
                    $message = "Password updated successfully.";
                } else {
                    $error = "New passwords do not match.";
                }
            } else {
                $error = "Incorrect current password.";
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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] flex min-h-screen font-sans">

    <!-- Sidebar -->
    <aside class="w-72 bg-[#0F172A] text-white flex flex-col p-8 space-y-10 fixed h-full shadow-2xl">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl">S</div>
            <h2 class="text-2xl font-black tracking-tight">Storyline</h2>
        </div>
        <nav class="flex-grow space-y-2">
            <a href="index" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Dashboard</span>
            </a>
            <a href="posts" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4v4h4"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16h6"></path></svg>
                <span>Post Manager</span>
            </a>
            <a href="categories" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                <span>Categories</span>
            </a>
            <a href="settings" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="profile" class="flex items-center space-x-4 px-4 py-3 rounded-xl bg-blue-600 text-white transition font-bold shadow-lg shadow-blue-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>Profile</span>
            </a>
        </nav>
        <div class="border-t border-slate-800 pt-6">
            <a href="logout" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-red-400 hover:text-red-300 hover:bg-red-400/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow ml-72 p-12 overflow-auto">
        <div class="max-w-5xl mx-auto">
            <header class="mb-12 flex justify-between items-end">
                <div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Account Settings</h1>
                    <p class="text-slate-500 font-medium">Update your professional profile and security details.</p>
                </div>
                <div class="hidden md:block">
                    <a href="../index" class="text-sm font-bold text-slate-400 hover:text-blue-600 transition flex items-center space-x-2">
                        <span>View public site</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                </div>
            </header>

            <?php if (isset($message)): ?>
                <div class="bg-emerald-50 text-emerald-600 p-5 rounded-2xl mb-10 border border-emerald-100 flex items-center space-x-3 font-bold shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="bg-rose-50 text-rose-600 p-5 rounded-2xl mb-10 border border-rose-100 flex items-center space-x-3 font-bold shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">

                <!-- Profile Section -->
                <section class="lg:col-span-2 bg-white p-10 rounded-[2.5rem] shadow-sm border border-slate-100">
                    <div class="flex items-center space-x-4 mb-10">
                        <div class="w-1.5 h-8 bg-blue-600 rounded-full"></div>
                        <h2 class="text-2xl font-black text-slate-900">Personal Information</h2>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="space-y-10">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="flex flex-col md:flex-row items-center space-y-6 md:space-y-0 md:space-x-10 p-8 bg-slate-50 rounded-3xl border border-slate-100">
                            <div class="relative group">
                                <div class="w-32 h-32 rounded-3xl overflow-hidden shadow-2xl border-4 border-white ring-1 ring-slate-200">
                                    <?php if (!empty($user['profile_picture']) && file_exists('../uploads/' . $user['profile_picture'])): ?>
                                        <img id="profile-preview" src="../uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-full h-full object-cover transition duration-500 group-hover:scale-110">
                                        <div id="profile-placeholder" class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white hidden items-center justify-center text-4xl font-black transition duration-500 group-hover:scale-110">
                                            <?php echo strtoupper(substr($user['email'], 0, 1)); ?>
                                        </div>
                                    <?php else: ?>
                                        <div id="profile-placeholder" class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center text-4xl font-black transition duration-500 group-hover:scale-110">
                                            <?php echo strtoupper(substr($user['email'], 0, 1)); ?>
                                        </div>
                                        <img id="profile-preview" class="w-full h-full object-cover hidden">
                                    <?php endif; ?>
                                </div>
                                <label class="absolute -bottom-3 -right-3 bg-white p-3 rounded-2xl text-blue-600 cursor-pointer hover:bg-blue-600 hover:text-white transition-all duration-300 shadow-xl border border-slate-100 group-hover:scale-110">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <input type="file" name="profile_pic" id="profile-input" class="hidden" onchange="previewImage(this)">
                                </label>
                            </div>
                            <div class="mt-4 flex space-x-2">
                                <button type="button" onclick="document.getElementById('profile-input').click()" class="text-xs font-bold bg-white text-slate-600 px-3 py-1 rounded-lg border border-slate-200 hover:bg-slate-50">Change</button>
                                <button type="button" onclick="removeImage()" class="text-xs font-bold bg-white text-rose-600 px-3 py-1 rounded-lg border border-slate-200 hover:bg-rose-50">Remove</button>
                                <input type="hidden" name="remove_pic" id="remove-pic-input" value="0">
                            </div>
                            <div class="flex-grow text-center md:text-left">
                                <h3 class="text-xl font-bold text-slate-900 mb-1"><?php echo htmlspecialchars($user['email'] ?? ''); ?></h3>
                                <p class="text-slate-400 font-semibold text-sm uppercase tracking-widest">Platform Administrator</p>
                                <p class="mt-4 text-xs text-slate-400 max-w-xs leading-relaxed italic">Allowed: JPG, PNG, GIF. Max size 2MB recommended.</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <label class="block text-sm font-bold text-slate-700 ml-1 uppercase tracking-wider">Professional Bio</label>
                            <textarea name="bio" rows="6" class="w-full px-6 py-5 bg-slate-50 border-2 border-transparent rounded-3xl focus:border-blue-500 focus:bg-white transition-all duration-300 outline-none font-medium text-slate-700 shadow-inner" placeholder="Tell the audience about your role..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-5 rounded-[2rem] font-black text-lg hover:bg-blue-700 transition-all duration-300 shadow-2xl shadow-blue-500/20 active:scale-[0.98]">
                            Save Profile Changes
                        </button>
                    </form>
                </section>

                <!-- Security Section -->
                <section class="bg-white p-10 rounded-[2.5rem] shadow-sm border border-slate-100">
                    <div class="flex items-center space-x-4 mb-10">
                        <div class="w-1.5 h-8 bg-slate-900 rounded-full"></div>
                        <h2 class="text-2xl font-black text-slate-900">Security</h2>
                    </div>

                    <form method="post" class="space-y-8">
                        <input type="hidden" name="change_password" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="space-y-6">
                            <div class="space-y-3">
                                <label class="block text-sm font-bold text-slate-700 ml-1 uppercase tracking-wider">Current Password</label>
                                <div class="relative">
                                    <input type="password" name="current_password" required class="w-full px-6 py-5 bg-slate-50 border-2 border-transparent rounded-3xl focus:border-blue-500 focus:bg-white transition-all duration-300 outline-none font-medium text-slate-700 shadow-inner pl-14" placeholder="••••••••">
                                    <div class="absolute left-6 top-1/2 -translate-y-1/2 text-slate-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <label class="block text-sm font-bold text-slate-700 ml-1 uppercase tracking-wider">New Password</label>
                                <div class="relative">
                                    <input type="password" name="new_password" required class="w-full px-6 py-5 bg-slate-50 border-2 border-transparent rounded-3xl focus:border-blue-500 focus:bg-white transition-all duration-300 outline-none font-medium text-slate-700 shadow-inner pl-14" placeholder="••••••••">
                                    <div class="absolute left-6 top-1/2 -translate-y-1/2 text-slate-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <label class="block text-sm font-bold text-slate-700 ml-1 uppercase tracking-wider">Confirm New Password</label>
                                <div class="relative">
                                    <input type="password" name="confirm_password" required class="w-full px-6 py-5 bg-slate-50 border-2 border-transparent rounded-3xl focus:border-blue-500 focus:bg-white transition-all duration-300 outline-none font-medium text-slate-700 shadow-inner pl-14" placeholder="••••••••">
                                    <div class="absolute left-6 top-1/2 -translate-y-1/2 text-slate-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg></div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] font-black text-lg hover:bg-slate-800 transition-all duration-300 shadow-2xl shadow-black/10 active:scale-[0.98]">
                            Update Credentials
                        </button>
                    </form>
                    <div class="mt-8 p-6 bg-amber-50 rounded-3xl border border-amber-100">
                        <p class="text-xs text-amber-700 font-medium leading-relaxed">
                            <strong>Note:</strong> You will receive an email notification upon a successful password change for security auditing.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profile-preview');
                    const placeholder = document.getElementById('profile-placeholder');

                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    if (placeholder) placeholder.classList.add('hidden');
                    document.getElementById('remove-pic-input').value = '0';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            const preview = document.getElementById('profile-preview');
            const placeholder = document.getElementById('profile-placeholder');
            const input = document.getElementById('profile-input');

            input.value = '';
            if (preview) preview.classList.add('hidden');
            if (placeholder) {
                placeholder.classList.remove('hidden');
            } else {
                // If no placeholder exists in DOM, we should show the initials or default
                location.reload(); // Simplest way to reset to DB state if we don't want to complexify JS
                return;
            }
            document.getElementById('remove-pic-input').value = '1';
        }
    </script>
</body>
</html>
