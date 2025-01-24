<?php
require_once 'session.php';
require_once 'db.php';

if (isset($_GET['po_id'])) {
    $po_id = $_GET['po_id'];
    $query = "SELECT * FROM po_items WHERE po_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($items);
} 