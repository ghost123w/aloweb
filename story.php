<?php
if (!file_exists(__DIR__ . '/includes/config.php')) {
    header("Location: install/index.php");
    exit;
}

require_once 'includes/config.php';

$title_slug = $_GET['title'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if categories table exists
    $catTableCheck = $pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0;

    if ($catTableCheck) {
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.seo_title = ? LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT *, NULL as category_name, NULL as category_slug FROM posts WHERE seo_title = ? LIMIT 1");
    }

    $stmt->execute([$title_slug]);
    $post = $stmt->fetch();

    if (!$post) {
        header("Location: index.php");
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch();
    if (!$settings) $settings = [];

} catch (PDOException $e) {
    die("Database connection failed.");
}

function getYouTubeID($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return $match[1] ?? null;
}

// SEO Fallbacks
$meta_title = !empty($post['meta_title']) ? $post['meta_title'] : $post['title'] . " - YourStoryline";
$meta_keywords = !empty($post['meta_keywords']) ? $post['meta_keywords'] : ($settings['meta_keywords'] ?? '');
$meta_description = !empty($post['meta_description']) ? $post['meta_description'] : substr(strip_tags($post['content']), 0, 160);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($meta_title); ?></title>
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3 { font-family: 'Playfair Display', serif; }
    </style>
</head>
<body class="bg-white text-slate-900">

    <!-- Navigation -->
    <nav class="border-b border-slate-100 py-6 sticky top-0 bg-white/80 backdrop-blur-md z-50">
        <div class="max-w-4xl mx-auto px-6 flex justify-between items-center">
            <a href="index" class="text-2xl font-black tracking-tighter text-slate-900 uppercase">YourStoryline</a>
            <a href="index" class="text-sm font-bold text-slate-400 hover:text-slate-900 transition uppercase tracking-widest">Back to Stories</a>
        </div>
    </nav>

    <article class="max-w-4xl mx-auto px-6 py-20">
        <header class="text-center space-y-8 mb-16">
            <div class="flex flex-col items-center space-y-4">
                <a href="index?category=<?php echo urlencode($post['category_slug'] ?? ''); ?>" class="bg-blue-600 text-white px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 transition">
                    <?php echo htmlspecialchars($post['category_name'] ?? 'General'); ?>
                </a>
                <span class="text-sm font-bold text-slate-400 uppercase tracking-[0.2em]"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
            </div>
            <h1 class="text-5xl md:text-7xl font-black leading-tight tracking-tight text-slate-900">
                <?php echo htmlspecialchars($post['title']); ?>
            </h1>
        </header>

        <?php if ($post['featured_image']): ?>
            <div class="mb-16 rounded-3xl overflow-hidden shadow-2xl">
                <img src="uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-auto object-cover">
            </div>
        <?php elseif ($post['youtube_url'] && ($vid = getYouTubeID($post['youtube_url']))): ?>
            <div class="mb-16 rounded-3xl overflow-hidden shadow-2xl aspect-video">
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?php echo $vid; ?>" frameborder="0" allowfullscreen></iframe>
            </div>
        <?php endif; ?>

        <div class="prose prose-slate prose-xl mx-auto leading-relaxed text-slate-700 space-y-8">
            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
        </div>

        <!-- Share & Footer -->
        <div class="mt-20 pt-10 border-t border-slate-100 flex justify-between items-center">
            <div class="text-sm font-bold text-slate-400 uppercase tracking-widest">Share this story</div>
            <div class="flex space-x-4">
                <a href="#" class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition">T</a>
                <a href="#" class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition">F</a>
            </div>
        </div>
    </article>

    <!-- Footer -->
    <footer class="bg-slate-900 text-white py-20 mt-20">
        <div class="max-w-4xl mx-auto px-6 text-center space-y-8">
            <h3 class="text-3xl font-black tracking-tighter">YourStoryline</h3>
            <p class="text-slate-400 text-lg max-w-md mx-auto"><?php echo htmlspecialchars($settings['meta_description'] ?? 'Curating the world\'s most compelling stories.'); ?></p>
            <div class="text-slate-500 text-sm font-bold pt-8 border-t border-white/5">
                © <?php echo date('Y'); ?> YourStoryline. Precision narrative craft.
            </div>
        </div>
    </footer>

</body>
</html>
