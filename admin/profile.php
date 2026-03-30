<?php
session_start();
if (!file_exists('../includes/config.php')) {
    header("Location: ../install/index");
    exit;
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login");
    exit;
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db_error = null;
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
                        $new_file_name = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - H-shadow Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0c0e14; color: #ffffff; }
        .glass-card {
            background: rgba(23, 25, 35, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #94a3b8;
        }
        .sidebar-item:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        .sidebar-item.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
        input, select, textarea {
            background: rgba(255, 255, 255, 0.02) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }
        input:focus, textarea:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }
    </style>
</head>
<body class="flex min-h-screen relative overflow-x-hidden">
    <!-- Background Accents -->
    <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[120px] -z-10 -mr-64 -mt-64"></div>
    <div class="fixed bottom-0 left-0 w-[400px] h-[400px] bg-purple-600/10 rounded-full blur-[100px] -z-10 -ml-32 -mb-32"></div>

    <aside class="w-72 bg-[#11131a] text-slate-400 flex flex-col p-8 space-y-10 shadow-2xl fixed h-full z-50 border-r border-white/5">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl text-white shadow-lg shadow-blue-500/20">H</div>
            <h2 class="text-2xl font-black tracking-tighter text-white">H-shadow</h2>
        </div>
        <nav class="flex-grow space-y-2">
            <a href="index" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Dashboard</span>
            </a>
            <a href="posts" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4v4h4"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16h6"></path></svg>
                <span>Post Manager</span>
            </a>
            <a href="categories" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                <span>Categories</span>
            </a>
            <a href="settings" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="profile" class="sidebar-item active flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>Profile</span>
            </a>
        </nav>
        <div class="border-t border-white/5 pt-6">
            <a href="logout" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold text-rose-500/80">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-grow ml-72 p-12 overflow-auto">
        <div class="max-w-5xl mx-auto">
            <header class="mb-12 flex justify-between items-end">
                <div>
                    <h1 class="text-4xl font-black text-white tracking-tighter mb-2">Architect Profile</h1>
                    <p class="text-slate-500 font-medium">Managing the identity behind the narrative.</p>
                </div>
                <div class="hidden md:block">
                    <a href="../index" class="text-xs font-bold text-slate-400 hover:text-blue-600 transition flex items-center space-x-2 tracking-widest uppercase">
                        <span>Platform View</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                </div>
            </header>

            <?php if (isset($message)): ?>
                <div class="bg-blue-600/10 text-blue-400 p-6 rounded-[2rem] mb-10 border border-blue-500/20 flex items-center space-x-4 font-bold shadow-lg shadow-blue-500/5">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="bg-rose-500/10 text-rose-400 p-6 rounded-[2rem] mb-10 border border-rose-500/20 flex items-center space-x-4 font-bold shadow-lg shadow-rose-500/5">
                    <div class="w-10 h-10 bg-rose-600 rounded-xl flex items-center justify-center text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">

                <!-- Profile Section -->
                <section class="lg:col-span-2 space-y-8">
                    <form method="post" enctype="multipart/form-data" class="space-y-8">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="glass-card p-10 rounded-[3rem] space-y-10">
                            <div class="flex flex-col md:flex-row items-center space-y-8 md:space-y-0 md:space-x-12">
                                <div class="relative group">
                                    <div class="w-40 h-40 rounded-[2.5rem] overflow-hidden shadow-2xl bg-black/40 border-4 border-white/5 ring-4 ring-blue-600/10 group-hover:ring-blue-600/30 transition-all duration-500">
                                        <?php if (!empty($user['profile_picture']) && file_exists('../uploads/' . $user['profile_picture'])): ?>
                                            <img id="profile-preview" src="../uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" class="w-full h-full object-cover transition duration-700 group-hover:scale-110">
                                        <?php else: ?>
                                            <div id="profile-placeholder" class="w-full h-full bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center text-5xl font-black transition duration-700 group-hover:scale-110">
                                                <?php echo strtoupper(substr($user['email'], 0, 1)); ?>
                                            </div>
                                            <img id="profile-preview" class="w-full h-full object-cover hidden">
                                        <?php endif; ?>
                                    </div>
                                    <label class="absolute -bottom-4 -right-4 bg-blue-600 p-4 rounded-2xl text-white cursor-pointer hover:bg-blue-700 transition-all duration-300 shadow-2xl shadow-blue-500/40 border border-white/10 group-hover:scale-110 active:scale-95">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        <input type="file" name="profile_pic" id="profile-input" class="hidden" onchange="previewImage(this)">
                                    </label>
                                </div>
                                <div class="flex-grow text-center md:text-left space-y-4">
                                    <div>
                                        <h3 class="text-3xl font-black text-white tracking-tight mb-1"><?php echo htmlspecialchars($user['email'] ?? ''); ?></h3>
                                        <p class="text-blue-500 font-black text-[10px] uppercase tracking-[0.3em]">Lead Architect & Administrator</p>
                                    </div>
                                    <div class="flex flex-wrap justify-center md:justify-start gap-3 pt-2">
                                        <button type="button" onclick="document.getElementById('profile-input').click()" class="text-[10px] font-black uppercase tracking-widest bg-white/5 hover:bg-white/10 text-white px-5 py-2.5 rounded-xl border border-white/5 transition-all">Change Visual</button>
                                        <button type="button" onclick="removeImage()" class="text-[10px] font-black uppercase tracking-widest bg-rose-600/10 hover:bg-rose-600 text-rose-500 hover:text-white px-5 py-2.5 rounded-xl border border-rose-500/10 transition-all">Purge Asset</button>
                                        <input type="hidden" name="remove_pic" id="remove-pic-input" value="0">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6 pt-4">
                                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Biographical Narrative</label>
                                <textarea name="bio" rows="6" class="w-full px-8 py-6 rounded-[2rem] outline-none text-lg leading-relaxed placeholder:text-slate-800" placeholder="Define your role within the system..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="w-full bg-blue-600 text-white py-6 rounded-[2rem] font-black text-xl hover:bg-blue-700 transition-all duration-300 shadow-2xl shadow-blue-500/30 active:scale-[0.98]">
                                Commit Identity Changes
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Security Section -->
                <section class="space-y-8">
                    <div class="glass-card p-10 rounded-[3rem] space-y-10">
                        <div class="flex items-center space-x-4">
                            <div class="w-1.5 h-8 bg-blue-600 rounded-full"></div>
                            <h2 class="text-2xl font-black text-white tracking-tight">Access Control</h2>
                        </div>

                        <form method="post" class="space-y-8">
                            <input type="hidden" name="change_password" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <div class="space-y-6">
                                <div class="space-y-4">
                                    <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Current Key</label>
                                    <div class="relative">
                                        <input type="password" name="current_password" required class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold pl-12" placeholder="••••••••">
                                        <div class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">New Security Key</label>
                                    <div class="relative">
                                        <input type="password" name="new_password" required class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold pl-12" placeholder="••••••••">
                                        <div class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Verify New Key</label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" required class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold pl-12" placeholder="••••••••">
                                        <div class="absolute left-5 top-1/2 -translate-y-1/2 text-slate-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg></div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white py-5 rounded-2xl font-black text-lg hover:bg-blue-700 transition-all duration-300 shadow-2xl shadow-blue-500/20 active:scale-[0.98]">
                                Update Credentials
                            </button>
                        </form>

                        <div class="p-6 rounded-2xl bg-blue-600/5 border border-blue-500/10">
                            <h4 class="text-blue-400 font-black text-[10px] uppercase tracking-widest mb-2 flex items-center">
                                <span class="mr-2">🛡️</span> Security Protocol
                            </h4>
                            <p class="text-slate-500 text-[10px] leading-relaxed font-bold uppercase tracking-tight">
                                Changing your access key will trigger an automated alert to your registered email for auditing.
                            </p>
                        </div>
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
                location.reload();
                return;
            }
            document.getElementById('remove-pic-input').value = '1';
        }
    </script>
</body>
</html>
<?php } catch (PDOException $e) { $db_error = $e->getMessage(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connectivity Alert - H-shadow Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0c0e14; color: #ffffff; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6 relative overflow-hidden">
    <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-rose-600/10 rounded-full blur-[120px] -z-10 -mr-64 -mt-64"></div>
    <div class="max-w-2xl w-full bg-[#11131a] border-2 border-rose-500/20 p-12 rounded-[3rem] text-center shadow-2xl">
        <div class="w-20 h-20 bg-rose-600/10 rounded-3xl flex items-center justify-center mx-auto mb-8">
            <svg class="w-10 h-10 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <h2 class="text-3xl font-black text-white mb-4 tracking-tight">Database Connectivity Issue</h2>
        <p class="text-slate-400 font-bold mb-10 leading-relaxed uppercase text-xs tracking-[0.2em]"><?php echo htmlspecialchars($db_error); ?></p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
            <a href="../install/index" class="w-full sm:w-auto bg-rose-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:bg-rose-700 transition shadow-xl shadow-rose-600/20">Launch Installer</a>
            <a href="profile" class="text-slate-500 font-bold hover:text-white transition uppercase text-xs tracking-widest">Retry Link</a>
        </div>
    </div>
</body>
</html>
<?php } ?>
