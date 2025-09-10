<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin('manager');

$userId = $_SESSION['user_id'];
$query = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$query->bind_result($name, $role);
$query->fetch();
$query->close();
$nameParts = explode(' ', $name);
$initials = strtoupper(substr($nameParts[0],0,1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1],0,1)) : '');

// Summary stats
$totalBookings = $conn->query("SELECT COUNT(*) as cnt FROM requests")->fetch_assoc()['cnt'];
$pendingRequests = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE status='pending'")->fetch_assoc()['cnt'];
$approvedRequests = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE status='approved'")->fetch_assoc()['cnt'];
$completedRequests = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE status='completed'")->fetch_assoc()['cnt'];
$bookingsThisMonth = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE MONTH(created_at)=MONTH(NOW())")->fetch_assoc()['cnt'];

$totalVehicles = $conn->query("SELECT COUNT(*) as cnt FROM vehicles")->fetch_assoc()['cnt'];
$availableVehicles = $conn->query("SELECT COUNT(*) as cnt FROM vehicles WHERE status='available'")->fetch_assoc()['cnt'];
$inUseVehicles = $conn->query("SELECT COUNT(*) as cnt FROM vehicles WHERE status='in_use'")->fetch_assoc()['cnt'];

$totalUsers = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$activeUsers = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$utilizationRate = $totalVehicles > 0 ? round(($inUseVehicles / $totalVehicles) * 100) : 0;

// Pending approvals
$pending = $conn->query("SELECT r.*, u.name as requester, v.name as vehicle_name, v.license_plate
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    WHERE r.status='pending'
    ORDER BY r.created_at DESC LIMIT 5");

// Recent activity
$activity = $conn->query("SELECT r.*, u.name as requester
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - BookiT</title>
    <link rel="stylesheet" href="assets/main.css">
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <div class="logo">
                <img src="assets/Logo-Fast_Kwacha.png" alt="BookiT Logo" style="height:28px;margin-right:10px;">
            </div>
            <nav>
                <a href="admin_dashboard.php" class="active">Dashboard</a>
                <a href="all_bookings.php">All Bookings</a>
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
    <div class="container" style="max-width:1200px;margin:40px auto 0 auto;">
        <h2 class="dashboard-title">Welcome back, <?php echo htmlspecialchars($name); ?>!</h2>
        <p class="dashboard-subtitle">Here's what's happening with your bookings</p>
        <div class="dashboard-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px;margin-bottom:32px;">
            <div class="dashboard-card card">
                <b>Total Bookings</b>
                <div class="card-value" style="font-size:2rem;font-weight:700;"><?php echo $totalBookings; ?></div>
                <div class="card-desc" style="color:#888;"><?php echo $bookingsThisMonth; ?> this month</div>
            </div>
            <div class="dashboard-card card">
                <b>Pending Requests</b>
                <div class="card-value" style="font-size:2rem;font-weight:700;"><?php echo $pendingRequests; ?></div>
                <div class="card-desc" style="color:#888;">Awaiting approval</div>
            </div>
            <div class="dashboard-card card">
                <b>Approved</b>
                <div class="card-value" style="font-size:2rem;font-weight:700;"><?php echo $approvedRequests; ?></div>
                <div class="card-desc" style="color:#888;">Ready to go</div>
            </div>
            <div class="dashboard-card card">
                <b>Completed</b>
                <div class="card-value" style="font-size:2rem;font-weight:700;"><?php echo $completedRequests; ?></div>
                <div class="card-desc" style="color:#888;">Successfully finished</div>
            </div>
        </div>
        <div class="dashboard-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:32px;">
            <div class="dashboard-card card">
                <b>Vehicle Status</b>
                <div class="card-value" style="font-size:2rem;font-weight:700;"><?php echo $availableVehicles; ?>/<?php echo $totalVehicles; ?></div>
                <div class="card-desc" style="color:#888;">Available vehicles</div>
            </div>
            <div class="dashboard-card card">
                <b>In Use</b>
                <div class="card-value" style="font-size:2rem;font-weight:700;"><?php echo $inUseVehicles; ?></div>
                <div class="card-desc" style="color:#888;">Currently booked</div>
            </div>
            <div class="dashboard-card card">
                <b>Utilization Rate</b>
                <div class="card-value" style="font-size:2rem;font-weight:700;"><?php echo $utilizationRate; ?>%</div>
                <div class="card-desc" style="color:#888;">Vehicle efficiency</div>
            </div>
            
        </div>
        <div class="dashboard-row" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
            <div class="dashboard-card card">
                <b>Pending Approvals</b>
                <div style="color:#888;">Booking requests awaiting your approval</div>
                <?php while($row = $pending->fetch_assoc()): ?>
                    <div style="margin:18px 0;border-bottom:1px solid #eee;padding-bottom:10px;">
                        <div style="font-weight:600;"><?php echo htmlspecialchars($row['purpose']); ?></div>
                        <div style="color:#666;"><?php echo htmlspecialchars($row['destination'] ?? ''); ?></div>
                        <div style="font-size:14px;margin:4px 0;">
                        <span style="color:#888;font-size:13px;"><?php echo date('M d', strtotime($a['created_at'] ?? '')); ?></span>                            <span style="margin-left:12px;">🚗 <?php echo htmlspecialchars($row['vehicle_name'] . " (" . $row['license_plate'] . ")"); ?></span>
                        </div>
                        <span class="badge badge-pending">pending</span>
                        <form method="post" action="manage_requests.php" style="display:inline;">
                            <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="action" value="approve" class="modal-submit-btn">Approve</button>
                            <button type="button" class="modal-cancel-btn" onclick="showRejectReason(this)">Reject</button>
                            <div class="reject-reason-box" style="display:none;margin-top:8px;">
                                <textarea name="decision_reason" placeholder="Reason for rejection..." style="width:100%;padding:6px;"></textarea>
                                <button type="submit" name="action" value="reject" class="modal-cancel-btn" style="margin-top:6px;">Confirm Reject</button>
                            </div>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
            <div class="dashboard-card card">
                <b>Recent Activity</b>
                <div style="color:#888;">Latest updates and actions in the system</div>
                <?php while($a = $activity->fetch_assoc()): ?>
                    <div style="margin-bottom:14px;">
                        <span style="font-weight:500;"><?php echo ucfirst($a['status']); ?>:</span>
                        <span><?php echo htmlspecialchars($a['purpose']); ?></span>
                        <span style="color:#888;font-size:13px;"><?php echo date('M d', strtotime($a['created_at'])); ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
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
