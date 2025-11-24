<?php
$page_title = "Staff & Commission Management";
require_once '../config/functions.php';
require_once '../db_connect.php';

// Check if user is logged in and is an Admin
check_login();
check_access('admin');

$message = '';
$staff_to_edit = null;

// --- I. COMMISSION CALCULATION (Run on page load if needed) ---
// This logic checks for COMPLETED but UNPROCESSED appointments and calculates commission.
function calculate_commissions($conn) {
    // 1. Find completed appointments that haven't been paid commission yet
    $sql_unpaid = "
        SELECT a.app_id, a.staff_id, s.price, st.commission_rate
        FROM appointments a
        JOIN services s ON a.service_id = s.service_id
        JOIN staff st ON a.staff_id = st.staff_id
        LEFT JOIN commissions c ON a.app_id = c.app_id
        WHERE a.status = 'Completed' AND c.commission_id IS NULL;
    ";
    $result_unpaid = $conn->query($sql_unpaid);
    $count = 0;

    if ($result_unpaid->num_rows > 0) {
        $stmt_insert = $conn->prepare("INSERT INTO commissions (app_id, staff_id, commission_amount) VALUES (?, ?, ?)");
        
        while ($row = $result_unpaid->fetch_assoc()) {
            $commission_rate = $row['commission_rate'] / 100.0;
            $commission_amount = $row['price'] * $commission_rate;
            
            // Insert the commission record
            $stmt_insert->bind_param("iid", $row['app_id'], $row['staff_id'], $commission_amount);
            if ($stmt_insert->execute()) {
                $count++;
            }
        }
        $stmt_insert->close();
    }
    return $count;
}

// Automatically calculate new commissions on page load
$new_commissions = calculate_commissions($conn);
if ($new_commissions > 0) {
    $message .= '<p class="success-message">🎉 Calculated ' . $new_commissions . ' new commission record(s).</p>';
}


// --- II. Handle Staff Profile Submission (Edit Commission Rate) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'staff_profile') {
    $staff_id = (int)$_POST['staff_id'];
    $commission_rate = (float)$_POST['commission_rate'];

    $sql = "UPDATE staff SET commission_rate = ? WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $commission_rate, $staff_id);
    
    if ($stmt->execute()) {
        $message .= '<p class="success-message">Staff commission rate updated successfully.</p>';
    } else {
        $message .= '<p class="error-message">Error updating staff profile: ' . $conn->error . '</p>';
    }
    $stmt->close();
}

// --- III. Handle Schedule Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'schedule') {
    $staff_id = (int)$_POST['staff_id'];
    $day = $_POST['day_of_week'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];

    // Try to insert (or update if already exists due to UNIQUE KEY constraint)
    $sql = "INSERT INTO staff_schedules (staff_id, day_of_week, start_time, end_time) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $staff_id, $day, $start, $end);
    
    if ($stmt->execute()) {
        $message .= '<p class="success-message">Schedule updated for ' . htmlspecialchars($day) . '.</p>';
    } else {
        $message .= '<p class="error-message">Error updating schedule: ' . $conn->error . '</p>';
    }
    $stmt->close();
}

// --- IV. Handle Commission Payment ---
if (isset($_GET['action']) && $_GET['action'] === 'pay_commission' && is_numeric($_GET['id'])) {
    $commission_id = (int)$_GET['id'];
    $sql = "UPDATE commissions SET payment_status = 'Paid' WHERE commission_id = ? AND payment_status = 'Pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $commission_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message .= '<p class="success-message">Commission record ' . $commission_id . ' marked as PAID.</p>';
    } else {
        $message .= '<p class="error-message">Error or commission already paid.</p>';
    }
    $stmt->close();
    header("Location: staff_manage.php?status=" . urlencode(strip_tags($message)));
    exit();
}

// --- V. Fetch All Staff, Schedules, and Commissions ---
$staff_list = [];
$sql_staff = "SELECT s.staff_id, s.commission_rate, u.username, u.email, u.user_id FROM staff s JOIN users u ON s.user_id = u.user_id ORDER BY u.username";
$staff_list = $conn->query($sql_staff)->fetch_all(MYSQLI_ASSOC);

$schedules = [];
$result_sched = $conn->query("SELECT * FROM staff_schedules");
while($row = $result_sched->fetch_assoc()) {
    $schedules[$row['staff_id']][$row['day_of_week']] = $row;
}

