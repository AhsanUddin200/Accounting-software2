<?php
include 'config.php';

if(isset($_POST['id']) && isset($_POST['status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    
    $sql = "UPDATE ledger_head_requests 
            SET status = ?, 
                processed_date = NOW(), 
                processed_by = ? 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $_SESSION['user_id'], $id);
    
    if($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
}
?> 