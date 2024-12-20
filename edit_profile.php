<?php
// edit_profile.php
require 'session.php';
require 'db.php';

// Initialize variables
$username = '';
$email = '';
$avatar_path = '';
$error = '';
$success = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email, avatar FROM users WHERE id = ?");
if (!$stmt) {
    die("Prepare failed (fetch user data): (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("i", $_SESSION['user_id']);
if (!$stmt->execute()) {
    die("Execute failed (fetch user data): (" . $stmt->errno . ") " . $stmt->error);
}
$stmt->bind_result($username, $email, $avatar_path);
if (!$stmt->fetch()) {
    die("No user data found.");
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $avatar = $_FILES['avatar'];

    // Basic validation
    if (empty($new_username) || empty($new_email)) {
        $error = "Username and Email are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!empty($new_password) && ($new_password !== $confirm_password)) {
        $error = "Passwords do not match.";
    } else {
        // Check if username or email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        if (!$stmt) {
            die("Prepare failed (check existing user): (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("ssi", $new_username, $new_email, $_SESSION['user_id']);
        if (!$stmt->execute()) {
            die("Execute failed (check existing user): (" . $stmt->errno . ") " . $stmt->error);
        }
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Username or Email already taken.";
            $stmt->close();
        } else {
            $stmt->close();

            // Handle avatar upload if a new file is uploaded
            if ($avatar['size'] > 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($avatar['type'], $allowed_types)) {
                    $error = "Invalid avatar file type. Only JPG, PNG, and GIF are allowed.";
                } elseif ($avatar['size'] > 2 * 1024 * 1024) { // 2MB limit
                    $error = "Avatar file size exceeds 2MB.";
                } else {
                    $upload_dir = 'uploads/avatars/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $avatar_filename = time() . '_' . basename($avatar['name']);
                    $target_file = $upload_dir . $avatar_filename;

                    if (move_uploaded_file($avatar['tmp_name'], $target_file)) {
                        // Optionally, delete the old avatar if it's not the default
                        if ($avatar_path && $avatar_path !== 'uploads/avatars/default_avatar.png') {
                            if (file_exists($avatar_path)) {
                                unlink($avatar_path);
                            }
                        }
                        $avatar_path = $target_file;
                    } else {
                        $error = "Failed to upload avatar.";
                    }
                }
            }

            if (empty($error)) {
                // Update user data
                if (!empty($new_password)) {
                    // Hash the new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET username = ?, email = ?, password = ?, avatar = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_query);
                    if (!$stmt) {
                        die("Prepare failed (update user with password): (" . $conn->errno . ") " . $conn->error);
                    }
                    $stmt->bind_param("ssssi", $new_username, $new_email, $hashed_password, $avatar_path, $_SESSION['user_id']);
                } else {
                    $update_query = "UPDATE users SET username = ?, email = ?, avatar = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_query);
                    if (!$stmt) {
                        die("Prepare failed (update user without password): (" . $conn->errno . ") " . $conn->error);
                    }
                    $stmt->bind_param("sssi", $new_username, $new_email, $avatar_path, $_SESSION['user_id']);
                }

                if (!$stmt->execute()) {
                    die("Execute failed (update user): (" . $stmt->errno . ") " . $stmt->error);
                }

                $stmt->close();

                // Update session username if changed
                $_SESSION['username'] = $new_username;

                // Log the profile update action
                log_action($conn, $_SESSION['user_id'], 'Updated Profile', 'User updated their profile information.');

                // Update local variables
                $username = $new_username;
                $email = $new_email;

                $success = "Profile updated successfully.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <!-- Include Bootstrap CSS for styling (Optional but recommended) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-avatar {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Accounting Software</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="user_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="edit_profile.php">Edit Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_transactions.php">View Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_transaction.php">Add Transaction</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Edit Profile Content -->
    <div class="container mt-4">
        <h2>Edit Profile</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" action="edit_profile.php">
            <div class="mb-3 text-center">
                <img src="<?php echo htmlspecialchars($avatar_path ? $avatar_path : 'uploads/avatars/default_avatar.png'); ?>" alt="Avatar" class="profile-avatar">
            </div>
            <div class="mb-3">
                <label for="avatar" class="form-label">Change Avatar</label>
                <input type="file" name="avatar" id="avatar" class="form-control" accept="image/*">
                <small class="form-text text-muted">Allowed types: JPG, PNG, GIF. Max size: 2MB.</small>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required value="<?php echo htmlspecialchars($username); ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <hr>
            <h4>Change Password</h4>
            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password">
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Leave blank to keep current password">
            </div>
            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    </div>

    <!-- Include Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
