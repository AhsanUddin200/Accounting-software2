<?php
require_once 'db.php';
session_start();

if(isset($_POST['request_id']) && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    
    // Set status based on action
    $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    
    // Update the status
    $sql = "UPDATE ledger_head_requests 
            SET status = ?, 
                processed_by = ?, 
                processed_date = NOW() 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $status, $_SESSION['user_id'], $request_id);
    
    if($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'status' => $status,
            'processed_by' => $_SESSION['username'] // Assuming you have username in session
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database update failed'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request parameters'
    ]);
}
?> 