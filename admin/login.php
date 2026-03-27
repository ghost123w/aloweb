<?php
session_start();
if (!file_exists('../includes/config.php')) {
    header("Location: ../install/index.php");
    exit;
}
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['admin_email'] = $user['email'];

            require_once '../includes/functions.php';
            sendNotification($user['email'], "New Login Detected", "A login occurred at " . date('Y-m-d H:i'));

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage() . ". <br><a href='../install/index.php' class='underline text-red-700'>Click here to run installer</a> if the database is not configured.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - YourStoryline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #0c0e14; color: #ffffff; }
        .glass-card {
            background: rgba(23, 25, 35, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        input {
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
<body class="flex items-center justify-center min-h-screen relative overflow-hidden">
    <!-- Background Accents -->
    <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[120px] -z-10 -mr-64 -mt-64"></div>
    <div class="fixed bottom-0 left-0 w-[400px] h-[400px] bg-purple-600/10 rounded-full blur-[100px] -z-10 -ml-32 -mb-32"></div>

    <div class="glass-card p-12 rounded-[3rem] w-full max-w-md relative z-10 border border-white/5">
        <div class="text-center mb-12">
            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center font-black text-3xl text-white shadow-2xl shadow-blue-500/20 mx-auto mb-6">S</div>
            <h1 class="text-4xl font-black text-white tracking-tighter mb-2 italic">Command Portal</h1>
            <p class="text-slate-500 font-bold uppercase tracking-[0.2em] text-[10px]">Strategic Narrative Access</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-rose-500/10 text-rose-400 p-5 rounded-2xl mb-8 border border-rose-500/20 text-xs font-bold flex items-center space-x-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-8">
            <div class="space-y-3">
                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Signal Identifier (Email)</label>
                <input type="email" name="email" required class="w-full px-6 py-5 rounded-2xl outline-none transition font-bold" placeholder="archivist@storyline.io">
            </div>
            <div class="space-y-3">
                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Access Key (Password)</label>
                <input type="password" name="password" required class="w-full px-6 py-5 rounded-2xl outline-none transition font-bold" placeholder="••••••••">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-6 rounded-2xl font-black text-xl hover:bg-blue-700 transition shadow-2xl shadow-blue-500/30 active:scale-[0.98]">
                Establish Link
            </button>
        </form>

        <div class="mt-12 text-center">
            <a href="../index" class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-600 hover:text-white transition">← Return to Interface</a>
        </div>
    </div>
</body>
</html>
