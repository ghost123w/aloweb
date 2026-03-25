<?php
session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($step === 1) {
    $php_version = phpversion();
    $pdo_loaded = extension_loaded('pdo_mysql');
    $openssl_loaded = extension_loaded('openssl');
    $errors = [];

    if (version_compare($php_version, '8.0.0', '<')) {
        $errors[] = "PHP version 8.0.0 or higher is required. Your version: $php_version";
    }
    if (!$pdo_loaded) {
        $errors[] = "The 'pdo_mysql' extension is required.";
    }
    if (!$openssl_loaded) {
        $errors[] = "The 'openssl' extension is required.";
    }

    echo "<h1>Installer - Stage 1: System Requirements</h1>";
    if (empty($errors)) {
        echo "<p style='color: green;'>All system requirements met.</p>";
        echo "<a href='index?step=2'>Next: Database Configuration</a>";
    } else {
        echo "<ul style='color: red;'>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
    }
} elseif ($step === 2) {
    echo "<h1>Installer - Stage 2: Database Configuration</h1>";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = $_POST['db_host'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        $name = $_POST['db_name'];

        try {
            $pdo = new PDO("mysql:host=$host", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` COLLATE utf8_general_ci");
            $pdo->exec("USE `$name`");

            $sql = file_get_contents('schema.sql');
            $pdo->exec($sql);

            $_SESSION['db_config'] = [
                'host' => $host,
                'user' => $user,
                'pass' => $pass,
                'name' => $name
            ];

            header("Location: index?step=3");
            exit;
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Connection failed: " . $e->getMessage() . "</p>";
        }
    }
    ?>
    <form method="post">
        <label>DB Host: <input type="text" name="db_host" value="localhost" required></label><br>
        <label>DB User: <input type="text" name="db_user" required></label><br>
        <label>DB Pass: <input type="password" name="db_pass"></label><br>
        <label>DB Name: <input type="text" name="db_name" required></label><br>
        <button type="submit">Test Connection & Create Tables</button>
    </form>
    <?php
} elseif ($step === 3) {
    echo "<h1>Installer - Stage 3: Admin Account Creation</h1>";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['admin_email'];
        $pass = $_POST['admin_pass'];
        $hashed_pass = password_hash($pass, PASSWORD_BCRYPT);

        $db = $_SESSION['db_config'];
        try {
            $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
            $stmt->execute([$email, $hashed_pass]);

            $_SESSION['admin_email'] = $email;

            header("Location: index?step=4");
            exit;
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
    ?>
    <form method="post">
        <label>Admin Email: <input type="email" name="admin_email" required></label><br>
        <label>Admin Password: <input type="password" name="admin_pass" required></label><br>
        <button type="submit">Create Admin Account</button>
    </form>
    <?php
} elseif ($step === 4) {
    echo "<h1>Installer - Stage 4: Completion</h1>";
    $db = $_SESSION['db_config'];
    $config_content = "<?php\n";
    $config_content .= "define('DB_HOST', '" . addslashes($db['host']) . "');\n";
    $config_content .= "define('DB_USER', '" . addslashes($db['user']) . "');\n";
    $config_content .= "define('DB_PASS', '" . addslashes($db['pass']) . "');\n";
    $config_content .= "define('DB_NAME', '" . addslashes($db['name']) . "');\n";
    $config_content .= "define('ADMIN_EMAIL', '" . addslashes($_SESSION['admin_email']) . "');\n";
    $config_content .= "?>";

    if (file_put_contents('../includes/config.php', $config_content)) {
        echo "<p style='color: green;'>Configuration file created successfully.</p>";
        echo "<p><strong>IMPORTANT:</strong> Delete the <code>/install</code> folder immediately for security.</p>";
        echo "<a href='../index'>Go to Homepage</a>";
    } else {
        echo "<p style='color: red;'>Failed to create configuration file. Please check permissions.</p>";
    }
}
?>
