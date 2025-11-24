<?php
$page_title = "Book an Appointment";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/functions.php';
require_once 'db_connect.php';

// Check if a booking was attempted and process it
$message = '';

// --- Handle New Client Creation & Booking Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['client_name']) && isset($_POST['service_id'])) {
    
    // 1. Sanitize and collect data
    $client_name = trim($_POST['client_name']);
    $client_phone = trim($_POST['client_phone']);
    $client_email = trim($_POST['client_email']);
    $service_id = (int)$_POST['service_id'];
    $staff_id = (int)$_POST['staff_id'];
    $app_date = $_POST['app_date'];
    $app_time = $_POST['app_time'];
    $start_datetime_str = $app_date . ' ' . $app_time; 

    // Basic validation
    if (empty($client_name) || empty($service_id) || empty($staff_id) || empty($app_date) || empty($app_time)) {
        $message = '<p class="error-message">Please fill out all required fields.</p>';
        goto end_booking_logic; // Skip to fetching data if validation fails
    }

    // --- A. Find or Create Client ---
    $client_id = null;
    
    // Try to find client by phone (simple check for existing users)
    $sql_find_client = "SELECT client_id FROM clients WHERE phone = ?";
    $stmt_find = $conn->prepare($sql_find_client);
    $stmt_find->bind_param("s", $client_phone);
    $stmt_find->execute();
    $result_find = $stmt_find->get_result();

    if ($result_find->num_rows > 0) {
        $client_id = $result_find->fetch_assoc()['client_id'];
        $message .= '<p class="info-message">Welcome back, ' . htmlspecialchars($client_name) . '! Using existing client record.</p>';
    } else {
        // Create new client if not found
        $sql_insert_client = "INSERT INTO clients (name, phone, email) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert_client);
        $stmt_insert->bind_param("sss", $client_name, $client_phone, $client_email);
        
        if ($stmt_insert->execute()) {
            $client_id = $stmt_insert->insert_id;
            $message .= '<p class="success-message">New client record created for ' . htmlspecialchars($client_name) . '.</p>';
        } else {
            $message = '<p class="error-message">Error creating client record: ' . $conn->error . '</p>';
            goto end_booking_logic; // Stop if client creation fails
        }
        $stmt_insert->close();
    }
    $stmt_find->close();
    
    // --- B. Appointment Creation (Reuse Logic from admin page) ---
    
    if ($client_id) {
        // 1. Get Service Duration
        $sql_duration = "SELECT duration_minutes FROM services WHERE service_id = ?";
        $stmt_duration = $conn->prepare($sql_duration);
        $stmt_duration->bind_param("i", $service_id);
        $stmt_duration->execute();
        $result_duration = $stmt_duration->get_result();
        $service = $result_duration->fetch_assoc();
        $duration = $service['duration_minutes'];
        $stmt_duration->close();

        // 2. Calculate End Time
        $start_timestamp = strtotime($start_datetime_str);
        $end_timestamp = $start_timestamp + ($duration * 60); 
        $end_datetime_str = date('Y-m-d H:i:s', $end_timestamp);
        
        // 3. Run Conflict Check (Same as admin check)
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
            $message = '<p class="error-message">CONFLICT: The selected stylist is already booked during this time slot. Please choose another time or stylist.</p>';
            $stmt_conflict->close();
        } else {
            // 4. Insert Appointment
            $stmt_conflict->close(); 

            $sql_insert = "INSERT INTO appointments (client_id, service_id, staff_id, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, 'Booked')";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iiiss", $client_id, $service_id, $staff_id, $start_datetime_str, $end_datetime_str);

            if ($stmt_insert->execute()) {
                $message = '<p class="success-message">🎉 Appointment successfully booked! You will receive a confirmation shortly.</p>';
            } else {
                $message = '<p class="error-message">Database Error during booking: ' . $conn->error . '</p>';
            }
            $stmt_insert->close();
        }
    }
}

end_booking_logic: // Label for goto statement if needed

// --- Fetch Data for Booking Form ---
// Get all Services for the dropdown
$services = $conn->query("SELECT service_id, name, duration_minutes, price FROM services WHERE is_active = TRUE ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Get all Staff for the dropdown
$staff = [];
$sql_staff = "SELECT s.staff_id, u.username FROM staff s JOIN users u ON s.user_id = u.user_id";
$staff = $conn->query($sql_staff)->fetch_all(MYSQLI_ASSOC);


include 'template/header.php';
echo $message;
?>

<div class="container my-5">
    <h1 class="text-center mb-4">Book Your Appointment</h1>
    <p class="text-center lead">Welcome! Select your service and preferred stylist below to check availability.</p>

    <div class="card shadow-lg p-4 mx-auto" style="max-width: 600px;">
        <form method="POST" action="public_booking.php" id="bookingForm">
            
            <h4 class="mb-3">1. Your Details</h4>
            
            <div class="form-group mb-3">
                <label for="client_name">Full Name <span class="text-danger">*</span></label>
                <input type="text" id="client_name" name="client_name" class="form-control" required placeholder="John Doe">
            </div>

            <div class="form-group mb-3">
                <label for="client_phone">Phone (Used for lookup/confirmation) <span class="text-danger">*</span></label>
                <input type="tel" id="client_phone" name="client_phone" class="form-control" required placeholder="555-123-4567">
            </div>
            
            <div class="form-group mb-4">
                <label for="client_email">Email (Optional)</label>
                <input type="email" id="client_email" name="client_email" class="form-control" placeholder="john@example.com">
            </div>
            
            <hr>
            <h4 class="mb-3">2. Service & Stylist</h4>

            <div class="form-group mb-3">
                <label for="service_id">Service <span class="text-danger">*</span></label>
                <select id="service_id" name="service_id" class="form-control" required>
                    <option value="">-- Select Service --</option>
                    <?php foreach ($services as $s): ?>
                        <option 
                            value="<?php echo $s['service_id']; ?>" 
                            data-duration="<?php echo $s['duration_minutes']; ?>"
                        >
                            <?php echo htmlspecialchars($s['name']) . " ($" . number_format($s['price'], 2) . " - " . $s['duration_minutes'] . " min)"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-4">
                <label for="staff_id">Preferred Stylist <span class="text-danger">*</span></label>
                <select id="staff_id" name="staff_id" class="form-control" required>
                    <option value="">-- Select Stylist --</option>
                    <?php foreach ($staff as $st): ?>
                        <option value="<?php echo $st['staff_id']; ?>"><?php echo htmlspecialchars($st['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <hr>
            <h4 class="mb-3">3. Date & Time</h4>

            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label for="app_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="app_date" name="app_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6 form-group mb-4">
                    <label for="app_time">Time (HH:MM) <span class="text-danger">*</span></label>
                    <!-- Step="1800" enforces 30-minute intervals -->
                    <input type="time" id="app_time" name="app_time" class="form-control" required step="1800">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">Confirm and Book</button>
        </form>
    </div>
</div>

<?php 
$conn->close();
include 'template/footer.php';
?>