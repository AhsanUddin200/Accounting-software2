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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #27AE60;
            --danger-color: #C0392B;
            --warning-color: #f72585;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
            min-height: 60px;
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
            font-size: 1.1rem;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            padding: 3px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .alert-success {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .alert-danger {
            background-color: rgba(192, 57, 43, 0.1);
            color: var(--danger-color);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 2rem 0;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-edit me-2"></i>Edit Profile
            </a>
            <div class="ms-auto">
                <a href="user_dashboard.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-circle me-2"></i>Profile Information
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" action="edit_profile.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="text-center mb-4">
                                <img src="<?php echo htmlspecialchars($avatar_path ? $avatar_path : 'uploads/avatars/default_avatar.png'); ?>" 
                                     alt="Profile Avatar" class="profile-avatar" id="avatarPreview">
                                <div class="mt-3">
                                    <label for="avatar" class="btn btn-outline-primary">
                                        <i class="fas fa-camera me-2"></i>Change Avatar
                                    </label>
                                    <input type="file" name="avatar" id="avatar" class="d-none" accept="image/*" 
                                           onchange="previewAvatar(event)">
                                    <div class="text-muted small mt-2">
                                        Allowed formats: JPG, PNG, GIF (Max: 2MB)
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" name="username" id="username" class="form-control" required 
                                       value="<?php echo htmlspecialchars($username); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email" name="email" id="email" class="form-control" required 
                                       value="<?php echo htmlspecialchars($email); ?>">
                            </div>

                            <div class="divider"></div>

                            <h5 class="mb-3">
                                <i class="fas fa-lock me-2"></i>Change Password
                            </h5>

                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" name="password" id="password" class="form-control" 
                                       placeholder="Leave blank to keep current password">
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" 
                                       class="form-control" placeholder="Leave blank to keep current password">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                                <a href="user_dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
