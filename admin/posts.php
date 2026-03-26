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
require_once '../includes/functions.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
</head>
<body class="bg-gray-50 flex min-h-screen font-sans">
    <aside class="w-64 bg-slate-900 text-white flex flex-col p-6 space-y-8">
        <h2 class="text-2xl font-black text-blue-400">Storyline</h2>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">D</span><span>Dashboard</span></a>
            <a href="posts" class="flex items-center space-x-3 text-lg bg-blue-600 p-3 rounded-xl font-bold transition shadow-lg shadow-blue-500/20"><span class="w-5 h-5 flex items-center justify-center bg-white/20 rounded">P</span><span>Post Manager</span></a>
            <a href="categories" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">C</span><span>Categories</span></a>
            <a href="settings" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">S</span><span>SEO Settings</span></a>
            <a href="profile" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">U</span><span>Profile</span></a>
        </nav>
        <div class="border-t border-slate-800 pt-6"><a href="logout" class="flex items-center space-x-3 text-lg text-red-400 hover:text-red-300 transition font-semibold"><span>Logout</span></a></div>
    </aside>

    <main class="flex-grow p-10 overflow-auto">
        <div class="max-w-4xl mx-auto">
            <header class="mb-10 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-slate-800"><?php echo $id ? 'Edit Story' : 'New Story'; ?></h1>
                    <p class="text-slate-500">Draft your next big headline.</p>
                </div>
                <a href="posts" class="text-slate-400 hover:text-slate-600 font-bold">Cancel</a>
            </header>

            <form method="post" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($post['featured_image']); ?>">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Story Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required class="w-full px-4 py-4 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-lg font-bold outline-none" placeholder="Enter a catchy title...">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                        <select name="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $post['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">SEO URL Slug</label>
                        <input type="text" name="seo_title" value="<?php echo htmlspecialchars($post['seo_title']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-slate-500 font-mono" placeholder="my-awesome-story">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Featured Image</label>
                            <div class="space-y-4">
                                <div id="image-preview-container" class="relative group">
                                    <div id="image-preview" class="w-full aspect-video rounded-3xl bg-slate-50 border-2 border-dashed border-slate-200 overflow-hidden flex items-center justify-center relative">
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
                                <input type="file" name="featured_image" id="post-image" class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-6 file:rounded-full file:border-0 file:text-sm file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition cursor-pointer shadow-xl shadow-blue-500/20">
                            </div>
                        </div>
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">YouTube Video URL</label>
                                <input type="text" name="youtube_url" value="<?php echo htmlspecialchars($post['youtube_url']); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="https://youtube.com/watch?v=...">
                                <p class="text-[10px] text-slate-400 mt-2 italic">Provide a link to embed a video instead of an image.</p>
                            </div>
                            <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100">
                                <h4 class="text-blue-800 font-bold text-sm mb-2 flex items-center">
                                    <span class="mr-2">💡</span> Pro Tip
                                </h4>
                                <p class="text-blue-700 text-xs leading-relaxed">
                                    Use a high-quality 16:9 aspect ratio image for the best visual impact on the homepage hero section.
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
                    <div class="bg-slate-50 p-6 rounded-2xl space-y-4">
                        <h3 class="font-bold text-slate-800">SEO Metadata</h3>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Meta Title</label>
                            <input type="text" name="meta_title" value="<?php echo htmlspecialchars($post['meta_title']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none" placeholder="SEO Title">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Meta Keywords</label>
                            <input type="text" name="meta_keywords" value="<?php echo htmlspecialchars($post['meta_keywords']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none" placeholder="keyword1, keyword2">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Meta Description</label>
                            <textarea name="meta_description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Brief description for search engines..."><?php echo htmlspecialchars($post['meta_description']); ?></textarea>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Content</label>
                        <textarea name="content" rows="12" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Tell your story..."><?php echo htmlspecialchars($post['content']); ?></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-xl hover:bg-blue-700 transition shadow-xl shadow-blue-500/20">
                        <?php echo $id ? 'Update Story' : 'Publish Story'; ?>
                    </button>
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
</head>
<body class="bg-gray-50 flex h-screen font-sans">
    <aside class="w-64 bg-slate-900 text-white flex flex-col p-6 space-y-8">
        <h2 class="text-2xl font-black text-blue-400">Storyline</h2>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">D</span><span>Dashboard</span></a>
            <a href="posts" class="flex items-center space-x-3 text-lg bg-blue-600 p-3 rounded-xl font-bold transition shadow-lg shadow-blue-500/20"><span class="w-5 h-5 flex items-center justify-center bg-white/20 rounded">P</span><span>Post Manager</span></a>
            <a href="categories" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">C</span><span>Categories</span></a>
            <a href="settings" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">S</span><span>SEO Settings</span></a>
            <a href="profile" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">U</span><span>Profile</span></a>
        </nav>
        <div class="border-t border-slate-800 pt-6"><a href="logout" class="flex items-center space-x-3 text-lg text-red-400 hover:text-red-300 transition font-semibold"><span>Logout</span></a></div>
    </aside>

    <main class="flex-grow p-10 overflow-auto">
        <div class="max-w-6xl mx-auto">
            <header class="flex justify-between items-center mb-12">
                <div>
                    <h1 class="text-3xl font-black text-slate-800">Your Stories</h1>
                    <p class="text-slate-500">Manage all your published content.</p>
                </div>
                <a href="posts?action=add" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-bold hover:bg-blue-700 transition shadow-xl shadow-blue-500/10">+ New Story</a>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (empty($posts)): ?>
                    <div class="col-span-full bg-white p-20 rounded-[3rem] text-center border-2 border-dashed border-slate-200">
                        <p class="text-slate-400 font-black text-xl italic">The archives are empty. Ready to publish?</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($posts as $post): ?>
                <div class="bg-white rounded-[2.5rem] p-6 shadow-sm border border-slate-100 hover:shadow-xl hover:shadow-blue-500/5 transition duration-500 flex flex-col group">
                    <div class="relative aspect-video rounded-3xl overflow-hidden mb-6 bg-slate-50">
                        <?php if ($post['featured_image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-200 italic font-black text-sm uppercase">No Featured Image</div>
                        <?php endif; ?>
                        <div class="absolute top-4 left-4 bg-white/90 backdrop-blur-md px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest text-blue-600 shadow-sm">
                            <?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?>
                        </div>
                    </div>

                    <div class="flex-grow space-y-3 px-2">
                        <div class="flex justify-between items-start">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                        </div>
                        <h3 class="text-xl font-black text-slate-800 leading-tight line-clamp-2"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="text-xs text-slate-400 font-mono truncate">/<?php echo htmlspecialchars($post['seo_title']); ?></p>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-50 flex items-center justify-between px-2">
                        <a href="posts?action=edit&id=<?php echo $post['id']; ?>" class="bg-slate-900 text-white px-6 py-2.5 rounded-2xl text-xs font-black hover:bg-blue-600 transition duration-300">Edit Story</a>
                            <form action="posts?action=delete&id=<?php echo $post['id']; ?>" method="POST" onsubmit="return confirm('Archive this story forever?')" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <button type="submit" class="text-red-400 p-2 hover:bg-red-50 rounded-xl transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>
        <?php
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
