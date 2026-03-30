<?php
if (!file_exists(__DIR__ . '/includes/config.php')) {
    header("Location: install/index");
    exit;
}

require_once 'includes/config.php';

// Cache-Control headers for performance
header("Cache-Control: public, max-age=3600"); // 1 hour browser cache

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$settings = [];
$nav_categories = [];
$posts = [];
$hero = null;
$sidebar_posts = [];
$db_error = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    require_once 'includes/functions.php';

    $stmt_settings = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC) ?: [];

    // Fetch categories for navigation
    $stmt_nav = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $nav_categories = $stmt_nav->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ALL posts
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
        // Take next 5 for sidebar
        $sidebar_posts = array_splice($posts, 0, 5);
    }

    // Fetch Footer Menu in a single query
    $footer_stmt = $pdo->query("
        SELECT fs.id as section_id, fs.title as section_title, fl.label, fl.url
        FROM footer_sections fs
        LEFT JOIN footer_links fl ON fs.id = fl.section_id
        ORDER BY fs.sort_order ASC, fl.sort_order ASC
    ");
    $footer_data = $footer_stmt->fetchAll(PDO::FETCH_ASSOC);
    $organized_footer = [];
    foreach ($footer_data as $row) {
        $sid = $row['section_id'];
        if (!isset($organized_footer[$sid])) {
            $organized_footer[$sid] = [
                'title' => $row['section_title'],
                'links' => []
            ];
        }
        if ($row['label']) {
            $organized_footer[$sid]['links'][] = [
                'label' => $row['label'],
                'url' => $row['url']
            ];
        }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #000000; color: #ffffff; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
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
                <a href="index" class="text-4xl font-[900] tracking-tighter text-white uppercase italic">NEWS5</a>
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

    <!-- Mega Menu -->
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

            <div class="grid grid-cols-1 lg:grid-cols-3">
                <!-- Left: Hero Headline (Visible on Desktop) -->
                <div class="hidden lg:flex flex-col justify-center p-12 bg-black border-r border-white/5">
                    <span class="text-red-600 font-black uppercase tracking-widest text-[10px] mb-4 block"><?php echo htmlspecialchars($hero['category_name'] ?? 'Featured'); ?></span>
                    <h1 class="text-4xl font-black leading-tight text-white mb-8 italic">
                        <a href="story?<?php echo !empty($hero['seo_title']) ? 'title=' . urlencode($hero['seo_title']) : 'id=' . $hero['id']; ?>" class="hover:text-red-600 transition">
                            <?php echo htmlspecialchars($hero['title']); ?>
                        </a>
                    </h1>
                    <p class="text-slate-500 text-sm font-medium leading-relaxed line-clamp-3">
                        <?php echo strip_tags($hero['content']); ?>
                    </p>
                </div>

                <!-- Center: Hero Image -->
                <div class="relative aspect-video lg:aspect-auto overflow-hidden group">
                    <a href="story?<?php echo !empty($hero['seo_title']) ? 'title=' . urlencode($hero['seo_title']) : 'id=' . $hero['id']; ?>">
                        <?php if (!empty($hero['featured_image'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($hero['featured_image']); ?>" class="w-full h-full object-cover transition duration-1000 group-hover:scale-105" loading="eager">
                        <?php else: ?>
                            <div class="w-full h-full bg-zinc-900"></div>
                        <?php endif; ?>

                        <!-- Mobile Title Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent lg:hidden"></div>
                        <div class="absolute bottom-0 left-0 p-8 lg:hidden">
                            <span class="text-red-600 font-black uppercase tracking-widest text-[10px] mb-2 block"><?php echo htmlspecialchars($hero['category_name'] ?? 'Featured'); ?></span>
                            <h1 class="text-2xl font-black leading-tight text-white italic">
                                <?php echo htmlspecialchars($hero['title']); ?>
                            </h1>
                        </div>
                    </a>
                </div>

                <!-- Right: Latest Stories Sidebar -->
                <div class="bg-black p-8 lg:p-10 border-l border-white/5">
                    <h3 class="text-white font-black uppercase tracking-widest text-xs mb-8 flex items-center">
                        <span class="w-8 h-px bg-red-600 mr-3"></span> Latest Stories
                    </h3>
                    <div class="space-y-8">
                        <?php foreach ($sidebar_posts as $sp): ?>
                            <article class="group">
                                <a href="story?<?php echo !empty($sp['seo_title']) ? 'title=' . urlencode($sp['seo_title']) : 'id=' . $sp['id']; ?>" class="flex space-x-4">
                                    <div class="w-20 h-14 flex-shrink-0 overflow-hidden bg-zinc-900">
                                        <?php if ($sp['featured_image']): ?>
                                            <img src="uploads/<?php echo htmlspecialchars($sp['featured_image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition">
                                        <?php endif; ?>
                                    </div>
                                    <h4 class="text-xs font-bold leading-snug text-slate-300 group-hover:text-red-600 transition line-clamp-2 uppercase">
                                        <?php echo htmlspecialchars($sp['title']); ?>
                                    </h4>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Promotion Banner -->
            <div class="bg-red-600 py-3 px-8 text-center text-[10px] font-black uppercase tracking-[0.3em] text-white">
                Global Network Coverage • 24/7 Digital Operations • NEWS5 Premium Platform
            </div>

            <!-- News Grid -->
            <section class="p-6 md:p-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                <?php foreach ($posts as $post): ?>
                    <article class="group flex flex-col space-y-4">
                        <a href="story?<?php echo !empty($post['seo_title']) ? 'title=' . urlencode($post['seo_title']) : 'id=' . $post['id']; ?>" class="block aspect-[16/10] overflow-hidden bg-zinc-900 rounded-sm">
                            <?php if ($post['featured_image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                            <?php endif; ?>
                        </a>
                        <div class="space-y-2">
                            <span class="text-red-600 font-black uppercase tracking-widest text-[9px]"><?php echo htmlspecialchars($post['category_name'] ?? 'General'); ?></span>
                            <h2 class="text-lg font-black leading-tight text-white group-hover:text-red-600 transition italic line-clamp-2">
                                <a href="story?<?php echo !empty($post['seo_title']) ? 'title=' . urlencode($post['seo_title']) : 'id=' . $post['id']; ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </h2>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

        <?php endif; ?>

        <!-- Category Discovery Section -->
        <section class="p-12 border-t border-white/5 bg-zinc-950/50">
            <div class="flex justify-between items-end mb-12">
                <div>
                    <h2 class="text-red-600 font-black uppercase tracking-widest text-[10px] mb-2">Discovery</h2>
                    <h3 class="text-3xl font-black text-white tracking-tighter italic">Explore the taxonomy.</h3>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
                <?php foreach ($nav_categories as $cat): ?>
                    <a href="index?category=<?php echo urlencode($cat['slug']); ?>" class="group block space-y-4">
                        <div class="aspect-square rounded-2xl overflow-hidden bg-zinc-900 border border-white/5 relative shadow-2xl transition-all duration-500 group-hover:border-red-600/30 group-hover:-translate-y-1">
                            <?php if (!empty($cat['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($cat['image']); ?>" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 transition duration-700" loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-zinc-800 font-black text-4xl group-hover:text-red-600 transition">#</div>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                            <div class="absolute bottom-4 left-4">
                                <span class="text-[10px] font-black text-white uppercase tracking-tighter"><?php echo htmlspecialchars($cat['name']); ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-black border-t border-white/10 pt-20 pb-10">
        <div class="max-w-[1400px] mx-auto px-6 md:px-10">

            <!-- Social Icons Top Bar -->
            <div class="flex flex-wrap items-center gap-6 mb-12 pb-12 border-b border-white/5">
                <?php
                $socials = [
                    ['url' => $settings['youtube_url'] ?? '', 'icon' => '<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'],
                    ['url' => $settings['facebook_url'] ?? '', 'icon' => '<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'],
                    ['url' => $settings['twitter_url'] ?? '', 'icon' => '<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.045 4.126H5.078z"/></svg>'],
                    ['url' => $settings['tiktok_url'] ?? '', 'icon' => '<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.9-.32-1.89-.23-2.74.24-.81.47-1.37 1.33-1.43 2.25-.02.46.03.93.14 1.38.2.54.55 1.03 1.01 1.35.5.34 1.1.51 1.7.5.59.05 1.2-.13 1.7-.42.75-.43 1.23-1.19 1.35-2.03.06-1.92.03-3.84.04-5.76.02-3.81-.04-7.63.02-11.44z"/></svg>'],
                    ['url' => $settings['instagram_url'] ?? '', 'icon' => '<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.981 1.28.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.981-6.98.058-1.28.072-1.689.072-4.948 0-3.259-.014-3.668-.072-4.948-.2-4.353-2.612-6.782-6.98-6.981C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>'],
                    ['url' => $settings['linkedin_url'] ?? '', 'icon' => '<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>'],
                    ['url' => $settings['whatsapp_url'] ?? '', 'icon' => '<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.011c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>']
                ];
                foreach ($socials as $s): if (!empty($s['url'])): ?>
                    <a href="<?php echo htmlspecialchars($s['url']); ?>" class="text-white opacity-40 hover:opacity-100 transition">
                        <?php echo $s['icon']; ?>
                    </a>
                    <div class="w-px h-4 bg-white/10"></div>
                <?php endif; endforeach; ?>
            </div>

            <!-- Taxonomy Columns -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-12 mb-20">
                <?php foreach ($organized_footer as $section): ?>
                    <div class="space-y-6">
                        <h4 class="text-white font-bold text-lg uppercase tracking-widest text-[11px]"><?php echo htmlspecialchars($section['title']); ?></h4>
                        <ul class="space-y-3">
                            <?php foreach ($section['links'] as $fl): ?>
                                <li><a href="<?php echo htmlspecialchars($fl['url']); ?>" class="text-slate-400 hover:text-white transition text-[13px] font-medium"><?php echo htmlspecialchars($fl['label']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Bottom Legal Bar -->
            <div class="pt-10 border-t border-white/5 flex flex-wrap gap-8 text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">
                <a href="#" class="hover:text-white transition">Terms & Conditions</a>
                <a href="#" class="hover:text-white transition">Privacy & Cookies</a>
                <a href="#" class="hover:text-white transition">Privacy Options</a>
                <a href="#" class="hover:text-white transition">Accessibility</a>
                <a href="subscribe" class="hover:text-white transition">Contact Us</a>
            </div>
        </div>
    </footer>

</body>
</html>
