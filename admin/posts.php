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

$db_error = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Stats for metrics
    $total_posts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();

    // Check if categories table exists for join
    $catTableCheck = $pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0;
    if (!$catTableCheck) {
        header("Location: index");
        exit;
    }

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            die("CSRF token validation failed.");
        }
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: posts");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            die("CSRF token validation failed.");
        }
        $title = $_POST['title'];
        $content = $_POST['content'];
        $seo_title = $_POST['seo_title'];
        if (empty($seo_title)) {
            $seo_title = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        }
        $youtube_url = $_POST['youtube_url'];
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        $meta_title = $_POST['meta_title'];
        $meta_keywords = $_POST['meta_keywords'];
        $meta_description = $_POST['meta_description'];

        $featured_image = $_POST['existing_image'] ?? '';

        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $file_ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($file_ext, $allowed_ext)) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($_FILES['featured_image']['tmp_name']);
                $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                if (in_array($mime_type, $allowed_mime)) {
                    $new_file_name = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_dir . $new_file_name)) {
                        $featured_image = $new_file_name;
                    }
                }
            }
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, seo_title = ?, featured_image = ?, youtube_url = ?, category_id = ?, meta_title = ?, meta_keywords = ?, meta_description = ? WHERE id = ?");
            $stmt->execute([$title, $content, $seo_title, $featured_image, $youtube_url, $category_id, $meta_title, $meta_keywords, $meta_description, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content, seo_title, featured_image, youtube_url, category_id, meta_title, meta_keywords, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $seo_title, $featured_image, $youtube_url, $category_id, $meta_title, $meta_keywords, $meta_description]);

            // Notification for new post to subscribers
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $site_url = "$protocol://$host";

            $stmt_sub = $pdo->query("SELECT email FROM subscribers");
            while ($subscriber = $stmt_sub->fetch()) {
                sendNotification($subscriber['email'], "New Story: $title", "Check out our new story: $title at " . "$site_url/story?title=" . urlencode($seo_title));
            }
        }
        header("Location: posts");
        exit;
    }

    if ($action === 'edit' || $action === 'add') {
        $post = ['title' => '', 'content' => '', 'seo_title' => '', 'featured_image' => '', 'youtube_url' => '', 'category_id' => null, 'meta_title' => '', 'meta_keywords' => '', 'meta_description' => ''];
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
        }

        // Fetch categories
        $stmt_cat = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $categories = $stmt_cat->fetchAll();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? 'Edit' : 'Add'; ?> Post - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0c0e14; color: #ffffff; }
        .glass-card {
            background: rgba(23, 25, 35, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            border-color: rgba(59, 130, 246, 0.3);
            background: rgba(30, 35, 50, 0.6);
            transform: translateY(-5px);
        }
        .sidebar-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-item:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        .sidebar-item.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
        input, select, textarea {
            background: rgba(255, 255, 255, 0.02) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }
        input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
        }
    </style>
