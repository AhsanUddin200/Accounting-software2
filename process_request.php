<?php
require_once 'session.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $head_name = $_POST['head_name']; // Selected head name
    $category = $_POST['category'];    // Category name
    $description = $_POST['description'];
    
    // Get head_id from accounting_heads table
    $sql = "SELECT id FROM accounting_heads WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $head_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $head = $result->fetch_assoc();
    $head_id = $head['id'];
    
    // Insert request with head_id
    $sql = "INSERT INTO ledger_head_requests 
            (requested_head_name, description, head_id, status, created_at) 
            VALUES (?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $category, $description, $head_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Request submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error submitting request']);
    }
}
?> 