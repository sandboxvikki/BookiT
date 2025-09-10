<?php
session_start();

function checkLogin($requiredRole = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }

    // Prevent cached pages after logout
    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
    header("Pragma: no-cache"); // HTTP 1.0
    header("Expires: 0"); // Proxies

    // Role-based dashboard redirection
    if ($requiredRole) {
        include("db.php");
        $userId = $_SESSION['user_id'];
        $query = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $query->bind_param("i", $userId);
        $query->execute();
        $query->bind_result($role);
        $query->fetch();
        $query->close();

        if ($role !== $requiredRole) {
            // Redirect to correct dashboard
            if ($role === 'employee') {
                header("Location: dashboard.php");
            } elseif ($role === 'manager') {
                header("Location: manager_dashboard.php");
            } elseif ($role === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: logout.php");
            }
            exit;
        }
    }
}
?>
