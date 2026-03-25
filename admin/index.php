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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - YourStoryline Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex h-screen font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col p-6 space-y-8">
        <h2 class="text-2xl font-black text-blue-400">Storyline</h2>
        <nav class="flex-grow space-y-4">
            <a href="index" class="flex items-center space-x-3 text-lg bg-blue-600 p-3 rounded-xl font-bold transition shadow-lg shadow-blue-500/20"><span class="w-5 h-5 flex items-center justify-center bg-white/20 rounded">D</span><span>Dashboard</span></a>
            <a href="posts" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">P</span><span>Post Manager</span></a>
            <a href="categories" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">C</span><span>Categories</span></a>
            <a href="settings" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">S</span><span>SEO Settings</span></a>
            <a href="profile" class="flex items-center space-x-3 text-lg text-slate-400 hover:text-white hover:bg-white/5 p-3 rounded-xl transition font-semibold"><span class="w-5 h-5 flex items-center justify-center bg-white/10 rounded">U</span><span>Profile</span></a>
        </nav>
        <div class="border-t border-slate-800 pt-6">
            <a href="logout" class="flex items-center space-x-3 text-lg text-red-400 hover:text-red-300 transition font-semibold"><span>Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-grow p-10 overflow-auto">
        <div class="max-w-4xl">
            <header class="flex justify-between items-center mb-12">
                <h1 class="text-3xl font-black text-slate-800">Welcome, Admin</h1>
                <a href="../index" class="bg-white border border-slate-200 px-6 py-2 rounded-xl text-slate-600 font-bold hover:bg-slate-50 transition shadow-sm">View Website →</a>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Statistics Cards -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center space-y-2">
                    <span class="text-slate-400 font-bold text-xs uppercase tracking-widest">Manage Stories</span>
                    <a href="posts" class="text-blue-600 font-black text-xl hover:underline">Edit Posts</a>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center space-y-2">
                    <span class="text-slate-400 font-bold text-xs uppercase tracking-widest">Taxonomy</span>
                    <a href="categories" class="text-blue-600 font-black text-xl hover:underline">Categories</a>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center space-y-2">
                    <span class="text-slate-400 font-bold text-xs uppercase tracking-widest">Settings</span>
                    <a href="settings" class="text-blue-600 font-black text-xl hover:underline">Update SEO</a>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center space-y-2">
                    <span class="text-slate-400 font-bold text-xs uppercase tracking-widest">Admin Profile</span>
                    <a href="profile" class="text-blue-600 font-black text-xl hover:underline">My Bio</a>
                </div>
            </div>

            <!-- Quick Action Area -->
            <div class="mt-12 bg-blue-600 p-10 rounded-3xl text-white shadow-2xl shadow-blue-500/20 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-black mb-2">Ready to tell a new story?</h2>
                    <p class="text-blue-100">Click the button to create a new post with featured images and videos.</p>
                </div>
                <a href="posts?action=add" class="bg-white text-blue-600 px-8 py-4 rounded-2xl font-black text-lg hover:bg-blue-50 transition shadow-xl shadow-black/5">Create New Post</a>
            </div>
        </div>
    </main>

</body>
</html>
