<?php
// Include necessary files and enforce access control
require_once '../config/functions.php';
require_once '../db_connect.php';

// Check if user is logged in and has access (Admin OR Receptionist)
check_login();
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'receptionist') {
    header("Location: ../dashboard.php?error=Unauthorized Access");
    exit();
}

// Check if ID is provided and is a valid number
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: clients_manage.php?message=Invalid client ID specified.");
    exit();
}

$client_id = $_GET['id'];

// Use a prepared statement for DELETE for security
$sql = "DELETE FROM clients WHERE client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id); // 'i' for integer

if ($stmt->execute()) {
    // Check if any row was actually deleted
    if ($stmt->affected_rows > 0) {
        $message = "Client deleted successfully.";
    } else {
        $message = "Client ID not found.";
    }
} else {
    // Error handling: If this client has active appointments, the database will often throw a Foreign Key Constraint error.
    if ($conn->errno == 1451) {
        $message = "Cannot delete client. They have existing appointments or payment records (Foreign Key Constraint).";
    } else {
        $message = "Database error: " . $conn->error;
    }
}

$stmt->close();
$conn->close();

// Redirect back to the client list with a status message
header("Location: clients_manage.php?status=" . urlencode($message));
exit();
?>