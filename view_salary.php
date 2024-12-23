<?php
// view_salary.php

// Include necessary files
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch salary records for the user
$stmt = $conn->prepare("SELECT month, total_present, total_absent, total_salary 
                        FROM salaries 
                        WHERE user_id = ? 
                        ORDER BY month DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$salaries = [];
while ($row = $result->fetch_assoc()) {
    $salaries[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Salary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 30px; }
        .navbar-brand { font-weight: bold; }
        .table-responsive {
            max-height: 600px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-secondary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Financial Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="user_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="view_salary.php">My Salary</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">Financial Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Salary Content -->
    <div class="container">
        <h2 class="mb-4"><i class="bi bi-cash-stack me-2"></i>My Salary</h2>

        <!-- Salary Table -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Salary History</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($salaries)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Month</th>
                                    <th>Total Present</th>
                                    <th>Total Absent</th>
                                    <th>Total Salary (Rs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salaries as $salary): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($salary['month'] . '-01')); ?></td>
                                        <td><?php echo htmlspecialchars($salary['total_present']); ?></td>
                                        <td><?php echo htmlspecialchars($salary['total_absent']); ?></td>
                                        <td><?php echo number_format($salary['total_salary'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center">
                        <p class="mb-0"><i class="bi bi-info-circle me-2"></i>No salary records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
