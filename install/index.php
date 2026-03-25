<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Installer - Step <?php echo isset($_GET['step']) ? (int)$_GET['step'] : 1; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
<div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
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

    echo "<h1 class='text-2xl font-bold mb-4 text-gray-800'>Step 1: System Requirements</h1>";
    if (empty($errors)) {
        echo "<p class='text-green-600 font-semibold mb-4'>All system requirements met.</p>";
        echo "<a href='index.php?step=2' class='block w-full bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 transition'>Next: Database Configuration</a>";
    } else {
        echo "<ul class='text-red-500 mb-4 list-disc list-inside bg-red-50 p-4 rounded-lg'>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
        echo "<button onclick='window.location.reload()' class='w-full bg-gray-600 text-white py-2 rounded-lg hover:bg-gray-700 transition'>Retry Checks</button>";
    }
} elseif ($step === 2) {
    echo "<h1 class='text-2xl font-bold mb-4 text-gray-800'>Step 2: Database Configuration</h1>";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = $_POST['db_host'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        $name = $_POST['db_name'];

        try {
            $pdo = new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` COLLATE utf8_general_ci");
            $pdo->exec("USE `$name`");

            $sql = file_get_contents('schema.sql');
            if ($sql === false) {
                throw new Exception("Could not read schema.sql file.");
            }
            $pdo->exec($sql);

            $_SESSION['db_config'] = [
                'host' => $host,
                'user' => $user,
                'pass' => $pass,
                'name' => $name
            ];

            header("Location: index.php?step=3");
            exit;
        } catch (PDOException $e) {
            echo "<p class='bg-red-50 text-red-500 p-4 rounded-lg mb-4'>Connection failed: " . $e->getMessage() . "</p>";
        }
    }
    ?>
    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">DB Host</label>
            <input type="text" name="db_host" value="localhost" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">DB User</label>
            <input type="text" name="db_user" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">DB Pass</label>
            <input type="password" name="db_pass" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">DB Name</label>
            <input type="text" name="db_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition font-semibold">Test Connection & Create Tables</button>
    </form>
    <?php
} elseif ($step === 3) {
    echo "<h1 class='text-2xl font-bold mb-4 text-gray-800'>Step 3: Admin Account Creation</h1>";
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

            header("Location: index.php?step=4");
            exit;
        } catch (PDOException $e) {
            echo "<p class='bg-red-50 text-red-500 p-4 rounded-lg mb-4'>Error: " . $e->getMessage() . "</p>";
        }
    }
    ?>
    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Admin Email</label>
            <input type="email" name="admin_email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Admin Password</label>
            <input type="password" name="admin_pass" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition font-semibold">Create Admin Account</button>
    </form>
    <?php
} elseif ($step === 4) {
    echo "<h1 class='text-2xl font-bold mb-4 text-gray-800 text-center'>Step 4: Completion</h1>";
    $db = $_SESSION['db_config'] ?? null;
    if (!$db) {
         echo "<p class='text-red-500 text-center'>Session expired. Please restart installer.</p>";
         echo "<a href='index.php?step=1' class='block mt-4 text-blue-600 text-center'>Start Over</a>";
    } else {
        $config_content = "<?php\n";
        $config_content .= "define('DB_HOST', '" . str_replace("'", "\'", $db['host']) . "');\n";
        $config_content .= "define('DB_USER', '" . str_replace("'", "\'", $db['user']) . "');\n";
        $config_content .= "define('DB_PASS', '" . str_replace("'", "\'", $db['pass']) . "');\n";
        $config_content .= "define('DB_NAME', '" . str_replace("'", "\'", $db['name']) . "');\n";
        $config_content .= "define('ADMIN_EMAIL', '" . str_replace("'", "\'", $_SESSION['admin_email']) . "');\n";
        $config_content .= "?>";

        if (file_put_contents('../includes/config.php', $config_content)) {
            echo "<div class='text-center'>";
            echo "<p class='text-green-600 font-bold text-lg mb-2'>Successfully Installed!</p>";
            echo "<p class='text-gray-600 mb-6'>Configuration file created successfully.</p>";
            echo "<div class='bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6'>";
            echo "<p class='text-sm text-yellow-700 font-semibold'>IMPORTANT: Delete the <code>/install</code> folder immediately for security.</p>";
            echo "</div>";
            echo "<a href='../index.php' class='block w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition font-bold shadow-md'>Go to Homepage</a>";
            echo "</div>";
        } else {
            echo "<p class='bg-red-50 text-red-500 p-4 rounded-lg mb-4 text-center'>Failed to create configuration file. Please check permissions for <code>includes/</code> directory.</p>";
        }
    }
}
?>
</div>
</body>
</html>
