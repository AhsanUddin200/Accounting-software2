<?php
// signup.php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Check if user already exists
    $check = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($check);
    if ($result->num_rows > 0) {
        echo "User already exists.";
    } else {
        // Insert new user
        $insert = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$password')";
        if ($conn->query($insert) === TRUE) {
            $_SESSION['message'] = "Registration successful. Please login.";
            header("Location: login.php");
            exit();
        } else {
            echo "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Signup</title>
    <style>
        /* Basic styling */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { width: 300px; margin: 100px auto; padding: 30px; background: #fff; border-radius: 5px; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 10px; margin: 5px 0 10px; }
        input[type="submit"] { width: 100%; padding: 10px; background: #5cb85c; border: none; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Signup</h2>
        <form method="POST" action="signup.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="submit" value="Signup">
        </form>
        <p>Already have an account? <a href="login.php">Login here</a>.</p>
    </div>
</body>
</html>
