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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            die("CSRF token validation failed.");
        }
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - YourStoryline Admin</title>
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
        input:focus, select:focus, textarea:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }
    </style>
</head>
<body class="flex min-h-screen relative overflow-x-hidden">
    <!-- Background Accents -->
    <div class="fixed top-0 right-0 w-[600px] h-[600px] bg-blue-600/5 rounded-full blur-[120px] -z-10 -mr-64 -mt-64"></div>
    <div class="fixed bottom-0 left-0 w-[500px] h-[500px] bg-emerald-600/5 rounded-full blur-[100px] -z-10 -ml-32 -mb-32"></div>

    <aside class="w-72 bg-[#11131a] text-slate-400 flex flex-col p-8 space-y-10 shadow-2xl fixed h-full z-50 border-r border-white/5">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl text-white shadow-lg shadow-blue-500/20">S</div>
            <h2 class="text-2xl font-black tracking-tighter text-white">Storyline</h2>
        </div>
        <nav class="flex-grow space-y-2">
            <a href="index" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2-2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
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
            <a href="settings" class="sidebar-item active flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="profile" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
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
                    <h1 class="text-4xl font-black text-white tracking-tighter mb-2">System Config</h1>
                    <p class="text-slate-500 font-medium">Fine-tuning the platform's global intelligence parameters.</p>
                </div>
                <div class="hidden md:block">
                    <a href="../index" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-blue-500 transition">Return to Platform</a>
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

            <form method="post" class="space-y-10">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- SEO Intelligence Section -->
                <section class="glass-card p-10 rounded-[3rem] space-y-10">
                    <div class="flex items-center space-x-4">
                        <div class="w-1.5 h-8 bg-blue-600 rounded-full"></div>
                        <h2 class="text-2xl font-black text-white tracking-tight">Search Visibility</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-8">
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Platform Keywords</label>
                            <textarea name="meta_keywords" rows="3" class="w-full px-8 py-6 rounded-[2rem] outline-none text-sm font-bold placeholder:text-slate-800" placeholder="storyline, news, digital-archive..."><?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?></textarea>
                        </div>
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Global Narrative Meta</label>
                            <textarea name="meta_description" rows="3" class="w-full px-8 py-6 rounded-[2rem] outline-none text-sm font-bold placeholder:text-slate-800" placeholder="How should search engines perceive this platform?"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- SMTP Infrastructure Section -->
                <section class="glass-card p-10 rounded-[3rem] space-y-10">
                    <div class="flex items-center space-x-4">
                        <div class="w-1.5 h-8 bg-purple-600 rounded-full"></div>
                        <h2 class="text-2xl font-black text-white tracking-tight">Mail Infrastructure</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">SMTP Relay Host</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold" placeholder="smtp.provider.com">
                        </div>
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Interface Port</label>
                            <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>" class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold" placeholder="587">
                        </div>
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">System Identifier (User)</label>
                            <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold" placeholder="notifications@domain.com">
                        </div>
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Access Key (Password)</label>
                            <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold" placeholder="••••••••">
                        </div>
                        <div class="space-y-4 md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Encryption Protocol</label>
                            <select name="smtp_enc" class="w-full px-6 py-4 rounded-2xl outline-none text-sm font-bold appearance-none !bg-[#1a1c23]">
                                <option value="" <?php if (($settings['smtp_enc'] ?? '') === '') echo 'selected'; ?>>Unencrypted (None)</option>
                                <option value="tls" <?php if (($settings['smtp_enc'] ?? '') === 'tls') echo 'selected'; ?>>TLS Security</option>
                                <option value="ssl" <?php if (($settings['smtp_enc'] ?? '') === 'ssl') echo 'selected'; ?>>SSL Hardened</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Communication Branding Section -->
                <section class="glass-card p-10 rounded-[3rem] space-y-10">
                    <div class="flex items-center space-x-4">
                        <div class="w-1.5 h-8 bg-orange-600 rounded-full"></div>
                        <h2 class="text-2xl font-black text-white tracking-tight">Signal Signature</h2>
                    </div>
                    <div class="space-y-6">
                        <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Email Signature (HTML Interface)</label>
                        <textarea name="site_signature" rows="4" class="w-full px-8 py-6 rounded-[2rem] outline-none text-sm font-mono placeholder:text-slate-800" placeholder="<p>Best regards, The Storyline Team</p>"><?php echo htmlspecialchars($settings['site_signature'] ?? ''); ?></textarea>
                    </div>
                </section>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="w-full md:w-auto bg-blue-600 text-white px-16 py-6 rounded-[2.5rem] font-black text-xl hover:bg-blue-700 transition-all duration-300 shadow-2xl shadow-blue-500/30 active:scale-[0.98]">
                        Archive Configurations
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
