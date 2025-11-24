<?php
// templates/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/db_connect.php'; 

$unread_notifications = [];
$notif_count = 0;

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        // Fetch notifications for Admin/System
        $sql = "SELECT notification_id, message, type FROM notifications WHERE is_read = FALSE AND user_id IS NULL ORDER BY created_at DESC LIMIT 5";
        $result = $conn->query($sql);
        if ($result) {
            $unread_notifications = $result->fetch_all(MYSQLI_ASSOC);
            $notif_count = count($unread_notifications);
        }
    }
}
$page_title = $page_title ?? "Salon Management System"; // Use existing title or default
// Note: We leave $conn open here.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body { background-color: #f8f8f8; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; margin-bottom: 20px; }
        .nav-link { margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <header class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <h1 class="h3 text-success">Elegance Salon Management</h1>
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="me-3 text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</span>
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
                <?php endif; ?>
            </div>
        </header>

        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>

                        <?php if (isset($_SESSION['role'])): ?>
                            
                            <?php if ($_SESSION['role'] === 'stylist'): ?>
                                <li class="nav-item"><a class="nav-link" href="../stylist/appointments_view.php">My Schedule</a></li>
                                
                            <?php elseif ($_SESSION['role'] === 'receptionist' || $_SESSION['role'] === 'admin'): ?>
                                <li class="nav-item"><a class="nav-link" href="../receptionist/clients_manage.php">Clients</a></li>
                                <li class="nav-item"><a class="nav-link" href="../receptionist/appointments_manage.php">Appointments</a></li>
                            <?php endif; ?>

                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li class="nav-item"><a class="nav-link" href="../admin/services_manage.php">Services</a></li>
                                <li class="nav-item"><a class="nav-link" href="../admin/inventory_manage.php">Inventory</a></li>
                                <li class="nav-item"><a class="nav-link" href="../admin/staff_manage.php">Staff/Commissions</a></li>
                                <li class="nav-item"><a class="nav-link" href="../admin/reports_analytics.php">Reports</a></li>
                            <?php endif; ?>

                        <?php endif; ?>
                    </ul>
                </div>
                
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && $notif_count > 0): ?>
                    <a href="../admin/notifications_view.php" class="btn btn-warning position-relative me-3">
                        🔔 Notifications 
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $notif_count; ?>
                            <span class="visually-hidden">unread messages</span>
                        </span>
                    </a>
                <?php endif; ?>
                
            </div>
        </nav>
        <div class="content">