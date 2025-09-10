<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin('manager'); // or 'admin' if only admins can approve

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE requests SET status='approved', manager_id=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("ii", $_SESSION['user_id'], $request_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manager_dashboard.php?msg=approved");
        exit;
    } elseif ($action === 'reject') {
        $reason = isset($_POST['decision_reason']) ? trim($_POST['decision_reason']) : '';
        $stmt = $conn->prepare("UPDATE requests SET status='rejected', decision_reason=?, manager_id=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sii", $reason, $_SESSION['user_id'], $request_id);
        $stmt->execute();
        $stmt->close();
        header("Location: manager_dashboard.php?msg=rejected");
        exit;
    }
}
header("Location: manager_dashboard.php?msg=error");
exit;