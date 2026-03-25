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

    $stmt = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 6");
    $posts = $stmt->fetchAll();

} catch (PDOException $e) {
    // If DB is not ready or settings table missing, we might still show the page or an error
    $settings = null;
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YourStoryline - Home</title>
    <meta name="keywords" content="<?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

    <!-- Navigation -->
    <nav class="bg-white shadow-sm py-4">
        <div class="max-w-6xl mx-auto px-4 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold text-blue-600 tracking-tight">YourStoryline</a>
            <div class="space-x-6">
                <a href="index.php" class="text-gray-600 hover:text-blue-600 transition">Home</a>
                <a href="admin/login.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition font-semibold">Admin Panel</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="bg-blue-600 py-20 text-white text-center">
        <div class="max-w-4xl mx-auto px-4">
            <h1 class="text-4xl md:text-6xl font-extrabold mb-6 leading-tight">Every Story Deserves to be Told</h1>
            <p class="text-xl md:text-2xl mb-10 text-blue-100">Welcome to YourStoryline, a clean and modern platform for your personal stories and blog posts.</p>
            <a href="#stories" class="bg-white text-blue-600 px-8 py-3 rounded-full font-bold text-lg hover:bg-gray-100 transition shadow-lg">Explore Stories</a>
        </div>
    </header>

    <!-- Main Content -->
    <main id="stories" class="max-w-6xl mx-auto px-4 py-16">
        <h2 class="text-3xl font-bold mb-12 text-center text-gray-800">Latest Stories</h2>

        <?php if (empty($posts)): ?>
            <div class="text-center py-12 bg-white rounded-xl shadow-sm">
                <p class="text-xl text-gray-500 mb-4">No stories posted yet. Stay tuned!</p>
                <a href="admin/posts.php?action=add" class="text-blue-600 font-bold hover:underline">Start writing the first story →</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($posts as $post): ?>
                    <article class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition">
                        <div class="p-6">
                            <span class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-2 block"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                            <h3 class="text-xl font-bold mb-3 hover:text-blue-600 transition">
                                <a href="story.php?title=<?php echo urlencode($post['seo_title']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                            </h3>
                            <p class="text-gray-600 mb-6 line-clamp-3"><?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 150)) . '...'; ?></p>
                            <a href="story.php?title=<?php echo urlencode($post['seo_title']); ?>" class="font-bold text-blue-600 hover:text-blue-700 inline-flex items-center">Read Full Story <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12 mt-20">
        <div class="max-w-6xl mx-auto px-4 text-center">
            <h3 class="text-xl font-bold mb-6">YourStoryline</h3>
            <p class="text-gray-400 mb-8 max-w-lg mx-auto"><?php echo htmlspecialchars($settings['meta_description'] ?? 'Every story deserves a beautiful home.'); ?></p>
            <div class="flex justify-center space-x-6 mb-8 text-gray-400">
                <a href="#" class="hover:text-white transition">Twitter</a>
                <a href="#" class="hover:text-white transition">Instagram</a>
                <a href="#" class="hover:text-white transition">Facebook</a>
            </div>
            <p class="text-sm text-gray-500 border-t border-gray-700 pt-8">© <?php echo date('Y'); ?> YourStoryline. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
