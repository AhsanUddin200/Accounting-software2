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

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Sanitize and validate inputs
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        $new_password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $avatar = $_FILES['avatar'];

        // Basic validation
        if (empty($new_username) || empty($new_email)) {
            $error = "Username aur Email zaroori hain.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email ka format ghalat hai.";
        } elseif (!empty($new_password) && ($new_password !== $confirm_password)) {
            $error = "Passwords match nahi kar rahe.";
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
                $error = "Username ya Email already taken.";
                $stmt->close();
            } else {
                $stmt->close();

                // Handle avatar upload if a new file is uploaded
                if ($avatar['size'] > 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($avatar['type'], $allowed_types)) {
                        $error = "Invalid avatar file type. Sirf JPG, PNG, aur GIF allowed hain.";
                    } elseif ($avatar['size'] > 2 * 1024 * 1024) { // 2MB limit
                        $error = "Avatar file size 2MB se zyada hai.";
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
                        $stmt->bind_param("sssii", $new_username, $new_email, $hashed_password, $avatar_path, $_SESSION['user_id']);
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

                    // Log the profile update action (Assuming log_action function exists)
                    log_action($conn, $_SESSION['user_id'], 'Updated Profile', 'User ne apni profile update ki.');

                    // Update local variables
                    $username = $new_username;
                    $email = $new_email;

                    $success = "Profile successfully update ho gaya.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <!-- Include Bootstrap CSS for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-avatar {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 2px solid var(--primary);
        }
        .form-label {
            font-weight: 600;
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
                <ul class="navbar-nav align-items-center">
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
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Edit Your Profile</h3>
                        <form method="POST" enctype="multipart/form-data" action="edit_profile.php">
                            <!-- CSRF Token -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="text-center mb-4">
                                <img src="<?php echo htmlspecialchars($avatar_path ? $avatar_path : 'uploads/avatars/default_avatar.png'); ?>" alt="Avatar" class="profile-avatar" id="avatarPreview">
                            </div>
                            <div class="mb-3 text-center">
                                <label for="avatar" class="form-label">Change Avatar</label>
                                <input type="file" name="avatar" id="avatar" class="form-control" accept="image/*" onchange="previewAvatar(event)">
                                <small class="form-text text-muted">Allowed types: JPG, PNG, GIF. Max size: 2MB.</small>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" id="username" class="form-control" required value="<?php echo htmlspecialchars($username); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" required value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                            <hr>
                            <h5>Change Password</h5>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password">
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Leave blank to keep current password">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Avatar Preview -->
    <script>
        function previewAvatar(event) {
            const avatarPreview = document.getElementById('avatarPreview');
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
    </script>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
