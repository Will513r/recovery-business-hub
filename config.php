<?php
// config.php
// Store your database credentials securely. 
// On Hostinger, your host is usually 'localhost' or an IP provided in your panel.
$db_host = 'localhost';
$db_user = 'your_database_user';
$db_pass = 'your_database_password';
$db_name = 'your_database_name';

// Create a new MySQLi connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check if the connection was successful
if ($conn->connect_error) {
    // If it fails, stop the script and print the error
    die("Database Connection Failed: " . $conn->connect_error);
}

// Optional: Set character set to utf8mb4 for proper text encoding
$conn->set_charset("utf8mb4");
