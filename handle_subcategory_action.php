<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $subcategory_id = intval($_POST['id'] ?? 0);

    try {
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM account_subcategories WHERE id = ?");
            $stmt->bind_param("i", $subcategory_id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Sub-category deleted successfully']);
            } else {
                throw new Exception("Error deleting sub-category");
            }
        } else {
            throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
} 