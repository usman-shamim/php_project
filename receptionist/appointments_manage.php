<?php
$page_title = "Appointment Management";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/functions.php';
require_once '../db_connect.php';

check_login();

// Check if user is authorized for management duties (Admin only, since "receptionist" role is being deprecated)
$is_admin = ($_SESSION['role'] === 'admin');

$message = '';

// --- I. Handle Status Change and Payment Processing ---
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $app_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'complete') {
        // Mark appointment as Completed
        $sql = "UPDATE appointments SET status = 'Completed' WHERE app_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $app_id);
        if ($stmt->execute()) {
            $message = '<p class="success-message">Appointment ID ' . $app_id . ' marked as **Completed**. Ready for payment.</p>';
        } else {
            $message = '<p class="error-message">Error marking appointment complete: ' . $conn->error . '</p>';
        }
        $stmt->close();
    }
    // Redirect to clear GET variables after action
    header("Location: appointments_manage.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'payment') {
    // Process Payment
    $app_id = (int)$_POST['app_id'];
    $amount_paid = (float)$_POST['amount_paid'];
    $payment_method = $_POST['payment_method'];
    $invoice_number = "INV-" . time(); // Simple unique invoice number generation

    // 1. Check if the appointment is already paid
    $check_paid = $conn->query("SELECT app_id FROM payments WHERE app_id = $app_id");
    if ($check_paid->num_rows > 0) {
        $message = '<p class="error-message">This appointment has already been paid.</p>';
    } else {
        // 2. Insert Payment Record
        $sql = "INSERT INTO payments (app_id, amount_paid, payment_method, invoice_number) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // 'idss' means Integer, Double, String, String
        $stmt->bind_param("idss", $app_id, $amount_paid, $payment_method, $invoice_number);

        if ($stmt->execute()) {
            $message = '<p class="success-message">Payment of $' . number_format($amount_paid, 2) . ' recorded. Invoice: ' . $invoice_number . '</p>';
        } else {
            $message = '<p class="error-message">Error recording payment: ' . $conn->error . '</p>';
        }
        $stmt->close();
    }
}


// --- I. A. Handle New Appointment Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['form_type']) && isset($_POST['client_id'])) {
    // This block catches the main "Book Appointment" form submission
    $client_id = (int)$_POST['client_id'];
    $service_id = (int)$_POST['service_id'];
    $staff_id = (int)$_POST['staff_id'];
    
    // Combine Date and Time into MySQL DATETIME format
    $app_date = $_POST['app_date'];
    $app_time = $_POST['app_time'];
    $start_datetime_str = $app_date . ' ' . $app_time; 
    
    // 1. Get Service Duration
    $sql_duration = "SELECT duration_minutes FROM services WHERE service_id = ?";
    $stmt_duration = $conn->prepare($sql_duration);
    $stmt_duration->bind_param("i", $service_id);
    $stmt_duration->execute();
    $result_duration = $stmt_duration->get_result();
    $service = $result_duration->fetch_assoc();

    if (!$service) {
         $message = '<p class="error-message">Error: Selected service is invalid or not found.</p>';
    } else {
        $duration = $service['duration_minutes'];
        $stmt_duration->close();

        // 2. Calculate End Time
        $start_timestamp = strtotime($start_datetime_str);
        // Ensure time is calculated correctly (1800 is HH:MM, strtotime handles it)
        if ($start_timestamp === false) {
             $message = '<p class="error-message">Error: Invalid date or time format provided.</p>';
        } else {
            $end_timestamp = $start_timestamp + ($duration * 60); // Add minutes converted to seconds
            $end_datetime_str = date('Y-m-d H:i:s', $end_timestamp);
            
            // 3. Run Conflict Check (Check for overlaps with the chosen staff)
            $sql_conflict = "
                SELECT app_id FROM appointments 
                WHERE staff_id = ?
                AND status != 'Cancelled'
                AND (
                    (start_time < ? AND end_time > ?) OR  
                    (start_time < ? AND end_time > ?) OR  
                    (start_time = ?)                     
                )
            ";
            $stmt_conflict = $conn->prepare($sql_conflict);
            $stmt_conflict->bind_param(
                "isssss", 
                $staff_id, 
                $end_datetime_str, 
                $start_datetime_str, 
                $end_datetime_str, 
                $start_datetime_str,
                $start_datetime_str
            );
            $stmt_conflict->execute();
            $conflict_result = $stmt_conflict->get_result();

            if ($conflict_result->num_rows > 0) {
                $message = '<p class="error-message">CONFLICT: The stylist is already booked during this time slot.</p>';
                $stmt_conflict->close();
            } else {
                // 4. Insert Appointment
                $stmt_conflict->close(); 

                $sql_insert = "INSERT INTO appointments (client_id, service_id, staff_id, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, 'Booked')";
                $stmt_insert = $conn->prepare($sql_insert);
                // The 's' types here ensure the DATETIME strings are handled correctly
                $stmt_insert->bind_param("iiiss", $client_id, $service_id, $staff_id, $start_datetime_str, $end_datetime_str);

                if ($stmt_insert->execute()) {
                    $message = '<p class="success-message">Appointment successfully booked!</p>';
                } else {
                    $message = '<p class="error-message">Database Error during insertion: ' . $conn->error . '</p>';
                }
                $stmt_insert->close();
            }
        }
    }
}


