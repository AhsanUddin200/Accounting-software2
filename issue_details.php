<?php
session_start();
require_once 'db.php';
require_once 'session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get issue ID from URL
$issue_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get issue details with book information
$query = "SELECT bi.*, lb.title, lb.book_number, lb.author, lb.shelf_number, lb.book_image, lb.school 
          FROM book_issues bi 
          JOIN library_books lb ON bi.book_id = lb.id 
          WHERE bi.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $issue_id);
$stmt->execute();
$result = $stmt->get_result();
$issue = $result->fetch_assoc();

// If no issue found, redirect back
if (!$issue) {
    header("Location: view_issued_books.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Details - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .book-image {
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -34px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="https://images.crunchbase.com/image/upload/c_pad,h_170,w_170,f_auto,b_white,q_auto:eco,dpr_1/v1436326579/fv5juvmpaq9zxgnkueof.png" alt="FMS Logo" height="40" class="me-2">
                <i class="fas fa-book me-2"></i>Issue Details
            </a>
            <div class="ms-auto">
                <a href="view_issued_books.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Issued Books
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Book Details Card -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <img src="<?php echo !empty($issue['book_image']) ? htmlspecialchars($issue['book_image']) : 'assets/images/no-book-cover.png'; ?>" 
                             alt="Book Cover" class="book-image mb-3">
                        <h5 class="card-title"><?php echo htmlspecialchars($issue['title']); ?></h5>
                        <p class="text-muted">Book #<?php echo htmlspecialchars($issue['book_number']); ?></p>
                        <hr>
                        <div class="text-start">
                            <p><span class="detail-label">Author:</span> <?php echo htmlspecialchars($issue['author']); ?></p>
                            <p><span class="detail-label">Shelf:</span> <?php echo htmlspecialchars($issue['shelf_number']); ?></p>
                            <p><span class="detail-label">School:</span> <?php echo htmlspecialchars($issue['school']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Issue Details Card -->
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">Issue Information</h4>
                            <span class="badge status-badge bg-<?php 
                                echo $issue['status'] === 'returned' ? 'success' : 
                                    ($issue['status'] === 'issued' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($issue['status']); ?>
                            </span>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="text-muted mb-3">Student Details</h5>
                                <p><span class="detail-label">Name:</span> <?php echo htmlspecialchars($issue['student_name']); ?></p>
                                <p><span class="detail-label">Class:</span> <?php echo htmlspecialchars($issue['student_class']); ?></p>
                                <p><span class="detail-label">Roll Number:</span> <?php echo htmlspecialchars($issue['student_roll_number']); ?></p>
                                <p><span class="detail-label">Contact:</span> <?php echo htmlspecialchars($issue['student_contact']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5 class="text-muted mb-3">Issue Timeline</h5>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <p class="mb-0"><strong>Issued On:</strong></p>
                                        <p><?php echo date('d M Y', strtotime($issue['issue_date'])); ?></p>
                                    </div>
                                    <div class="timeline-item">
                                        <p class="mb-0"><strong>Due Date:</strong></p>
                                        <p><?php echo date('d M Y', strtotime($issue['due_date'])); ?></p>
                                    </div>
                                    <?php if ($issue['status'] === 'returned'): ?>
                                    <div class="timeline-item">
                                        <p class="mb-0"><strong>Returned On:</strong></p>
                                        <p><?php echo date('d M Y', strtotime($issue['return_date'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($issue['remarks'])): ?>
                        <div class="mt-4">
                            <h5 class="text-muted mb-3">Remarks</h5>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($issue['remarks'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($issue['status'] === 'issued'): ?>
                        <div class="mt-4">
                            <button class="btn btn-success" onclick="returnBook(<?php echo $issue_id; ?>)">
                                <i class="fas fa-undo-alt me-1"></i> Return Book
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function returnBook(issueId) {
        if (confirm('Are you sure you want to mark this book as returned?')) {
            fetch('return_book.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ issue_id: issueId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'view_issued_books.php';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error returning book. Please try again.');
            });
        }
    }
    </script>
</body>
</html> 