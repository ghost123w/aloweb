<?php
session_start();
if (!file_exists('../includes/config.php')) {
    header("Location: ../install/index");
    exit;
}
require_once '../includes/config.php';
require_once '../includes/functions.php';

$db_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = sprintf("%06d", random_int(0, 999999));
            $expires = date("Y-m-d H:i:s", strtotime("+15 minutes"));

            // Delete existing tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            // Insert new token (OTP)
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $otp, $expires]);

            $message = "Your password reset OTP is: <b style='font-size: 24px;'>$otp</b><br><br>This OTP will expire in 15 minutes.";

            if (sendNotification($email, "Password Reset OTP", $message)) {
                $_SESSION['reset_email'] = $email;
                header("Location: reset_password");
                exit;
            } else {
                $error = "Failed to send recovery email. Please check your SMTP settings or server mail configuration.";
            }
        } else {
            $error = "Email address not found.";
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '42S02') { // Table not found
            repairDatabase($pdo);
            // Retry the original logic once after repair
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $otp = sprintf("%06d", random_int(0, 999999));
                    $expires = date("Y-m-d H:i:s", strtotime("+15 minutes"));
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                    $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")->execute([$email, $otp, $expires]);
                    $message = "Your password reset OTP is: <b style='font-size: 24px;'>$otp</b><br><br>This OTP will expire in 15 minutes.";
                    if (sendNotification($email, "Password Reset OTP", $message)) {
                        $_SESSION['reset_email'] = $email;
                        header("Location: reset_password");
                        exit;
                    }
                }
            } catch (PDOException $ex) {
                $db_error = $ex->getMessage();
            }
        } else {
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
    <title>Forgot Password - YourStoryline</title>
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
                <a href="forgot_password" class="block mt-6 text-slate-500 text-[10px] font-black uppercase tracking-widest hover:text-white transition">Retry Connection</a>
            </div>
        <?php else: ?>
        <div class="text-center mb-12">
            <h1 class="text-4xl font-black text-white tracking-tighter mb-2 italic">Recover Access</h1>
            <p class="text-slate-500 font-bold uppercase tracking-[0.2em] text-[10px]">Credential Restoration Protocol</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-rose-500/10 text-rose-400 p-5 rounded-2xl mb-8 border border-rose-500/20 text-xs font-bold flex items-center space-x-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-teal-500/10 text-teal-400 p-5 rounded-2xl mb-8 border border-teal-500/20 text-xs font-bold flex items-center space-x-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-8">
            <div class="space-y-3">
                <label class="block text-[10px] font-black text-slate-500 ml-1 uppercase tracking-[0.2em]">Registered Email</label>
                <input type="email" name="email" required class="w-full px-6 py-5 rounded-2xl outline-none transition font-bold" placeholder="archivist@storyline.io">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-6 rounded-2xl font-black text-xl hover:bg-blue-700 transition shadow-2xl shadow-blue-500/30 active:scale-[0.98]">
                Send OTP
            </button>
        </form>

        <div class="mt-12 text-center">
            <a href="login" class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-600 hover:text-white transition">← Back to Portal</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
