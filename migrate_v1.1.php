<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!file_exists(__DIR__ . '/includes/config.php')) {
    die("Config file not found. Please run the installer first.");
}

require_once __DIR__ . '/includes/config.php';

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("Access denied. Please log in as an administrator to run this script.");
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.\n";

    // 1. Create Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        image VARCHAR(255) NULL
    )");
    echo "Categories table checked/created.\n";

    // 1.1 Ensure image column exists in categories
    $catCols = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM categories");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $catCols[] = $row['Field'];
    }
    if (!in_array('image', $catCols)) {
        $pdo->exec("ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL AFTER slug");
        echo "Column 'image' added to categories table.\n";
    }

    // 2. Update Posts Table
    $columnsToAdd = [
        'seo_title' => "VARCHAR(255) AFTER content",
        'featured_image' => "VARCHAR(255) AFTER seo_title",
        'youtube_url' => "VARCHAR(255) AFTER featured_image",
        'category_id' => "INT NULL AFTER youtube_url",
        'meta_title' => "VARCHAR(255) AFTER category_id",
        'meta_keywords' => "TEXT AFTER meta_title",
        'meta_description' => "TEXT AFTER meta_keywords"
    ];

    $existingColumns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM posts");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }

    foreach ($columnsToAdd as $col => $definition) {
        if (!in_array($col, $existingColumns)) {
            $pdo->exec("ALTER TABLE posts ADD COLUMN $col $definition");
            echo "Column '$col' added to posts table.\n";
        } else {
            echo "Column '$col' already exists in posts table.\n";
        }
    }

    // Add Foreign Key if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE posts ADD CONSTRAINT fk_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL");
        echo "Foreign key constraint added.\n";
    } catch (PDOException $e) {
        echo "Note: Foreign key constraint might already exist or could not be added: " . $e->getMessage() . "\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
?>
