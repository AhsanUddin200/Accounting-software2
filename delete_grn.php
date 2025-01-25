<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: grn_list.php?error=Invalid GRN ID");
    exit;
}

$grn_id = intval($_GET['id']);

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if GRN exists and get its status
    $check_query = "SELECT status FROM goods_receipt_notes WHERE id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $grn_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $grn = $result->fetch_assoc();

    if (!$grn) {
        throw new Exception('GRN not found');
    }

    // Only allow deletion if status is 'pending'
    if ($grn['status'] !== 'pending') {
        throw new Exception('Only pending GRNs can be deleted');
    }

    // Delete GRN items first (due to foreign key constraint)
    $delete_items = "DELETE FROM grn_items WHERE grn_id = ?";
    $stmt = $conn->prepare($delete_items);
    $stmt->bind_param("i", $grn_id);
    $stmt->execute();

    // Delete GRN header
    $delete_grn = "DELETE FROM goods_receipt_notes WHERE id = ?";
    $stmt = $conn->prepare($delete_grn);
    $stmt->bind_param("i", $grn_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Log the deletion
    $user = $_SESSION['user_name'] ?? 'Unknown';
    $log_query = "INSERT INTO activity_log (user_name, action, details) VALUES (?, 'DELETE_GRN', ?)";
    $stmt = $conn->prepare($log_query);
    $details = "Deleted GRN ID: " . $grn_id;
    $stmt->bind_param("ss", $user, $details);
    $stmt->execute();

    header("Location: grn_list.php?success=GRN deleted successfully");
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    header("Location: grn_list.php?error=" . urlencode($e->getMessage()));
    exit;
}

$conn->close(); 