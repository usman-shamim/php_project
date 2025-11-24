<?php
$page_title = "Manage Services";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/functions.php';
require_once '../db_connect.php';

// --- CORRECTED FUNCTION CALL ---
check_login();
// Using the function defined in your config/functions.php
check_access('admin');

$message = '';

// --- I. Handle Form Submissions ---

// 1. Add New Service
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $duration = (int)$_POST['duration_minutes'];
    $price = (float)$_POST['price'];
    $description = trim($_POST['description']);

    // Check if the service name already exists
    $check_sql = "SELECT service_id FROM services WHERE name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = '<p class="error-message">Error: A service with this name already exists.</p>';
    } else {
        $sql = "INSERT INTO services (name, duration_minutes, price, description, is_active) VALUES (?, ?, ?, ?, TRUE)";
        $stmt = $conn->prepare($sql);
        
        // Correct types for (name, duration, price, description) -> (s, i, d, s)
        if ($stmt->bind_param('sids', $name, $duration, $price, $description)) {
            if ($stmt->execute()) {
                $message = '<p class="success-message">Service "' . htmlspecialchars($name) . '" added successfully!</p>';
            } else {
                $message = '<p class="error-message">Error adding service: ' . $conn->error . '</p>';
            }
        } else {
             $message = '<p class="error-message">Error preparing statement parameters.</p>';
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// 2. Update Existing Service
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update') {
    $service_id = (int)$_POST['service_id'];
    $name = trim($_POST['name']);
    $duration = (int)$_POST['duration_minutes'];
    $price = (float)$_POST['price'];
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = "UPDATE services SET name = ?, duration_minutes = ?, price = ?, description = ?, is_active = ? WHERE service_id = ?";
    $stmt = $conn->prepare($sql);
    // Types: s (name), i (duration), d (price), s (description), i (is_active), i (service_id)
    $stmt->bind_param('sidsii', $name, $duration, $price, $description, $is_active, $service_id);

    if ($stmt->execute()) {
        $message = '<p class="success-message">Service ID ' . $service_id . ' updated successfully!</p>';
    } else {
        $message = '<p class="error-message">Error updating service: ' . $conn->error . '</p>';
    }
    $stmt->close();
}

// 3. Status Update (Deactivate/Activate)
if (isset($_GET['action']) && $_GET['action'] === 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $service_id = (int)$_GET['id'];
    $status = (int)$_GET['status']; // 0 for Inactive, 1 for Active

    $sql = "UPDATE services SET is_active = ? WHERE service_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $status, $service_id);

    if ($stmt->execute()) {
        $message = '<p class="success-message">Service ID ' . $service_id . ' status updated successfully!</p>';
    } else {
        $message = '<p class="error-message">Error updating status: ' . $conn->error . '</p>';
    }
    $stmt->close();
    // Redirect to clean up URL
    header("Location: services_manage.php");
    exit();
}

// 4. Delete Service (Safety check - not usually done in real systems, but provided for completeness)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $service_id = (int)$_GET['id'];
    
    // Check for existing appointments linked to this service
    $check_app_sql = "SELECT app_id FROM appointments WHERE service_id = ?";
    $check_app_stmt = $conn->prepare($check_app_sql);
    $check_app_stmt->bind_param("i", $service_id);
    $check_app_stmt->execute();
    $check_app_result = $check_app_stmt->get_result();

    if ($check_app_result->num_rows > 0) {
        $message = '<p class="error-message">Cannot delete Service ID ' . $service_id . ' because there are ' . $check_app_result->num_rows . ' appointments currently linked to it. Please deactivate instead.</p>';
    } else {
        $sql = "DELETE FROM services WHERE service_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $service_id);

        if ($stmt->execute()) {
            $message = '<p class="success-message">Service ID ' . $service_id . ' deleted successfully!</p>';
        } else {
            $message = '<p class="error-message">Error deleting service: ' . $conn->error . '</p>';
        }
        $stmt->close();
    }
    $check_app_stmt->close();
    // Redirect to clean up URL
    header("Location: services_manage.php");
    exit();
}


