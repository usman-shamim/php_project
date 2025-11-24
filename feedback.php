<?php
// feedback.php
require_once 'db_connect.php'; // Use the existing connection

$feedback_status = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $comments = trim($_POST['comments']);
    $rating = (int)($_POST['rating'] ?? 5);

    if (empty($comments)) {
        $feedback_status = '<p class="error-message">Comments field cannot be empty.</p>';
    } else {
        // Secure INSERT using prepared statements
        $sql = "INSERT INTO feedback (name, email, rating, comments) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // 'ssis' means String, String, Integer, String
        $stmt->bind_param("ssis", $name, $email, $rating, $comments);

        if ($stmt->execute()) {
            $feedback_status = '<p class="success-message">Thank you for your feedback! It has been submitted successfully.</p>';
            // Clear form fields
            $_POST = array(); 
        } else {
            $feedback_status = '<p class="error-message">Error submitting feedback: ' . $conn->error . '</p>';
        }
        $stmt->close();
    }
}
$conn->close();
?>

<h2>Submit Feedback</h2>

<?php echo $feedback_status; ?>

<form method="POST" action="index.php?page=feedback">
    <input type="hidden" name="submit_feedback" value="1">

    <div class="form-group">
        <label for="name">Your Name (Optional):</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="email">Your Email (Optional):</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
    </div>

    <div class="form-group">
        <label for="rating">Application Rating (1=Poor, 5=Excellent):</label>
        <select id="rating" name="rating">
            <option value="5" selected>5 - Excellent</option>
            <option value="4">4 - Good</option>
            <option value="3">3 - Fair</option>
            <option value="2">2 - Poor</option>
            <option value="1">1 - Very Poor</option>
        </select>
    </div>
    
    <div class="form-group">
        <label for="comments">Comments (Required):</label>
        <textarea id="comments" name="comments" rows="5" required><?php echo htmlspecialchars($_POST['comments'] ?? ''); ?></textarea>
    </div>
    
    <button type="submit" class="btn">Submit Feedback</button>
</form>

<?php
// Re-open connection for the footer if needed elsewhere, though we close it above.
require_once 'db_connect.php'; 
?>