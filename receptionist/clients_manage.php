<?php
$page_title = "Client Management";
// Include necessary files and enforce access control
require_once '../config/functions.php';
require_once '../db_connect.php';

// Check if user is logged in and has access (Admin OR Receptionist)
check_login();
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'receptionist') {
    header("Location: ../dashboard.php?error=Unauthorized Access");
    exit();
}

$message = '';
$client_to_edit = null; // Used to pre-fill the form for editing

// --- I. Handle Form Submission (Add or Edit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $client_id = $_POST['client_id'] ?? null;
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $preferences = trim($_POST['preferences']);

    // Simple Validation
    if (empty($name) || empty($phone)) {
        $message = '<p class="error-message">Client Name and Phone are required fields.</p>';
    } else {
        if ($client_id) {
            // UPDATE Operation (Edit Client)
            $sql = "UPDATE clients SET name=?, phone=?, email=?, preferences=? WHERE client_id=?";
            $stmt = $conn->prepare($sql);
            // 'sssi' means String, String, String, Integer
            $stmt->bind_param("ssssi", $name, $phone, $email, $preferences, $client_id);
            if ($stmt->execute()) {
                $message = '<p class="success-message">Client updated successfully.</p>';
            } else {
                $message = '<p class="error-message">Error updating client: ' . $conn->error . '</p>';
            }
        } else {
            // INSERT Operation (Add New Client)
            $sql = "INSERT INTO clients (name, phone, email, preferences) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            // 'ssss' means String, String, String, String
            $stmt->bind_param("ssss", $name, $phone, $email, $preferences);
            if ($stmt->execute()) {
                $message = '<p class="success-message">New client added successfully.</p>';
            } else {
                // Check for duplicate key error (e.g., duplicate phone/email)
                if ($conn->errno == 1062) {
                    $message = '<p class="error-message">Error adding client: Phone or Email may already exist.</p>';
                } else {
                    $message = '<p class="error-message">Error adding client: ' . $conn->error . '</p>';
                }
            }
        }
        $stmt->close();
    }
}

// --- II. Handle Edit Request ---
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $sql = "SELECT client_id, name, phone, email, preferences FROM clients WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $client_to_edit = $result->fetch_assoc();
    } else {
        $message = '<p class="error-message">Client not found.</p>';
    }
    $stmt->close();
}

// --- III. Fetch All Clients for List View ---
$clients = [];
$sql = "SELECT * FROM clients ORDER BY name ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
} else {
    $message = '<p class="error-message">Error fetching clients: ' . $conn->error . '</p>';
}

include '../templates/header.php'; // Start HTML output
?>

<h2>Client Management (<?php echo $client_to_edit ? 'Edit' : 'Add New'; ?> Client)</h2>

<?php echo $message; ?>

<form method="POST" action="clients_manage.php">
    <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_to_edit['client_id'] ?? ''); ?>">
    
    <div class="form-group">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($client_to_edit['name'] ?? ''); ?>" required>
    </div>
    
    <div class="form-group">
        <label for="phone">Phone:</label>
        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($client_to_edit['phone'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client_to_edit['email'] ?? ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="preferences">Preferences / Notes:</label>
        <textarea id="preferences" name="preferences"><?php echo htmlspecialchars($client_to_edit['preferences'] ?? ''); ?></textarea>
    </div>
    
    <button type="submit" class="btn"><?php echo $client_to_edit ? 'Update Client' : 'Add Client'; ?></button>
    <?php if ($client_to_edit): ?>
        <a href="clients_manage.php" class="btn btn-danger">Cancel Edit</a>
    <?php endif; ?>
</form>

<hr>

<h2>Client List</h2>

<?php if (empty($clients)): ?>
    <p>No clients found in the database.</p>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Preferences</th>
                <th>Member Since</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $client): ?>
            <tr>
                <td><?php echo htmlspecialchars($client['client_id']); ?></td>
                <td><?php echo htmlspecialchars($client['name']); ?></td>
                <td><?php echo htmlspecialchars($client['phone']); ?></td>
                <td><?php echo htmlspecialchars($client['email']); ?></td>
                <td><?php echo htmlspecialchars(substr($client['preferences'], 0, 50)) . ''; ?></td>
                <td><?php echo date('Y-m-d', strtotime($client['created_at'])); ?></td>
                <td>
                    <a href="clients_manage.php?edit_id=<?php echo $client['client_id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="client_delete.php?id=<?php echo $client['client_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete <?php echo addslashes($client['name']); ?>? This cannot be undone.');">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php 
$conn->close();
include '../templates/footer.php'; // End HTML output
?>