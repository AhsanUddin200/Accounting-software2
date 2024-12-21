<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $successes = [];

    if (!isset($_POST['salaries']) || !is_array($_POST['salaries'])) {
        $errors[] = "Invalid form submission.";
    } else {
        foreach ($_POST['salaries'] as $user_id => $salary) {
            $salary = floatval($salary);
            if ($salary < 0) {
                $errors[] = "Invalid salary amount for User ID: $user_id.";
                continue;
            }

            $stmt = $conn->prepare("UPDATE users SET fixed_salary = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("di", $salary, $user_id);
                if ($stmt->execute()) {
                    log_action($conn, $_SESSION['user_id'], 'Updated Salary', "Updated salary to $$salary for User ID: $user_id.");
                    $successes[] = "Salary updated for User ID: $user_id.";
                } else {
                    $errors[] = "Failed to update salary for User ID: $user_id.";
                }
                $stmt->close();
            } else {
                $errors[] = "Database error: Unable to prepare statement.";
            }
        }
    }
}

$users_result = $conn->query("SELECT id, username, fixed_salary FROM users ORDER BY username ASC");
if (!$users_result) {
    die("Query failed (Fetch Users): (" . $conn->errno . ") " . $conn->error);
}

$users = [];
while ($user = $users_result->fetch_assoc()) {
    $users[] = $user;
}
$users_result->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Salaries | Accounting Software</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #34495E;
            --success: #27AE60;
            --danger-color: #dc2626;
            --background-color: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }   

        body {
            body { background-color: #f8f9fa; },
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(to right, #1e40af, #3b82f6);
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            color: #4b5563;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        .table tbody tr:hover {
            background-color: #f1f5f9;
        }

        .salary-input {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.5rem;
            transition: all 0.2s;
        }

        .salary-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
            outline: none;
        }

        .btn-update {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-update:hover {
            background-color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .alert {
            border-radius: 0.75rem;
            border: none;
        }

        .nav-link {
            padding: 0.75rem 1rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .nav-link:hover {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2) !important;
            border-radius: 0.5rem;
            color: white !important;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-calculator me-2"></i>
                Accounting Software
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-1"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_categories.php">
                            <i class="fas fa-tags me-1"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_salaries.php">
                            <i class="fas fa-money-bill-wave me-1"></i> Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">
                            <i class="fas fa-chart-bar me-1"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> 
                            Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="fas fa-money-bill-wave text-primary me-2"></i>
                Manage Salaries
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Salaries</li>
                </ol>
            </nav>
        </div>

        <!-- Salary Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <h6 class="text-muted mb-2">Total Employees</h6>
                    <h3 class="mb-0">
                        <i class="fas fa-users text-primary me-2"></i>
                        <?php echo count($users); ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6 class="text-muted mb-2">Total Monthly Payroll</h6>
                    <h3 class="mb-0">
                        <i class="fas fa-dollar-sign text-success me-2"></i>
                        <?php 
                            $total_payroll = array_sum(array_column($users, 'fixed_salary'));
                            echo number_format($total_payroll, 2);
                        ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6 class="text-muted mb-2">Average Salary</h6>
                    <h3 class="mb-0">
                        <i class="fas fa-chart-line text-info me-2"></i>
                        <?php 
                            $avg_salary = count($users) > 0 ? $total_payroll / count($users) : 0;
                            echo number_format($avg_salary, 2);
                        ?>
                    </h3>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($successes)): ?>
            <div class="alert alert-success fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php foreach ($successes as $msg): ?>
                    <div><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Salaries Table Card -->
        <div class="card">
            <div class="card-body p-0">
                <form method="POST" action="manage_salaries.php" id="salaryForm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="px-4">Employee ID</th>
                                    <th>Employee Name</th>
                                    <th class="text-end">Current Salary ($)</th>
                                    <th class="text-end px-4">New Salary ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="px-4">#<?php echo htmlspecialchars($user['id']); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initial rounded-circle bg-light text-primary p-2 me-2">
                                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <?php echo number_format($user['fixed_salary'], 2); ?>
                                            </td>
                                            <td class="text-end px-4">
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0" 
                                                       name="salaries[<?php echo $user['id']; ?>]" 
                                                       class="form-control salary-input text-end" 
                                                       value="<?php echo number_format($user['fixed_salary'], 2, '.', ''); ?>" 
                                                       required>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="fas fa-user-slash text-muted me-2"></i>
                                            No employees found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light p-4">
                <div class="d-flex justify-content-end">
                    <button type="submit" form="salaryForm" class="btn btn-primary btn-update">
                        <i class="fas fa-save me-2"></i>
                        Update All Salaries
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Add thousand separator to salary inputs
        document.querySelectorAll('.salary-input').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = this.value.replace(/[^\d.-]/g, '');
                if (value !== '') {
                    value = parseFloat(value).toFixed(2);
                    this.value = value;
                }
            });
        });

        // Confirm before submitting changes
        document.getElementById('salaryForm').addEventListener('submit', function// Confirm before submitting changes
        document.getElementById('salaryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const confirmMsg = `Are you sure you want to update the salaries?
                              This action will:
                              - Update all employee salaries
                              - Take effect immediately
                              - Be logged in the system`;
            
            if (confirm(confirmMsg)) {
                this.submit();
            }
        });

        // Auto-format salary inputs on blur
        document.querySelectorAll('.salary-input').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    const formattedValue = parseFloat(this.value).toFixed(2);
                    this.value = formattedValue;
                }
            });
        });

        // Highlight changed values
        document.querySelectorAll('.salary-input').forEach(input => {
            const originalValue = input.value;
            input.addEventListener('change', function() {
                if (this.value !== originalValue) {
                    this.classList.add('bg-light');
                    this.style.fontWeight = 'bold';
                } else {
                    this.classList.remove('bg-light');
                    this.style.fontWeight = 'normal';
                }
            });
        });

        // Disable form submission on Enter key
        document.querySelectorAll('.salary-input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });
    </script>
</body>
</html>