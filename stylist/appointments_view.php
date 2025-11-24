<?php
$page_title = "My Schedule";
require_once '../config/functions.php';
require_once '../db_connect.php';

check_login();

// 1. Ensure the user is a 'stylist'
if ($_SESSION['role'] !== 'stylist') {
    // Redirect if not a stylist
    header("Location: ../dashboard.php");
    exit();
}

$message = '';
$stylist_user_id = $_SESSION['user_id'];
$stylist_staff_id = null;


// 2. Fetch the logged-in Stylist's staff_id
// This is crucial to filter the appointments.
$sql_staff_id = "SELECT staff_id FROM staff WHERE user_id = ?";
$stmt_staff_id = $conn->prepare($sql_staff_id);
$stmt_staff_id->bind_param("i", $stylist_user_id);
$stmt_staff_id->execute();
$result_staff_id = $stmt_staff_id->get_result();
if ($row = $result_staff_id->fetch_assoc()) {
    $stylist_staff_id = $row['staff_id'];
}
$stmt_staff_id->close();


// --- III. Fetch Appointments for Stylist ---
$appointments = [];

if ($stylist_staff_id !== null) {
    $sql_app = "
        SELECT 
            a.app_id, a.start_time, a.end_time, a.status,
            c.name AS client_name, c.phone AS client_phone,
            s.name AS service_name, s.price
        FROM appointments a
        JOIN clients c ON a.client_id = c.client_id
        JOIN services s ON a.service_id = s.service_id
        WHERE a.staff_id = ? 
        AND a.start_time >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) -- Show today and future
        ORDER BY a.start_time ASC
    ";

    $stmt_app = $conn->prepare($sql_app);
    $stmt_app->bind_param("i", $stylist_staff_id);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();
    
    while ($row = $result_app->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt_app->close();
}


// --- IV. HTML Output ---
include '../templates/header.php';
echo $message;
?>

<h2>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>! Your Schedule</h2>

<?php if (empty($appointments)): ?>
    <p>You have no appointments scheduled for today or in the future.</p>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Client Name</th>
                <th>Client Phone</th>
                <th>Service (Price)</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $app): 
                $start = strtotime($app['start_time']);
                $end = strtotime($app['end_time']);
                $duration_minutes = round(abs($end - $start) / 60, 2);
            ?>
            <tr>
                <td><?php echo date('Y-m-d H:i', $start); ?></td>
                <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                <td><?php echo htmlspecialchars($app['client_phone']); ?></td>
                <td><?php echo htmlspecialchars($app['service_name']); ?> ($<?php echo number_format($app['price'], 2); ?>)</td>
                <td><?php echo $duration_minutes; ?> min</td>
                <td><strong><?php echo htmlspecialchars($app['status']); ?></strong></td>
                <td>
                    <?php if ($app['status'] !== 'Cancelled'): ?>
                        <!-- Updated to use a button and call a JavaScript function -->
                        <button 
                            class="btn btn-sm btn-info" 
                            onclick="showClientDetails(
                                '<?php echo htmlspecialchars($app['client_name']); ?>', 
                                '<?php echo htmlspecialchars($app['client_phone']); ?>', 
                                '<?php echo htmlspecialchars($app['service_name']); ?>', 
                                '<?php echo date('H:i', $start); ?> - <?php echo date('H:i', $end); ?>'
                            )"
                        >
                            View Details
                        </button>
                    <?php else: ?>
                        <span class="text-danger">Cancelled</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
/**
 * Displays key client and appointment details in a non-alert message box.
 * Note: Using alert() here as a quick demonstration, but in a production environment, 
 * this should be replaced by a custom Bootstrap modal for better UX.
 */
function showClientDetails(name, phone, service, time) {
    alert(
        "--- Appointment Details ---\n\n" +
        "Client: " + name + "\n" +
        "Phone: " + phone + "\n" +
        "Service: " + service + "\n" +
        "Time: " + time + "\n\n" +
        "Note: This is the client's information for the service."
    );
}
</script>

<?php 
$conn->close();
include '../templates/footer.php';
?>