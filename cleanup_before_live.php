<?php
require_once 'session.php';
require_once 'db.php';

// Only allow admin to run this script
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete all ledger entries
    $conn->query("DELETE FROM ledgers");
    
    // Delete all transactions
    $conn->query("DELETE FROM transactions");
    
    // Reset any running numbers or sequences
    $conn->query("ALTER TABLE transactions AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE ledgers AUTO_INCREMENT = 1");
    
    // Log the cleanup
    $cleanup_log = "System cleanup performed on " . date('Y-m-d H:i:s');
    $conn->query("INSERT INTO system_logs (action, description, user_id) 
                 VALUES ('SYSTEM_CLEANUP', '$cleanup_log', {$_SESSION['user_id']})");
    
    // Commit transaction
    $conn->commit();
    
    echo "System successfully cleaned up and ready for live deployment";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "Error during cleanup: " . $e->getMessage();
} 