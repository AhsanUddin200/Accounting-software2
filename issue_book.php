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

// Base WHERE clause for cost center filtering
$where_clause = "";
if (!$is_super_admin) {
    $where_clause = " AND lb.cost_center_id = " . intval($_SESSION['cost_center_id']);
}

// Get available books for dropdown with cost center filtering
$books_query = "SELECT lb.id, lb.book_number, lb.title, cc.name as cost_center_name 
                FROM library_books lb 
                LEFT JOIN cost_centers cc ON lb.cost_center_id = cc.id 
                WHERE lb.status = 'available'" . $where_clause . 
                " ORDER BY lb.book_number";
$books_result = $conn->query($books_query);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $cost_center_name ? "$cost_center_name - " : ""; ?>Issue Book - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .issue-history {
            margin-top: 2rem;
        }
        .select2-container {
            width: 100% !important;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
            padding-left: 12px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-search__field {
            padding: 8px !important;
        }
        .select2-results__option {
            padding: 8px 12px;
        }
        .select2-results__option--highlighted {
            background-color: #0d6efd !important;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-book me-2"></i>Issue Book
                <?php if (!$is_super_admin): ?>
                    - <?php echo htmlspecialchars($cost_center_name); ?>
                <?php endif; ?>
            </a>
            <div class="ms-auto">
                <a href="library_dashboard.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Library Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-book-reader me-2"></i>Issue Book to Student</h5>
                    </div>
                    <div class="card-body">
                        <form id="issueBookForm">
                            <div class="mb-3">
                                <label class="form-label">Select Book*</label>
                                <select class="form-select" name="book_id" id="bookSelect" required>
                                    <option value="">Type to search book...</option>
                                    <?php while($book = $books_result->fetch_assoc()): ?>
                                        <option value="<?php echo $book['id']; ?>" 
                                                data-book-number="<?php echo htmlspecialchars($book['book_number']); ?>"
                                                data-title="<?php echo htmlspecialchars($book['title']); ?>">
                                            <?php 
                                            echo htmlspecialchars($book['book_number'] . ' - ' . $book['title']);
                                            if ($is_super_admin) {
                                                echo ' (' . htmlspecialchars($book['cost_center_name']) . ')';
                                            }
                                            ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Student Name*</label>
                                <input type="text" class="form-control" name="student_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Class*</label>
                                <input type="text" class="form-control" name="student_class" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Roll Number*</label>
                                <input type="text" class="form-control" name="student_roll_number" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" name="student_contact">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Issue Date*</label>
                                <input type="date" class="form-control" name="issue_date" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Due Date*</label>
                                <input type="date" class="form-control" name="due_date" required 
                                       value="<?php echo date('Y-m-d', strtotime('+15 days')); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Issue Book
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Issues Table -->
        <div class="issue-history">
            <h4><i class="fas fa-history me-2"></i>Recent Book Issues</h4>
            <div class="table-responsive">
                <table class="table table-hover bg-white rounded shadow-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Book</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <?php if ($is_super_admin): ?>
                            <th>Cost Center</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_issues_query = "SELECT bi.*, lb.title, cc.name as cost_center_name 
                                              FROM book_issues bi 
                                              JOIN library_books lb ON bi.book_id = lb.id 
                                              LEFT JOIN cost_centers cc ON lb.cost_center_id = cc.id 
                                              WHERE 1=1" . $where_clause . "
                                              ORDER BY bi.issue_date DESC LIMIT 5";
                        $recent_issues = $conn->query($recent_issues_query);
                        
                        while($issue = $recent_issues->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($issue['title']); ?></td>
                            <td><?php echo htmlspecialchars($issue['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($issue['student_class']); ?></td>
                            <td><?php echo date('d M Y', strtotime($issue['issue_date'])); ?></td>
                            <td><?php echo date('d M Y', strtotime($issue['due_date'])); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $issue['status'] === 'issued' ? 'warning' : 'success'; ?>">
                                    <?php echo ucfirst($issue['status']); ?>
                                </span>
                            </td>
                            <?php if ($is_super_admin): ?>
                            <td><?php echo htmlspecialchars($issue['cost_center_name']); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#bookSelect').select2({
                theme: 'bootstrap-5',
                placeholder: 'Type book number or title to search...',
                allowClear: true,
                width: '100%'
            });
        });

        document.getElementById('issueBookForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('process_issue_book.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Book issued successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error issuing book. Please try again.');
            });
        });
    </script>
</body>
</html> 