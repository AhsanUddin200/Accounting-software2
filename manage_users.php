<?php
// manage_users.php
require 'session.php';
require 'db.php';

// Check if the logged-in user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Initialize variables
$success = "";
$error = "";

// Handle Add User
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "Email already exists.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt_insert) {
                    $stmt_insert->bind_param("ssss", $username, $email, $hashed_password, $role);
                    if ($stmt_insert->execute()) {
                        $success = "User added successfully.";
                    } else {
                        $error = "Error: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                    $error = "Error preparing statement: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            $error = "Error preparing statement: " . $conn->error;
        }
    }
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    // Prevent admin from deleting themselves
    if ($delete_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        // Check if user exists
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $delete_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows == 0) {
                $error = "User not found.";
            } else {
                // Proceed to delete
                $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt_delete) {
                    $stmt_delete->bind_param("i", $delete_id);
                    if ($stmt_delete->execute()) {
                        $success = "User deleted successfully.";
                    } else {
                        $error = "Error deleting user: " . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                } else {
                    $error = "Error preparing delete statement: " . $conn->error;
                }
            }
            $stmt_check->close();
        } else {
            $error = "Error preparing check statement: " . $conn->error;
        }
    }
}

// Fetch all users
$users = [];
$stmt_fetch = $conn->prepare("SELECT id, username, email, role FROM users ORDER BY id ASC");
if ($stmt_fetch) {
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt_fetch->close();
} else {
    die("Error preparing fetch statement: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <style>
        /* Basic styling */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { width: 90%; max-width: 1000px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        h2 { text-align: center; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f9f9f9; }
        form { margin-bottom: 30px; }
        label { display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; margin: 5px 0 10px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background: #5cb85c; border: none; color: #fff; cursor: pointer; }
        input[type="submit"]:hover { background: #4cae4c; }
        .delete-button { color: #a94442; text-decoration: none; }
        .delete-button:hover { text-decoration: underline; }
        .back-button { text-align: center; margin-top: 20px; }
        .back-button a { 
            padding: 10px 20px; 
            background-color: #5bc0de; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 4px; 
        }
        .back-button a:hover { background-color: #31b0d5; }
        .action-buttons { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Users</h2>

        <!-- Display Success or Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add User Form -->
        <h3>Add New User</h3>
        <form method="POST" action="manage_users.php">
            <label for="username">Username<span style="color: red;">*</span></label>
            <input type="text" id="username" name="username" placeholder="Username" required>

            <label for="email">Email<span style="color: red;">*</span></label>
            <input type="email" id="email" name="email" placeholder="Email" required>

            <label for="password">Password<span style="color: red;">*</span></label>
            <input type="password" id="password" name="password" placeholder="Password" required>

            <label for="role">Role<span style="color: red;">*</span></label>
            <select id="role" name="role" required>
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
            </select>

            <input type="submit" name="add_user" value="Add User">
        </form>

        <!-- Users Table -->
        <h3>All Users</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a class="delete-button" href="manage_users.php?delete=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                            <?php else: ?>
                                <em>Self</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No users found.</td>
                </tr>
            <?php endif; ?>
        </table>

        <div class="back-button">
            <a href="admin_dashboard.php">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
