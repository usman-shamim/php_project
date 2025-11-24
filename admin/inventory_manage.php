<?php
$page_title = "Inventory Management";
require_once '../config/functions.php';
require_once '../db_connect.php';

// Check if user is logged in and is an Admin
check_login();
check_access('admin');

$message = '';
$item_to_edit = null;

// --- I. Handle Inventory Form Submission (Add, Edit, or Restock) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'inventory') {
    $item_id = $_POST['item_id'] ?? null;
    $name = trim($_POST['name']);
    $supplier_id = (int)$_POST['supplier_id'];
    $stock_level = (int)$_POST['stock_level'];
    $threshold = (int)$_POST['low_stock_threshold'];
    $cost = (float)$_POST['unit_cost'];

    if (empty($name) || $stock_level < 0 || $threshold < 0 || $cost < 0) {
        $message = '<p class="error-message">All fields must contain valid, non-negative values.</p>';
    } else {
        $last_restock = date('Y-m-d'); // Set restock date on any stock level change

        if ($item_id) {
            // UPDATE Operation (Edit Item)
            $sql = "UPDATE inventory SET name=?, supplier_id=?, stock_level=?, low_stock_threshold=?, unit_cost=?, last_restock_date=? WHERE item_id=?";
            $stmt = $conn->prepare($sql);
            // 'siiidsi' means String, Integer, Integer, Integer, Double, String, Integer
            $stmt->bind_param("siiidsi", $name, $supplier_id, $stock_level, $threshold, $cost, $last_restock, $item_id);
        } else {
            // INSERT Operation (Add New Item)
            $sql = "INSERT INTO inventory (name, supplier_id, stock_level, low_stock_threshold, unit_cost, last_restock_date) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            // 'siiids' means String, Integer, Integer, Integer, Double, String
            $stmt->bind_param("siiids", $name, $supplier_id, $stock_level, $threshold, $cost, $last_restock);
        }

        if ($stmt->execute()) {
            $message = '<p class="success-message">Inventory item ' . ($item_id ? 'updated' : 'added') . ' successfully.</p>';
            // --- NEW: Low Stock Notification Check ---
            if ($stock_level <= $threshold) {
                $item_name = $name; // Use the current item name
                $notif_message = "LOW STOCK ALERT: $item_name is at $stock_level units (Threshold: $threshold). Order immediately.";

                // Use a prepared statement to insert the notification
                $stmt_n = $conn->prepare("INSERT INTO notifications (type, message, related_id) VALUES ('Inventory', ?, ?)");
                // Since we don't have the item_id for a new insert yet, we'll pass NULL for related_id here for simplicity
                $null_id = NULL;
                $stmt_n->bind_param("si", $notif_message, $null_id);
                $stmt_n->execute();
                $stmt_n->close();
            }
        } else {
            $message = '<p class="error-message">Error: ' . $conn->error . '</p>';
        }
        $stmt->close();
    }
}

// --- II. Handle Edit or Delete Request ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? null;

    if ($id && is_numeric($id)) {
        if ($action === 'edit') {
            // Fetch item data for editing
            $sql = "SELECT item_id, name, supplier_id, stock_level, low_stock_threshold, unit_cost FROM inventory WHERE item_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $item_to_edit = $result->fetch_assoc();
            }
            $stmt->close();
        } elseif ($action === 'delete') {
            // DELETE Operation
            $sql = "DELETE FROM inventory WHERE item_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $message = '<p class="success-message">Inventory item deleted successfully.</p>';
            } else {
                $message = '<p class="error-message">Error deleting item: ' . $conn->error . '</p>';
            }
            $stmt->close();
            // Redirect to clear the GET parameters
            header("Location: inventory_manage.php?status=" . urlencode(strip_tags($message)));
            exit();
        }
    }
}

// --- III. Fetch Suppliers for Dropdown ---
$suppliers = $conn->query("SELECT supplier_id, name FROM suppliers ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// --- IV. Fetch All Inventory Items for List View ---
$inventory_list = [];
$sql = "
    SELECT 
        i.item_id, i.name, i.stock_level, i.low_stock_threshold, i.unit_cost, i.last_restock_date,
        s.name AS supplier_name
    FROM inventory i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    ORDER BY i.name ASC
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventory_list[] = $row;
    }
}


include '../template/header.php';
// Display any status message from a redirect
if (isset($_GET['status'])) {
    echo '<p class="success-message">' . htmlspecialchars($_GET['status']) . '</p>';
}
echo $message;
?>

<h2>Inventory Management</h2>

