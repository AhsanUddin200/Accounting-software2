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
            --primary-color: #dc3545;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }

        .unauthorized-container {
            max-width: 600px;
            width: 100%;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .error-icon {
            font-size: 5rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .error-code {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .error-message {
            font-size: 1.5rem;
            color: var(--secondary-color);
            margin-bottom: 2rem;
        }

        .error-description {
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .btn-back {
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-back:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .contact-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
            font-size: 0.9rem;
            color: var(--secondary-color);
        }

        @media (max-width: 576px) {
            .unauthorized-container {
                padding: 20px;
            }
            .error-code {
                font-size: 2rem;
            }
            .error-message {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="unauthorized-container">
        <i class="fas fa-exclamation-triangle error-icon"></i>
        <div class="error-code">401</div>
        <div class="error-message">Unauthorized Access</div>
        <p class="error-description">
            Sorry, you don't have permission to access this page. 
            Please make sure you have the necessary credentials or contact your administrator for access.
        </p>
        <a href="admin_dashboard.php" class="btn btn-back">
            <i class="fas fa-home"></i>
            Return to Homepage
        </a>
        <div class="contact-info">
            <p>Need help? Contact your system administrator</p>
            <p><i class="fas fa-envelope me-2"></i>admin@fms.com</p>
        </div>
    </div>
</body>
</html> 