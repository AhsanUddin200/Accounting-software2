<?php
// view_audit_logs.php

// Enable error reporting temporarily for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include session.php from main directory
require_once __DIR__ . '/session.php';

// Ensure the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Pagination Settings
$limit = 20; // Number of logs per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch total number of audit logs
$total_logs_result = $conn->query("SELECT COUNT(*) as total FROM audit_logs");
if (!$total_logs_result) {
    die("Query failed (Total Logs): (" . $conn->errno . ") " . $conn->error);
}
$total_logs = $total_logs_result->fetch_assoc()['total'] ?? 0;

// Calculate total pages
$total_pages = ceil($total_logs / $limit);

// Fetch audit logs with user details
$stmt = $conn->prepare("SELECT audit_logs.id, users.username, audit_logs.action, audit_logs.details, audit_logs.timestamp 
                        FROM audit_logs 
                        LEFT JOIN users ON audit_logs.user_id = users.id 
                        ORDER BY audit_logs.timestamp DESC 
                        LIMIT ? OFFSET ?");
if (!$stmt) {
    die("Prepare failed (Fetch Audit Logs): (" . $conn->errno . ") " . $conn->error);
}

$stmt->bind_param("ii", $limit, $offset);
if (!$stmt->execute()) {
    die("Execute failed (Fetch Audit Logs): (" . $stmt->errno . ") " . $stmt->error);
}

$result = $stmt->get_result();
$audit_logs = [];
while ($row = $result->fetch_assoc()) {
    $audit_logs[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Audit Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #27AE60;
            --danger-color: #C0392B;
            --warning-color: #f72585;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin: 0 0.2rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
            transform: translateY(-1px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }

        .table-responsive {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            max-height: 600px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(67, 97, 238, 0.03);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.75rem 1rem;
            color: var(--primary-color);
            background-color: white;
            border: 1px solid #dee2e6;
            margin: 0 0.25rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .section-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        @media (max-width: 768px) {
            .navbar-nav {
                padding: 1rem 0;
            }
            
            .nav-link {
                padding: 0.5rem 1rem;
            }
            
            .table-responsive {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-history me-2"></i>Audit Logs
            </a>
            <div class="ms-auto">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="section-title">
            <i class="fas fa-history me-2"></i>System Audit Logs
        </h2>

        <!-- Display Audit Logs -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($audit_logs) > 0): ?>
                        <?php foreach ($audit_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['id']); ?></td>
                                <td>
                                    <i class="fas fa-user-circle me-2"></i>
                                    <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td>
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No audit logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Audit Logs Pagination">
                <ul class="pagination">
                    <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
