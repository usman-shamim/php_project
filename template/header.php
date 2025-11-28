<?php
// template/header.php

// 1. Session Start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Connection
require_once dirname(__DIR__) . '/db_connect.php'; 

// 3. Variables & Notification Fetching
$unread_notifications = [];
$notif_count = 0;

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    // Fetch notifications only for 'admin' and only unread system messages (user_id IS NULL)
    $sql = "SELECT notification_id, message, type FROM notifications WHERE is_read = FALSE AND user_id IS NULL ORDER BY created_at DESC LIMIT 5";
    
    // Check if $conn is valid before querying
    if (isset($conn) && $conn instanceof mysqli) {
        $result = $conn->query($sql);
        if ($result) {
            $unread_notifications = $result->fetch_all(MYSQLI_ASSOC);
            $notif_count = count($unread_notifications);
        }
    }
}

// 4. Set Page Title (Requires $page_title to be set in the file that includes this header)
$page_title = $page_title ?? "Salon Management System"; 

// Base path assumption: template/ is one directory down from the root.
$base_path = '../'; 
$user_role = $_SESSION['role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Elegance Salon Management System" />
    <title><?php echo htmlspecialchars($page_title) . " | Salon Management"; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Merriweather+Sans:400,700" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Merriweather:400,300,300italic,400italic,700,700italic" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/SimpleLightbox/2.1.0/simpleLightbox.min.css" rel="stylesheet" />
    
    <link href="<?php echo $base_path; ?>css/styles.css" rel="stylesheet" /> 
    
    <style>
        /* Override default Creative theme body padding/margins for admin pages */
        #page-top { padding-top: 0 !important; } 

        /* Create a content container box to retain the "dashboard" feel */
        .main-dashboard-content { 
            background: #fff; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 0 15px rgba(0,0,0,0.05); 
            margin-top: 20px; 
            margin-bottom: 30px; 
            min-height: 80vh;
        }
        /* Ensure the fixed navbar links are readable against the dark background */
        .navbar-nav .nav-link { 
            color: #fff !important; 
        }
        .navbar-nav .nav-link:hover {
            color: var(--bs-primary) !important; /* Uses the theme's primary color */
        }
    </style>
</head>

<body id="page-top">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top py-3" id="mainNav">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand text-light" href="<?php echo $base_path; ?>dashboard.php">Elegance Staff Portal</a>
            
            <div class="d-flex align-items-center order-lg-3">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white me-3 d-none d-lg-inline-block small">
                        <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo ucfirst($user_role); ?>)
                    </span>
                    <a href="<?php echo $base_path; ?>logout.php" class="btn btn-outline-danger btn-sm me-2">Logout</a>
                <?php endif; ?>

                <?php if ($user_role === 'admin' && $notif_count > 0): ?>
                    <a href="<?php echo $base_path; ?>admin/notifications_view.php" class="btn btn-warning position-relative btn-sm">
                        <i class="bi-bell"></i> 
                        <span class="d-none d-md-inline">Notifications</span>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $notif_count; ?>
                            <span class="visually-hidden">unread messages</span>
                        </span>
                    </a>
                <?php endif; ?>

                <button class="navbar-toggler navbar-toggler-right" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse order-lg-2" id="navbarResponsive">
                <ul class="navbar-nav ms-auto my-2 my-lg-0">
                    <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>dashboard.php"><i class="bi-speedometer2"></i> Dashboard</a></li>

                    <?php if ($user_role): ?>
                        
                        <?php if ($user_role === 'stylist'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>stylist/appointments_view.php"><i class="bi-calendar-check"></i> My Schedule</a></li>
                            
                        <?php elseif ($user_role === 'receptionist' || $user_role === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>receptionist/clients_manage.php"><i class="bi-people"></i> Clients</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>receptionist/appointments_manage.php"><i class="bi-calendar-event"></i> Appointments</a></li>
                        <?php endif; ?>

                        <?php if ($user_role === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>admin/services_manage.php"><i class="bi-scissors"></i> Services</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>admin/inventory_manage.php"><i class="bi-box-seam"></i> Inventory</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>admin/staff_manage.php"><i class="bi-person-badge"></i> Staff/Commissions</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo $base_path; ?>admin/reports_analytics.php"><i class="bi-graph-up"></i> Reports</a></li>
                        <?php endif; ?>

                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <section class="page-section" style="padding-top: 100px;">
        <div class="container px-4 px-lg-5">
            <div class="main-dashboard-content">
