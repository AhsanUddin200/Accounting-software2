<?php
session_start();
require_once 'db.php';
require_once 'session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if super admin
$is_super_admin = ($_SESSION['username'] === 'saim' || 
                   $_SESSION['username'] === 'admin' || 
                   empty($_SESSION['cost_center_id']));

// Handle search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$cost_center = isset($_GET['cost_center']) ? $_GET['cost_center'] : 'all';

// Build query based on filters
$query = "SELECT bi.*, lb.title, lb.book_number, cc.name as cost_center_name 
          FROM book_issues bi 
          JOIN library_books lb ON bi.book_id = lb.id 
          LEFT JOIN cost_centers cc ON lb.cost_center_id = cc.id 
          WHERE 1=1";

// Add cost center filtering
if (!$is_super_admin) {
    $query .= " AND lb.cost_center_id = " . intval($_SESSION['cost_center_id']);
} elseif ($cost_center !== 'all') {
    $query .= " AND lb.cost_center_id = ?";
}

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

// Build parameter array and types string
$params = array();
$types = '';

if ($is_super_admin && $cost_center !== 'all') {
    $params[] = $cost_center;
    $types .= 'i';
}

if (!empty($search)) {
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

if ($status !== 'all') {
    $params[] = $status;
    $types .= 's';
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get cost center name for regular admin
$cost_center_name = "";
if (!$is_super_admin) {
    $cc_query = "SELECT name FROM cost_centers WHERE id = ?";
    $cc_stmt = $conn->prepare($cc_query);
    $cc_stmt->bind_param('i', $_SESSION['cost_center_id']);
    $cc_stmt->execute();
    $cc_result = $cc_stmt->get_result();
    if ($row = $cc_result->fetch_assoc()) {
        $cost_center_name = $row['name'];
    }
}

// Get all cost centers for super admin filter
$cost_centers = array();
if ($is_super_admin) {
    $cc_query = "SELECT id, name FROM cost_centers ORDER BY name";
    $cc_result = $conn->query($cc_query);
    while ($row = $cc_result->fetch_assoc()) {
        $cost_centers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $cost_center_name ? "$cost_center_name - " : ""; ?>View Issued Books - Library System</title>
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
                <i class="fas fa-book me-2"></i>Issued Books
                <?php if (!$is_super_admin): ?>
                    - <?php echo htmlspecialchars($cost_center_name); ?>
                <?php endif; ?>
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
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search books or students..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <?php if ($is_super_admin): ?>
                <div class="col-md-2">
                    <select class="form-select" name="cost_center">
                        <option value="all">All Cost Centers</option>
                        <?php foreach ($cost_centers as $cc): ?>
                            <option value="<?php echo $cc['id']; ?>" 
                                    <?php echo $cost_center == $cc['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cc['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="issued" <?php echo $status === 'issued' ? 'selected' : ''; ?>>Issued</option>
                        <option value="returned" <?php echo $status === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-2">
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
                        <?php if ($is_super_admin): ?>
                        <th>Cost Center</th>
                        <?php endif; ?>
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
                                <?php if ($is_super_admin): ?>
                                <td><?php echo htmlspecialchars($row['cost_center_name']); ?></td>
                                <?php endif; ?>
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
                            <td colspan="<?php echo $is_super_admin ? 7 : 6; ?>" class="text-center">
                                No records found
                            </td>
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
            // Show loading state on button
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Returning...';
            button.disabled = true;

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
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    // Reset button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error returning book. Please try again.');
                // Reset button state
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    }

    function viewDetails(issueId) {
        // You can implement the view details functionality here
        window.location.href = `issue_details.php?id=${issueId}`;
    }
    </script>
</body>
</html> 