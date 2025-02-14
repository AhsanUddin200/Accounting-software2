<?php
require_once 'session.php';
require_once 'db.php';

// Check if admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle assigning campus access
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $campus = $_POST['campus'];
    
    // Update user's campus access
    $stmt = $conn->prepare("UPDATE users SET campus = ? WHERE id = ?");
    $stmt->bind_param("si", $campus, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Campus access updated successfully";
    } else {
        $_SESSION['error'] = "Error updating access";
    }
}

// Get all users except admin
$users = $conn->query("SELECT * FROM users WHERE role != 'admin' ORDER BY username");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Campus Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Manage Campus Access</h2>
        
        <div class="card">
            <div class="card-header">
                Users Campus Access
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Current Campus</th>
                            <th>Change Campus</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['campus'] ? htmlspecialchars($user['campus']) : 'No Campus Assigned'; ?></td>
                                <td>
                                    <form method="POST" class="d-flex">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="campus" class="form-select me-2" required>
                                            <option value="">Select Campus</option>
                                            <option value="Islamabad" <?php echo $user['campus'] == 'Islamabad' ? 'selected' : ''; ?>>
                                                Islamabad Campus
                                            </option>
                                            <option value="Khariyal" <?php echo $user['campus'] == 'Khariyal' ? 'selected' : ''; ?>>
                                                Khariyal Campus
                                            </option>
                                            <option value="Korangi" <?php echo $user['campus'] == 'Korangi' ? 'selected' : ''; ?>>
                                                Korangi Campus
                                            </option>
                                            <option value="Munawwar" <?php echo $user['campus'] == 'Munawwar' ? 'selected' : ''; ?>>
                                                Munawwar Campus
                                            </option>
                                            <option value="Online" <?php echo $user['campus'] == 'Online' ? 'selected' : ''; ?>>
                                                Online Campus
                                            </option>
                                        </select>
                                        <button type="submit" class="btn btn-primary">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <a href="remove_access.php?id=<?php echo $user['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Remove campus access?')">
                                        Remove Access
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 