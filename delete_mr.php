<?php
require_once 'session.php';
require_once 'db.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get MR ID from query string
$mr_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($mr_id <= 0) { // Validate MR ID
    die("Error: Invalid MR ID.");
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Delete items first due to foreign key constraints
    $delete_items_query = "DELETE FROM mr_items WHERE mr_id = ?";
    $delete_items_stmt = $conn->prepare($delete_items_query);
    if (!$delete_items_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $delete_items_stmt->bind_param("i", $mr_id);
    $delete_items_stmt->execute();

    // Delete the MR
    $delete_mr_query = "DELETE FROM material_requisitions WHERE id = ?";
    $delete_mr_stmt = $conn->prepare($delete_mr_query);
    if (!$delete_mr_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $delete_mr_stmt->bind_param("i", $mr_id);
    $delete_mr_stmt->execute();

    // Commit transaction
    $conn->commit();

    // Redirect on success
    header("Location: mr_list.php?success=1");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $error_message = "Error: " . $e->getMessage();
    error_log($error_message);
    header("Location: mr_list.php?error=1");
    exit();
} 