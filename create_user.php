<?php
require_once 'session.php';
require_once 'db.php';

// Check if super admin (either by role or username)
if (!isset($_SESSION['role']) || 
    ($_SESSION['role'] !== 'admin' && 
     $_SESSION['username'] !== 'saim' && 
     $_SESSION['username'] !== 'admin')) {
    header("Location: access_denied.php");
    exit();
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $cost_center_id = $_POST['cost_center'];
    $email = $username;
    $role = 'admin';
    
    // Check if trying to create super admin accounts
    if (strtolower($username) === 'saim' || strtolower($username) === 'admin') {
        $error = "Cannot create accounts with reserved usernames (saim/admin)";
    } 
    // Regular validation
    elseif (empty($username) || empty($password) || empty($cost_center_id)) {
        $error = "All fields are required";
    } else {
        try {
            // Check if email already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "This email/username already exists!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new admin user with cost center
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, cost_center_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $cost_center_id);
                
                if ($stmt->execute()) {
                    $success = "Admin user created successfully!";
                } else {
                    $error = "Error creating admin user: " . $conn->error;
                }
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch cost centers for dropdown
$cost_centers = $conn->query("SELECT id, code, name FROM cost_centers ORDER BY name");

// Fetch only admins with assigned cost centers
$users_query = "SELECT u.username, cc.name as cost_center_name
                FROM users u 
                INNER JOIN cost_centers cc ON u.cost_center_id = cc.id 
                WHERE u.cost_center_id IS NOT NULL 
                AND u.role = 'admin'  -- This ensures we only get regular admins
                ORDER BY u.username";
$users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Admin User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .top-back-btn {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #4355E7;
            padding: 10px 20px;
            z-index: 1000;
            display: flex;
            align-items: center;
        }

        .top-back-btn a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-back-btn a:hover {
            color: #f0f0f0;
        }

        /* Add padding to body to account for fixed button */
        body {
            padding-top: 45px;
        }

        :root {
            --primary-blue: #4355E7;
            --primary-dark: #3244c5;
            --light-blue: #f0f2ff;
        }

        .page-container {
            padding: 20px;
        }

        .back-btn {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: #3244c5;
            color: white;
        }

        .assignments-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            font-size: 1.2rem;
            margin: 20px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .assignments-title i {
            color: var(--primary-blue);
        }

        .assignments-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .assignments-table thead th {
            background: var(--primary-blue);
            color: white;
            font-weight: 500;
            padding: 12px 20px;
            border: none;
        }

        .assignments-table tbody td {
            padding: 12px 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .assignments-table tbody tr:last-child td {
            border-bottom: none;
        }

        .user-info, .center-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-info i, .center-info i {
            color: var(--primary-blue);
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <!-- Back Button at the very top -->
    <div class="top-back-btn">
        <a href="admin_dashboard.php">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>

    

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>Create Admin User
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user me-2"></i>Username/Email
                                </label>
                                <input type="email" name="username" class="form-control" required>
                                <small class="text-muted">This will be used as both username and email</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-building me-2"></i>Assign Cost Center
                                </label>
                                <select name="cost_center" class="form-select" required>
                                    <option value="">Select Cost Center</option>
                                    <?php while ($center = $cost_centers->fetch_assoc()): ?>
                                        <option value="<?php echo $center['id']; ?>">
                                            <?php echo htmlspecialchars($center['code'] . ' - ' . $center['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="warning-note">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important Note:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>The usernames 'saim' and 'admin' are reserved for super admin accounts.</li>
                                    <li>These accounts cannot be created as they are system-level administrators.</li>
                                    <li>Super admin accounts do not have cost centers assigned to them.</li>
                                </ul>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Create Admin User
                                </button>
                                <a href="admin_dashboard.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times-circle me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-container">
        <!-- Title -->
        <div class="assignments-title">
            <i class="fas fa-building"></i>
            Cost Center Assignments
        </div>

        <!-- Table -->
        <div class="assignments-table">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Admin Username</th>
                        <th>Assigned Cost Center</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users_result->num_rows > 0): ?>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="center-info">
                                        <i class="fas fa-building"></i>
                                        <?php echo htmlspecialchars($user['cost_center_name']); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted py-3">
                                No cost centers are currently assigned to any admin
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 