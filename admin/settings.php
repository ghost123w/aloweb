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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex h-screen font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col p-6 space-y-8">
        <h2 class="text-2xl font-black text-blue-400">Storyline</h2>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">D</span><span>Dashboard</span></a>
            <a href="posts" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">P</span><span>Post Manager</span></a>
            <a href="settings" class="flex items-center space-x-3 text-lg bg-blue-600 p-3 rounded-xl font-bold transition shadow-lg shadow-blue-500/20"><span class="w-5 h-5 flex items-center justify-center bg-white/20 rounded">S</span><span>SEO Settings</span></a>
            <a href="profile" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">U</span><span>Profile</span></a>
        </nav>
        <div class="border-t border-slate-800 pt-6">
            <a href="logout" class="flex items-center space-x-3 text-lg text-red-400 hover:text-red-300 transition font-semibold"><span>Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-10 overflow-auto">
        <div class="max-w-4xl mx-auto">
            <header class="mb-10">
                <h1 class="text-3xl font-black text-slate-800">SEO & System Settings</h1>
                <p class="text-slate-500">Configure your website's meta data and mail server.</p>
            </header>

            <?php if (isset($message)): ?>
                <div class="bg-green-50 text-green-600 p-4 rounded-xl mb-8 border border-green-100 font-bold">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-8">
                <!-- SEO Section -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center"><span class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center mr-3 text-sm">SEO</span> Search Engine Optimization</h2>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Meta Keywords</label>
                            <textarea name="meta_keywords" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none" placeholder="story, blog, personal"><?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Meta Description</label>
                            <textarea name="meta_description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none" placeholder="A brief description of your site"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- SMTP Section -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center"><span class="w-8 h-8 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center mr-3 text-sm">Mail</span> SMTP Configuration</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Port</label>
                            <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP User</label>
                            <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">SMTP Password</label>
                            <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Encryption</label>
                            <select name="smtp_enc" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                                <option value="" <?php if (($settings['smtp_enc'] ?? '') === '') echo 'selected'; ?>>None</option>
                                <option value="tls" <?php if (($settings['smtp_enc'] ?? '') === 'tls') echo 'selected'; ?>>TLS</option>
                                <option value="ssl" <?php if (($settings['smtp_enc'] ?? '') === 'ssl') echo 'selected'; ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Signature Section -->
                <section class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center"><span class="w-8 h-8 bg-orange-50 text-orange-600 rounded-lg flex items-center justify-center mr-3 text-sm">Sign</span> Email Signature</h2>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Signature (HTML supported)</label>
                        <textarea name="site_signature" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none" placeholder="&lt;p&gt;Best regards, Admin&lt;/p&gt;"><?php echo htmlspecialchars($settings['site_signature'] ?? ''); ?></textarea>
                    </div>
                </section>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:bg-blue-700 transition shadow-xl shadow-blue-500/20">
                        Save All Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

</body>
</html>
