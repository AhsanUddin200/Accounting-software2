<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access | FMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #304FFE;
            --secondary-color: #1a237e;
            --text-color: #1a1a1a;
            --background-start: #E8EAF6;
            --background-end: #C5CAE9;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .background-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            color: rgba(255, 255, 255, 0.1);
            animation: float 15s infinite;
        }

        .shape:nth-child(1) {
            top: 20%;
            left: 10%;
            font-size: 4rem;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            top: 60%;
            right: 15%;
            font-size: 5rem;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            bottom: 10%;
            left: 30%;
            font-size: 3rem;
            animation-delay: 4s;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
            100% { transform: translateY(0) rotate(0deg); }
        }

        .unauthorized-container {
            max-width: 700px;
            width: 100%;
            padding: 50px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .error-icon {
            font-size: 5rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        .error-code {
            font-size: 3.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .error-message {
            font-size: 1.8rem;
            color: var(--text-color);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .error-description {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        .btn-back {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
            border: none;
            box-shadow: 0 10px 20px rgba(48, 79, 254, 0.3);
        }

        .btn-back:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(48, 79, 254, 0.4);
            color: white;
        }

        .contact-info {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid rgba(48, 79, 254, 0.1);
            font-size: 1rem;
            color: var(--text-color);
        }

        .contact-info p {
            margin-bottom: 0.5rem;
        }

        .contact-info i {
            color: var(--primary-color);
        }

        @media (max-width: 576px) {
            .unauthorized-container {
                padding: 30px;
                margin: 20px;
            }
            .error-code {
                font-size: 2.5rem;
            }
            .error-message {
                font-size: 1.5rem;
            }
            .error-icon {
                font-size: 4rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-shapes">
        <i class="fas fa-lock shape"></i>
        <i class="fas fa-user-shield shape"></i>
        <i class="fas fa-shield-alt shape"></i>
    </div>

    <div class="unauthorized-container">
        <i class="fas fa-exclamation-circle error-icon"></i>
        <div class="error-code">401</div>
        <div class="error-message">Access Denied</div>
        <p class="error-description">
            Oops! It seems you don't have the necessary permissions to access this page. 
            This area is restricted to authorized personnel only. Please verify your credentials 
            or contact your system administrator for assistance.
        </p>
        <a href="admin_dashboard.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i>
            Return to Dashboard
        </a>
        <div class="contact-info">
            <p><strong>Need Help?</strong></p>
            <p><i class="fas fa-envelope me-2"></i>admin@gmail.com</p>
         
        </div>
    </div>
</body>
</html> 