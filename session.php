<?php
// session.php
session_start();
require 'db.php';

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Prepare the SQL statement
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
if (!$stmt) {
    die("Prepare failed (session.php): (" . $conn->errno . ") " . $conn->error);
}

// Bind parameters
if (!$stmt->bind_param("i", $_SESSION['user_id'])) {
    die("Binding parameters failed (session.php): (" . $stmt->errno . ") " . $stmt->error);
}

// Execute the statement
if (!$stmt->execute()) {
    die("Execute failed (session.php): (" . $stmt->errno . ") " . $stmt->error);
}

// Bind result variables
if (!$stmt->bind_result($user_role)) {
    die("Binding result failed (session.php): (" . $stmt->errno . ") " . $stmt->error);
}

// Fetch the result
if (!$stmt->fetch()) {
    die("Fetching result failed (session.php): (" . $stmt->errno . ") " . $stmt->error);
}

$stmt->close();

// Store role in session
$_SESSION['role'] = $user_role;

// Function to log actions
function log_action($conn, $user_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    if (!$stmt) {
        // Optionally log this error to a file instead of displaying it
        return;
    }
    $stmt->bind_param("iss", $user_id, $action, $details);
    if (!$stmt->execute()) {
        // Optionally log this error to a file instead of displaying it
    }
    $stmt->close();
}
?>
