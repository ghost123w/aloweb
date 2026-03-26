<?php
if (!file_exists(__DIR__ . '/includes/config.php')) {
    header("Location: install/index.php");
    exit;
}

require_once 'includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch();

    // Check if categories table exists
    $catTableCheck = $pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0;
    $nav_categories = [];
    $posts = [];

    if ($catTableCheck) {
        // Fetch categories for navigation
        $stmt_nav = $pdo->query("SELECT * FROM categories ORDER BY name ASC LIMIT 5");
        $nav_categories = $stmt_nav->fetchAll();

        // Category filtering
        $category_slug = $_GET['category'] ?? null;
        if ($category_slug) {
            $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE c.slug = ? ORDER BY p.created_at DESC LIMIT 10");
            $stmt->execute([$category_slug]);
        } else {
            $stmt = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC LIMIT 10");
        }
    } else {
        // Fallback for legacy schema
        $stmt = $pdo->query("SELECT *, NULL as category_name, NULL as category_slug FROM posts ORDER BY created_at DESC LIMIT 10");
    }
    $posts = $stmt->fetchAll();

    // The first post will be the hero
    $hero = array_shift($posts);

} catch (PDOException $e) {
    $settings = null;
    $posts = [];
    $hero = null;
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
    <title>YourStoryline - Premium Magazine</title>
    <meta name="keywords" content="<?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3 { font-family: 'Playfair Display', serif; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fadeIn { animation: fadeIn 0.8s ease-out forwards; }
    </style>
</head>
<body class="bg-white text-slate-900 overflow-x-hidden">

    <!-- Premium Navigation -->
    <nav class="border-b border-slate-100 py-6 sticky top-0 bg-white/80 backdrop-blur-md z-50">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
            <div class="flex items-center space-x-8">
                <a href="index" class="text-3xl font-black tracking-tighter text-slate-900 uppercase">YourStoryline</a>
                <div class="hidden md:flex space-x-6 text-sm font-bold uppercase tracking-widest text-slate-400">
                    <a href="index" class="hover:text-blue-600 transition text-blue-600">All Stories</a>
                    <?php foreach ($nav_categories as $nav_cat): ?>
                        <a href="index?category=<?php echo urlencode($nav_cat['slug']); ?>" class="hover:text-blue-600 transition"><?php echo htmlspecialchars($nav_cat['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="admin/login" class="text-sm font-bold text-slate-900 border-2 border-slate-900 px-6 py-2 rounded-full hover:bg-slate-900 hover:text-white transition uppercase">Admin</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">

        <?php if ($hero): ?>
        <!-- Categories Carousel/Grid -->
        <?php if (!empty($nav_categories)): ?>
        <section class="mb-20 animate-fadeIn">
            <h3 class="text-sm font-black uppercase tracking-[0.3em] text-slate-400 mb-8 text-center">Explore by Category</h3>
            <div class="flex flex-wrap justify-center gap-8">
                <?php foreach ($nav_categories as $cat): ?>
                    <a href="index?category=<?php echo urlencode($cat['slug']); ?>" class="group relative w-44 h-44 rounded-[2.5rem] overflow-hidden shadow-xl hover:shadow-blue-500/20 transition duration-500 hover:-translate-y-2">
                        <?php if (!empty($cat['image'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($cat['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <?php else: ?>
                            <div class="w-full h-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-blue-50 transition duration-500 font-black text-4xl">#</div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/20 to-transparent opacity-80 group-hover:opacity-100 transition duration-500 flex items-end p-6">
                            <span class="text-white font-black text-lg tracking-tight"><?php echo htmlspecialchars($cat['name']); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-12 mb-20 items-center">
            <div class="lg:col-span-7 relative group overflow-hidden rounded-3xl">
                <?php if ($hero['featured_image']): ?>
                    <img src="uploads/<?php echo htmlspecialchars($hero['featured_image']); ?>" class="w-full aspect-[16/10] object-cover group-hover:scale-105 transition duration-700">
                <?php elseif ($hero['youtube_url'] && ($vid = getYouTubeID($hero['youtube_url']))): ?>
                    <iframe class="w-full aspect-video rounded-3xl" src="https://www.youtube.com/embed/<?php echo $vid; ?>" frameborder="0" allowfullscreen></iframe>
                <?php else: ?>
                    <div class="w-full aspect-[16/10] bg-slate-100 flex items-center justify-center text-slate-300 rounded-3xl">No Media</div>
                <?php endif; ?>
                <a href="index?category=<?php echo urlencode($hero['category_slug'] ?? ''); ?>" class="absolute top-6 left-6 bg-blue-600 text-white px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 transition">
                    <?php echo htmlspecialchars($hero['category_name'] ?? 'Featured'); ?>
                </a>
            </div>
            <div class="lg:col-span-5 space-y-6">
                <span class="text-sm font-bold text-blue-600 uppercase tracking-[0.2em]"><?php echo date('M d, Y', strtotime($hero['created_at'])); ?></span>
                <h1 class="text-5xl md:text-6xl font-black leading-[1.1] tracking-tight hover:text-blue-600 transition cursor-pointer">
                    <a href="story?title=<?php echo urlencode($hero['seo_title']); ?>"><?php echo htmlspecialchars($hero['title']); ?></a>
                </h1>
                <p class="text-xl text-slate-500 leading-relaxed font-medium line-clamp-3">
                    <?php echo htmlspecialchars(substr(strip_tags($hero['content']), 0, 200)) . '...'; ?>
                </p>
                <div class="pt-4">
                    <a href="story?title=<?php echo urlencode($hero['seo_title']); ?>" class="inline-flex items-center text-lg font-black group">
                        Read Full Story
                        <span class="ml-3 w-10 h-10 bg-slate-900 text-white rounded-full flex items-center justify-center group-hover:bg-blue-600 transition duration-300">→</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Secondary Grid -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-12 border-t border-slate-100 pt-16">
            <?php foreach ($posts as $post): ?>
            <article class="group">
                <div class="relative overflow-hidden rounded-2xl mb-6">
                    <?php if ($post['featured_image']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full aspect-square object-cover group-hover:scale-105 transition duration-500">
                    <?php elseif ($post['youtube_url'] && ($vid = getYouTubeID($post['youtube_url']))): ?>
                         <div class="aspect-square bg-slate-900 flex items-center justify-center rounded-2xl">
                             <img src="https://img.youtube.com/vi/<?php echo $vid; ?>/maxresdefault.jpg" class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition duration-500">
                             <div class="absolute inset-0 flex items-center justify-center"><div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-white border border-white/30 group-hover:bg-blue-600 transition">▶</div></div>
                         </div>
                    <?php else: ?>
                        <div class="w-full aspect-square bg-slate-50 flex items-center justify-center text-slate-200">No Image</div>
                    <?php endif; ?>
                    <a href="index?category=<?php echo urlencode($post['category_slug'] ?? ''); ?>" class="absolute bottom-4 left-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest text-blue-600 hover:bg-white transition">
                        <?php echo htmlspecialchars($post['category_name'] ?? 'General'); ?>
                    </a>
                </div>
                <div class="space-y-4">
                    <span class="text-xs font-black text-slate-400 uppercase tracking-widest block"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                    <h2 class="text-2xl font-bold leading-tight group-hover:text-blue-600 transition">
                        <a href="story?title=<?php echo urlencode($post['seo_title']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </h2>
                    <p class="text-slate-500 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 100)) . '...'; ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </section>
        <?php else: ?>
            <div class="text-center py-32">
                <h2 class="text-4xl font-black mb-4">The press is quiet today.</h2>
                <p class="text-slate-400 mb-10">Waiting for the next big story to break.</p>
                <a href="admin/posts?action=add" class="bg-blue-600 text-white px-10 py-4 rounded-full font-black hover:bg-blue-700 transition shadow-xl shadow-blue-500/20">Write First Story</a>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-white py-20 mt-32">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-4 gap-16">
            <div class="lg:col-span-2 space-y-8">
                <h3 class="text-4xl font-black tracking-tighter">YourStoryline</h3>
                <p class="text-slate-400 max-w-sm text-lg leading-relaxed"><?php echo htmlspecialchars($settings['meta_description'] ?? 'Curating the world\'s most compelling stories in a clean, modern aesthetic.'); ?></p>
                <div class="flex space-x-6">
                    <a href="#" class="w-12 h-12 bg-white/5 rounded-full flex items-center justify-center hover:bg-blue-600 transition">T</a>
                    <a href="#" class="w-12 h-12 bg-white/5 rounded-full flex items-center justify-center hover:bg-blue-600 transition">I</a>
                    <a href="#" class="w-12 h-12 bg-white/5 rounded-full flex items-center justify-center hover:bg-blue-600 transition">F</a>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-black uppercase tracking-[0.2em] mb-8 text-slate-500">Navigation</h4>
                <ul class="space-y-4 font-bold">
                    <li><a href="index" class="hover:text-blue-400 transition">Home</a></li>
                    <li><a href="admin/login" class="hover:text-blue-400 transition">Admin Login</a></li>
                    <li><a href="#" class="hover:text-blue-400 transition">Terms of Use</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-black uppercase tracking-[0.2em] mb-8 text-slate-500">Newsletter</h4>
                <form action="subscribe" method="POST" class="space-y-4">
                    <input type="email" name="email" placeholder="Email Address" required class="w-full bg-white/5 border border-white/10 px-6 py-4 rounded-xl focus:border-blue-500 outline-none transition text-white">
                    <button type="submit" class="w-full bg-blue-600 py-4 rounded-xl font-black hover:bg-blue-700 transition shadow-lg shadow-blue-500/20 text-white">Subscribe</button>
                </form>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-6 mt-20 pt-8 border-t border-white/5 text-center text-slate-500 text-sm font-bold">
            © <?php echo date('Y'); ?> YourStoryline. Crafting narratives with precision.
        </div>
    </footer>

</body>
</html>
