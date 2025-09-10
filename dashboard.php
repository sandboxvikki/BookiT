<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin('employee');

// Get user info from database
$userId = $_SESSION['user_id'];
$query = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$query->bind_result($name, $role);
$query->fetch();
$query->close();

$nameParts = explode(' ', $name);
$initials = strtoupper(substr($nameParts[0],0,1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1],0,1)) : '');

// Handle booking feedback message
$bookingMsg = '';
if (isset($_GET['booking'])) {
    if ($_GET['booking'] === 'success') {
        $bookingMsg = "Your booking request was submitted successfully!";
    } elseif ($_GET['booking'] === 'error') {
        $bookingMsg = "There was an error submitting your booking request. Please try again.";
    } elseif ($_GET['booking'] === 'overlap') {
        $bookingMsg = "This vehicle is not available for the selected time. Please choose different dates or vehicle.";
    }
}

// Handle booking request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purpose'])) {
    $vehicle_id = isset($_POST['vehicle_id']) && $_POST['vehicle_id'] !== '' ? intval($_POST['vehicle_id']) : null;
    $start = $_POST['start_datetime'];
    $end = $_POST['end_datetime'];
    $purpose = trim($_POST['purpose']);
    $destination = isset($_POST['destination']) ? trim($_POST['destination']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'Normal';
    $passengers = isset($_POST['passengers']) ? intval($_POST['passengers']) : 1;
    $requirements = isset($_POST['requirements']) ? trim($_POST['requirements']) : '';

    // Prevent overlapping bookings or maintenance
    $overlapCount = 0;
    $maintenanceCount = 0;
    if ($vehicle_id) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM requests
            WHERE vehicle_id = ?
              AND status IN ('pending','approved')
              AND (
                (start_datetime <= ? AND end_datetime >= ?) OR
                (start_datetime <= ? AND end_datetime >= ?) OR
                (start_datetime >= ? AND end_datetime <= ?)
              )
        ");
        $stmt->bind_param("issssss", $vehicle_id, $start, $start, $end, $end, $start, $end);
        $stmt->execute();
        $stmt->bind_result($overlapCount);
        $stmt->fetch();
        $stmt->close();

        // Also check maintenance if maintenance_logs table exists
        $checkMaintenance = $conn->query("SHOW TABLES LIKE 'maintenance_logs'");
        if ($checkMaintenance->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM maintenance_logs
                WHERE vehicle_id = ?
                  AND (
                    (start_date <= ? AND end_date >= ?) OR
                    (start_date <= ? AND end_date >= ?) OR
                    (start_date >= ? AND end_date <= ?)
                  )
            ");
            $stmt->bind_param("issssss", $vehicle_id, $start, $start, $end, $end, $start, $end);
            $stmt->execute();
            $stmt->bind_result($maintenanceCount);
            $stmt->fetch();
            $stmt->close();
        }
    }

    if (($vehicle_id && ($overlapCount > 0 || $maintenanceCount > 0))) {
        header("Location: dashboard.php?booking=overlap");
        exit;
    } else {
        // Save request
        $stmt = $conn->prepare("
            INSERT INTO requests 
            (user_id, vehicle_id, start_datetime, end_datetime, purpose, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->bind_param("iisss", $user_id, $vehicle_id, $start_datetime, $end_datetime, $purpose);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: dashboard.php?booking=success");
            exit;
        } else {
            $stmt->close();
            header("Location: dashboard.php?booking=error");
            exit;
        }
    }
}

// Fetch summary data
$myBookingsCount = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE user_id = $userId")->fetch_assoc()['cnt'];
$pendingCount = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE user_id = $userId AND status = 'pending'")->fetch_assoc()['cnt'];
$approvedCount = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE user_id = $userId AND status = 'approved'")->fetch_assoc()['cnt'];
$completedCount = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE user_id = $userId AND status = 'completed'")->fetch_assoc()['cnt'];

