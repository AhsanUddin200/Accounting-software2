<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_GET['mr_id'])) {
    echo json_encode([]);
    exit;
}

$query = "SELECT * FROM mr_items WHERE mr_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_GET['mr_id']);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

header('Content-Type: application/json');
echo json_encode($items); 