</head>
<body class="flex min-h-screen relative overflow-x-hidden">
    <!-- Background Accents -->
    <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[120px] -z-10 -mr-64 -mt-64"></div>
    <div class="fixed bottom-0 left-0 w-[400px] h-[400px] bg-purple-600/10 rounded-full blur-[100px] -z-10 -ml-32 -mb-32"></div>

    <aside class="w-72 bg-[#11131a] text-slate-400 flex flex-col p-8 space-y-10 shadow-2xl fixed h-full z-50 border-r border-white/5">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl text-white shadow-lg shadow-blue-500/20">S</div>
            <h2 class="text-2xl font-black tracking-tighter text-white">Storyline</h2>
        </div>
        <nav class="flex-grow space-y-2">
            <a href="index" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2-2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Dashboard</span>
            </a>
            <a href="posts" class="sidebar-item active flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4v4h4"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16h6"></path></svg>
                <span>Post Manager</span>
            </a>
            <a href="categories" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                <span>Categories</span>
            </a>
            <a href="settings" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="profile" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>Profile</span>
            </a>
        </nav>
        <div class="border-t border-white/5 pt-6">
            <a href="logout" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold text-rose-500/80">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-grow ml-72 p-12 overflow-auto">
        <div class="max-w-5xl mx-auto">
            <header class="mb-12 flex justify-between items-end">
                <div>
                    <h1 class="text-4xl font-black text-white tracking-tighter mb-2"><?php echo $id ? 'Edit Story' : 'New Story'; ?></h1>
                    <p class="text-slate-500 font-medium">Drafting the narrative of the future.</p>
                </div>
                <a href="posts" class="text-slate-400 hover:text-white transition font-bold uppercase tracking-widest text-xs">Back to Manager</a>
            </header>

            <form method="post" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($post['featured_image']); ?>">
                <div class="glass-card p-10 rounded-[3rem] space-y-10">
                    <div class="space-y-6">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Story Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required class="w-full px-6 py-6 rounded-3xl text-2xl font-black outline-none placeholder:text-slate-800" placeholder="Enter a visionary headline...">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    <div class="space-y-6">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Taxonomy</label>
                        <select name="category_id" class="w-full px-6 py-4 rounded-2xl font-bold outline-none appearance-none !bg-[#1a1c23]">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $post['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-6">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Permalink Slug</label>
                        <input type="text" name="seo_title" value="<?php echo htmlspecialchars($post['seo_title']); ?>" class="w-full px-6 py-4 rounded-2xl outline-none font-mono text-slate-400" placeholder="story-url-path">
                    </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="space-y-6">
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Visual Asset</label>
                            <div class="space-y-6">
                                <div id="image-preview-container" class="relative group">
                                    <div id="image-preview" class="w-full aspect-video rounded-[2.5rem] bg-black/40 border-2 border-dashed border-white/10 overflow-hidden flex items-center justify-center relative">
                                        <?php if ($post['featured_image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="text-center p-6">
                                                <div class="text-slate-300 mb-2 flex justify-center">
                                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </div>
                                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Image Preview</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" id="remove-image" class="absolute top-4 right-4 bg-red-600 text-white p-2 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition duration-300 hover:bg-red-700 <?php echo $post['featured_image'] ? '' : 'hidden'; ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </div>
                                <input type="file" name="featured_image" id="post-image" class="w-full text-xs text-slate-500 file:mr-6 file:py-3 file:px-8 file:rounded-2xl file:border-0 file:text-xs file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition cursor-pointer shadow-xl shadow-blue-500/20">
                            </div>
                        </div>
                        <div class="space-y-8">
                            <div class="space-y-6">
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Video Integration</label>
                                <input type="text" name="youtube_url" value="<?php echo htmlspecialchars($post['youtube_url']); ?>" class="w-full px-6 py-4 rounded-2xl outline-none transition placeholder:text-slate-800" placeholder="https://youtube.com/watch?v=...">
                            </div>
                            <div class="p-8 rounded-[2rem] bg-gradient-to-br from-blue-600/10 to-purple-600/10 border border-white/5">
                                <h4 class="text-blue-400 font-black text-xs uppercase tracking-widest mb-3 flex items-center">
                                    <span class="mr-2">⚡</span> Optimizer
                                </h4>
                                <p class="text-slate-400 text-xs leading-relaxed font-medium">
                                    Use 16:9 cinematic assets for maximum platform impact.
                                </p>
                            </div>
                        </div>
                    </div>

                    <script>
                        const postImage = document.getElementById('post-image');
                        const preview = document.getElementById('image-preview');
                        const removeBtn = document.getElementById('remove-image');
                        const existingImageInput = document.getElementsByName('existing_image')[0];

                        postImage.addEventListener('change', function(e) {
                            const file = e.target.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = function(event) {
                                    preview.innerHTML = `<img src="${event.target.result}" class="w-full h-full object-cover">`;
                                    preview.classList.remove('border-dashed');
                                    removeBtn.classList.remove('hidden');
                                };
                                reader.readAsDataURL(file);
                            }
                        });

                        removeBtn.addEventListener('click', function() {
                            postImage.value = '';
                            existingImageInput.value = '';
                            preview.innerHTML = `
                                <div class="text-center p-6">
                                    <div class="text-slate-300 mb-2 flex justify-center">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Image Preview</p>
                                </div>
                            `;
                            preview.classList.add('border-dashed');
                            removeBtn.classList.add('hidden');
                        });
                    </script>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                        <div class="md:col-span-2 space-y-6">
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Narrative Content</label>
                            <textarea name="content" rows="15" required class="w-full px-8 py-8 rounded-[2.5rem] outline-none placeholder:text-slate-800 text-lg leading-relaxed" placeholder="Tell the stories that matter..."><?php echo htmlspecialchars($post['content']); ?></textarea>
                        </div>
                        <div class="space-y-10">
                            <div class="space-y-6">
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Meta Intelligence</label>
                                <div class="space-y-4">
                                    <input type="text" name="meta_title" value="<?php echo htmlspecialchars($post['meta_title']); ?>" class="w-full px-6 py-4 rounded-2xl outline-none text-xs font-bold" placeholder="SEO Title">
                                    <input type="text" name="meta_keywords" value="<?php echo htmlspecialchars($post['meta_keywords']); ?>" class="w-full px-6 py-4 rounded-2xl outline-none text-xs font-bold" placeholder="Keywords">
                                    <textarea name="meta_description" rows="5" class="w-full px-6 py-4 rounded-2xl outline-none text-xs font-medium" placeholder="Search description..."><?php echo htmlspecialchars($post['meta_description']); ?></textarea>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white py-6 rounded-[2rem] font-black text-xl hover:bg-blue-700 transition shadow-2xl shadow-blue-500/30 active:scale-[0.98]">
                                <?php echo $id ? 'Commit Changes' : 'Launch Narrative'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
        <?php
    } else {
        $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY created_at DESC");
        $posts = $stmt->fetchAll();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0c0e14; color: #ffffff; }
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .sidebar-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-item:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        .sidebar-item.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
    </style>
</head>
<body class="flex min-h-screen relative overflow-x-hidden">
    <!-- Background Accents -->
    <div class="fixed top-0 right-0 w-[600px] h-[600px] bg-blue-600/5 rounded-full blur-[120px] -z-10 -mr-64 -mt-64"></div>
    <div class="fixed bottom-0 left-0 w-[500px] h-[500px] bg-emerald-600/5 rounded-full blur-[100px] -z-10 -ml-32 -mb-32"></div>

    <aside class="w-72 bg-[#11131a] text-slate-400 flex flex-col p-8 space-y-10 shadow-2xl fixed h-full z-50 border-r border-white/5">
        <div class="flex items-center space-x-4">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-xl text-white shadow-lg shadow-blue-500/20">S</div>
            <h2 class="text-2xl font-black tracking-tighter text-white">Storyline</h2>
        </div>
        <nav class="flex-grow space-y-2">
            <a href="index" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2-2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v-2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Dashboard</span>
            </a>
            <a href="posts" class="sidebar-item active flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 4v4h4"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 16h6"></path></svg>
                <span>Post Manager</span>
            </a>
            <a href="categories" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 11h.01M7 15h.01M13 7h.01M13 11h.01M13 15h.01M17 7h.01M17 11h.01M17 15h.01"></path></svg>
                <span>Categories</span>
            </a>
            <a href="settings" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="profile" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>Profile</span>
            </a>
        </nav>
        <div class="border-t border-white/5 pt-6">
            <a href="logout" class="sidebar-item flex items-center space-x-4 px-5 py-3.5 rounded-2xl font-bold text-rose-500/80">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-grow ml-72 p-12 overflow-auto">
        <div class="max-w-7xl mx-auto">
            <?php if ($db_error): ?>
                <div class="bg-rose-50 border-2 border-rose-100 p-12 rounded-[3rem] text-center my-10">
                    <h2 class="text-rose-700 font-black text-3xl mb-4 italic tracking-tight">Database Connectivity Alert</h2>
                    <p class="text-rose-600 font-bold mb-10 max-w-2xl mx-auto leading-relaxed"><?php echo htmlspecialchars($db_error); ?></p>
                    <div class="flex flex-col md:flex-row items-center justify-center gap-6">
                        <a href="../install/index" class="bg-rose-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:bg-rose-700 transition shadow-xl shadow-rose-600/20">Re-run System Installer</a>
                        <a href="posts" class="text-rose-400 font-bold hover:text-rose-600 transition">Retry Connection</a>
                    </div>
                </div>
            <?php else: ?>
            <header class="flex justify-between items-end mb-16">
                <div>
                    <h1 class="text-6xl font-black text-white tracking-tighter mb-4">Archive</h1>
                    <p class="text-teal-500 font-bold uppercase tracking-[0.3em] text-[10px]">Strategic Narrative Management</p>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="hidden lg:flex items-center space-x-3 bg-white/5 border border-white/10 px-6 py-3 rounded-2xl">
                        <div class="w-2 h-2 rounded-full bg-teal-400 animate-ping"></div>
                        <span class="text-xs font-bold text-slate-300 uppercase tracking-widest">System Live</span>
                    </div>
                    <a href="posts?action=add" class="bg-blue-600 text-white px-10 py-4 rounded-[2rem] font-black text-lg hover:bg-blue-700 transition shadow-[0_0_40px_rgba(37,99,235,0.3)] active:scale-[0.98]">+ New Story</a>
                </div>
            </header>

            <!-- Mock Analytics Section to match the image aesthetic -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-16">
                <div class="glass-card p-6 rounded-[2rem] relative overflow-hidden">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Platform Velocity</span>
                    <div class="text-2xl font-black text-white">432,502</div>
                    <div class="text-[10px] text-teal-400 font-bold mt-2">+2.4% vs last week</div>
                    <div class="absolute bottom-0 left-0 w-full h-1 bg-gradient-to-r from-teal-500/0 via-teal-500/50 to-teal-500/0"></div>
                </div>
                <div class="glass-card p-6 rounded-[2rem]">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Narrative Engagement</span>
                    <div class="text-2xl font-black text-white">89.4%</div>
                    <div class="flex space-x-1 mt-3">
                        <div class="w-1.5 h-4 bg-blue-500/50 rounded-full"></div>
                        <div class="w-1.5 h-6 bg-blue-500 rounded-full"></div>
                        <div class="w-1.5 h-3 bg-blue-500/30 rounded-full"></div>
                        <div class="w-1.5 h-5 bg-blue-500/80 rounded-full"></div>
                    </div>
                </div>
                <div class="glass-card p-6 rounded-[2rem]">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Active Reach</span>
                    <div class="text-2xl font-black text-white"><?php echo $total_posts * 1250; ?></div>
                    <div class="text-[10px] text-purple-400 font-bold mt-2">Aggregated Impressions</div>
                </div>
                <div class="glass-card p-6 rounded-[2rem] flex items-center justify-center border-dashed border-white/10 hover:bg-white/5 transition cursor-pointer">
                    <span class="text-xs font-black text-slate-500 uppercase tracking-widest">+ Add Metric</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                <?php if (empty($posts)): ?>
                    <div class="col-span-full bg-white p-20 rounded-[3rem] text-center border-2 border-dashed border-slate-200 flex flex-col items-center">
                        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6 text-slate-200">
                             <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h2 class="text-slate-400 font-black text-2xl mb-2 italic tracking-tight">The archives are empty.</h2>
                        <p class="text-slate-400 font-medium mb-10 max-w-xs mx-auto text-center">Ready to publish.</p>
                        <a href="posts?action=add" class="bg-blue-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:bg-blue-700 transition shadow-xl shadow-blue-500/20">Publish Your First Story</a>
                    </div>
                <?php endif; ?>

                <?php foreach ($posts as $post): ?>
                <div class="glass-card rounded-[3rem] p-8 hover:bg-white/5 transition-all duration-500 group flex flex-col">
                    <div class="relative aspect-video rounded-[2rem] overflow-hidden mb-8 bg-black/40 shadow-inner">
                        <?php if ($post['featured_image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-1000 opacity-90 group-hover:opacity-100">
                        <?php else: ?>
                            <div class="w-full h-full flex flex-col items-center justify-center space-y-3 opacity-20">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-4 left-4 bg-blue-600/90 backdrop-blur-md px-4 py-1.5 rounded-2xl text-[10px] font-black uppercase tracking-[0.1em] text-white shadow-lg">
                            <?php echo htmlspecialchars($post['category_name'] ?? 'General'); ?>
                        </div>
                    </div>

                    <div class="flex-grow space-y-5">
                        <div class="flex items-center justify-between">
                             <div class="flex items-center space-x-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-teal-400"></div>
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                             </div>
                             <div class="text-[10px] font-bold text-slate-600">ID: #<?php echo $post['id']; ?></div>
                        </div>
                        <h3 class="text-2xl font-black text-white leading-tight tracking-tighter line-clamp-2 group-hover:text-blue-400 transition-colors"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <div class="flex items-center space-x-3 text-[10px] text-slate-500 font-mono bg-black/30 p-2.5 rounded-xl border border-white/5">
                            <span class="text-blue-500/50">URL:</span>
                            <span class="truncate">/<?php echo htmlspecialchars($post['seo_title']); ?></span>
                        </div>
                    </div>

                    <div class="mt-10 flex items-center space-x-4">
                        <a href="posts?action=edit&id=<?php echo $post['id']; ?>" class="flex-grow bg-blue-600 hover:bg-blue-500 text-white text-center py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 shadow-lg shadow-blue-600/20 active:scale-95">Edit Story</a>
                        <form action="posts?action=delete&id=<?php echo $post['id']; ?>" method="POST" onsubmit="return confirm('Erase this record from history?')" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="w-12 h-12 flex items-center justify-center text-rose-500/40 hover:text-rose-500 hover:bg-rose-500/10 border border-white/5 rounded-2xl transition-all active:scale-90">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
<?php } } catch (PDOException $e) { $db_error = $e->getMessage(); ?>
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
            <a href="posts" class="text-slate-500 font-bold hover:text-white transition uppercase text-xs tracking-widest">Retry Link</a>
        </div>
    </div>
</body>
</html>
<?php } ?>