<form method="POST" action="inventory_manage.php">
    <h3><?php echo $item_to_edit ? 'Edit Item: ' . htmlspecialchars($item_to_edit['name']) : 'Add New Item'; ?></h3>
    <input type="hidden" name="form_type" value="inventory">
    <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item_to_edit['item_id'] ?? ''); ?>">

    <div class="form-group">
        <label for="name">Item Name:</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($item_to_edit['name'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label for="supplier_id">Supplier:</label>
        <select id="supplier_id" name="supplier_id">
            <option value="0">-- No Supplier --</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo $supplier['supplier_id']; ?>"
                    <?php echo (isset($item_to_edit['supplier_id']) && $item_to_edit['supplier_id'] == $supplier['supplier_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($supplier['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <label for="stock_level">Current Stock:</label>
            <input type="number" id="stock_level" name="stock_level" value="<?php echo htmlspecialchars($item_to_edit['stock_level'] ?? 0); ?>" required min="0">
        </div>
        <div style="flex: 1;">
            <label for="low_stock_threshold">Low Stock Threshold:</label>
            <input type="number" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo htmlspecialchars($item_to_edit['low_stock_threshold'] ?? 5); ?>" required min="1">
        </div>
        <div style="flex: 1;">
            <label for="unit_cost">Unit Cost ($):</label>
            <input type="number" step="0.01" id="unit_cost" name="unit_cost" value="<?php echo htmlspecialchars($item_to_edit['unit_cost'] ?? '0.00'); ?>" required min="0">
        </div>
    </div>

    <button type="submit" class="btn"><?php echo $item_to_edit ? 'Update Item' : 'Add Item'; ?></button>
    <?php if ($item_to_edit): ?>
        <a href="inventory_manage.php" class="btn btn-danger">Cancel Edit</a>
    <?php endif; ?>
</form>

<hr>

<h2>Stock Level Overview</h2>
<p style="color: red; font-weight: bold;">Items highlighted in red are at or below the low stock threshold.</p>

<?php
// --- V. Low Inventory Alert and Report Generation (Simple) ---
$low_stock_items = array_filter($inventory_list, function ($item) {
    return $item['stock_level'] <= $item['low_stock_threshold'];
});

if (!empty($low_stock_items)):
?>
    <h3 style="color: red;">🚨 LOW STOCK ALERT (<?php echo count($low_stock_items); ?> Items)</h3>
    <a href="?action=generate_report" class="btn" style="background-color: #f7a049;">Generate Purchase Order List</a>

    <?php
    // Simple code to "generate report" (In a complex system, this would write a file/email)
    if (isset($_GET['action']) && $_GET['action'] === 'generate_report'): ?>
        <div style="border: 1px solid #ccc; padding: 15px; margin-top: 10px; background-color: #fff3cd;">
            <h4>Purchase Order Summary:</h4>
            <ul>
                <?php foreach ($low_stock_items as $item):
                    // Calculate quantity to order (e.g., reorder up to 3x threshold)
                    $qty_to_order = $item['low_stock_threshold'] * 3;
                ?>
                    <li>**<?php echo htmlspecialchars($item['name']); ?>** (Current: <?php echo $item['stock_level']; ?>) - Order **<?php echo $qty_to_order; ?>** from <?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?>.</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Item Name</th>
            <th>Supplier</th>
            <th>Stock Level</th>
            <th>Cost (Unit)</th>
            <th>Value (Total)</th>
            <th>Last Restock</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($inventory_list as $item): ?>
            <tr <?php echo ($item['stock_level'] <= $item['low_stock_threshold']) ? 'style="background-color: #ffdddd;"' : ''; ?>>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($item['stock_level']); ?> / T:<?php echo htmlspecialchars($item['low_stock_threshold']); ?></td>
                <td>$<?php echo htmlspecialchars(number_format($item['unit_cost'], 2)); ?></td>
                <td>$<?php echo htmlspecialchars(number_format($item['stock_level'] * $item['unit_cost'], 2)); ?></td>
                <td><?php echo htmlspecialchars($item['last_restock_date'] ?? 'N/A'); ?></td>
                <td>
                    <a href="inventory_manage.php?action=edit&id=<?php echo $item['item_id']; ?>" class="btn">Edit</a>
                    <a href="inventory_manage.php?action=delete&id=<?php echo $item['item_id']; ?>" class="btn btn-danger" onclick="return confirm('Confirm deletion of <?php echo addslashes($item['name']); ?>?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
// Ensure the connection is closed ONLY HERE
if (isset($conn)) {
    $conn->close();
}
include '../template/footer.php';
?>