// --- II. Fetch Data for Forms and Calendar (Existing Logic) ---

// Get all Clients for the dropdown
$clients = $conn->query("SELECT client_id, name, phone FROM clients ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Get all Services for the dropdown
$services = $conn->query("SELECT service_id, name, duration_minutes, price FROM services WHERE is_active = TRUE ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Get all Staff for the dropdown
$staff = [];
$sql_staff = "SELECT s.staff_id, u.username FROM staff s JOIN users u ON s.user_id = u.user_id";
$staff = $conn->query($sql_staff)->fetch_all(MYSQLI_ASSOC);


// --- III. Fetch Appointments for Calendar/List (Modified to include payment status) ---
$appointments = [];
$sql_app = "
    SELECT 
        a.app_id, a.start_time, a.end_time, a.status,
        c.name AS client_name, c.phone AS client_phone,
        u.username AS staff_name,
        s.name AS service_name, s.price,
        p.payment_id, p.amount_paid, p.payment_method, p.invoice_number
    FROM appointments a
    JOIN clients c ON a.client_id = c.client_id
    JOIN staff st ON a.staff_id = st.staff_id
    JOIN users u ON st.user_id = u.user_id
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN payments p ON a.app_id = p.app_id -- LEFT JOIN to see unpaid appointments
    WHERE a.start_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) -- Show last 7 days and future
    ORDER BY a.start_time ASC
";

$result_app = $conn->query($sql_app);
if ($result_app) {
    while ($row = $result_app->fetch_assoc()) {
        $appointments[] = $row;
    }
}

// --- IV. HTML Output ---
include '../templates/header.php';
echo $message;
?>

<h2>Appointment Management</h2>
<form method="POST" action="appointments_manage.php">
    <h3>Book New Appointment</h3>

    <div class="form-group">
        <label for="client_id">Client:</label>
        <select id="client_id" name="client_id" required>
            <option value="">-- Select Client --</option>
            <?php foreach ($clients as $c): ?>
                <option value="<?php echo $c['client_id']; ?>"><?php echo htmlspecialchars($c['name']) . " (" . htmlspecialchars($c['phone']) . ")"; ?></option>
            <?php endforeach; ?>
        </select>
        <small><a href="clients_manage.php">Add New Client</a> if not listed.</small>
    </div>

    <div class="form-group">
        <label for="service_id">Service:</label>
        <select id="service_id" name="service_id" required>
            <option value="">-- Select Service --</option>
            <?php foreach ($services as $s): ?>
                <option value="<?php echo $s['service_id']; ?>">
                    <?php echo htmlspecialchars($s['name']) . " ($" . number_format($s['price'], 2); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="staff_id">Stylist:</label>
        <select id="staff_id" name="staff_id" required>
            <option value="">-- Select Stylist --</option>
            <?php foreach ($staff as $st): ?>
                <option value="<?php echo $st['staff_id']; ?>"><?php echo htmlspecialchars($st['username']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="form-group" style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <label for="app_date">Date:</label>
            <!-- Min attribute removed to allow booking past appointments for testing/admin correction -->
            <input type="date" id="app_date" name="app_date" required>
        </div>
        <div style="flex: 1;">
            <label for="app_time">Time (HH:MM):</label>
            <input type="time" id="app_time" name="app_time" required step="1800">
        </div>
    </div>
    
    <button type="submit" class="btn btn-success mt-2">Book Appointment</button>
</form>

<hr>

<h2>Recent & Upcoming Appointments</h2>

<?php if (empty($appointments)): ?>
    <p>No recent or upcoming appointments found.</p>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Client</th>
                <th>Stylist</th>
                <th>Service (Price)</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $app): 
                $is_paid = $app['payment_id'] !== null;
                $is_completed = $app['status'] === 'Completed';
                
                // --- ROBUST TIME COMPARISON using DateTime objects ---
                $is_in_past = false;
                try {
                    // Create DateTime object for appointment end time
                    $app_end_datetime = new DateTime($app['end_time']);
                    // Create DateTime object for the current time
                    $current_datetime = new DateTime();
                    
                    // Check if the appointment end time is strictly less than the current time
                    $is_in_past = $app_end_datetime < $current_datetime; 
                } catch (Exception $e) {
                    // Fail gracefully if date parsing errors occur
                    error_log("Date parsing error for appointment {$app['app_id']}: " . $e->getMessage());
                    $is_in_past = false;
                }
                // --- END TIME COMPARISON ---
            ?>
            <tr>
                <td><?php echo date('Y-m-d H:i', strtotime($app['start_time'])); ?></td>
                <td><?php echo htmlspecialchars($app['client_name']); ?></td>
                <td><?php echo htmlspecialchars($app['staff_name']); ?></td>
                <td><?php echo htmlspecialchars($app['service_name']); ?> ($<?php echo number_format($app['price'], 2); ?>)</td>
                <td><strong><?php echo htmlspecialchars($app['status']); ?></strong></td>
                <td>
                    <?php if ($is_paid): ?>
                        <span class="text-success fw-bold">PAID</span> (Inv: <?php echo htmlspecialchars($app['invoice_number']); ?>)
                    <?php elseif ($is_completed): ?>
                        <span class="text-danger fw-bold">UNPAID</span>
                    <?php else: ?>
                        Pending
                    <?php endif; ?>
                </td>
                <td>
                    <?php 
                    $has_action = false; // Flag to track if any action button/form is displayed

                    // Action 1: Mark as Completed (Only if status is 'Booked' AND end time is in the past)
                    if ($app['status'] === 'Booked' && $is_in_past) {
                        echo '<a href="?action=complete&id=' . $app['app_id'] . '" class="btn btn-warning btn-sm" style="margin-bottom: 5px;">Mark Complete</a>';
                        $has_action = true;
                    }

                    // Action 2: Process Payment (Only if Completed and Unpaid)
                    if ($is_completed && !$is_paid) {
                        // Display a simple payment form triggered by a button
                        echo '
                        <button class="btn btn-success btn-sm" onclick="document.getElementById(\'pay-' . $app['app_id'] . '\').style.display=\'block\'">Take Payment</button>
                        <div id="pay-' . $app['app_id'] . '" style="display:none; border: 1px solid #ccc; padding: 10px; margin-top: 5px;">
                            <form method="POST" action="appointments_manage.php">
                                <input type="hidden" name="form_type" value="payment">
                                <input type="hidden" name="app_id" value="' . $app['app_id'] . '">
                                <input type="hidden" name="amount_paid" value="' . $app['price'] . '">
                                
                                <p>Amount Due: <strong>$' . number_format($app['price'], 2) . '</strong></p>
                                
                                <label for="method-' . $app['app_id'] . '">Method:</label>
                                <select id="method-' . $app['app_id'] . '" name="payment_method" class="form-control mb-2" required>
                                    <option value="Card">Card</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Mobile Pay">Mobile Pay</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Confirm Payment</button>
                            </form>
                        </div>';
                        $has_action = true;
                    }

                    // Display N/A if no relevant action is available
                    if (!$has_action) {
                        echo 'N/A';
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php 
$conn->close();
include '../templates/footer.php';
?>