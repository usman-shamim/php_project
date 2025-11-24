<?php
require_once 'config/functions.php';
check_login(); // Ensure user is logged in

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Simple routing based on user role
if ($role === 'admin') {
    $welcome_message = "Welcome, Admin " . htmlspecialchars($username) . "!";
    $dashboard_link = "admin/inventory_manage.php";
} elseif ($role === 'receptionist') {
    $welcome_message = "Welcome, Receptionist " . htmlspecialchars($username) . "!";
    $dashboard_link = "receptionist/appointments_manage.php";
} elseif ($role === 'stylist') {
    // *** FIX: Route Stylist to their dedicated schedule view ***
    $welcome_message = "Welcome, Stylist " . htmlspecialchars($username) . "!";
    $dashboard_link = "stylist/appointments_view.php";
} else {
    $welcome_message = "Welcome, User " . htmlspecialchars($username) . "!";
    $dashboard_link = "index.php"; // Fallback
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>
<body>
    <h1><?php echo $welcome_message; ?></h1>
    <p>Your current role is: <strong><?php echo htmlspecialchars($role); ?></strong></p>
    
    <p>Redirecting you to your main area...</p>
    
    <script>
        // Use JavaScript for a quick client-side redirect after a brief moment
        setTimeout(function() {
            window.location.href = '<?php echo $dashboard_link; ?>';
        }, 1500); // Wait 1.5 seconds
    </script>

    <p><a href="<?php echo $dashboard_link; ?>">Click here if not redirected.</a></p>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>