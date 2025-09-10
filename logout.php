<?php
session_start();
session_unset();
session_destroy();

// Prevent cached pages from being visible after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Location: index.php?logged_out=1");
exit;