// --- II. Fetch All Services for Display ---
$services = $conn->query("SELECT * FROM services ORDER BY is_active DESC, name ASC")->fetch_all(MYSQLI_ASSOC);

include '../template/header.php';
echo $message;
?>

<h2>Service Management</h2>

<!-- Add New Service Form -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
        Add New Service
    </div>
    <div class="card-body">
        <form method="POST" action="services_manage.php">
            <input type="hidden" name="action" value="add">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Service Name:</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="duration_minutes" class="form-label">Duration (Minutes):</label>
                    <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" required min="5" step="5">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="price" class="form-label">Price ($):</label>
                    <input type="number" class="form-control" id="price" name="price" required min="0.01" step="0.01">
                </div>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description (for clients):</label>
                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Service</button>
        </form>
    </div>
</div>

<!-- Services List -->
<h3>Current Services</h3>
<?php if (empty($services)): ?>
    <p class="alert alert-info">No services have been defined yet.</p>
<?php else: ?>
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Price</th>
                <th>Duration (Min)</th>
                <th>Description</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
                <td><?php echo $service['service_id']; ?></td>
                <td><?php echo htmlspecialchars($service['name']); ?></td>
                <td>$<?php echo number_format($service['price'], 2); ?></td>
                <td><?php echo $service['duration_minutes']; ?></td>
                <td><?php echo htmlspecialchars(substr($service['description'], 0, 50)) . (strlen($service['description']) > 50 ? '...' : ''); ?></td>
                <td>
                    <span class="badge <?php echo $service['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td>
                    <!-- Edit Button triggers modal or in-line form. Here, we'll use a simple edit link for simplicity -->
                    <button class="btn btn-sm btn-info text-white" 
                        onclick="document.getElementById('edit-form-<?php echo $service['service_id']; ?>').style.display = 'table-row'; 
                                 this.closest('td').querySelector('.edit-button').style.display = 'none';">
                        <span class="edit-button">Edit</span>
                    </button>
                    <!-- Deactivate/Activate link -->
                    <?php if ($service['is_active']): ?>
                        <a href="?action=update_status&id=<?php echo $service['service_id']; ?>&status=0" class="btn btn-sm btn-warning">Deactivate</a>
                    <?php else: ?>
                        <a href="?action=update_status&id=<?php echo $service['service_id']; ?>&status=1" class="btn btn-sm btn-success">Activate</a>
                    <?php endif; ?>

                </td>
            </tr>
            <!-- In-line Edit Form (hidden by default) -->
            <tr id="edit-form-<?php echo $service['service_id']; ?>" style="display:none; background-color: #e9f7ff;">
                <td colspan="7">
                    <form method="POST" action="services_manage.php" class="p-3 border rounded">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name:</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($service['name']); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Duration (Min):</label>
                                <input type="number" class="form-control" name="duration_minutes" value="<?php echo $service['duration_minutes']; ?>" required min="5" step="5">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Price ($):</label>
                                <input type="number" class="form-control" name="price" value="<?php echo $service['price']; ?>" required min="0.01" step="0.01">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description:</label>
                            <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($service['description']); ?></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active_<?php echo $service['service_id']; ?>" name="is_active" value="1" <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active_<?php echo $service['service_id']; ?>">
                                Active (Visible for public booking)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm me-2">Save Changes</button>
                        <a href="?action=delete&id=<?php echo $service['service_id']; ?>" 
                           onclick="return confirm('WARNING: Are you sure you want to PERMANENTLY delete this service?');" 
                           class="btn btn-danger btn-sm">Delete</a>
                        <button type="button" class="btn btn-secondary btn-sm" 
                            onclick="document.getElementById('edit-form-<?php echo $service['service_id']; ?>').style.display = 'none'; 
                                     document.querySelector('button[onclick*=\'edit-form-<?php echo $service['service_id']; ?>\']').style.display = 'block';">
                            Cancel
                        </button>
                    </form>
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