<?php if (isset($_GET['logged_out'])) echo "<p class='success'>You have been logged out.</p>"; ?>
<?php if (isset($_GET['registered'])) echo "<p class='success'>Registration successful. Please log in.</p>"; ?>
<?php
include("includes/db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit;
        }
    }
    $error = "Invalid login details.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>BookiT - Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <img src="assets/Logo-Fast_Kwacha.png" alt="BookiT Logo" style="height:32px;margin-bottom:10px;">
            <h2>Welcome Back</h2>
            <p class="subtitle">Sign in to your BookiT account</p>
            <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
            <?php if (isset($_GET['logged_out'])) echo "<div class='success'>You have been logged out.</div>"; ?>
            <?php if (isset($_GET['registered'])) echo "<div class='success'>Registration successful. Please log in.</div>"; ?>
            <form method="post">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Sign In</button>
            </form>
            <p class="footer">
                Don't have an account? <a href="register.php">Sign up</a><br>
                <a href="index.php">&larr; Back to home</a>
            </p>
        </div>
    </div>
</body>
</html>
