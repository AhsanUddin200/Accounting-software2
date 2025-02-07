<?php
session_start();
require_once 'db.php';
require_once 'session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';

// Build query based on filters
$query = "SELECT bi.*, lb.title, lb.book_number 
          FROM book_issues bi 
          JOIN library_books lb ON bi.book_id = lb.id 
          WHERE 1=1";

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (lb.title LIKE ? OR bi.student_name LIKE ? OR bi.student_roll_number LIKE ?)";
}

if ($status !== 'all') {
    $query .= " AND bi.status = ?";
}

switch ($date_filter) {
    case 'overdue':
        $query .= " AND bi.due_date < CURDATE() AND bi.status = 'issued'";
        break;
    case 'today':
        $query .= " AND DATE(bi.issue_date) = CURDATE()";
        break;
    case 'week':
        $query .= " AND bi.issue_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
        break;
}

$query .= " ORDER BY bi.issue_date DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Bind parameters based on filters
if (!empty($search) && $status !== 'all') {
    $stmt->bind_param('ssss', $search, $search, $search, $status);
} elseif (!empty($search)) {
    $stmt->bind_param('sss', $search, $search, $search);
} elseif ($status !== 'all') {
    $stmt->bind_param('s', $status);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Issued Books - Library System</title>
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
        .filter-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 0.5em 1em;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="https://images.crunchbase.com/image/upload/c_pad,h_170,w_170,f_auto,b_white,q_auto:eco,dpr_1/v1436326579/fv5juvmpaq9zxgnkueof.png" alt="FMS Logo" height="40" class="me-2">
                <i class="fas fa-book me-2"></i>Issued Books
            </a>
            <div class="ms-auto">
                <a href="library_dashboard.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Filters and Search -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search books or students..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="issued" <?php echo $status === 'issued' ? 'selected' : ''; ?>>Issued</option>
                        <option value="returned" <?php echo $status === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="date_filter">
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="overdue" <?php echo $date_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Table -->
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Book Details</th>
                        <th>Student Details</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['book_number']); ?></strong><br>
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['student_name']); ?></strong><br>
                                    Class: <?php echo htmlspecialchars($row['student_class']); ?><br>
                                    Roll: <?php echo htmlspecialchars($row['student_roll_number']); ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['issue_date'])); ?></td>
                                <td>
                                    <?php echo date('d M Y', strtotime($row['due_date'])); ?>
                                    <?php if ($row['status'] === 'issued' && strtotime($row['due_date']) < time()): ?>
                                        <br><span class="badge bg-danger">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge status-badge bg-<?php 
                                        echo $row['status'] === 'returned' ? 'success' : 
                                            ($row['status'] === 'issued' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'issued'): ?>
                                        <button class="btn btn-sm btn-success" onclick="returnBook(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-undo-alt"></i> Return
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
                    alert('Book returned successfully!');
                    location.reload();
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

    function viewDetails(issueId) {
        // Implement view details functionality
        alert('View details functionality will be implemented here');
    }
    </script>
</body>
</html> 