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

    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'categories'")->rowCount() > 0;
    if (!$tableCheck) {
        header("Location: index");
        exit;
    }

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            die("CSRF token validation failed.");
        }
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: categories");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            die("CSRF token validation failed.");
        }
        $name = $_POST['name'];
        $slug = $_POST['slug'];
        $image = $_POST['existing_image'] ?? '';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($file_ext, $allowed_ext)) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($_FILES['image']['tmp_name']);
                $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                if (in_array($mime_type, $allowed_mime)) {
                    $new_file_name = 'cat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_file_name)) {
                        $image = $new_file_name;
                    }
                }
            }
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $image, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, image) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $image]);
        }
        header("Location: categories");
        exit;
    }

    if ($action === 'edit' || $action === 'add') {
        $category = ['name' => '', 'slug' => '', 'image' => ''];
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch();
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? 'Edit' : 'Add'; ?> Category - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex min-h-screen font-sans">
    <aside class="w-64 bg-slate-900 text-white flex flex-col p-6 space-y-8">
        <h2 class="text-2xl font-black text-blue-400">Storyline</h2>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">D</span><span>Dashboard</span></a>
            <a href="posts" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">P</span><span>Post Manager</span></a>
            <a href="categories" class="flex items-center space-x-3 text-lg bg-blue-600 p-3 rounded-xl font-bold transition shadow-lg shadow-blue-500/20"><span class="w-5 h-5 flex items-center justify-center bg-white/20 rounded">C</span><span>Categories</span></a>
            <a href="settings" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">S</span><span>SEO Settings</span></a>
            <a href="profile" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">U</span><span>Profile</span></a>
        </nav>
        <div class="border-t border-slate-800 pt-6"><a href="logout" class="flex items-center space-x-3 text-lg text-red-400 hover:text-red-300 transition font-semibold"><span>Logout</span></a></div>
    </aside>

    <main class="flex-grow p-10 overflow-auto">
        <div class="max-w-4xl mx-auto">
            <header class="mb-10 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-black text-slate-800"><?php echo $id ? 'Edit Category' : 'New Category'; ?></h1>
                    <p class="text-slate-500">Organize your stories.</p>
                </div>
                <a href="categories" class="text-slate-400 hover:text-slate-600 font-bold">Cancel</a>
            </header>

            <form method="post" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($category['image']); ?>">
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required class="w-full px-4 py-4 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 text-lg font-bold outline-none" placeholder="Enter category name...">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Slug</label>
                            <input type="text" name="slug" value="<?php echo htmlspecialchars($category['slug']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-slate-500 font-mono" placeholder="category-slug">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category Image</label>
                            <div class="flex items-center space-x-6">
                                <div id="image-preview" class="w-24 h-24 rounded-2xl bg-slate-100 overflow-hidden border-2 border-slate-200 flex-shrink-0 relative">
                                    <?php if ($category['image']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($category['image']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-300 text-xs">Preview</div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow">
                                    <input type="file" name="image" id="cat-image" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition cursor-pointer">
                                    <p class="text-[10px] text-slate-400 mt-2">Recommended: Square image, max 2MB.</p>
                                </div>
                            </div>
                        </div>

                        <script>
                            document.getElementById('cat-image').addEventListener('change', function(e) {
                                const preview = document.getElementById('image-preview');
                                const file = e.target.files[0];
                                if (file) {
                                    const reader = new FileReader();
                                    reader.onload = function(event) {
                                        preview.innerHTML = `<img src="${event.target.result}" class="w-full h-full object-cover">`;
                                    };
                                    reader.readAsDataURL(file);
                                }
                            });
                        </script>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-2xl font-black text-xl hover:bg-blue-700 transition shadow-xl shadow-blue-500/20">
                        <?php echo $id ? 'Update Category' : 'Create Category'; ?>
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
        <?php
    } else {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $categories = $stmt->fetchAll();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex h-screen font-sans">
    <aside class="w-64 bg-slate-900 text-white flex flex-col p-6 space-y-8">
        <h2 class="text-2xl font-black text-blue-400">Storyline</h2>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">D</span><span>Dashboard</span></a>
            <a href="posts" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">P</span><span>Post Manager</span></a>
            <a href="categories" class="flex items-center space-x-3 text-lg bg-blue-600 p-3 rounded-xl font-bold transition shadow-lg shadow-blue-500/20"><span class="w-5 h-5 flex items-center justify-center bg-white/20 rounded">C</span><span>Categories</span></a>
            <a href="settings" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">S</span><span>SEO Settings</span></a>
            <a href="profile" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">U</span><span>Profile</span></a>
        </nav>
        <div class="border-t border-slate-800 pt-6"><a href="logout" class="flex items-center space-x-3 text-lg text-red-400 hover:text-red-300 transition font-semibold"><span>Logout</span></a></div>
    </aside>

    <main class="flex-grow p-10 overflow-auto">
        <div class="max-w-4xl mx-auto">
            <header class="flex justify-between items-center mb-12">
                <div>
                    <h1 class="text-3xl font-black text-slate-800">Categories</h1>
                    <p class="text-slate-500">Manage your story categories.</p>
                </div>
                <a href="categories?action=add" class="bg-blue-600 text-white px-8 py-3 rounded-2xl font-bold hover:bg-blue-700 transition shadow-xl shadow-blue-500/10">+ New Category</a>
            </header>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Category</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Slug</th>
                            <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="3" class="px-8 py-12 text-center text-slate-400 font-medium">No categories found.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($categories as $cat): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-8 py-6 font-bold text-slate-800">
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 overflow-hidden flex-shrink-0">
                                        <?php if ($cat['image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($cat['image']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-slate-300">#</div>
                                        <?php endif; ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-center text-slate-500 font-mono text-sm">
                                <?php echo htmlspecialchars($cat['slug']); ?>
                            </td>
                            <td class="px-8 py-6 text-right space-x-3">
                                <a href="categories?action=edit&id=<?php echo $cat['id']; ?>" class="text-blue-600 font-bold hover:text-blue-800 transition">Edit</a>
                                <form action="categories?action=delete&id=<?php echo $cat['id']; ?>" method="POST" onsubmit="return confirm('Delete this category? Stories will be uncategorized.')" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <button type="submit" class="text-red-400 font-bold hover:text-red-600 transition">Delete</button>
                                </form>
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
