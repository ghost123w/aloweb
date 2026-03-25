<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
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

        if ($id) {
            $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, seo_title = ? WHERE id = ?");
            $stmt->execute([$title, $content, $seo_title, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content, seo_title) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $seo_title]);

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
        $post = ['title' => '', 'content' => '', 'seo_title' => ''];
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
        }
        ?>
        <h1><?php echo $id ? 'Edit' : 'Add'; ?> Post</h1>
        <form method="post">
            <label>Title:<br><input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required></label><br>
            <label>SEO Title:<br><input type="text" name="seo_title" value="<?php echo htmlspecialchars($post['seo_title']); ?>"></label><br>
            <label>Content:<br><textarea name="content" rows="10" cols="50" required><?php echo htmlspecialchars($post['content']); ?></textarea></label><br>
            <button type="submit">Save Post</button>
        </form>
        <a href="posts.php">Cancel</a>
        <?php
    } else {
        $stmt = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC");
        $posts = $stmt->fetchAll();
        ?>
        <h1>Post Manager</h1>
        <a href="posts.php?action=add">Add New Post</a>
        <table border="1">
            <tr>
                <th>Title</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($posts as $post): ?>
            <tr>
                <td><?php echo htmlspecialchars($post['title']); ?></td>
                <td><?php echo $post['created_at']; ?></td>
                <td>
                    <a href="posts.php?action=edit&id=<?php echo $post['id']; ?>">Edit</a>
                    <a href="posts.php?action=delete&id=<?php echo $post['id']; ?>" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <a href="index.php">Back to Dashboard</a>
        <?php
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
