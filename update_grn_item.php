<?php
require_once 'session.php';
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE grn_items SET 
        received_qty = ?,
        condition_status = ?,
        remarks = ?
        WHERE id = ?");
        
    $stmt->bind_param("dssi", 
        $_POST['received_qty'],
        $_POST['condition_status'],
        $_POST['remarks'],
        $_POST['item_id']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 