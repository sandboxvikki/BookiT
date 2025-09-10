<?php
include("includes/db.php");
include("includes/auth.php");
checkLogin();

$userId = $_SESSION['user_id'];

// Fetch current user data
$query = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$query->bind_param("i", $userId);
$query->execute();
$query->bind_result($name, $email);
$query->fetch();
$query->close();

// Handle profile update
$success = $error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        $newName  = trim($_POST['name']);
        $newEmail = trim($_POST['email']);

        $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        $stmt->bind_param("ssi", $newName, $newEmail, $userId);
        if ($stmt->execute()) {
            $success = "Profile updated successfully!";
            $name = $newName; 
            $email = $newEmail; 
        } else {
            $error = "Error updating profile.";
        }
        $stmt->close();
    }

    // Handle password change
    if (isset($_POST['change_password'])) {
        $currentPass = $_POST['current_password'];
        $newPass     = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($hashedPassword);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($currentPass, $hashedPassword)) {
            $error = "Current password is incorrect.";
        } elseif ($newPass !== $confirmPass) {
            $error = "New passwords do not match.";
        } else {
            $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $newHashed, $userId);
            if ($stmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Error updating password.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile - BookiT</title>
    <link rel="stylesheet" href="assets/main.css">
    <style>
        .profile-container {
            max-width: 700px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h2 { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input {
            width: 100%; padding: 10px;
            border: 1px solid #ddd; border-radius: 8px;
        }
        button {
            background: #232c3d; color: #fff;
            padding: 10px 18px; border: none;
            border-radius: 8px; cursor: pointer;
            transition: background 0.3s;
        }
        button:hover { background: #f5aa20ff; }
        .alert { margin: 10px 0; padding: 12px; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-left">
            <div class="logo">
                <img src="assets/Logo-Fast_Kwacha.png" alt="BookiT Logo" style="height:28px;margin-right:10px;">
            </div>
            <a href="javascript:history.back()">← Back</a>
        </div>
        <div class="user-info">
            <a href="logout.php" style="text-decoration:none;color:white;background:#f5aa20ff;border-radius:5px;padding:4px;">Logout</a>
        </div>
    </div>

    <div class="profile-container">
        <h2>My Profile</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Update Profile Form -->
        <form method="post">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" disabled>            </div>
            <button type="submit" name="update_profile">Update Profile</button>
        </form>

        <!-- Change Password Section -->
        <div class="section">
            <h3>Change Password</h3>
            <form method="post">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password">Change Password</button>
            </form>
        </div>
    </div>
</body>
</html>
