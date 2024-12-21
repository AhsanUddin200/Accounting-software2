<?php
    // user_dashboard.php
    require 'session.php';
    require 'db.php';

    // Check if the user is not an admin
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    }

    // Fetch user's total income
    $income_result = $conn->prepare("SELECT SUM(amount) as total_income FROM transactions WHERE user_id = ? AND type = 'income'");
    if (!$income_result) {
        die("Prepare failed (Total Income): (" . $conn->errno . ") " . $conn->error);
    }
    $income_result->bind_param("i", $_SESSION['user_id']);
    if (!$income_result->execute()) {
        die("Execute failed (Total Income): (" . $income_result->errno . ") " . $income_result->error);
    }
    $income = $income_result->get_result()->fetch_assoc()['total_income'] ?? 0;
    $income_result->close();

    // Fetch user's total expenses
    $expense_result = $conn->prepare("SELECT SUM(amount) as total_expenses FROM transactions WHERE user_id = ? AND type = 'expense'");
    if (!$expense_result) {
        die("Prepare failed (Total Expenses): (" . $conn->errno . ") " . $conn->error);
    }
    $expense_result->bind_param("i", $_SESSION['user_id']);
    if (!$expense_result->execute()) {
        die("Execute failed (Total Expenses): (" . $expense_result->errno . ") " . $expense_result->error);
    }
    $expenses = $expense_result->get_result()->fetch_assoc()['total_expenses'] ?? 0;
    $expense_result->close();

    // Calculate net balance
    $net_balance = $income - $expenses;

    // Fetch recent transactions (last 5)
    $stmt = $conn->prepare("SELECT transactions.*, categories.name as category_name FROM transactions 
                            JOIN categories ON transactions.category_id = categories.id 
                            WHERE transactions.user_id = ? 
                            ORDER BY transactions.date DESC LIMIT 5");
    if (!$stmt) {
        die("Prepare failed (Recent Transactions): (" . $conn->errno . ") " . $conn->error);
    }
    $stmt->bind_param("i", $_SESSION['user_id']);
    if (!$stmt->execute()) {
        die("Execute failed (Recent Transactions): (" . $stmt->errno . ") " . $stmt->error);
    }
    $result = $stmt->get_result();
    $recent_transactions = [];
    while ($row = $result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
    $stmt->close();

    // Fetch user's avatar
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed (Fetch Avatar): (" . $conn->errno . ") " . $conn->error);
    }
    $stmt->bind_param("i", $_SESSION['user_id']);
    if (!$stmt->execute()) {
        die("Execute failed (Fetch Avatar): (" . $stmt->errno . ") " . $stmt->error);
    }
    $stmt->bind_result($avatar_path);
    $stmt->fetch();
    $stmt->close();

    // If avatar path is empty, set to default
    if (empty($avatar_path)) {
        $avatar_path = 'uploads/avatars/default_avatar.png';
    }

    // Fetch unread notifications
    $notif_stmt = $conn->prepare("SELECT id, message, timestamp FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY timestamp DESC");
    if ($notif_stmt) {
        $notif_stmt->bind_param("i", $_SESSION['user_id']);
        $notif_stmt->execute();
        $notif_result = $notif_stmt->get_result();
        $notifications = [];
        while ($row = $notif_result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $notif_stmt->close();
    } else {
        $notifications = [];
    }

    // Mark notifications as read
    if (count($notifications) > 0) {
        $update_notif_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        if ($update_notif_stmt) {
            $update_notif_stmt->bind_param("i", $_SESSION['user_id']);
            $update_notif_stmt->execute();
            $update_notif_stmt->close();
        }
    }

    // Log dashboard view
    log_action($conn, $_SESSION['user_id'], 'Viewed User Dashboard', 'User accessed the dashboard.');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>User Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
               :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4bb543;
            --info-color: #4895ef;
            --warning-color: #f72585;
            --danger-color: #ef233c;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
            
            body {
                background-color: #f8f9fa;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .navbar {
                background: var(--primary-color) !important;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .card {
                border: none;
                border-radius: 15px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                transition: transform 0.2s;
            }
            
            .card:hover {
                transform: translateY(-5px);
            }
            
            .stats-card {
                padding: 1.5rem;
                margin: 15px 0;
            }
            
            .stats-card .card-title {
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 1rem;
            }
            
            .stats-card .card-text {
                font-size: 2.2em;
                font-weight: bold;
            }
            
            .table {
                background: white;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .table thead {
                background: var(--primary-color);
                color: white;
            }
            
            .btn {
                border-radius: 8px;
                padding: 8px 20px;
                font-weight: 500;
            }
            
            .avatar-img {
                width: 45px;
                height: 45px;
                object-fit: cover;
                border-radius: 50%;
                border: 2px solid white;
            }
            
            .dropdown-menu {
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
            }
        </style>
    </head>
    <body>
        <!-- Navigation Bar -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">Financial Management System</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item">
                            <a class="nav-link" href="view_transactions.php">View Transactions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_transaction.php">Add Transaction</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="financial_reports.php">Financial Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="edit_profile.php">Edit Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="user_dashboard.php">Dashboard</a>
                        </li>
                        <!-- <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <?php if (count($notifications) > 0): ?>
                                    <span class="badge bg-danger"><?php echo count($notifications); ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <li><a class="dropdown-item" href="#"><?php echo htmlspecialchars($notif['message']); ?></a></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="#">No new notifications.</a></li>
                                <?php endif; ?>
                            </ul>
                        </li> -->

                        <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <?php if (count($notifications) > 0): ?>
                                <span class="badge bg-danger"><?php echo count($notifications); ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <small><?php echo htmlspecialchars($notif['timestamp']); ?></small>
                                            <br>
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="#">No new notifications.</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                        <li class="nav-item d-flex align-items-center">
                            <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar" class="avatar-img me-2">
                            <span class="nav-link">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</span>
                            <a class="nav-link ms-2" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <div class="d-flex gap-2">
                    <a href="add_transaction.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Transaction
                    </a>
                    <a href="financial_reports.php" class="btn btn-outline-primary">
                        <i class="bi bi-graph-up"></i> View Reports
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Income Card -->
                <div class="col-md-4">
                    <div class="card stats-card bg-gradient text-white" style="background: #2ECC71;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title">Total Income</h5>
                                <i class="bi bi-arrow-up-circle fs-3"></i>
                            </div>
                            <p class="card-text">$<?php echo number_format($income, 2); ?></p>
                        </div>
                    </div>
                </div>
                <!-- Expenses Card -->
                <div class="col-md-4">
                    <div class="card stats-card bg-gradient text-white" style="background: #E74C3C;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title">Total Expenses</h5>
                                <i class="bi bi-arrow-down-circle fs-3"></i>
                            </div>
                            <p class="card-text">$<?php echo number_format($expenses, 2); ?></p>
                        </div>
                    </div>
                </div>
                <!-- Net Balance Card -->
                <div class="col-md-4">
                    <div class="card stats-card bg-gradient text-white" style="background: #3498DB;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title">Net Balance</h5>
                                <i class="bi bi-wallet2 fs-3"></i>
                            </div>
                            <p class="card-text">$<?php echo number_format($net_balance, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="fw-bold">Recent Transactions</h3>
                        <a href="view_transactions.php" class="btn btn-outline-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_transactions) > 0): ?>
                                    <?php foreach ($recent_transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                            <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                            <td class="<?php echo ($transaction['type'] == 'income' && $transaction['description'] == 'Monthly Salary') ? 'text-success fw-bold' : ''; ?>">
                                                <?php echo ucfirst(htmlspecialchars($transaction['type'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['category_name'] ?? 'Salary'); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['date']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td>
                                                <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-primary">Edit</a> | 
                                                <a href="view_transactions.php?delete=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this transaction?');">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No recent transactions found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Include Bootstrap JS and Icons (Optional) -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Initialize Bootstrap tooltips if needed
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        </script>
    </body>
    </html>
