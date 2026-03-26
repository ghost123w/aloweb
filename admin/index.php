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
$migration_needed = false;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check for categories table and image column
    $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
    if ($stmt->rowCount() == 0) {
        $migration_needed = true;
    } else {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM categories LIKE 'image'");
        if ($stmtCol->rowCount() == 0) {
            $migration_needed = true;
        }
    }

    // Stats for Dashboard
    $total_posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    $total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $total_subscribers = 0;
    if ($pdo->query("SHOW TABLES LIKE 'subscribers'")->rowCount() > 0) {
        $total_subscribers = $pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    }

    // Category distribution for Chart
    $cat_stats = $pdo->query("SELECT c.name, COUNT(p.id) as count FROM categories c LEFT JOIN posts p ON c.id = p.category_id GROUP BY c.id")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Connection error handled elsewhere or ignore for now
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] flex min-h-screen relative">
    <!-- Abstract background elements -->
    <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-blue-100/30 rounded-full blur-3xl -z-10 -mr-64 -mt-64"></div>
    <div class="fixed bottom-0 left-0 w-[400px] h-[400px] bg-purple-100/20 rounded-full blur-3xl -z-10 -ml-32 -mb-32"></div>

    <!-- Sidebar -->
    <aside class="w-72 bg-[#0F172A] text-white flex flex-col p-8 space-y-10 shadow-2xl fixed h-full z-50">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl shadow-lg shadow-blue-500/20">S</div>
            <h2 class="text-2xl font-black tracking-tight">Storyline</h2>
        </div>
        <nav class="flex-grow space-y-2">
            <a href="index" class="flex items-center space-x-4 px-4 py-3 rounded-xl bg-blue-600 text-white transition font-bold shadow-lg shadow-blue-500/20">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Dashboard</span>
            </a>
            <a href="posts" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4v4h4"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16h6"></path></svg>
                <span>Post Manager</span>
            </a>
            <a href="categories" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                <span>Categories</span>
            </a>
            <a href="settings" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="profile" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>Profile</span>
            </a>
        </nav>
        <div class="border-t border-slate-800 pt-6">
            <a href="logout" class="flex items-center space-x-4 px-4 py-3 rounded-xl text-red-400 hover:text-red-300 hover:bg-red-400/5 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow ml-72 p-12 overflow-auto">
        <div class="max-w-6xl mx-auto">
            <?php if ($migration_needed): ?>
                <div class="bg-amber-50 border-2 border-amber-200 p-8 rounded-3xl mb-12 flex items-center justify-between">
                    <div>
                        <h2 class="text-amber-800 font-black text-xl mb-1">Database Update Required</h2>
                        <p class="text-amber-700">Your installation needs a quick update to support the new Category and SEO features.</p>
                    </div>
                    <a href="../migrate_v1.1.php" class="bg-amber-600 text-white px-8 py-3 rounded-2xl font-black hover:bg-amber-700 transition shadow-xl shadow-amber-600/20">Run Migration Now</a>
                </div>
            <?php endif; ?>

            <header class="flex justify-between items-end mb-16">
                <div>
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Overview</h1>
                    <p class="text-slate-500 font-medium italic">Welcome back! Here's what's happening with your Storyline.</p>
                </div>
                <a href="../index" class="bg-white border-2 border-slate-100 px-6 py-3 rounded-2xl text-slate-600 font-bold hover:bg-slate-50 transition flex items-center space-x-2 shadow-sm">
                    <span>Visit Live Site</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-16">
                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full -mr-16 -mt-16 transition-transform group-hover:scale-110"></div>
                    <span class="text-slate-400 font-black text-[10px] uppercase tracking-[0.2em] block mb-4">Total Narrative</span>
                    <div class="flex items-end space-x-2">
                        <span class="text-5xl font-black text-slate-900 leading-none"><?php echo $total_posts; ?></span>
                        <span class="text-slate-400 font-bold mb-1">Stories</span>
                    </div>
                </div>
                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-purple-50 rounded-full -mr-16 -mt-16 transition-transform group-hover:scale-110"></div>
                    <span class="text-slate-400 font-black text-[10px] uppercase tracking-[0.2em] block mb-4">Taxonomy</span>
                    <div class="flex items-end space-x-2">
                        <span class="text-5xl font-black text-slate-900 leading-none"><?php echo $total_categories; ?></span>
                        <span class="text-slate-400 font-bold mb-1">Categories</span>
                    </div>
                </div>
                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full -mr-16 -mt-16 transition-transform group-hover:scale-110"></div>
                    <span class="text-slate-400 font-black text-[10px] uppercase tracking-[0.2em] block mb-4">Audience</span>
                    <div class="flex items-end space-x-2">
                        <span class="text-5xl font-black text-slate-900 leading-none"><?php echo $total_subscribers; ?></span>
                        <span class="text-slate-400 font-bold mb-1">Subscribers</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-10">
                <!-- Chart Area -->
                <div class="lg:col-span-3 bg-white p-10 rounded-[3rem] shadow-sm border border-slate-100">
                    <h3 class="text-xl font-black text-slate-900 mb-8 tracking-tight">Content Distribution</h3>
                    <div class="h-64">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <!-- Quick Actions Area -->
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-[#0F172A] p-10 rounded-[3rem] text-white shadow-2xl relative overflow-hidden h-full flex flex-col justify-between">
                        <div class="relative z-10">
                            <h2 class="text-3xl font-black mb-4 leading-tight">Draft your next <br><span class="text-blue-500">masterpiece.</span></h2>
                            <p class="text-slate-400 font-medium mb-8">Ready to share your story with the world?</p>
                        </div>
                        <a href="posts?action=add" class="bg-blue-600 text-white px-8 py-5 rounded-[2rem] font-black text-lg hover:bg-blue-700 transition shadow-xl shadow-blue-500/20 text-center relative z-10">
                            + Create New Post
                        </a>
                        <!-- Decorative element -->
                        <div class="absolute bottom-0 right-0 w-48 h-48 bg-blue-600/10 rounded-full -mr-24 -mb-24 blur-3xl"></div>
                    </div>
                </div>
            </div>

            <script>
                const ctx = document.getElementById('categoryChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($cat_stats, 'name')); ?>,
                        datasets: [{
                            label: 'Posts per Category',
                            data: <?php echo json_encode(array_column($cat_stats, 'count')); ?>,
                            backgroundColor: '#3B82F6',
                            borderRadius: 12,
                            barThickness: 40
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { display: false }, ticks: { font: { weight: 'bold' } } },
                            x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } }
                        }
                    }
                });
            </script>
        </div>
    </main>

</body>
</html>