// Get monthly count for "this month" display
$thisMonthCount = $conn->query("SELECT COUNT(*) as cnt FROM requests WHERE user_id = $userId AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc()['cnt'];

// Upcoming bookings
$upcoming = $conn->query("SELECT r.*, v.name as vehicle_name, v.license_plate 
    FROM requests r 
    LEFT JOIN vehicles v ON r.vehicle_id = v.id 
    WHERE r.user_id = $userId AND r.status IN ('pending','approved') AND r.start_datetime >= NOW()
    ORDER BY r.start_datetime ASC LIMIT 3");

// Recent activity (last 5 requests)
$activity = $conn->query("SELECT r.*, v.name as vehicle_name 
    FROM requests r 
    LEFT JOIN vehicles v ON r.vehicle_id = v.id 
    WHERE r.user_id = $userId 
    ORDER BY r.created_at DESC LIMIT 5");

// Fetch available vehicles for the modal
$vehicles = $conn->query("SELECT id, name, license_plate FROM vehicles WHERE status = 'available'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>BookiT - Dashboard</title>
    <link rel="stylesheet" href="assets/main.css">
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <div class="logo">
                <img src="assets/Logo-Fast_Kwacha.png" alt="BookiT Logo" style="height:28px;margin-right:10px;">
            </div>
            <nav>
                    <a href="dashboard.php" class="active">Dashboard</a>
                    <a href="my_requests.php">My Bookings</a>
                    <a href="profile.php">Profile</a>
                        <a href="logbook.php">Logbook</a>
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
        
        <div style="display:flex;justify-content:flex-end;margin-bottom:18px;">
            <button id="requestVehicleBtn" class="new-booking-btn">New Booking</button>
        </div>
        
        <div class="dashboard-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px;margin-bottom:32px;">
            <div class="dashboard-card" style="background:#fff;border-radius:14px;padding:28px 24px;  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                <b>My Bookings</b>
                <div class="card-value" style="font-size:2.2rem;font-weight:700;"><?php echo $myBookingsCount; ?></div>
                <div class="card-desc" style="color:#888;font-size:15px;margin-top:6px;"><?php echo $thisMonthCount; ?> this month</div>
            </div>
            <div class="dashboard-card" style="background:#fff;border-radius:14px;padding:28px 24px;  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:8px;">Pending Requests</div>
                <div style="font-size:2.2rem;font-weight:700;"><?php echo $pendingCount; ?></div>
                <div style="color:#888;font-size:15px;margin-top:6px;">Awaiting approval</div>
            </div>
            <div class="dashboard-card" style="background:#fff;border-radius:14px;padding:28px 24px;  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:8px;">Approved</div>
                <div style="font-size:2.2rem;font-weight:700;"><?php echo $approvedCount; ?></div>
                <div style="color:#888;font-size:15px;margin-top:6px;">Ready to go</div>
            </div>
            <div class="dashboard-card" style="background:#fff;border-radius:14px;padding:28px 24px;  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:8px;">Completed</div>
                <div style="font-size:2.2rem;font-weight:700;"><?php echo $completedCount; ?></div>
                <div style="color:#888;font-size:15px;margin-top:6px;">Successfully finished</div>
            </div>
        </div>

        <div class="dashboard-row" style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
            <div class="dashboard-card" style="background:#fff;border-radius:14px;padding:28px 24px;  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:12px;">My Upcoming Bookings</div>
                <?php if ($upcoming->num_rows == 0): ?>
                    <span style="color:#888;">No upcoming bookings.</span>
                <?php else: ?>
                    <?php while ($r = $upcoming->fetch_assoc()): ?>
                        <div style="margin-bottom:18px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <div style="font-weight:600;"><?php echo htmlspecialchars($r['purpose'] ?? ''); ?></div>
                            <div style="color:#666;"><?php echo htmlspecialchars($r['destination'] ?? ''); ?></div>
                            <div style="font-size:14px; margin:4px 0;">
                                <span>🗓 <?php echo date('M d, H:i', strtotime($r['start_datetime'])); ?> - <?php echo date('M d, H:i', strtotime($r['end_datetime'])); ?></span>
                                <?php if ($r['vehicle_name']): ?>
                                    <span style="margin-left:12px;">🚗 <?php echo htmlspecialchars($r['vehicle_name'] . " (" . $r['license_plate'] . ")"); ?></span>
                                <?php else: ?>
                                    <span style="margin-left:12px;">🚗 Vehicle to be assigned</span>
                                <?php endif; ?>
                            </div>
                            <span style="background:<?php echo $r['status']=='approved'?'#232c3d':'#e67c1a'; ?>;color:#fff;padding:2px 10px;border-radius:8px;font-size:13px;">
                                <?php echo $r['status']; ?>
                            </span>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
            <div class="dashboard-card" style="background:#fff;border-radius:14px;padding:28px 24px;  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);">
                <div style="font-weight:600;font-size:1.1rem;margin-bottom:12px;">Recent Activity</div>
                <?php while ($a = $activity->fetch_assoc()): ?>
                    <div style="margin-bottom:14px;">
                        <span style="font-weight:500;"><?php echo ucfirst($a['status']); ?>:</span>
                        <span><?php echo htmlspecialchars($a['purpose'] ?? ''); ?></span>
                        <div style="color:#888; font-size:13px;"><?php echo date('M d', strtotime($a['created_at'])); ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Vehicle Request Modal -->
    <div id="requestModal" class="modal-overlay" style="display:none;">
        <div class="modal-content request-modal">
            <span class="close-modal" id="closeModalBtn">&times;</span>
            <h2 style="font-size:1.35rem; font-weight:700; margin-bottom:2px;">Request Vehicle Booking</h2>
            <p style="color:#6b7280; font-size:15px; margin-bottom:18px;">Submit a new vehicle booking request for approval.</p>
            <form id="vehicleRequestForm" method="post" action="dashboard.php" autocomplete="off">
                <div class="form-group">
                    <label for="purpose">Purpose of Trip</label>
                    <input type="text" name="purpose" id="purpose" placeholder="e.g., Client meeting, site visit, training" required>
                </div>
                <div class="form-group">
                    <label for="destination">Destination</label>
                    <input type="text" name="destination" id="destination" placeholder="Enter destination address or location" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_datetime">Start Date & Time</label>
                        <input type="datetime-local" name="start_datetime" id="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="end_datetime">End Date & Time</label>
                        <input type="datetime-local" name="end_datetime" id="end_datetime" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select name="priority" id="priority">
                            <option value="Normal">Normal</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="passengers">Number of Passengers</label>
                        <input type="number" name="passengers" id="passengers" min="1" value="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="vehicle_id">Preferred Vehicle (Optional)</label>
                    <select name="vehicle_id" id="vehicle_id">
                        <option value="">Let manager assign vehicle</option>
                        <?php while($v = $vehicles->fetch_assoc()): ?>
                            <option value="<?php echo $v['id']; ?>">
                                <?php echo htmlspecialchars($v['name'] . ' (' . $v['license_plate'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="vehicle-warning" style="color:#e67c1a; font-size:14px; margin-top:6px;">
                        <?php 
                        // Reset the vehicles query result for the warning check
                        $vehicleCount = $conn->query("SELECT COUNT(*) as count FROM vehicles WHERE status = 'available'")->fetch_assoc()['count'];
                        if ($vehicleCount == 0): ?>
                            No vehicles available for selected time. Manager will need to assign manually.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label for="requirements">Special Requirements (Optional)</label>
                    <textarea name="requirements" id="requirements" rows="2" placeholder="Any special equipment, accessibility needs, or other requirements"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" id="cancelModalBtn" class="modal-cancel-btn">Cancel</button>
                    <button type="submit" class="modal-submit-btn">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success/Error Message -->
    <?php if ($bookingMsg): ?>
        <div id="bookingMsg" style="position:fixed;top:80px;right:40px;z-index:2000;background:<?php echo strpos($bookingMsg, 'success') !== false ? '#232c3d' : '#e74c3c'; ?>;color:#fff;padding:18px 32px;border-radius:12px;box-shadow:0 2px 8px #0002;font-size:16px;display:flex;align-items:center;gap:12px;">
            <span><?php echo $bookingMsg; ?></span>
            <button onclick="document.getElementById('bookingMsg').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
        </div>
    <?php endif; ?>

    <script>
        const modalOverlay = document.getElementById('requestModal');
        const requestBtn = document.getElementById('requestVehicleBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelModalBtn');
        const form = document.getElementById('vehicleRequestForm');

        // Show modal
        requestBtn.onclick = function() {
            modalOverlay.style.display = 'flex';
            setTimeout(() => modalOverlay.classList.add('show'), 10);
        };

        // Hide modal function
        function closeModal() {
            modalOverlay.classList.remove('show');
            setTimeout(() => {
                modalOverlay.style.display = 'none';
                form.reset(); // Clear form when closing
            }, 250);
        }

        // Hide modal (close button or cancel button)
        closeBtn.onclick = closeModal;
        cancelBtn.onclick = closeModal;

        // Hide modal when clicking outside modal content
        modalOverlay.onclick = function(e) {
            if (e.target === modalOverlay) {
                closeModal();
            }
        };

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalOverlay.style.display === 'flex') {
                closeModal();
            }
        });

        // Auto-hide the booking message after 5 seconds
        if (document.getElementById('bookingMsg')) {
            setTimeout(function() {
                const msg = document.getElementById('bookingMsg');
                if (msg) msg.style.display = 'none';
            }, 5000);
        }

        // Set minimum datetime to current time
        const now = new Date();
        const localISOTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        document.getElementById('start_datetime').min = localISOTime;
        document.getElementById('end_datetime').min = localISOTime;

        // Update end datetime minimum when start datetime changes
        document.getElementById('start_datetime').addEventListener('change', function() {
            document.getElementById('end_datetime').min = this.value;
        });
    </script>
</body>
</html>