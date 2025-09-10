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

// Handle logbook entry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_entry'])) {
    $vehicle_id = intval($_POST['vehicle_id']);
    $departure_time = $_POST['departure_time'];
    $return_time = $_POST['return_time'];
    $destination = trim($_POST['destination']);
    $purpose = trim($_POST['purpose']);
    $odometer_start = intval($_POST['odometer_start']);
    $odometer_end = intval($_POST['odometer_end']);
    $fuel_used = floatval($_POST['fuel_used']);
    $notes = trim($_POST['notes']);
    
    $stmt = $conn->prepare("
        INSERT INTO vehicle_logbook 
        (user_id, vehicle_id, departure_time, return_time, destination, purpose, odometer_start, odometer_end, fuel_used, notes, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iissssiids", $userId, $vehicle_id, $departure_time, $return_time, $destination, $purpose, $odometer_start, $odometer_end, $fuel_used, $notes);
    
    if ($stmt->execute()) {
        $success_msg = "Logbook entry saved successfully!";
    } else {
        $error_msg = "Error saving logbook entry. Please try again.";
    }
    $stmt->close();
}

// Fetch vehicles for dropdown
$vehicles = $conn->query("SELECT id, name, license_plate FROM vehicles WHERE status = 'available'");

// Fetch recent logbook entries
$recent_entries = $conn->query("
    SELECT l.*, v.name as vehicle_name, v.license_plate, u.name as driver_name
    FROM vehicle_logbook l 
    LEFT JOIN vehicles v ON l.vehicle_id = v.id 
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.user_id = $userId 
    ORDER BY l.created_at DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>FleetFlow - Vehicle Logbook</title>
    <link rel="stylesheet" href="assets/main.css">
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <div class="logo">
                <img src="assets/Logo-Fast_Kwacha.png" alt="FleetFlow Logo" style="height:28px;margin-right:10px;">
                FleetFlow
            </div>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="my_requests.php">My Bookings</a>
                <a href="logbook.php" class="active">Logbook</a>
                <a href="profile.php">Profile</a>
            </nav>
        </div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($name); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($role); ?></span>
            <span class="avatar"><?php echo $initials; ?></span>
            <a href="logout.php" style="text-decoration: none; color: white; background-color: #f5aa20ff; border-radius: 5px; padding: 4px;">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="logbook-header">
            <div>
                <h2 class="dashboard-title">Vehicle Logbook</h2>
                <p class="dashboard-subtitle">Record your vehicle usage and track trips</p>
            </div>
            <button id="newEntryBtn" class="new-booking-btn">New Entry</button>
        </div>

        <!-- Logbook Voucher Template -->
        <div class="voucher-container" id="voucherTemplate" style="display: none;">
            <div class="voucher">
                <div class="voucher-header">
                    <div class="company-info">
                        <div class="company-logo">
                            <img src="assets/Logo-Fast_Kwacha.png" alt="Fast Kwacha Logo" style="height: 40px;">
                        </div>
                        <div class="company-details">
                            <h2>FAST KWACHA</h2>
                            <p>Microfinance | Digital Payments | Credit Rating</p>
                            <div class="tagline">"MAKE MONEY MOVES, WE MOVE YOU FASTER!"</div>
                        </div>
                    </div>
                    <div class="voucher-info">
                        <h3>VEHICLE LOGBOOK</h3>
                        <div class="voucher-number">
                            <span>No.</span>
                            <span id="entryNumber">0001</span>
                        </div>
                        <div class="voucher-date">
                            <span>Date</span>
                            <span id="entryDate"></span>
                        </div>
                    </div>
                </div>

                <div class="voucher-body">
                    <div class="driver-info">
                        <span>Driver: <span id="driverName"></span></span>
                    </div>
                    
                    <table class="particulars-table">
                        <thead>
                            <tr>
                                <th>PARTICULARS</th>
                                <th>DETAILS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Vehicle</td><td id="vehicleInfo"></td></tr>
                            <tr><td>Departure Time</td><td id="departureTime"></td></tr>
                            <tr><td>Return Time</td><td id="returnTime"></td></tr>
                            <tr><td>Destination</td><td id="destination"></td></tr>
                            <tr><td>Purpose</td><td id="purpose"></td></tr>
                            <tr><td>Odometer Start</td><td id="odometerStart"></td></tr>
                            <tr><td>Odometer End</td><td id="odometerEnd"></td></tr>
                            <tr><td>Distance Traveled</td><td id="distanceTraveled"></td></tr>
                            <tr><td>Fuel Used (Liters)</td><td id="fuelUsed"></td></tr>
                            <tr><td>Notes</td><td id="notesField"></td></tr>
                        </tbody>
                    </table>

                    <div class="signatures">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <span>Driver Signature</span>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <span>Approved by</span>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <span>Received by</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="voucher-actions">
                <button onclick="printVoucher()" class="print-btn">Print Logbook Entry</button>
                <button onclick="closeVoucher()" class="close-btn">Close</button>
            </div>
        </div>

        <!-- Recent Entries -->
        <div class="dashboard-card large" style="margin-top: 24px;">
            <div class="card-title">Recent Logbook Entries</div>
            <div class="card-subtitle">Your latest vehicle usage records</div>
            
            <?php if ($recent_entries->num_rows == 0): ?>
                <div style="color: #6b7280; text-align: center; padding: 40px;">
                    No logbook entries yet. Create your first entry using the button above.
                </div>
            <?php else: ?>
                <div class="entries-list">
                    <?php while ($entry = $recent_entries->fetch_assoc()): ?>
                        <div class="entry-item" onclick="viewEntry(<?php echo htmlspecialchars(json_encode($entry), ENT_QUOTES, 'UTF-8'); ?>)">
                            <div class="entry-header">
                                <div class="entry-title">
                                    <?php echo htmlspecialchars($entry['vehicle_name']) . ' (' . htmlspecialchars($entry['license_plate']) . ')'; ?>
                                </div>
                                <div class="entry-date"><?php echo date('M d, Y', strtotime($entry['created_at'])); ?></div>
                            </div>
                            <div class="entry-details">
                                <div class="entry-destination">📍 <?php echo htmlspecialchars($entry['destination']); ?></div>
                                <div class="entry-purpose"><?php echo htmlspecialchars($entry['purpose']); ?></div>
                                <div class="entry-stats">
                                    <span>Distance: <?php echo ($entry['odometer_end'] - $entry['odometer_start']); ?> km</span>
                                    <span>Fuel: <?php echo $entry['fuel_used']; ?>L</span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- New Entry Modal -->
    <div id="entryModal" class="modal-overlay" style="display:none;">
        <div class="modal-content logbook-modal">
            <span class="close-modal" id="closeModalBtn">&times;</span>
            <h2>New Logbook Entry</h2>
            <p style="color:#6b7280; font-size:15px; margin-bottom:18px;">Record your vehicle usage details</p>
            
            <form id="logbookForm" method="post" action="logbook.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="vehicle_id">Vehicle</label>
                        <select name="vehicle_id" id="vehicle_id" required>
                            <option value="">Select Vehicle</option>
                            <?php while($v = $vehicles->fetch_assoc()): ?>
                                <option value="<?php echo $v['id']; ?>">
                                    <?php echo htmlspecialchars($v['name'] . ' (' . $v['license_plate'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="destination">Destination</label>
                        <input type="text" name="destination" id="destination" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="departure_time">Departure Time</label>
                        <input type="datetime-local" name="departure_time" id="departure_time" required>
                    </div>
                    <div class="form-group">
                        <label for="return_time">Return Time</label>
                        <input type="datetime-local" name="return_time" id="return_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="purpose">Purpose of Trip</label>
                    <input type="text" name="purpose" id="purpose" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="odometer_start">Odometer Start (km)</label>
                        <input type="number" name="odometer_start" id="odometer_start" required>
                    </div>
                    <div class="form-group">
                        <label for="odometer_end">Odometer End (km)</label>
                        <input type="number" name="odometer_end" id="odometer_end" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fuel_used">Fuel Used (Liters)</label>
                        <input type="number" name="fuel_used" id="fuel_used" step="0.1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Any additional notes or observations"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" id="cancelModalBtn" class="modal-cancel-btn">Cancel</button>
                    <button type="submit" name="submit_entry" class="modal-submit-btn">Save Entry</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success_msg)): ?>
        <div id="successMsg" class="alert alert-success">
            <span><?php echo $success_msg; ?></span>
            <button onclick="document.getElementById('successMsg').style.display='none'" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div id="errorMsg" class="alert alert-error">
            <span><?php echo $error_msg; ?></span>
            <button onclick="document.getElementById('errorMsg').style.display='none'" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

    <script>
        const modal = document.getElementById('entryModal');
        const newEntryBtn = document.getElementById('newEntryBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelModalBtn');

        newEntryBtn.onclick = () => {
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        };

        function closeModal() {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 250);
        }

        closeBtn.onclick = closeModal;
        cancelBtn.onclick = closeModal;

        modal.onclick = (e) => {
            if (e.target === modal) closeModal();
        };

        function viewEntry(entry) {
            // Populate voucher template with entry data
            document.getElementById('entryNumber').textContent = String(entry.id).padStart(4, '0');
            document.getElementById('entryDate').textContent = new Date(entry.created_at).toLocaleDateString();
            document.getElementById('driverName').textContent = entry.driver_name;
            document.getElementById('vehicleInfo').textContent = entry.vehicle_name + ' (' + entry.license_plate + ')';
            document.getElementById('departureTime').textContent = new Date(entry.departure_time).toLocaleString();
            document.getElementById('returnTime').textContent = new Date(entry.return_time).toLocaleString();
            document.getElementById('destination').textContent = entry.destination;
            document.getElementById('purpose').textContent = entry.purpose;
            document.getElementById('odometerStart').textContent = entry.odometer_start + ' km';
            document.getElementById('odometerEnd').textContent = entry.odometer_end + ' km';
            document.getElementById('distanceTraveled').textContent = (entry.odometer_end - entry.odometer_start) + ' km';
            document.getElementById('fuelUsed').textContent = entry.fuel_used + ' L';
            document.getElementById('notesField').textContent = entry.notes || 'N/A';
            
            document.getElementById('voucherTemplate').style.display = 'flex';
        }

        function closeVoucher() {
            document.getElementById('voucherTemplate').style.display = 'none';
        }

        function printVoucher() {
            window.print();
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.style.display = 'none');
        }, 5000);
    </script>
</body>
</html>