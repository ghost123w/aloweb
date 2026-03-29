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

$db_error = null;
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
        renderAdminHeader(($id ? 'Edit' : 'Add') . " Category", "categories");
        ?>
    <main class="flex-grow ml-0 lg:ml-72 p-6 lg:p-12 mt-16 lg:mt-0 overflow-auto">
        <div class="max-w-5xl mx-auto">
            <?php if (isset($error)): ?>
                <div class="bg-rose-50/10 text-rose-400 p-6 rounded-[2rem] mb-10 border border-rose-500/20 flex items-center space-x-4 font-bold shadow-lg shadow-rose-500/5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            <header class="mb-12 flex justify-between items-end">
                <div>
                    <h1 class="text-4xl font-black text-white tracking-tighter mb-2"><?php echo $id ? 'Edit Taxonomy' : 'New Taxonomy'; ?></h1>
                    <p class="text-slate-500 font-medium">Defining the architectural nodes of the platform.</p>
                </div>
                <a href="categories" class="text-slate-400 hover:text-white transition font-bold uppercase tracking-widest text-xs">Back to Manager</a>
            </header>

            <form method="post" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($category['image']); ?>">
                <div class="glass-card p-10 rounded-[3rem] space-y-10">
                    <div class="space-y-6">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Label Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required class="w-full px-6 py-6 rounded-3xl text-2xl font-black outline-none placeholder:text-slate-800" placeholder="Enter a distinctive name...">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="space-y-6">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Permalink Slug</label>
                            <input type="text" name="slug" value="<?php echo htmlspecialchars($category['slug']); ?>" class="w-full px-6 py-4 rounded-2xl outline-none font-mono text-slate-400" placeholder="category-slug">
                            <p class="text-[10px] text-slate-500 font-bold italic px-2">Leave blank to auto-generate.</p>
                        </div>
                        <div class="space-y-6">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] ml-1">Node Asset</label>
                            <div class="flex items-center space-x-8">
                                <div id="image-preview-container" class="relative group">
                                    <div id="image-preview" class="w-24 h-24 rounded-2xl bg-black/40 overflow-hidden border-2 border-white/5 flex-shrink-0 flex items-center justify-center relative shadow-inner">
                                        <?php if ($category['image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($category['image']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="text-slate-700 font-black text-2xl opacity-20">#</div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" id="remove-image-btn" class="absolute -top-2 -right-2 bg-rose-600 text-white p-2 rounded-xl shadow-2xl opacity-0 group-hover:opacity-100 transition duration-300 hover:bg-rose-700 <?php echo $category['image'] ? '' : 'hidden'; ?>">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </div>
                                <div class="flex-grow space-y-4">
                                    <input type="file" name="image" id="cat-image" class="w-full text-[10px] text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition cursor-pointer">
                                    <input type="hidden" name="remove_image" id="remove-image-input" value="0">
                                </div>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const catImage = document.getElementById('cat-image');
                                const preview = document.getElementById('image-preview');
                                const removeBtn = document.getElementById('remove-image-btn');
                                const removeInput = document.getElementById('remove-image-input');

                                if(catImage) {
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
                                }

                                if(removeBtn) {
                                    removeBtn.addEventListener('click', function() {
                                        catImage.value = '';
                                        removeInput.value = '1';
                                        preview.innerHTML = '<div class="text-slate-700 font-black text-2xl opacity-20">#</div>';
                                        removeBtn.classList.add('hidden');
                                    });
                                }
                            });
                        </script>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-6 rounded-[2rem] font-black text-xl hover:bg-blue-700 transition shadow-2xl shadow-blue-500/30 active:scale-[0.98]">
                        <?php echo $id ? 'Update Taxonomy' : 'Create Taxonomy'; ?>
                    </button>
                </div>
            </form>
        </div>
    </main>
        <?php
        renderAdminFooter();
    } else {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $categories = $stmt->fetchAll();
        renderAdminHeader("Manage Categories", "categories");
        ?>
    <main class="flex-grow ml-0 lg:ml-72 p-6 lg:p-12 mt-16 lg:mt-0 overflow-auto">
        <div class="max-w-7xl mx-auto">
            <header class="flex justify-between items-end mb-16">
                <div>
                    <h1 class="text-6xl font-black text-white tracking-tighter mb-4">Taxonomy</h1>
                    <p class="text-slate-500 font-bold uppercase tracking-[0.3em] text-[10px]">Architectural Narrative Structure</p>
                </div>
                <a href="categories?action=add" class="bg-blue-600 text-white px-10 py-4 rounded-[2rem] font-black text-lg hover:bg-blue-700 transition shadow-2xl shadow-blue-500/30 active:scale-[0.98]">+ New Node</a>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
                <?php if (empty($categories)): ?>
                    <div class="col-span-full glass-card p-20 rounded-[3rem] text-center border-2 border-dashed border-white/5 flex flex-col items-center">
                        <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-6 text-slate-700">#</div>
                        <p class="text-slate-500 font-black text-xl italic tracking-tight">No taxonomies defined yet.</p>
                        <a href="categories?action=add" class="mt-8 bg-blue-600 text-white px-8 py-3 rounded-2xl font-black transition hover:bg-blue-700">Initialize First Node</a>
                    </div>
                <?php endif; ?>

                <?php foreach ($categories as $cat): ?>
                <div class="glass-card rounded-[3rem] p-8 hover:bg-white/5 transition-all duration-500 group flex flex-col relative">
                    <div class="relative w-24 h-24 rounded-[2rem] overflow-hidden mb-8 bg-black/40 mx-auto ring-4 ring-white/5 group-hover:ring-blue-600/30 transition-all duration-500 shadow-inner">
                        <?php if ($cat['image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($cat['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-1000 opacity-80 group-hover:opacity-100">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-slate-700 font-black text-3xl opacity-20 group-hover:opacity-40 transition-opacity">#</div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center space-y-3 mb-10">
                        <h3 class="text-2xl font-black text-white leading-tight tracking-tight group-hover:text-blue-400 transition-colors"><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p class="text-[10px] text-slate-500 font-mono truncate px-4 bg-black/30 py-2.5 rounded-xl border border-white/5 uppercase tracking-widest">/<?php echo htmlspecialchars($cat['slug']); ?></p>
                    </div>

                    <div class="mt-auto flex items-center justify-center space-x-3 pt-8 border-t border-white/5">
                        <a href="categories?action=edit&id=<?php echo $cat['id']; ?>" class="flex-grow bg-blue-600 hover:bg-blue-500 text-white text-center py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-blue-600/20 active:scale-95">Modify</a>
                        <form action="categories?action=delete&id=<?php echo $cat['id']; ?>" method="POST" onsubmit="return confirm('Erase this node from the system?')" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="w-12 h-12 flex items-center justify-center text-rose-500/40 hover:text-rose-500 hover:bg-rose-500/10 border border-white/5 rounded-2xl transition-all active:scale-90">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
        <?php
        renderAdminFooter();
    }
} catch (PDOException $e) { $db_error = $e->getMessage(); ?>
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
            <a href="categories" class="text-slate-500 font-bold hover:text-white transition uppercase text-xs tracking-widest">Retry Link</a>
        </div>
    </div>
</body>
</html>
<?php } ?>
