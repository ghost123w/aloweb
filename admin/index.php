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
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #e0e5ec; }

        .neu-flat {
            background: #e0e5ec;
            box-shadow: 9px 9px 16px rgb(163,177,198,0.6), -9px -9px 16px rgba(255,255,255, 0.5);
        }
        .neu-inset {
            background: #e0e5ec;
            box-shadow: inset 6px 6px 12px #b8b9be, inset -6px -6px 12px #ffffff;
        }
        .neu-button {
            background: #e0e5ec;
            box-shadow: 6px 6px 12px #b8b9be, -6px -6px 12px #ffffff;
            transition: all 0.2s ease;
        }
        .neu-button:active {
            box-shadow: inset 4px 4px 8px #b8b9be, inset -4px -4px 8px #ffffff;
            transform: scale(0.98);
        }
    </style>
</head>
<body class="flex min-h-screen relative overflow-x-hidden">

    <!-- Sidebar -->
    <aside class="w-72 bg-[#e0e5ec] text-slate-600 flex flex-col p-8 space-y-10 shadow-[20px_0_40px_rgba(163,177,198,0.3)] fixed h-full z-50">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 neu-button rounded-xl flex items-center justify-center font-black text-xl text-blue-600">S</div>
            <h2 class="text-2xl font-black tracking-tight text-slate-700">Storyline</h2>
        </div>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-4 px-4 py-3 rounded-xl neu-inset text-blue-600 transition font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Dashboard</span>
            </a>
            <a href="posts" class="flex items-center space-x-4 px-4 py-3 rounded-xl neu-button hover:text-blue-600 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4v4h4"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16h6"></path></svg>
                <span>Post Manager</span>
            </a>
            <a href="categories" class="flex items-center space-x-4 px-4 py-3 rounded-xl neu-button hover:text-blue-600 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                <span>Categories</span>
            </a>
            <a href="settings" class="flex items-center space-x-4 px-4 py-3 rounded-xl neu-button hover:text-blue-600 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="profile" class="flex items-center space-x-4 px-4 py-3 rounded-xl neu-button hover:text-blue-600 transition font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>Profile</span>
            </a>
        </nav>
        <div class="border-t border-slate-300 pt-6">
            <a href="logout" class="flex items-center space-x-4 px-4 py-3 rounded-xl neu-button text-rose-500 hover:text-rose-600 transition font-semibold">
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

            <header class="flex justify-between items-end mb-20">
                <div>
                    <h1 class="text-5xl font-black text-slate-700 tracking-tighter mb-4">Command Center</h1>
                    <p class="text-slate-400 font-bold uppercase tracking-[0.2em] text-xs">Strategic Narrative Intelligence Dashboard</p>
                </div>
                <a href="../index" class="neu-button px-8 py-4 rounded-2xl text-slate-600 font-black transition flex items-center space-x-3 group">
                    <span class="group-hover:text-blue-600">Visit Platform</span>
                    <svg class="w-5 h-5 group-hover:translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-20">
                <div class="neu-flat p-10 rounded-[3rem] relative overflow-hidden group border border-white/20">
                    <span class="text-slate-400 font-black text-[10px] uppercase tracking-[0.3em] block mb-6">Narrative Volume</span>
                    <div class="flex items-baseline space-x-3">
                        <span class="text-6xl font-extrabold text-slate-700 leading-none"><?php echo $total_posts; ?></span>
                        <span class="text-slate-400 font-bold text-sm">Units</span>
                    </div>
                </div>
                <div class="neu-flat p-10 rounded-[3rem] relative overflow-hidden group border border-white/20">
                    <span class="text-slate-400 font-black text-[10px] uppercase tracking-[0.3em] block mb-6">Taxonomy Depth</span>
                    <div class="flex items-baseline space-x-3">
                        <span class="text-6xl font-extrabold text-slate-700 leading-none"><?php echo $total_categories; ?></span>
                        <span class="text-slate-400 font-bold text-sm">Nodes</span>
                    </div>
                </div>
                <div class="neu-flat p-10 rounded-[3rem] relative overflow-hidden group border border-white/20">
                    <span class="text-slate-400 font-black text-[10px] uppercase tracking-[0.3em] block mb-6">Audience reach</span>
                    <div class="flex items-baseline space-x-3">
                        <span class="text-6xl font-extrabold text-slate-700 leading-none"><?php echo $total_subscribers; ?></span>
                        <span class="text-slate-400 font-bold text-sm">Active</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-12">
                <!-- Chart Area (Content Distributor) -->
                <div class="lg:col-span-3 neu-flat p-12 rounded-[4rem] border border-white/30">
                    <div class="flex justify-between items-center mb-10">
                        <h3 class="text-2xl font-black text-slate-700 tracking-tighter">Content Distribution</h3>
                        <div class="w-3 h-3 rounded-full bg-blue-500 animate-pulse"></div>
                    </div>
                    <div class="neu-inset p-8 rounded-[2.5rem]">
                        <div class="h-72">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Area -->
                <div class="lg:col-span-2 space-y-12">
                    <div class="neu-flat p-10 rounded-[3rem] text-slate-700 relative overflow-hidden h-full flex flex-col justify-between border border-white/40">
                        <div class="relative z-10">
                            <h2 class="text-3xl font-black mb-4 leading-tight tracking-tighter">Draft your next <br><span class="text-blue-600 underline decoration-blue-200 underline-offset-8">masterpiece.</span></h2>
                            <p class="text-slate-400 font-bold text-sm uppercase tracking-widest mb-12">Narrative Forge</p>
                        </div>
                        <a href="posts?action=add" class="neu-button text-slate-700 px-8 py-6 rounded-[2.5rem] font-black text-xl hover:text-blue-600 transition text-center relative z-10">
                            + Initialize Story
                        </a>
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
                            backgroundColor: '#4A90E2',
                            hoverBackgroundColor: '#357ABD',
                            borderRadius: 20,
                            barThickness: 32,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { display: false },
                                ticks: { color: '#94a3b8', font: { weight: '600' } }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#94a3b8', font: { weight: '600' } }
                            }
                        }
                    }
                });
            </script>
        </div>
    </main>

</body>
</html>
