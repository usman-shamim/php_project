<?php
$page_title = "Reporting & Analytics";
require_once '../config/functions.php';
require_once '../db_connect.php';

// Check if user is logged in and is an Admin
check_login();
check_access('admin');

// --- I. Data Collection Functions ---

/**
 * Fetches total sales and appointment count for a given date range.
 * Defaults to the last 30 days.
 */
function get_sales_report($conn, $start_date, $end_date) {
    $report = ['total_revenue' => 0, 'total_appointments' => 0];

    // Total Revenue (Only from Completed/Paid Appointments)
    $sql_revenue = "
        SELECT SUM(p.amount_paid) AS total_revenue
        FROM payments p
        JOIN appointments a ON p.app_id = a.app_id
        WHERE p.payment_date BETWEEN ? AND ?
    ";
    $stmt_rev = $conn->prepare($sql_revenue);
    $stmt_rev->bind_param("ss", $start_date, $end_date);
    $stmt_rev->execute();
    $result_rev = $stmt_rev->get_result();
    $report['total_revenue'] = $result_rev->fetch_assoc()['total_revenue'] ?? 0;
    $stmt_rev->close();

    // Total Appointments (Completed Status)
    $sql_app_count = "
        SELECT COUNT(app_id) AS total_appointments
        FROM appointments 
        WHERE status = 'Completed' AND start_time BETWEEN ? AND ?
    ";
    $stmt_count = $conn->prepare($sql_app_count);
    $stmt_count->bind_param("ss", $start_date, $end_date);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $report['total_appointments'] = $result_count->fetch_assoc()['total_appointments'] ?? 0;
    $stmt_count->close();

    return $report;
}

/**
 * Fetches the popularity of services.
 */
function get_popular_services($conn, $start_date, $end_date) {
    $sql = "
        SELECT 
            s.name, 
            COUNT(a.app_id) AS booking_count
        FROM appointments a
        JOIN services s ON a.service_id = s.service_id
        WHERE a.start_time BETWEEN ? AND ? AND a.status IN ('Completed', 'Booked')
        GROUP BY s.service_id
        ORDER BY booking_count DESC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Fetches staff performance based on completed appointments.
 */
function get_staff_performance($conn, $start_date, $end_date) {
    $sql = "
        SELECT
            u.username AS staff_name,
            COUNT(a.app_id) AS appointments_completed,
            SUM(s.price) AS total_revenue_generated
        FROM appointments a
        JOIN staff st ON a.staff_id = st.staff_id
        JOIN users u ON st.user_id = u.user_id
        JOIN services s ON a.service_id = s.service_id
        WHERE a.status = 'Completed' AND a.start_time BETWEEN ? AND ?
        GROUP BY st.staff_id
        ORDER BY appointments_completed DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


// --- II. Determine Date Range ---
$today = date('Y-m-d');
// Default range: Last 30 days
$default_start = date('Y-m-d', strtotime('-30 days'));

$report_start_date = $_GET['start_date'] ?? $default_start;
$report_end_date = $_GET['end_date'] ?? $today;

// Add 23:59:59 to end date to include all appointments on that day
$end_datetime = $report_end_date . " 23:59:59";


// --- III. Execute Reports ---
$sales_data = get_sales_report($conn, $report_start_date, $end_datetime);
$popular_services = get_popular_services($conn, $report_start_date, $end_datetime);
$staff_performance = get_staff_performance($conn, $report_start_date, $end_datetime);


include '../template/header.php';
?>

<h2>Business Reporting & Analytics</h2>

<p>Analyzing data from <strong><?php echo htmlspecialchars($report_start_date); ?></strong> to <strong><?php echo htmlspecialchars($report_end_date); ?></strong>.</p>

<form method="GET" action="reports_analytics.php" style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc;">
    <label for="start_date">Start Date:</label>
    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($report_start_date); ?>" required>
    
    <label for="end_date" style="margin-left: 15px;">End Date:</label>
    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($report_end_date); ?>" required>
    
    <button type="submit" class="btn" style="margin-left: 15px;">Generate Report</button>
</form>

---

<h3>💰 Financial Summary</h3>

<div style="display: flex; gap: 20px;">
    <div class="card" style="flex: 1; background-color: #e6ffe6; border: 1px solid green;">
        <h4>Total Revenue (Paid)</h4>
        <p style="font-size: 24px; font-weight: bold; color: green;">$<?php echo number_format($sales_data['total_revenue'], 2); ?></p>
    </div>
    <div class="card" style="flex: 1; background-color: #e6f7ff; border: 1px solid #007bff;">
        <h4>Total Completed Appointments</h4>
        <p style="font-size: 24px; font-weight: bold; color: #007bff;"><?php echo htmlspecialchars($sales_data['total_appointments']); ?></p>
    </div>
</div>

---

<h3>📈 Popular Services (Top 10)</h3>
<?php if (empty($popular_services)): ?>
    <p>No service data found for this period.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Service Name</th>
                <th>Total Bookings</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($popular_services as $service): ?>
            <tr>
                <td><?php echo htmlspecialchars($service['name']); ?></td>
                <td><?php echo htmlspecialchars($service['booking_count']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

---

<h3>👥 Staff Performance</h3>
<?php if (empty($staff_performance)): ?>
    <p>No staff performance data found for this period (check if appointments are marked as 'Completed').</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Stylist</th>
                <th>Appointments Completed</th>
                <th>Revenue Generated</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($staff_performance as $staff): ?>
            <tr>
                <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                <td><?php echo htmlspecialchars($staff['appointments_completed']); ?></td>
                <td>$<?php echo number_format($staff['total_revenue_generated'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php 
$conn->close();
include '../template/footer.php';
?>