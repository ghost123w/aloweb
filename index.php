<?php
if (!file_exists(__DIR__ . '/includes/config.php')) {
    header("Location: install/index.php");
    exit;
}
// Normal website logic follows...
?>
