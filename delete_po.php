<?php
require_once 'session.php';
require_once 'db.php';

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: po_list.php");
    exit();
}

$po_id = intval($_GET['id']);

try {
    // Start transaction
    $conn->begin_transaction();

    // First check if PO exists and get its status
    $check_query = "SELECT status FROM purchase_orders WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $po = $result->fetch_assoc();

    if (!$po) {
        throw new Exception("Purchase Order not found");
    }

    // Only allow deletion if status is 'pending'
    if ($po['status'] !== 'pending') {
        throw new Exception("Cannot delete a Purchase Order that is not in pending status");
    }

    // Delete PO items first (due to foreign key constraint)
    $delete_items = "DELETE FROM po_items WHERE po_id = ?";
    $stmt = $conn->prepare($delete_items);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();

    // Then delete the PO
    $delete_po = "DELETE FROM purchase_orders WHERE id = ?";
    $stmt = $conn->prepare($delete_po);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Redirect with success message
    header("Location: po_list.php?success=2"); // 2 for deletion success
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Redirect with error message
    header("Location: po_list.php?error=" . urlencode($e->getMessage()));
    exit();
}
?> 