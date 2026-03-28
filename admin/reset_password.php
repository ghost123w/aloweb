<?php
session_start();
if (!file_exists('../includes/config.php')) {
    header("Location: ../install/index");
    exit;
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db_error = null;
$error = null;
$success = null;
$email = $_SESSION['reset_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $otp = $_POST['otp'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
            $stmt->execute([$email, $otp]);
            $reset = $stmt->fetch();

            if ($reset) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $pdo->beginTransaction();

                // Update user password
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_password, $email]);

                // Delete used token
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);

                $pdo->commit();
                $success = "Password updated successfully. You can now login.";
                unset($_SESSION['reset_email']);
            } else {
                $error = "Invalid or expired OTP.";
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $db_error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - YourStoryline</title>
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
    <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[120px] -z-10 -mr-64 -mt-64"></div>
    <div class="fixed bottom-0 left-0 w-[400px] h-[400px] bg-purple-600/10 rounded-full blur-[100px] -z-10 -ml-32 -mb-32"></div>

    <div class="glass-card p-12 rounded-[3rem] w-full max-w-md relative z-10 border border-white/5">
        <?php if ($db_error): ?>
             <div class="text-center">
                <div class="w-16 h-16 bg-rose-600/10 rounded-2xl flex items-center justify-center mx-auto mb-6 text-rose-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h2 class="text-2xl font-black text-white mb-4">Connectivity Error</h2>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-10"><?php echo htmlspecialchars($db_error); ?></p>
                <a href="../install/index" class="block w-full bg-rose-600 text-white py-4 rounded-2xl font-black hover:bg-rose-700 transition">Launch Installer</a>
            </div>
        <?php else: ?>
        <div class="text-center mb-12">
            <h1 class="text-4xl font-black text-white tracking-tighter mb-2 italic">Reset Password</h1>
            <p class="text-slate-500 font-bold uppercase tracking-[0.2em] text-[10px]">Security Credential Override</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/10 text-rose-400 p-5 rounded-2xl mb-8 border border-rose-500/20 text-xs font-bold flex items-center space-x-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-teal-500/10 text-teal-400 p-5 rounded-2xl mb-8 border border-teal-500/20 text-xs font-bold flex items-center space-x-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span><?php echo $success; ?></span>
            </div>
            <div class="mt-8 text-center">
                <a href="login" class="inline-block bg-blue-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:bg-blue-700 transition shadow-xl shadow-blue-600/20">Go to Login</a>
            </div>
        <?php else: ?>
        <form method="post" class="space-y-6">
            <div class="space-y-3">
                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required class="w-full px-6 py-4 rounded-2xl outline-none transition font-bold" placeholder="archivist@storyline.io">
            </div>
            <div class="space-y-3">
                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">6-Digit OTP</label>
                <input type="text" name="otp" required maxlength="6" class="w-full px-6 py-4 rounded-2xl outline-none transition font-bold text-center text-2xl tracking-[0.5em]" placeholder="000000">
            </div>
            <div class="space-y-3">
                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">New Password</label>
                <input type="password" name="password" required class="w-full px-6 py-4 rounded-2xl outline-none transition font-bold" placeholder="••••••••">
            </div>
            <div class="space-y-3">
                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="w-full px-6 py-4 rounded-2xl outline-none transition font-bold" placeholder="••••••••">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-6 rounded-2xl font-black text-xl hover:bg-blue-700 transition shadow-2xl shadow-blue-500/30 active:scale-[0.98]">
                Establish New Key
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-12 text-center">
            <a href="forgot_password" class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-600 hover:text-white transition">Resend OTP</a>
            <span class="mx-3 text-slate-800">|</span>
            <a href="login" class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-600 hover:text-white transition">Back to Portal</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
