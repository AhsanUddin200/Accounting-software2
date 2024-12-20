<?php
// session.php
session_start();
require_once 'db.php';          // Include database connection
require_once 'functions.php';   // Include common functions

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Prepare the SQL statement to fetch user role
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

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
