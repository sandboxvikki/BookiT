<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin('manager'); // or 'admin' if only admins can access

$userId = $_SESSION['user_id'];
$query = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$query->bind_result($name, $role);
$query->fetch();
$query->close();
$nameParts = explode(' ', $name);
$initials = strtoupper(substr($nameParts[0],0,1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1],0,1)) : '');

// Handle re-approve/reject actions
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE requests SET status='approved', manager_id=?, updated_at=NOW(), decision_reason=NULL WHERE id=?");
        $stmt->bind_param("ii", $userId, $request_id);
        $stmt->execute();
        $stmt->close();
        $actionMsg = "Booking approved!";
    } elseif ($action === 'reject') {
        $reason = isset($_POST['decision_reason']) ? trim($_POST['decision_reason']) : '';
        $stmt = $conn->prepare("UPDATE requests SET status='rejected', decision_reason=?, manager_id=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sii", $reason, $userId, $request_id);
        $stmt->execute();
        $stmt->close();
        $actionMsg = "Booking rejected!";
    }
}

// Fetch all bookings
$bookings = $conn->query("
    SELECT r.*, u.name as requester, v.name as vehicle_name, v.license_plate
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    ORDER BY r.created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Bookings - BookiT</title>
    <link rel="stylesheet" href="assets/main.css">
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <div class="logo">
                <img src="assets/Logo-Fast_Kwacha.png" alt="BookiT Logo" style="height:28px;margin-right:10px;">
            </div>
            <nav>
                <a href="manager_dashboard.php">Dashboard</a>
                <a href="all_bookings.php" class="active">All Bookings</a>
                <a href="vehicles.php">Vehicle Management</a>
                <a href="reports.php">Reports</a>
                <a href="profile.php">Profile</a>
            </nav>
        </div>
        <div class="user-info">
            <span class="user-role"><?php echo htmlspecialchars($role); ?></span>
            <span class="avatar"><?php echo $initials; ?></span>
            <a href="logout.php" style="text-decoration: none; color: white; background-color: #f5aa20ff; border-radius: 5px; padding: 4px;">Logout</a>
        </div>
    </div>
    <div class="container">
        <h2 class="dashboard-title">All Bookings</h2>
        <p class="dashboard-subtitle">View, approve, or reject any booking request</p>
        <?php if ($actionMsg): ?>
            <div style="background:#232c3d;color:#fff;padding:12px 24px;border-radius:8px;margin-bottom:18px;"><?php echo $actionMsg; ?></div>
        <?php endif; ?>
        <div class="dashboard-card card">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Requester</th>
                        <th>Purpose & Destination</th>
                        <th>Date & Time</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Decision Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $bookings->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['requester'] ?? ''); ?></td>
                        <td>
                            <div class="purpose-main"><?php echo htmlspecialchars($row['purpose'] ?? ''); ?></div>
                        </td>
                        <td>
                            <?php if($row['start_datetime']): ?>
                                <?php echo date('M d, h:i A', strtotime($row['start_datetime'])); ?><br>
                                <span class="date-sub">to <?php echo date('M d, h:i A', strtotime($row['end_datetime'])); ?></span>
                            <?php else: ?>
                                <span style="color:#999;">Not specified</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="vehicle-main"><?php echo htmlspecialchars($row['vehicle_name'] ?? 'N/A'); ?></div>
                            <div class="vehicle-sub"><?php echo htmlspecialchars($row['license_plate'] ?? ''); ?></div>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span>
                        </td>
                        <td>
                            <?php if($row['status']=='rejected' && !empty($row['decision_reason'])): ?>
                                <span style="color:#e74c3c;font-size:13px;"><?php echo htmlspecialchars($row['decision_reason']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                <?php if($row['status']=='pending' || $row['status']=='rejected'): ?>
                                    <button type="submit" name="action" value="approve" class="modal-submit-btn" style="padding:6px 14px;font-size:14px;">Approve</button>
                                <?php endif; ?>
                                <?php if($row['status']=='pending' || $row['status']=='approved'): ?>
                                    <button type="button" class="modal-cancel-btn" onclick="showRejectReason(this)">Reject</button>
                                    <div class="reject-reason-box" style="display:none;margin-top:8px;">
                                        <textarea name="decision_reason" placeholder="Reason for rejection..." style="width:100%;padding:6px;"></textarea>
                                        <button type="submit" name="action" value="reject" class="modal-cancel-btn" style="margin-top:6px;">Confirm Reject</button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    function showRejectReason(btn) {
        var box = btn.parentElement.querySelector('.reject-reason-box');
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</body>
</html>