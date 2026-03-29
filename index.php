<?php
if (!file_exists(__DIR__ . '/includes/config.php')) {
    header("Location: install/index");
    exit;
}

require_once 'includes/config.php';

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$settings = [];
$nav_categories = [];
$posts = [];
$hero = null;
$db_error = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Force database repair to ensure schema consistency
    require_once 'includes/functions.php';
    repairDatabase($pdo);

    $stmt_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC) ?: [];

    // Fetch categories for navigation
    $stmt_nav = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $nav_categories = $stmt_nav->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ALL posts (Removed LIMIT to satisfy requirement)
    $category_slug = $_GET['category'] ?? null;

    try {
        if ($category_slug) {
            $stmt_posts = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE c.slug = ? ORDER BY p.id DESC");
            $stmt_posts->execute([$category_slug]);
        } else {
            $stmt_posts = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
        }
        $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback for missing columns or join errors
        $stmt_posts = $pdo->query("SELECT * FROM posts ORDER BY id DESC");
        $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);
    }

    // Hero from the top post
    if (!empty($posts)) {
        $hero = array_shift($posts);
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

function getYouTubeID($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return $match[1] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['site_name'] ?? 'NEWS5'); ?> - Premium News</title>
    <?php if (!empty($settings['logo'])): ?>
    <link rel="icon" type="image/<?php echo pathinfo($settings['logo'], PATHINFO_EXTENSION); ?>" href="uploads/<?php echo htmlspecialchars($settings['logo']); ?>">
    <?php endif; ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?>">
    <?php echo $settings['custom_header_code'] ?? ''; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #000000; color: #ffffff; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
<body class="overflow-x-hidden">

    <!-- Top Bar -->
    <div class="bg-black text-white py-2 px-4 flex justify-between items-center text-[13px] border-b border-white/5">
        <div class="flex items-center space-x-2">
            <span>☀️ Clear - 23° C</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="opacity-40">☀️</span>
            <a href="subscribe" class="text-red-600 font-bold hover:underline">Subscribe</a>
        </div>
    </div>

    <!-- Main Header -->
    <header class="bg-black border-b border-white/5">
        <div class="max-w-[1400px] mx-auto flex items-stretch">
            <div class="bg-red-600 px-8 py-6 flex items-center justify-center min-w-[180px]">
                <a href="index" class="text-3xl font-[900] tracking-tighter text-white uppercase italic">NEWS5</a>
            </div>
            <div class="flex-grow flex items-center px-8">
                <!-- Navigation can go here if needed for desktop -->
            </div>
            <div class="bg-red-600 px-6 flex items-center justify-center cursor-pointer" onclick="toggleMenu()">
                <div class="space-y-1.5">
                    <div class="w-6 h-0.5 bg-white"></div>
                    <div class="w-6 h-0.5 bg-white"></div>
                    <div class="w-6 h-0.5 bg-white"></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mega Menu (Simplified for the new look) -->
    <div id="mega-menu" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/95 backdrop-blur-md" onclick="toggleMenu()"></div>
        <div class="absolute top-0 right-0 h-full w-full max-w-md bg-black border-l border-white/10 p-12 overflow-y-auto">
            <div class="flex justify-between items-center mb-12">
                <h2 class="text-red-600 font-black uppercase tracking-widest text-xl">Categories</h2>
                <button onclick="toggleMenu()" class="text-white text-3xl">&times;</button>
            </div>
            <div class="space-y-6">
                <a href="index" class="block text-2xl font-black hover:text-red-600 transition">Latest News</a>
                <?php foreach ($nav_categories as $cat): ?>
                    <a href="index?category=<?php echo urlencode($cat['slug']); ?>" class="block text-2xl font-black hover:text-red-600 transition"><?php echo htmlspecialchars($cat['name']); ?></a>
                <?php endforeach; ?>
            </div>
            <div class="mt-20 pt-10 border-t border-white/10">
                <a href="admin/login" class="text-slate-500 font-bold uppercase tracking-widest text-xs hover:text-white transition">Admin Access</a>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const menu = document.getElementById('mega-menu');
            menu.classList.toggle('hidden');
            document.body.style.overflow = menu.classList.contains('hidden') ? '' : 'hidden';
        }
    </script>

    <main class="max-w-[1400px] mx-auto px-0 md:px-0">

        <?php if ($db_error): ?>
            <div class="p-10 text-center my-20">
                <h2 class="text-red-600 font-black text-2xl mb-4 italic tracking-tight">Database Connectivity Issue</h2>
                <p class="text-slate-400 font-bold mb-10 max-w-lg mx-auto"><?php echo htmlspecialchars($db_error); ?></p>
                <a href="install/index" class="bg-red-600 text-white px-10 py-4 rounded-xl font-black text-lg hover:bg-red-700 transition shadow-xl shadow-red-600/20">Run Installer Interface</a>
            </div>
        <?php elseif ($hero): ?>

            <!-- Hero Section -->
            <section class="relative aspect-[16/9] md:aspect-[21/9] overflow-hidden group">
                <a href="story?<?php echo !empty($hero['seo_title']) ? 'title=' . urlencode($hero['seo_title']) : 'id=' . $hero['id']; ?>">
                    <?php if (!empty($hero['featured_image'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($hero['featured_image']); ?>" class="w-full h-full object-cover transition duration-1000 group-hover:scale-105">
                    <?php else: ?>
                        <div class="w-full h-full bg-zinc-900"></div>
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-8 md:p-16 max-w-3xl">
                        <span class="text-red-600 font-black uppercase tracking-widest text-xs mb-4 block"><?php echo htmlspecialchars($hero['category_name'] ?? 'Featured'); ?></span>
                        <h1 class="text-3xl md:text-5xl font-black leading-tight text-white mb-6">
                            <?php echo htmlspecialchars($hero['title']); ?>
                        </h1>
                    </div>
                </a>
            </section>

            <!-- News List -->
            <section class="divide-y divide-white/5">
                <?php if (empty($posts)): ?>
                    <!-- No more posts -->
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <article class="p-6 md:p-10 hover:bg-white/[0.02] transition group">
                            <a href="story?<?php echo !empty($post['seo_title']) ? 'title=' . urlencode($post['seo_title']) : 'id=' . $post['id']; ?>" class="flex items-center space-x-6 md:space-x-10">
                                <div class="w-32 h-20 md:w-48 md:h-32 flex-shrink-0 overflow-hidden rounded-sm">
                                    <?php if ($post['featured_image']): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-full object-cover transition duration-500 group-hover:scale-110">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-zinc-900"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow">
                                    <h2 class="text-lg md:text-2xl font-black leading-tight text-white group-hover:text-red-600 transition line-clamp-2">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </h2>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        <?php endif; ?>

        <!-- Category Grid -->
        <section class="p-10">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-red-600 font-black uppercase tracking-widest text-xs mb-2">Discovery</h2>
                    <h3 class="text-3xl font-black text-white tracking-tighter italic">Explore the taxonomy.</h3>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
                <?php foreach ($nav_categories as $cat): ?>
                    <a href="index?category=<?php echo urlencode($cat['slug']); ?>" class="group block space-y-4">
                        <div class="aspect-square rounded-2xl overflow-hidden bg-zinc-900 border border-white/5 relative shadow-2xl transition-all duration-500 group-hover:border-red-600/30 group-hover:-translate-y-1">
                            <?php if (!empty($cat['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($cat['image']); ?>" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 transition duration-700">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-zinc-800 font-black text-4xl group-hover:text-red-600 transition">#</div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                            <div class="absolute bottom-4 left-4">
                                <span class="text-xs font-black text-white uppercase tracking-tighter"><?php echo htmlspecialchars($cat['name']); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-black border-t border-white/5 py-20">
        <div class="max-w-[1400px] mx-auto px-10 flex flex-col md:flex-row justify-between items-center space-y-10 md:space-y-0">
            <div class="text-2xl font-[900] tracking-tighter text-white italic uppercase">NEWS5</div>
            <div class="flex space-x-8 text-xs font-black uppercase tracking-widest text-slate-500">
                <a href="index" class="hover:text-white transition">Home</a>
                <a href="admin/login" class="hover:text-white transition">Admin</a>
                <a href="subscribe" class="hover:text-white transition text-red-600">Subscribe</a>
            </div>
            <div class="text-[10px] font-bold text-zinc-700 uppercase tracking-widest">
                © <?php echo date('Y'); ?> NEWS5 MEDIA GROUP.
            </div>
        </div>
    </footer>

</body>
</html>
