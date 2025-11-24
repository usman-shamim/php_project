<?php
// Define connection constants
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // CHANGE THIS IN A PRODUCTION ENVIRONMENT
define('DB_PASSWORD', ''); // CHANGE THIS!
define('DB_NAME', 'salon_management'); 

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Stop execution and show error if connection fails
    die("Connection failed: " . $conn->connect_error);
}

// Set character set for proper data handling
$conn->set_charset("utf8");
?>