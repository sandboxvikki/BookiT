<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = intval($_POST['vehicle_id']);
    $start = $_POST['start_datetime'];
    $end = $_POST['end_datetime'];
    $purpose = trim($_POST['purpose']);

    // Prevent overlapping bookings or maintenance
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

    // Also check maintenance
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

    if ($overlapCount > 0 || $maintenanceCount > 0) {
        $error = "This vehicle is not available for the selected time.";
    } else {
        // Save request
        $stmt = $conn->prepare("
            INSERT INTO requests (user_id, vehicle_id, start_datetime, end_datetime, purpose, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->bind_param("iisss", $userId, $vehicle_id, $start, $end, $purpose);
        if ($stmt->execute()) {
            $success = "Request submitted! Await manager approval.";
        } else {
            $error = "Error submitting request.";
        }
        $stmt->close();
    }
}

// Get available vehicles (not under maintenance right now)
$vehicles = [];
$result = $conn->query("
    SELECT v.id, v.name, v.license_plate
    FROM vehicles v
    WHERE v.status = 'available'
      AND v.id NOT IN (
        SELECT vehicle_id FROM maintenance_logs
        WHERE NOW() BETWEEN start_date AND end_date
      )
");
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Request Vehicle - BookiT</title>
    <link rel="stylesheet" href="assets/main.css">
    <style>
        .form-card { background:#fff; max-width:500px; margin:40px auto; border-radius:12px;   box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); padding:32px; }
        .form-card h2 { margin-bottom:18px; }
        .form-card label { display:block; margin-top:16px; font-weight:500; }
        .form-card input, .form-card select, .form-card textarea {
            width:100%; padding:10px; margin-top:6px; border-radius:6px; border:1px solid #e5eaf2; font-size:15px;
        }
        .form-card button { margin-top:24px; background:#232c3d; color:#fff; border:none; border-radius:6px; padding:12px 24px; font-size:16px; cursor:pointer; }
        .form-card .error { color:#c0392b; margin-top:12px; }
        .form-card .success { color:#228b22; margin-top:12px; }
    </style>
</head>
<body>
    <div class="form-card">
        <h2>Request a Vehicle</h2>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
        <form method="post">
            <label for="vehicle_id">Select Vehicle</label>
            <select name="vehicle_id" id="vehicle_id" required>
                <option value="">-- Choose --</option>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?php echo $v['id']; ?>">
                        <?php echo htmlspecialchars($v['name'] . " (" . $v['license_plate'] . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="start_datetime">Start Date & Time</label>
            <input type="datetime-local" name="start_datetime" id="start_datetime" required>
            <label for="end_datetime">End Date & Time</label>
            <input type="datetime-local" name="end_datetime" id="end_datetime" required>
            <label for="purpose">Purpose</label>
            <textarea name="purpose" id="purpose" rows="3" required></textarea>
            <button type="submit">Submit Request</button>
        </form>
    </div>
</body>
</html>
