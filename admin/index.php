<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<h1>Admin Dashboard</h1>
<ul>
    <li><a href="settings.php">SEO Settings</a></li>
    <li><a href="profile.php">Profile Management</a></li>
    <li><a href="posts.php">Post Manager</a></li>
</ul>
<a href="logout.php">Logout</a> | <a href="../index">Back to Site</a>