$commissions = [];
$sql_comm = "
    SELECT 
        c.commission_id, c.commission_amount, c.payment_status, c.commission_date,
        u.username AS staff_name, 
        s.name AS service_name, 
        a.start_time
    FROM commissions c
    JOIN staff st ON c.staff_id = st.staff_id
    JOIN users u ON st.user_id = u.user_id
    JOIN appointments a ON c.app_id = a.app_id
    JOIN services s ON a.service_id = s.service_id
    ORDER BY c.payment_status DESC, c.commission_date DESC
";
$commissions = $conn->query($sql_comm)->fetch_all(MYSQLI_ASSOC);


include '../template/header.php';
// Display any status message from a redirect
if (isset($_GET['status'])) {
    echo '<p class="success-message">' . htmlspecialchars($_GET['status']) . '</p>';
}
echo $message;
?>

<h2>Staff Management & Scheduling</h2>

<?php if (empty($staff_list)): ?>
    <p class="error-message">No staff members found. Please create a user with the 'stylist' role and link them via the database initially.</p>
<?php endif; ?>

<?php foreach ($staff_list as $staff): ?>
<div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;">
    <h3>Stylist: <?php echo htmlspecialchars($staff['username']); ?> (ID: <?php echo $staff['staff_id']; ?>)</h3>
    
    <h4>Profile</h4>
    <form method="POST" action="staff_manage.php" style="margin-bottom: 15px;">
        <input type="hidden" name="form_type" value="staff_profile">
        <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
        
        <div class="form-group">
            <label for="rate-<?php echo $staff['staff_id']; ?>">Commission Rate (%):</label>
            <input type="number" step="0.01" id="rate-<?php echo $staff['staff_id']; ?>" name="commission_rate" value="<?php echo htmlspecialchars($staff['commission_rate']); ?>" required min="0">
        </div>
        <button type="submit" class="btn">Update Rate</button>
    </form>
    
    <h4>Work Schedule</h4>
    <table style="width: auto;">
        <thead><tr><th>Day</th><th>Start Time</th><th>End Time</th><th>Action</th></tr></thead>
        <tbody>
        <?php 
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        foreach ($days as $day):
            $sch = $schedules[$staff['staff_id']][$day] ?? null;
        ?>
            <tr>
                <form method="POST" action="staff_manage.php">
                    <input type="hidden" name="form_type" value="schedule">
                    <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                    <input type="hidden" name="day_of_week" value="<?php echo $day; ?>">
                    <td><?php echo $day; ?></td>
                    <td><input type="time" name="start_time" value="<?php echo htmlspecialchars($sch['start_time'] ?? '09:00'); ?>" required></td>
                    <td><input type="time" name="end_time" value="<?php echo htmlspecialchars($sch['end_time'] ?? '17:00'); ?>" required></td>
                    <td><button type="submit" class="btn">Save</button></td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>
<?php endforeach; ?>

<hr>

<h2>Commission Payout Tracking</h2>

<?php if (empty($commissions)): ?>
    <p>No commission records found. Ensure appointments are marked 'Completed' and paid for calculations to run.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date Earned</th>
                <th>Staff</th>
                <th>Service</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($commissions as $comm): ?>
            <tr>
                <td><?php echo date('Y-m-d', strtotime($comm['commission_date'])); ?> (Appt: <?php echo date('H:i', strtotime($comm['start_time'])); ?>)</td>
                <td><?php echo htmlspecialchars($comm['staff_name']); ?></td>
                <td><?php echo htmlspecialchars($comm['service_name']); ?></td>
                <td>$<?php echo number_format($comm['commission_amount'], 2); ?></td>
                <td>
                    <strong style="color: <?php echo ($comm['payment_status'] === 'Paid') ? 'green' : 'red'; ?>;">
                        <?php echo htmlspecialchars($comm['payment_status']); ?>
                    </strong>
                </td>
                <td>
                    <?php if ($comm['payment_status'] === 'Pending'): ?>
                        <a href="staff_manage.php?action=pay_commission&id=<?php echo $comm['commission_id']; ?>" class="btn" onclick="return confirm('Confirm commission payment of $<?php echo number_format($comm['commission_amount'], 2); ?> to <?php echo addslashes($comm['staff_name']); ?>?');">Mark Paid</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php 
$conn->close();
include '../template/footer.php';
?>