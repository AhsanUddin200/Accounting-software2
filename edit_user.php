<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = $error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $cost_center_id = !empty($_POST['cost_center_id']) ? intval($_POST['cost_center_id']) : null;
    
    try {
        if ($user_id > 0) {
            // Update existing user
            $stmt = $conn->prepare("UPDATE users SET username = ?, cost_center_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $username, $cost_center_id, $user_id);
        } else {
            // Create new user
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, cost_center_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $username, $password, $cost_center_id);
        }
        
        if ($stmt->execute()) {
            $success = "User " . ($user_id ? "updated" : "created") . " successfully!";
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch user data if editing
$user = null;
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

// Fetch cost centers
$cost_centers = $conn->query("SELECT id, code, name FROM cost_centers ORDER BY name");
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $user_id ? 'Edit' : 'Create'; ?> User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2><?php echo $user_id ? 'Edit' : 'Create'; ?> User</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required 
                       value="<?php echo $user ? htmlspecialchars($user['username']) : ''; ?>">
            </div>
            
            <?php if (!$user_id): ?>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label">Cost Center</label>
                <select name="cost_center_id" class="form-select" required>
                    <option value="">Select Cost Center</option>
                    <?php while ($center = $cost_centers->fetch_assoc()): ?>
                        <option value="<?php echo $center['id']; ?>" 
                            <?php echo ($user && $user['cost_center_id'] == $center['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($center['code'] . ' - ' . $center['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html> 