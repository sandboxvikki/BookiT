<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin();

$userId = $_SESSION['user_id'];

// Get user info for topbar
$query = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$query->bind_result($name, $role);
$query->fetch();
$query->close();
$nameParts = explode(' ', $name);
$initials = strtoupper(substr($nameParts[0],0,1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1],0,1)) : '');

// Handle search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$where = "WHERE r.user_id = $userId";
if ($search) {
    $searchEsc = $conn->real_escape_string($search);
    $where .= " AND (r.purpose LIKE '%$searchEsc%' OR v.name LIKE '%$searchEsc%' OR v.license_plate LIKE '%$searchEsc%')";
}
if ($status && $status != 'all') {
    $statusEsc = $conn->real_escape_string($status);
    $where .= " AND r.status = '$statusEsc'";
}

$sql = "SELECT r.*, v.name as vehicle_name, v.license_plate 
        FROM requests r 
        LEFT JOIN vehicles v ON r.vehicle_id = v.id 
        $where
        ORDER BY r.start_datetime DESC";
$result = $conn->query($sql);

// Status badge styles
function badge($text, $type = 'status') {
    $colors = [
        'approved' => '#232c3d',
        'pending' => '#e67c1a',
        'rejected' => '#e74c3c'
    ];
    $color = $colors[$text] ?? '#ececec';
    $txtColor = '#fff';
    return "<span class='badge badge-$text badge-$type' style='background:$color;color:$txtColor;'>$text</span>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Booking Requests</title>
    <link rel="stylesheet" href="assets/main.css">
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <div class="logo">
                <img src="assets/Logo-Fast_Kwacha.png" alt="BookiT Logo" style="height:28px;margin-right:10px;">
            </div>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="my_requests.php" class="active">My Bookings</a>
                <a href="profile.php">Profile</a>
            </nav>
        </div>
        <div class="user-info">
            <span class="user-role"><?php echo htmlspecialchars($role); ?></span>
            <span class="avatar"><?php echo $initials; ?></span>
            <a href="logout.php" style="text-decoration: none; color: white; background-color: #f5aa20ff; border-radius: 5px; padding: 4px;">Logout</a>
        </div>
    </div>
    <div class="requests-card">
        <div class="requests-header">
            <div>
                <h2>My Booking Requests</h2>
                <p>View and manage your vehicle booking requests</p>
            </div>
            <button onclick="window.location.href='dashboard.php#requestVehicleBtn'" class="new-booking-btn">+ New Booking</button>
        </div>
        <form method="get" class="requests-actions">
            <input type="text" name="search" class="search-bar" placeholder="Search bookings..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="status" class="status-filter">
                <option value="all" <?php if($status=='all'||$status=='')echo 'selected';?>>All Status</option>
                <option value="pending" <?php if($status=='pending')echo 'selected';?>>Pending</option>
                <option value="approved" <?php if($status=='approved')echo 'selected';?>>Approved</option>
                <option value="rejected" <?php if($status=='rejected')echo 'selected';?>>Rejected</option>
            </select>
            <button type="submit" class="new-booking-btn" style="padding:8px 18px;font-size:15px;">Search</button>
        </form>
        <table class="requests-table">
            <thead>
                <tr>
                    <th>Purpose</th>
                    <th>Date & Time</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows == 0): ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:#888;">No booking requests found.</td>
                </tr>
            <?php else: ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="purpose-main"><?php echo htmlspecialchars($row['purpose']); ?></div>
                    </td>
                    <td>
                        <?php echo date('M d, h:i A', strtotime($row['start_datetime'])); ?><br>
                        <span class="date-sub">to <?php echo date('M d, h:i A', strtotime($row['end_datetime'])); ?></span>
                    </td>
                    <td>
                        <div class="vehicle-main"><?php echo htmlspecialchars($row['vehicle_name']); ?></div>
                        <div class="vehicle-sub"><?php echo htmlspecialchars($row['license_plate']); ?></div>
                    </td>
                    <td>
                        <?php echo badge($row['status'], 'status'); ?>
                        <?php if($row['status']=='rejected' && !empty($row['decision_reason'])): ?>
                            <div class="decision-reason">
                                <?php echo htmlspecialchars($row['decision_reason']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="actions-btn" title="More actions">&#x2026;</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
