<?php
// login.php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Check credentials
    $query = "SELECT * FROM users WHERE email='$email' AND password='$password'";
    $result = $conn->query($query);
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];

        // Redirect based on role
        if ($user['role'] == 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: user_dashboard.php");
        }
        exit();
    } else {
        $_SESSION['message'] = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Basic Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* Body Styling */
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }

        /* Header Styling */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            width: 80px;
            height: 80px;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 28px;
            color: #000000;
            margin: 0;
        }

        /* Container Styling */
        .container {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        /* Heading Styling */
        h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #333;
        }

        /* Input Fields Styling */
        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .input-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .input-group input:focus {
            border-color: #6a11cb;
            outline: none;
        }

        /* Icon Styling */
        .input-group i {
            position: absolute;
            margin: 12px 10px;
            color: #888;
        }

        .input-group input {
            padding-left: 35px;
        }

        /* Button Styling */
        button {
            width: 100%;
            padding: 12px;
            background: #6a11cb;
            border: none;
            border-radius: 5px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        button:hover {
            background: #2575fc;
        }

        /* Error Message Styling */
        .error-message {
            color: #ff4d4d;
            margin-bottom: 15px;
            font-weight: bold;
        }

        /* Signup Link Styling */
        .signup-link {
            margin-top: 15px;
            font-size: 14px;
        }

        .signup-link a {
            color: #6a11cb;
            text-decoration: none;
            font-weight: bold;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .container {
                padding: 20px;
            }

            h2 {
                font-size: 20px;
            }

            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://dcassetcdn.com/design_img/682541/99243/99243_4267349_682541_image.jpg" alt="Financial Management System Logo">
            <h1>Financial Management System</h1>
        </div>
        <?php
            if (isset($_SESSION['message'])) {
                echo '<div class="error-message">' . $_SESSION['message'] . '</div>';
                unset($_SESSION['message']);
            }
        ?>
        <form method="POST" action="login.php">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p class="signup-link">Don't have an account? <a href="signup.php">Signup here</a>.</p>
    </div>
</body>
</html>