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

    repairDatabase($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            die("CSRF token validation failed.");
        }

        if (isset($_POST['update_socials'])) {
            $stmt = $pdo->prepare("UPDATE settings SET facebook_url = ?, twitter_url = ?, youtube_url = ?, instagram_url = ?, linkedin_url = ?, tiktok_url = ?, whatsapp_url = ?");
            $stmt->execute([
                $_POST['facebook_url'], $_POST['twitter_url'], $_POST['youtube_url'],
                $_POST['instagram_url'], $_POST['linkedin_url'], $_POST['tiktok_url'], $_POST['whatsapp_url']
            ]);
            $message = "Social links updated successfully.";
        }

        if (isset($_POST['add_section'])) {
            $stmt = $pdo->prepare("INSERT INTO footer_sections (title, sort_order) VALUES (?, ?)");
            $stmt->execute([$_POST['title'], $_POST['sort_order']]);
            $message = "Footer section added.";
        }

        if (isset($_POST['edit_section'])) {
            $stmt = $pdo->prepare("UPDATE footer_sections SET title = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$_POST['title'], $_POST['sort_order'], $_POST['id']]);
            $message = "Footer section updated.";
        }

        if (isset($_POST['delete_section'])) {
            $stmt = $pdo->prepare("DELETE FROM footer_sections WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = "Footer section deleted.";
        }

        if (isset($_POST['add_link'])) {
            $stmt = $pdo->prepare("INSERT INTO footer_links (section_id, label, url, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['section_id'], $_POST['label'], $_POST['url'], $_POST['sort_order']]);
            $message = "Link added.";
        }

        if (isset($_POST['edit_link'])) {
            $stmt = $pdo->prepare("UPDATE footer_links SET label = ?, url = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$_POST['label'], $_POST['url'], $_POST['sort_order'], $_POST['id']]);
            $message = "Link updated.";
        }

        if (isset($_POST['delete_link'])) {
            $stmt = $pdo->prepare("DELETE FROM footer_links WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $message = "Link deleted.";
        }
    }

    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $sections = $pdo->query("SELECT * FROM footer_sections ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $links = [];
    foreach ($sections as $s) {
        $stmt = $pdo->prepare("SELECT * FROM footer_links WHERE section_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$s['id']]);
        $links[$s['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filter Menu Manager - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0c0e14; color: #ffffff; }
        .glass-card {
            background: rgba(23, 25, 35, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        .sidebar-item { transition: all 0.3s ease; color: #64748b; }
        .sidebar-item:hover { color: #3b82f6; background: rgba(59, 130, 246, 0.05); }
        .sidebar-item.active { color: white; background: #3b82f6; box-shadow: 0 0 30px rgba(59, 130, 246, 0.3); }
        input { background: rgba(255, 255, 255, 0.02) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; color: white !important; }
        input:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important; }
    </style>
</head>
<body class="flex min-h-screen relative overflow-x-hidden">

    <!-- Mobile Header -->
    <div class="lg:hidden fixed top-0 left-0 right-0 bg-[#11131a] z-40 p-4 border-b border-white/5 flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center font-black text-white">S</div>
            <span class="font-black text-white">Storyline</span>
        </div>
        <button onclick="toggleSidebar()" class="p-2 text-slate-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
    </div>

    <!-- Sidebar Overlay -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden lg:hidden"></div>

    <aside id="sidebar" class="w-72 bg-[#11131a] text-slate-400 flex flex-col p-8 space-y-10 shadow-2xl fixed h-full z-50 border-r border-white/5 transition-transform duration-300 -translate-x-full lg:translate-x-0">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl text-white shadow-lg shadow-blue-500/20">S</div>
            <h2 class="text-2xl font-black tracking-tighter text-white">Storyline</h2>
        </div>
        <nav class="flex-grow space-y-2">
            <a href="index" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2-2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
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
            <a href="filter_menu" class="sidebar-item active flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                <span>Filter Menu</span>
            </a>
            <a href="settings" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
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

    <main class="flex-grow ml-0 lg:ml-72 p-6 lg:p-12 mt-16 lg:mt-0 overflow-auto">
        <div class="max-w-6xl mx-auto">
            <header class="mb-12 flex justify-between items-end">
                <div>
                    <h1 class="text-4xl font-black text-white tracking-tighter mb-2 italic">Filter Menu Manager</h1>
                    <p class="text-slate-500 font-medium">Construct the architectural footer and social presence.</p>
                </div>
            </header>

            <?php if (isset($message)): ?>
                <div class="bg-blue-600/10 text-blue-400 p-6 rounded-2xl mb-10 border border-blue-500/20 font-bold"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Social Media Section -->
            <section class="glass-card p-10 rounded-[3rem] mb-12">
                <h2 class="text-2xl font-black mb-8 text-white">Social Connectivities</h2>
                <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="update_socials" value="1">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase">YouTube</label>
                        <input type="url" name="youtube_url" value="<?php echo htmlspecialchars($settings['youtube_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl outline-none" placeholder="https://youtube.com/...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase">Facebook</label>
                        <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl outline-none" placeholder="https://facebook.com/...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase">X (Twitter)</label>
                        <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($settings['twitter_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl outline-none" placeholder="https://x.com/...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase">Instagram</label>
                        <input type="url" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl outline-none" placeholder="https://instagram.com/...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase">LinkedIn</label>
                        <input type="url" name="linkedin_url" value="<?php echo htmlspecialchars($settings['linkedin_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl outline-none" placeholder="https://linkedin.com/...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase">TikTok</label>
                        <input type="url" name="tiktok_url" value="<?php echo htmlspecialchars($settings['tiktok_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl outline-none" placeholder="https://tiktok.com/...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase">WhatsApp</label>
                        <input type="text" name="whatsapp_url" value="<?php echo htmlspecialchars($settings['whatsapp_url'] ?? ''); ?>" class="w-full px-4 py-3 rounded-xl outline-none" placeholder="URL or Number">
                    </div>
                    <div class="md:col-span-2 pt-4">
                        <button type="submit" class="bg-blue-600 px-10 py-3 rounded-xl font-black hover:bg-blue-700 transition">Commit Socials</button>
                    </div>
                </form>
            </section>

            <!-- Sections and Links -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <section class="glass-card p-10 rounded-[3rem]">
                    <h2 class="text-2xl font-black mb-8 text-white">Footer Taxonomy</h2>
                    <form method="post" class="space-y-6 mb-12 bg-white/5 p-6 rounded-2xl">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="add_section" value="1">
                        <input type="text" name="title" required placeholder="Section Title (e.g. About Us)" class="w-full px-4 py-3 rounded-xl outline-none">
                        <input type="number" name="sort_order" value="0" class="w-full px-4 py-3 rounded-xl outline-none">
                        <button type="submit" class="w-full bg-blue-600 py-3 rounded-xl font-black">Add Section</button>
                    </form>

                    <div class="space-y-4">
                        <?php foreach ($sections as $s): ?>
                            <div class="p-4 bg-white/5 border border-white/5 rounded-2xl flex justify-between items-center">
                                <div>
                                    <span class="text-white font-bold"><?php echo htmlspecialchars($s['title']); ?></span>
                                    <span class="text-[10px] text-slate-500 block">Order: <?php echo $s['sort_order']; ?></span>
                                </div>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" name="delete_section" class="text-rose-500 hover:text-rose-400 transition" onclick="return confirm('Delete this section and all its links?')">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="glass-card p-10 rounded-[3rem]">
                    <h2 class="text-2xl font-black mb-8 text-white">Node Linkage</h2>
                    <form method="post" class="space-y-4 mb-12 bg-white/5 p-6 rounded-2xl">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="add_link" value="1">
                        <select name="section_id" required class="w-full px-4 py-3 rounded-xl outline-none !bg-[#1a1c23] border border-white/10 text-white">
                            <?php foreach ($sections as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="label" required placeholder="Link Label" class="w-full px-4 py-3 rounded-xl outline-none">
                        <input type="text" name="url" required placeholder="URL (relative or absolute)" class="w-full px-4 py-3 rounded-xl outline-none">
                        <input type="number" name="sort_order" value="0" class="w-full px-4 py-3 rounded-xl outline-none">
                        <button type="submit" class="w-full bg-blue-600 py-3 rounded-xl font-black">Link Node</button>
                    </form>

                    <div class="space-y-6">
                        <?php foreach ($sections as $s): ?>
                            <div class="space-y-2">
                                <h4 class="text-[10px] font-black text-blue-500 uppercase tracking-widest"><?php echo htmlspecialchars($s['title']); ?></h4>
                                <?php foreach ($links[$s['id']] as $l): ?>
                                    <div class="flex justify-between items-center text-sm p-3 bg-white/[0.02] rounded-xl border border-white/5">
                                        <span class="text-slate-300"><?php echo htmlspecialchars($l['label']); ?></span>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="id" value="<?php echo $l['id']; ?>">
                                            <button type="submit" name="delete_link" class="text-rose-500/60 hover:text-rose-500">×</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>
