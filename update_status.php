<?php
require_once 'session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First get the request details
        $sql = "SELECT r.*, ah.id as head_id 
                FROM ledger_head_requests r
                JOIN accounting_heads ah ON r.requested_head_name = ah.name
                WHERE r.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if (!$request) {
            throw new Exception("Request not found");
        }

        // Update request status
        $sql = "UPDATE ledger_head_requests 
                SET status = ?, 
                    processed_by = ?, 
                    processed_date = NOW() 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $new_status, $_SESSION['user_id'], $request_id);
        $stmt->execute();
        
        // If request is approved, add category to account_categories
        if ($action === 'approve') {
            // Insert into account_categories with the existing columns
            $sql = "INSERT INTO account_categories 
                   (head_id, name, description, balance, created_at) 
                   VALUES (?, ?, ?, 0.00, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", 
                $request['head_id'],
                $request['category'],
                $request['description']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to add category: " . $stmt->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error in update_status.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 