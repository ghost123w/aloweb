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
require_once 'includes/layout.php';

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

    renderAdminHeader("Filter Menu Manager", "filter_menu");
?>
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
<?php
    renderAdminFooter();
} catch (PDOException $e) { $db_error = $e->getMessage(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connectivity Alert - YourStoryline Admin</title>
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
            <a href="filter_menu" class="text-slate-500 font-bold hover:text-white transition uppercase text-xs tracking-widest">Retry Link</a>
        </div>
    </div>
</body>
</html>
<?php } ?>
