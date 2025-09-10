<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin('manager'); // or 'admin' if only admins manage Vehicle

$userId = $_SESSION['user_id'];
$query = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$query->bind_result($name, $role);
$query->fetch();
$query->close();
$nameParts = explode(' ', $name);
$initials = strtoupper(substr($nameParts[0],0,1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1],0,1)) : '');

// Handle add/update/delete actions
$actionMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add vehicle
    if (isset($_POST['add_vehicle'])) {
        $name = trim($_POST['name']);
        $license_plate = trim($_POST['license_plate']);
        $type = trim($_POST['type']);
        $capacity = intval($_POST['capacity']);
        $status = $_POST['status'];
        $stmt = $conn->prepare("INSERT INTO vehicles (name, license_plate, type, capacity, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $name, $license_plate, $type, $capacity, $status);
        if ($stmt->execute()) {
            $actionMsg = "Vehicle added successfully!";
        } else {
            $actionMsg = "Error adding vehicle.";
        }
        $stmt->close();
    }
    // Update vehicle
    if (isset($_POST['update_vehicle'])) {
        $id = intval($_POST['vehicle_id']);
        $name = trim($_POST['name']);
        $license_plate = trim($_POST['license_plate']);
        $type = trim($_POST['type']);
        $capacity = intval($_POST['capacity']);
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE vehicles SET name=?, license_plate=?, type=?, capacity=?, status=? WHERE id=?");
        $stmt->bind_param("sssisi", $name, $license_plate, $type, $capacity, $status, $id);
        if ($stmt->execute()) {
            $actionMsg = "Vehicle updated successfully!";
        } else {
            $actionMsg = "Error updating vehicle.";
        }
        $stmt->close();
    }
    // Delete vehicle
    if (isset($_POST['delete_vehicle'])) {
        $id = intval($_POST['vehicle_id']);
        $stmt = $conn->prepare("DELETE FROM vehicles WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $actionMsg = "Vehicle deleted successfully!";
        } else {
            $actionMsg = "Error deleting vehicle.";
        }
        $stmt->close();
    }
}

// Fetch all vehicles
$vehicles = $conn->query("SELECT * FROM vehicles ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vehicle Management - BookiT</title>
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
                <a href="all_bookings.php">All Bookings</a>
                <a href="vehicles.php" class="active">Vehicle Management</a>
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
        <div class="requests-header">
            <div>
                <h2 class="dashboard-title">Vehicle Management</h2>
                <p class="dashboard-subtitle">Add, update, or remove vehicles from your fleet</p>
            </div>
            <button class="new-booking-btn" onclick="openAddVehicleModal()">Add Vehicle</button>
        </div>
        
        <?php if ($actionMsg): ?>
            <div style="background:#232c3d;color:#fff;padding:12px 24px;border-radius:8px;margin-bottom:18px;"><?php echo $actionMsg; ?></div>
        <?php endif; ?>
        
        <div class="dashboard-card card">
            <h3 style="margin-bottom:16px;">Vehicle List</h3>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>License Plate</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($v = $vehicles->fetch_assoc()): ?>
                    <tr>
                        <form method="post">
                            <td><?php echo $v['id']; ?></td>
                            <td>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($v['name'] ?? ''); ?>" required style="width:120px;">
                            </td>
                            <td>
                                <input type="text" name="license_plate" value="<?php echo htmlspecialchars($v['license_plate'] ?? ''); ?>" required style="width:100px;">
                            </td>
                            <td>
                                <input type="text" name="type" value="<?php echo htmlspecialchars($v['type'] ?? ''); ?>" required style="width:100px;" placeholder="e.g., SUV, Sedan">
                            </td>
                            <td>
                                <input type="number" name="capacity" value="<?php echo $v['capacity'] ?? ''; ?>" required style="width:70px;" min="1" max="50">
                            </td>
                            <td>
                                <select name="status" required>
                                    <option value="available" <?php if($v['status']=='available')echo 'selected';?>>Available</option>
                                    <option value="booked" <?php if($v['status']=='booked')echo 'selected';?>>Booked</option>
                                    <option value="maintenance" <?php if($v['status']=='maintenance')echo 'selected';?>>Maintenance</option>
                                </select>
                            </td>
                            <td style="display:flex;gap:8px;">
                                <input type="hidden" name="vehicle_id" value="<?php echo $v['id']; ?>">
                                <button type="submit" name="update_vehicle" class="modal-submit-btn" style="padding:6px 14px;font-size:14px;">Update</button>
                                <button type="submit" name="delete_vehicle" class="modal-cancel-btn" style="padding:6px 14px;font-size:14px;" onclick="return confirm('Delete this vehicle?');">Delete</button>
                            </td>
                        </form>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal" class="modal-overlay" style="display: none;">
        <div class="modal-content request-modal">
            <span class="close-modal" onclick="closeAddVehicleModal()">&times;</span>
            <h2>Add New Vehicle</h2>
            <p>Fill in the details to add a new vehicle to your fleet</p>
            
            <form method="post">
                <div class="form-group">
                    <label for="vehicle_name">Vehicle Name</label>
                    <input type="text" name="name" id="vehicle_name" required placeholder="e.g., Toyota Hiace, Honda Civic">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="vehicle_type">Vehicle Type</label>
                        <input type="text" name="type" id="vehicle_type" required placeholder="e.g., SUV, Sedan, Van">
                    </div>
                    
                    <div class="form-group">
                        <label for="vehicle_capacity">Capacity (Passengers)</label>
                        <input type="number" name="capacity" id="vehicle_capacity" required placeholder="e.g., 4, 7, 14" min="1" max="50">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="vehicle_license_plate">License Plate</label>
                    <input type="text" name="license_plate" id="vehicle_license_plate" required placeholder="e.g., ABC-1234">
                </div>
                
                <div class="form-group">
                    <label for="vehicle_status">Status</label>
                    <select name="status" id="vehicle_status" required>
                        <option value="available">Available</option>
                        <option value="booked">Booked</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="modal-cancel-btn" onclick="closeAddVehicleModal()">Cancel</button>
                    <button type="submit" name="add_vehicle" class="modal-submit-btn">Add Vehicle</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddVehicleModal() {
            const modal = document.getElementById('addVehicleModal');
            modal.style.display = 'flex';
            // Add the 'show' class after a brief delay for smooth animation
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeAddVehicleModal() {
            const modal = document.getElementById('addVehicleModal');
            modal.classList.remove('show');
            // Hide the modal after animation completes
            setTimeout(() => {
                modal.style.display = 'none';
                // Reset form
                document.querySelector('#addVehicleModal form').reset();
            }, 250);
        }

        // Close modal when clicking outside of it
        document.getElementById('addVehicleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddVehicleModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddVehicleModal();
            }
        });
    </script>
</body>
</html>