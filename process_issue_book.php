<?php
session_start();
require_once 'db.php';
require_once 'session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $book_id = $_POST['book_id'];
        $student_name = $_POST['student_name'];
        $student_class = $_POST['student_class'];
        $student_roll_number = $_POST['student_roll_number'];
        $student_contact = $_POST['student_contact'];
        $issue_date = $_POST['issue_date'];
        $due_date = $_POST['due_date'];
        $remarks = $_POST['remarks'];

        // Start transaction
        $conn->begin_transaction();

        // Insert into book_issues table
        $issue_query = "INSERT INTO book_issues (book_id, student_name, student_class, student_roll_number, 
                       student_contact, issue_date, due_date, remarks, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'issued')";
        
        $stmt = $conn->prepare($issue_query);
        $stmt->bind_param('isssssss', $book_id, $student_name, $student_class, $student_roll_number, 
                         $student_contact, $issue_date, $due_date, $remarks);
        
        if (!$stmt->execute()) {
            throw new Exception("Error issuing book: " . $stmt->error);
        }

        // Update book status
        $update_query = "UPDATE library_books SET status = 'issued' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('i', $book_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating book status: " . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Book issued successfully'
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