<?php
if (!file_exists(__DIR__ . '/includes/config.php')) {
    header("Location: install/index");
    exit;
}

require_once 'includes/config.php';

// Initialize variables to prevent "Undefined variable" warnings
$settings = [];
$nav_categories = [];
$posts = [];
$hero = null;
$catTableCheck = false;
$db_error = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings_data = $stmt_settings->fetch();
    if ($settings_data) $settings = $settings_data;

    // Check if categories table exists
    $catTableCheck = $pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0;

    if ($catTableCheck) {
        // Fetch categories for navigation
        $stmt_nav = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $nav_categories = $stmt_nav->fetchAll();

        // Category filtering
        $category_slug = $_GET['category'] ?? null;
        if ($category_slug) {
            $stmt_posts = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE c.slug = ? ORDER BY p.created_at DESC LIMIT 10");
            $stmt_posts->execute([$category_slug]);
        } else {
            $stmt_posts = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC LIMIT 10");
        }
        $posts = $stmt_posts->fetchAll();
    } else {
        // Fallback for legacy schema or unmigrated databases
        $postsTableCheck = $pdo->query("SHOW TABLES LIKE 'posts'")->rowCount() > 0;
        if ($postsTableCheck) {
            $stmt_posts = $pdo->query("SELECT *, NULL as category_name, NULL as category_slug FROM posts ORDER BY created_at DESC LIMIT 10");
            $posts = $stmt_posts->fetchAll();
        }
    }

    // Assign hero from the top post
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
    <title>YourStoryline - Premium Magazine</title>
    <?php if (!empty($settings['logo'])): ?>
    <link rel="icon" type="image/<?php echo pathinfo($settings['logo'], PATHINFO_EXTENSION); ?>" href="uploads/<?php echo htmlspecialchars($settings['logo']); ?>">
    <?php endif; ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?>">
    <?php echo $settings['custom_header_code'] ?? ''; ?>
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

    <!-- News Style Navigation -->
    <nav class="border-b border-slate-200 py-4 sticky top-0 bg-white z-50 shadow-sm">
        <div class="max-w-[1400px] mx-auto px-4 md:px-8 flex justify-between items-center">
            <div class="flex items-center space-x-10">
                <a href="index" class="flex items-center space-x-2">
                    <?php if (!empty($settings['logo'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" class="h-10 w-auto object-contain">
                    <?php else: ?>
                        <span class="text-2xl font-[900] tracking-tighter text-slate-900">STORYLINE</span>
                        <span class="text-2xl font-[900] tracking-tighter text-blue-600">NEWS</span>
                    <?php endif; ?>
                </a>
                <div class="hidden lg:flex items-center space-x-6 text-[13px] font-black uppercase tracking-tight text-slate-700">
                    <a href="index" class="hover:text-blue-600 transition">Latest</a>
                    <?php
                    $nav_limit = array_slice($nav_categories, 0, 8);
                    foreach ($nav_limit as $nav_cat): ?>
                        <a href="index?category=<?php echo urlencode($nav_cat['slug']); ?>" class="hover:text-blue-600 transition"><?php echo htmlspecialchars($nav_cat['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center space-x-8">
                <div class="hidden md:flex items-center space-x-2 group cursor-pointer">
                    <div class="w-2 h-2 rounded-full bg-red-600 group-hover:animate-pulse"></div>
                    <span class="text-[13px] font-black uppercase tracking-tight text-slate-900">Watch</span>
                </div>
                <button id="menu-toggle" class="flex flex-col space-y-1.5 focus:outline-none group">
                    <div class="w-6 h-0.5 bg-slate-900 transition-all group-hover:w-8"></div>
                    <div class="w-8 h-0.5 bg-slate-900"></div>
                    <div class="w-6 h-0.5 bg-slate-900 ml-auto transition-all group-hover:w-8"></div>
                </button>
            </div>
        </div>
    </nav>

    <!-- Mega Menu (JS Powered) -->
    <div id="mega-menu" class="fixed inset-0 z-40 hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleMenu()"></div>
        <div class="absolute top-[73px] left-0 w-full bg-white border-b border-slate-200 shadow-2xl animate-fadeIn origin-top">
            <div class="max-w-[1400px] mx-auto p-12">
                <div class="flex justify-between items-start mb-10">
                    <h2 class="text-xs font-black uppercase tracking-[0.3em] text-slate-400">Browse All Taxonomies</h2>
                    <button onclick="toggleMenu()" class="text-slate-400 hover:text-slate-900 transition font-bold uppercase text-[10px] tracking-widest flex items-center">Close <span class="ml-2 text-lg">×</span></button>
                </div>
                <div id="category-grid" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8">
                    <!-- Categories will be injected here -->
                    <div class="animate-pulse space-y-4">
                        <div class="aspect-square bg-slate-100 rounded-2xl"></div>
                        <div class="h-4 bg-slate-100 rounded w-1/2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let menuOpen = false;
        const megaMenu = document.getElementById('mega-menu');
        const categoryGrid = document.getElementById('category-grid');

        async function fetchCategories() {
            try {
                const response = await fetch('api/categories');
                const categories = await response.json();

                if (categories.length === 0) {
                    categoryGrid.innerHTML = '<div class="col-span-full py-12 text-center text-slate-400 font-bold uppercase tracking-widest">No categories defined.</div>';
                    return;
                }

                categoryGrid.innerHTML = categories.map(cat => `
                    <a href="index?category=${cat.slug}" class="group space-y-4 block">
                        <div class="aspect-square rounded-2xl overflow-hidden bg-slate-50 border border-slate-100 relative shadow-sm transition-all duration-500 group-hover:shadow-blue-500/20 group-hover:-translate-y-1">
                            ${cat.image ?
                                `<img src="uploads/${cat.image}" class="w-full h-full object-cover group-hover:scale-110 transition duration-700">` :
                                `<div class="w-full h-full flex items-center justify-center text-slate-300 font-black text-2xl group-hover:bg-blue-50 transition duration-500">#</div>`
                            }
                            <div class="absolute inset-0 bg-blue-600/0 group-hover:bg-blue-600/10 transition duration-500"></div>
                        </div>
                        <span class="block text-sm font-black text-slate-900 uppercase tracking-tight group-hover:text-blue-600 transition">${cat.name}</span>
                    </a>
                `).join('');
            } catch (error) {
                categoryGrid.innerHTML = '<div class="col-span-full py-12 text-center text-rose-500 font-bold uppercase tracking-widest">Network Error</div>';
            }
        }

        function toggleMenu() {
            menuOpen = !menuOpen;
            if (menuOpen) {
                megaMenu.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                if (categoryGrid.children.length <= 1) fetchCategories();
            } else {
                megaMenu.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }

        document.getElementById('menu-toggle').addEventListener('click', toggleMenu);
    </script>

    <main class="max-w-[1400px] mx-auto px-4 md:px-8 py-10">

        <?php if ($db_error): ?>
            <div class="bg-rose-50 border-2 border-rose-100 p-10 rounded-[2rem] text-center my-20">
                <h2 class="text-rose-600 font-black text-2xl mb-4 italic tracking-tight">Database Connectivity Issue</h2>
                <p class="text-rose-500 font-bold mb-10 max-w-lg mx-auto"><?php echo htmlspecialchars($db_error); ?></p>
                <a href="install/index" class="bg-rose-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:bg-rose-700 transition shadow-xl shadow-rose-500/20">Run Installer Interface</a>
            </div>
        <?php elseif ($hero): ?>
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-10 mb-16">
            <!-- Hero Left Content -->
            <div class="lg:col-span-4 space-y-6">
                <h1 class="text-3xl md:text-4xl font-black leading-tight text-slate-900 hover:text-blue-600 transition cursor-pointer">
                    <a href="story?title=<?php echo urlencode($hero['seo_title']); ?>"><?php echo htmlspecialchars($hero['title']); ?></a>
                </h1>
                <p class="text-[17px] text-slate-600 leading-relaxed">
                    <?php echo htmlspecialchars(substr(strip_tags($hero['content']), 0, 250)) . '...'; ?>
                </p>
                <div class="pt-6 border-t border-slate-100">
                    <div class="flex items-center space-x-2 mb-2">
                        <span class="text-[10px] font-black uppercase text-slate-400">From the</span>
                        <span class="text-[10px] font-black uppercase text-blue-600 tracking-widest"><?php echo htmlspecialchars($hero['category_name'] ?? 'General'); ?> Desk</span>
                    </div>
                    <a href="story?title=<?php echo urlencode($hero['seo_title']); ?>" class="text-lg font-black text-slate-900 hover:text-blue-600 transition">
                        Read more about this story
                    </a>
                </div>
            </div>

            <!-- Hero Center Image -->
            <div class="lg:col-span-5 relative group overflow-hidden">
                <a href="story?title=<?php echo urlencode($hero['seo_title']); ?>">
                <?php if ($hero['featured_image']): ?>
                    <img src="uploads/<?php echo htmlspecialchars($hero['featured_image']); ?>" class="w-full h-full min-h-[400px] object-cover transition duration-700 group-hover:opacity-95">
                <?php elseif ($hero['youtube_url'] && ($vid = getYouTubeID($hero['youtube_url']))): ?>
                    <iframe class="w-full h-full min-h-[400px]" src="https://www.youtube.com/embed/<?php echo $vid; ?>" frameborder="0" allowfullscreen></iframe>
                <?php else: ?>
                    <div class="w-full h-full min-h-[400px] bg-slate-100 flex items-center justify-center text-slate-300 font-black uppercase tracking-tighter">Story Asset Unavailable</div>
                <?php endif; ?>
                </a>
            </div>

            <!-- Hero Right Sidebar (Latest) -->
            <div class="lg:col-span-3 border-l border-slate-100 pl-10 hidden lg:block">
                <div class="space-y-10">
                    <?php
                    $sidebar_posts = array_slice($posts, 0, 4);
                    // Remove sidebar posts from main posts loop
                    $posts = array_slice($posts, 4);

                    if (empty($sidebar_posts)): ?>
                        <div class="py-12 text-center border-b border-dashed border-slate-200">
                            <p class="text-slate-400 font-bold text-xs uppercase tracking-widest">Sidebar Archive Empty</p>
                        </div>
                    <?php else:
                        foreach($sidebar_posts as $sp): ?>
                        <div class="relative pl-6 border-b border-dashed border-slate-200 pb-8 last:border-0">
                            <div class="absolute left-0 top-1.5 w-2 h-2 rounded-full bg-blue-600"></div>
                            <span class="text-[11px] font-black text-slate-400 uppercase tracking-tighter mb-2 block">
                                <?php
                                    $time_ago = floor((time() - strtotime($sp['created_at'])) / 3600);
                                    echo $time_ago > 0 ? $time_ago . "h ago" : "Just now";
                                ?>
                            </span>
                            <h4 class="text-[16px] font-black leading-snug text-slate-900 hover:text-blue-600 transition">
                                <a href="story?title=<?php echo urlencode($sp['seo_title']); ?>"><?php echo htmlspecialchars($sp['title']); ?></a>
                            </h4>
                        </div>
                        <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </section>

        <!-- Promotion Banner -->
        <?php
        // Take another post for the promotion slot if available
        $promo = array_shift($posts);
        if ($promo):
        ?>
        <section class="bg-slate-50 p-1 rounded-sm mb-16 flex flex-col md:flex-row items-stretch border-y border-slate-100">
            <div class="flex-grow p-8 md:p-12 space-y-4">
                <span class="inline-block bg-black text-white px-2 py-0.5 text-[10px] font-black uppercase tracking-widest">Promotion</span>
                <h3 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight">
                    <a href="story?title=<?php echo urlencode($promo['seo_title']); ?>" class="hover:text-blue-600 transition">
                        <?php echo htmlspecialchars($promo['title']); ?>
                    </a>
                </h3>
                <p class="text-slate-500 font-bold text-[11px] uppercase tracking-widest">
                    Powered by Storyline News
                </p>
            </div>
            <div class="w-full md:w-[350px] relative overflow-hidden">
                <?php if ($promo['featured_image']): ?>
                    <img src="uploads/<?php echo htmlspecialchars($promo['featured_image']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full bg-slate-200"></div>
                <?php endif; ?>
                <div class="absolute inset-0 bg-blue-600/10 flex items-center justify-center">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-full border border-white/30 flex items-center justify-center text-white text-xl">🎙️</div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Secondary Grid -->
        <?php if (!empty($posts)): ?>
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10 pt-10">
            <?php foreach ($posts as $post): ?>
            <article class="group flex flex-col space-y-5 border-b border-slate-50 pb-10 mb-10 last:border-0 last:mb-0 last:pb-0">
                <div class="relative overflow-hidden aspect-[16/10]">
                    <a href="story?title=<?php echo urlencode($post['seo_title']); ?>">
                    <?php if ($post['featured_image']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-full object-cover transition duration-500 group-hover:opacity-90">
                    <?php elseif ($post['youtube_url'] && ($vid = getYouTubeID($post['youtube_url']))): ?>
                         <div class="w-full h-full bg-slate-900 flex items-center justify-center">
                             <img src="https://img.youtube.com/vi/<?php echo $vid; ?>/maxresdefault.jpg" class="w-full h-full object-cover opacity-60">
                         </div>
                    <?php else: ?>
                        <div class="w-full h-full bg-slate-100 flex items-center justify-center text-slate-200">No Image</div>
                    <?php endif; ?>
                    </a>
                </div>
                <div class="space-y-3">
                    <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest"><?php echo htmlspecialchars($post['category_name'] ?? 'General'); ?></span>
                    <h2 class="text-xl font-black leading-tight text-slate-900 hover:text-blue-600 transition">
                        <a href="story?title=<?php echo urlencode($post['seo_title']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                    </h2>
                    <p class="text-slate-500 text-sm line-clamp-2 leading-relaxed"><?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 120)) . '...'; ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php endif; ?>

        <!-- Category Discovery Section (Always Visible) -->
        <section class="mt-20 pt-20 border-t border-slate-100">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-[0.4em] text-blue-600 mb-2">Category Discovery</h2>
                    <h3 class="text-3xl font-black text-slate-900 tracking-tighter">Explore the taxonomy.</h3>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8">
                <?php if (empty($nav_categories)): ?>
                    <div class="col-span-full py-12 text-center text-slate-300 font-bold uppercase tracking-widest text-xs">Nodes initializing...</div>
                <?php else: ?>
                    <?php foreach ($nav_categories as $cat): ?>
                    <a href="index?category=<?php echo urlencode($cat['slug']); ?>" class="group space-y-4 block">
                        <div class="aspect-square rounded-[2rem] overflow-hidden bg-slate-50 border border-slate-100 relative shadow-sm transition-all duration-500 group-hover:shadow-blue-500/20 group-hover:-translate-y-1">
                            <?php if ($cat['image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($cat['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-700 opacity-90 group-hover:opacity-100">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-slate-300 font-black text-2xl group-hover:bg-blue-50 transition duration-500">#</div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-blue-600/0 group-hover:bg-blue-600/5 transition duration-500"></div>
                        </div>
                        <span class="block text-[11px] font-black text-slate-900 uppercase tracking-tight group-hover:text-blue-600 transition text-center"><?php echo htmlspecialchars($cat['name']); ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-white py-20 mt-32">
        <div class="max-w-[1400px] mx-auto px-6 grid grid-cols-1 lg:grid-cols-4 gap-16">
            <div class="lg:col-span-2 space-y-8">
                <h3 class="text-4xl font-[900] tracking-tighter">STORYLINE NEWS</h3>
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
        <div class="max-w-[1400px] mx-auto px-6 mt-20 pt-8 border-t border-white/5 text-center text-slate-500 text-sm font-bold">
            © <?php echo date('Y'); ?> STORYLINE NEWS. All rights reserved.
        </div>
    </footer>

</body>
</html>
