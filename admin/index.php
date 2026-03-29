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

$migration_needed = false;
$total_posts = 0;
$total_categories = 0;
$total_subscribers = 0;
$cat_stats = [];
$db_error = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initial check for columns via functions.php repair
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

    // Only run full stats if tables exist
    if ($pdo->query("SHOW TABLES LIKE 'posts'")->rowCount() > 0) {
        $total_posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
    }

    if (!$migration_needed) {
        $total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $cat_stats = $pdo->query("SELECT c.name, COUNT(p.id) as count FROM categories c LEFT JOIN posts p ON c.id = p.category_id GROUP BY c.id")->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($pdo->query("SHOW TABLES LIKE 'subscribers'")->rowCount() > 0) {
        $total_subscribers = $pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn();
    }

    if (isset($_GET['repair'])) {
        if (repairDatabase($pdo)) {
            header("Location: index?repaired=1");
            exit;
        }
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

renderAdminHeader("Dashboard", "dashboard");
?>
    <!-- Main Content -->
    <main class="flex-grow ml-0 lg:ml-72 p-6 lg:p-12 mt-16 lg:mt-0 overflow-auto">
        <div class="max-w-6xl mx-auto">
            <?php if ($db_error): ?>
                <div class="bg-rose-50 border-2 border-rose-100 p-12 rounded-[3rem] text-center my-10">
                    <h2 class="text-rose-700 font-black text-3xl mb-4 italic tracking-tight">Database Connectivity Alert</h2>
                    <p class="text-rose-600 font-bold mb-10 max-w-2xl mx-auto leading-relaxed"><?php echo htmlspecialchars($db_error); ?></p>
                    <div class="flex flex-col md:flex-row items-center justify-center gap-6">
                        <a href="../install/index" class="bg-rose-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:bg-rose-700 transition shadow-xl shadow-rose-600/20">Re-run System Installer</a>
                        <a href="index" class="text-rose-400 font-bold hover:text-rose-600 transition">Retry Connection</a>
                    </div>
                </div>
            <?php else: ?>

            <?php if (isset($_GET['repaired'])): ?>
                <div class="bg-teal-50 border-2 border-teal-200 p-8 rounded-3xl mb-12 flex items-center justify-between">
                    <div>
                        <h2 class="text-teal-800 font-black text-xl mb-1">System Repaired</h2>
                        <p class="text-teal-700">Database schema has been successfully synchronized.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($migration_needed): ?>
                <div class="bg-amber-50 border-2 border-amber-200 p-8 rounded-3xl mb-12 flex items-center justify-between">
                    <div>
                        <h2 class="text-amber-800 font-black text-xl mb-1">Database Update Required</h2>
                        <p class="text-amber-700">Your installation needs a quick update to support the new Category and SEO features.</p>
                    </div>
                    <div class="flex space-x-4">
                        <a href="index?repair=1" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-black hover:bg-blue-700 transition shadow-xl shadow-blue-600/20 text-center">Auto-Repair</a>
                        <a href="../migrate_v1.1.php" class="bg-amber-600 text-white px-8 py-3 rounded-2xl font-black hover:bg-amber-700 transition shadow-xl shadow-amber-600/20 text-center">Run Migration</a>
                    </div>
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
                <div class="neu-outer p-10 rounded-[3rem] relative group border-none glow-blue">
                    <span class="text-slate-500 font-black text-[10px] uppercase tracking-[0.4em] block mb-8">Narrative Volume</span>
                    <div class="flex items-baseline space-x-3">
                        <span class="text-7xl font-extrabold text-white leading-none tracking-tighter"><?php echo $total_posts; ?></span>
                        <span class="text-blue-500 font-black text-[10px] uppercase">Units</span>
                    </div>
                </div>
                <div class="neu-outer p-10 rounded-[3rem] relative group border-none glow-purple">
                    <span class="text-slate-500 font-black text-[10px] uppercase tracking-[0.4em] block mb-8">Taxonomy Depth</span>
                    <div class="flex items-baseline space-x-3">
                        <span class="text-7xl font-extrabold text-white leading-none tracking-tighter"><?php echo $total_categories; ?></span>
                        <span class="text-purple-500 font-black text-[10px] uppercase">Nodes</span>
                    </div>
                </div>
                <div class="neu-outer p-10 rounded-[3rem] relative group border-none glow-teal">
                    <span class="text-slate-500 font-black text-[10px] uppercase tracking-[0.4em] block mb-8">Audience reach</span>
                    <div class="flex items-baseline space-x-3">
                        <span class="text-7xl font-extrabold text-white leading-none tracking-tighter"><?php echo $total_subscribers; ?></span>
                        <span class="text-teal-500 font-black text-[10px] uppercase">Active</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-12">
                <!-- Content Distributor (Neumorphic Design) -->
                <div class="lg:col-span-3 neu-outer p-12 rounded-[4rem]">
                    <div class="flex justify-between items-center mb-12 px-2">
                        <div>
                            <h3 class="text-2xl font-black text-white tracking-tighter">Content Distributor</h3>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Cross-Platform Asset Analysis</p>
                        </div>
                        <div class="w-12 h-12 neu-button-modern rounded-2xl flex items-center justify-center text-blue-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                    </div>
                    <div class="neu-inner p-10 rounded-[3rem]">
                        <div class="h-80">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Area -->
                <div class="lg:col-span-2 space-y-12">
                    <div class="neu-outer p-12 rounded-[4rem] text-white relative overflow-hidden h-full flex flex-col justify-between group">
                        <div class="relative z-10">
                            <div class="w-12 h-12 bg-blue-600 rounded-2xl mb-8 flex items-center justify-center shadow-[0_10px_20px_rgba(37,99,235,0.4)]">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            </div>
                            <h2 class="text-4xl font-black mb-4 leading-tight tracking-tighter italic">Forge new <br><span class="text-blue-500">narratives.</span></h2>
                            <p class="text-slate-500 font-bold text-[10px] uppercase tracking-[0.3em] mb-12">System Kernel v1.2</p>
                        </div>
                        <a href="posts?action=add" class="neu-button-modern text-white px-8 py-6 rounded-[2.5rem] font-black text-lg hover:text-blue-500 transition-all duration-300 text-center relative z-10 border border-white/5">
                            Initialize Protocol
                        </a>
                        <!-- Glow effect -->
                        <div class="absolute -bottom-20 -right-20 w-80 h-80 bg-blue-600/10 rounded-full blur-[80px] group-hover:bg-blue-600/20 transition-all duration-1000"></div>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                                ticks: { color: '#334155', font: { weight: '800', size: 10 } }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#334155', font: { weight: '800', size: 10 } }
                            }
                        }
                    }
                });
            </script>
            <?php endif; ?>
        </div>
    </main>
<?php renderAdminFooter(); ?>
