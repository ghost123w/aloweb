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
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }
        $image = $_POST['existing_image'] ?? '';

        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            $image = '';
        }

        // Check for duplicate slug
        if ($id) {
            $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
            $stmt_check->execute([$slug, $id]);
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
            $stmt_check->execute([$slug]);
        }

        if ($stmt_check->rowCount() > 0) {
            $error = "The slug '$slug' is already in use by another category.";
        }

        if (!isset($error) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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

        if (!isset($error)) {
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
            <?php if (isset($error)): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-100 font-bold">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
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
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Slug (Optional)</label>
                            <input type="text" name="slug" value="<?php echo htmlspecialchars($category['slug']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-slate-500 font-mono" placeholder="category-slug">
                            <p class="text-[10px] text-slate-400 mt-2 italic">Leave blank to auto-generate from name.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category Image</label>
                            <div class="flex items-center space-x-6">
                                <div id="image-preview-container" class="relative group">
                                    <div id="image-preview" class="w-24 h-24 rounded-2xl bg-slate-100 overflow-hidden border-2 border-slate-200 flex-shrink-0 flex items-center justify-center relative">
                                        <?php if ($category['image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($category['image']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-slate-300 text-xs font-bold uppercase">No Image</div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" id="remove-image-btn" class="absolute -top-2 -right-2 bg-red-600 text-white p-1.5 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition duration-300 hover:bg-red-700 <?php echo $category['image'] ? '' : 'hidden'; ?>">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </div>
                                <div class="flex-grow">
                                    <input type="file" name="image" id="cat-image" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition cursor-pointer">
                                    <p class="text-[10px] text-slate-400 mt-2 font-medium italic">Recommended: Square image, max 2MB. Preview updates instantly.</p>
                                    <input type="hidden" name="remove_image" id="remove-image-input" value="0">
                                </div>
                            </div>
                        </div>

                        <script>
                            const catImage = document.getElementById('cat-image');
                            const preview = document.getElementById('image-preview');
                            const removeBtn = document.getElementById('remove-image-btn');
                            const removeInput = document.getElementById('remove-image-input');

                            catImage.addEventListener('change', function(e) {
                                const file = e.target.files[0];
                                if (file) {
                                    const reader = new FileReader();
                                    reader.onload = function(event) {
                                        preview.innerHTML = `<img src="${event.target.result}" class="w-full h-full object-cover">`;
                                        removeBtn.classList.remove('hidden');
                                        removeInput.value = '0';
                                    };
                                    reader.readAsDataURL(file);
                                }
                            });

                            removeBtn.addEventListener('click', function() {
                                catImage.value = '';
                                removeInput.value = '1';
                                preview.innerHTML = '<div class="w-full h-full flex items-center justify-center text-slate-300 text-xs font-bold uppercase">No Image</div>';
                                removeBtn.classList.add('hidden');
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <?php if (empty($categories)): ?>
                    <div class="col-span-full bg-white p-20 rounded-[3rem] text-center border-2 border-dashed border-slate-200">
                        <p class="text-slate-400 font-black text-xl italic tracking-tight">No taxonomies defined yet.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($categories as $cat): ?>
                <div class="bg-white rounded-[2.5rem] p-6 shadow-sm border border-slate-100 hover:shadow-xl hover:shadow-blue-500/5 transition duration-500 flex flex-col group relative">
                    <div class="relative w-20 h-20 rounded-3xl overflow-hidden mb-6 bg-slate-50 mx-auto ring-4 ring-slate-50 group-hover:ring-blue-50 transition duration-500">
                        <?php if ($cat['image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($cat['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-200 font-black text-2xl">#</div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center space-y-2 mb-8">
                        <h3 class="text-xl font-black text-slate-800 leading-tight"><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p class="text-xs text-slate-400 font-mono truncate px-4">/<?php echo htmlspecialchars($cat['slug']); ?></p>
                    </div>

                    <div class="mt-auto flex items-center justify-center space-x-4 pt-6 border-t border-slate-50">
                        <a href="categories?action=edit&id=<?php echo $cat['id']; ?>" class="bg-slate-900 text-white px-6 py-2 rounded-2xl text-xs font-black hover:bg-blue-600 transition duration-300 shadow-lg shadow-black/5">Edit</a>
                        <form action="categories?action=delete&id=<?php echo $cat['id']; ?>" method="POST" onsubmit="return confirm('Delete this category? Stories will be uncategorized.')" class="inline">
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
