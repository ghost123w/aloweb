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

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    if ($action === 'delete' && $id) {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: posts.php");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $seo_title = $_POST['seo_title'];
        $youtube_url = $_POST['youtube_url'];
        $featured_image = $_POST['existing_image'] ?? '';

        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $file_ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = 'post_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_dir . $new_file_name)) {
                    $featured_image = $new_file_name;
                }
            }
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, seo_title = ?, featured_image = ?, youtube_url = ? WHERE id = ?");
            $stmt->execute([$title, $content, $seo_title, $featured_image, $youtube_url, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content, seo_title, featured_image, youtube_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $seo_title, $featured_image, $youtube_url]);

            // Notification for new post to subscribers
            $stmt_sub = $pdo->query("SELECT email FROM subscribers");
            while ($subscriber = $stmt_sub->fetch()) {
                sendNotification($subscriber['email'], "New Story: $seo_title", "Check out our new story: $title at " . "http://yourdomain.com/" . urlencode($seo_title));
            }
        }
        header("Location: posts.php");
        exit;
    }

    if ($action === 'edit' || $action === 'add') {
        $post = ['title' => '', 'content' => '', 'seo_title' => '', 'featured_image' => '', 'youtube_url' => ''];
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
        }
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
                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($post['featured_image']); ?>">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Story Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required class="w-full px-4 py-4 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-lg font-bold outline-none" placeholder="Enter a catchy title...">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">SEO URL Slug</label>
                        <input type="text" name="seo_title" value="<?php echo htmlspecialchars($post['seo_title']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-slate-500 font-mono" placeholder="my-awesome-story">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Featured Image</label>
                            <?php if ($post['featured_image']): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-32 object-cover rounded-xl mb-4 border border-slate-100">
                            <?php endif; ?>
                            <input type="file" name="featured_image" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">YouTube Video URL</label>
                            <input type="text" name="youtube_url" value="<?php echo htmlspecialchars($post['youtube_url']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none" placeholder="https://youtube.com/watch?v=...">
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
        $stmt = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC");
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

            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Story Info</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Date</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($posts)): ?>
                            <tr>
                                <td colspan="3" class="px-8 py-12 text-center text-slate-400 font-medium">No stories found. Start publishing today!</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($posts as $post): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-8 py-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-slate-100 rounded-lg overflow-hidden flex-shrink-0">
                                        <?php if ($post['featured_image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-slate-300"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800"><?php echo htmlspecialchars($post['title']); ?></div>
                                        <div class="text-xs text-slate-400 font-mono">/<?php echo htmlspecialchars($post['seo_title']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-center text-slate-500 font-medium text-sm">
                                <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                            </td>
                            <td class="px-8 py-6 text-right space-x-3">
                                <a href="posts.php?action=edit&id=<?php echo $post['id']; ?>" class="text-blue-600 font-bold hover:text-blue-800 transition">Edit</a>
                                <a href="posts.php?action=delete&id=<?php echo $post['id']; ?>" onclick="return confirm('Archive this story forever?')" class="text-red-400 font-bold hover:text-red-600 transition">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
