<?php
session_start();
require_once 'db.php';
require_once 'session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $issue_id = $data['issue_id'];

        // Start transaction
        $conn->begin_transaction();

        // Get book_id from issue
        $query = "SELECT book_id FROM book_issues WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $issue_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $book = $result->fetch_assoc();

        if (!$book) {
            throw new Exception('Issue record not found');
        }

        // Update issue status
        $update_issue = "UPDATE book_issues SET status = 'returned', return_date = CURRENT_DATE() WHERE id = ?";
        $stmt = $conn->prepare($update_issue);
        $stmt->bind_param('i', $issue_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Error updating issue status');
        }

        // Update book status
        $update_book = "UPDATE library_books SET status = 'available' WHERE id = ?";
        $stmt = $conn->prepare($update_book);
        $stmt->bind_param('i', $book['book_id']);
        
        if (!$stmt->execute()) {
            throw new Exception('Error updating book status');
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Book returned successfully'
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 