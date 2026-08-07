<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$deniedUser = $_SESSION['denied_user'] ?? ($_SESSION['samlUser'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Denied - Alma Inventory</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: #ffffff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 480px;
            text-align: center;
        }
        h2 {
            color: #dc3545;
            margin-top: 0;
        }
        p {
            margin: 1rem 0;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.6rem 1.2rem;
            background-color: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
        }
        .btn:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>Access Denied</h2>
        <?php if (!empty($deniedUser)): ?>
            <p>Authenticated as: <strong><?php echo htmlspecialchars($deniedUser); ?></strong></p>
        <?php endif; ?>
        <p>You do not have access to this application. If you believe this is an error, please contact dlingley@purdue.edu.</p>
        <a href="/saml/logout" class="btn">Log Out</a>
    </div>
</body>
</html>
