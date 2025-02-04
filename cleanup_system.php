<?php
require_once 'session.php';
require_once 'db.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cleanup'])) {
    // Verify the confirmation code
    if ($_POST['confirmation_code'] === 'DELETE-ALL-DATA') {
        $conn->begin_transaction();
        
        try {
            // Create system_logs table if it doesn't exist
            $create_logs_table = "CREATE TABLE IF NOT EXISTS system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                description TEXT,
                user_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )";
            $conn->query($create_logs_table);
            
            // Delete all ledger entries
            $conn->query("DELETE FROM ledgers");
            
            // Delete all transactions
            $conn->query("DELETE FROM transactions");
            
            // Reset auto-increment counters
            $conn->query("ALTER TABLE transactions AUTO_INCREMENT = 1");
            $conn->query("ALTER TABLE ledgers AUTO_INCREMENT = 1");
            
            // Log the cleanup
            $cleanup_log = "System cleanup performed on " . date('Y-m-d H:i:s');
            $conn->query("INSERT INTO system_logs (action, description, user_id) 
                         VALUES ('SYSTEM_CLEANUP', '$cleanup_log', {$_SESSION['user_id']})");
            
            $conn->commit();
            $message = "System successfully cleaned up and ready for live deployment";
            $status = 'success';
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error during cleanup: " . $e->getMessage();
            $status = 'danger';
        }
    } else {
        $message = "Invalid confirmation code. Cleanup aborted.";
        $status = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Cleanup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .warning-box {
            border: 2px solid #dc3545;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            background-color: #fff5f5;
        }
        .confirmation-code {
            font-family: monospace;
            font-weight: bold;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <!-- Navigation Bar -->
        <div class="mb-4">
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="card">
            <div class="card-header bg-danger text-white">
                <h3 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>System Cleanup</h3>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $status; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="warning-box">
                    <h4 class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>Warning!</h4>
                    <p>This action will:</p>
                    <ul>
                        <li>Delete ALL transactions</li>
                        <li>Delete ALL ledger entries</li>
                        <li>Reset ALL account balances to zero</li>
                        <li>This action CANNOT be undone</li>
                    </ul>
                    <p class="mb-0"><strong>Please make sure you have a backup before proceeding!</strong></p>
                </div>

                <form method="POST" onsubmit="return confirmCleanup()">
                    <div class="mb-3">
                        <label class="form-label">To proceed, type <span class="confirmation-code">DELETE-ALL-DATA</span> in the box below:</label>
                        <input type="text" name="confirmation_code" class="form-control" required>
                    </div>

                    <button type="submit" name="confirm_cleanup" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-2"></i>Proceed with System Cleanup
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmCleanup() {
        return confirm('Are you absolutely sure you want to proceed with the system cleanup? This action cannot be undone!');
    }
    </script>
</body>
</html> 