<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/user_manager.php';

$deniedUser = $_SESSION['denied_user'] ?? '';
if (empty($deniedUser)) {
    $deniedUser = getDisplayUserFromSaml(
        $_SESSION['samlUser'] ?? '',
        $_SESSION['denied_attributes'] ?? ($_SESSION['samlUserAttributes'] ?? [])
    );
}
$isLoggedOut = isset($_GET['logout']) && $_GET['logout'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isLoggedOut ? 'Logged Out' : 'Access Denied'; ?> - Alma Inventory</title>
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
        h2.logged-out {
            color: #0d6efd;
        }
        p {
            margin: 1rem 0;
            line-height: 1.5;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 1.25rem;
        }
        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
        }
        .btn-primary {
            background-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5c636a;
        }
        .btn-success {
            background-color: #198754;
        }
        .btn-success:hover {
            background-color: #157347;
        }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($isLoggedOut): ?>
            <h2 class="logged-out">Logged Out</h2>
            <p>You have successfully logged out of Alma Inventory.</p>
            <div class="btn-group">
                <a href="/saml/login" class="btn btn-primary">Sign In</a>
            </div>
        <?php else: ?>
            <h2>Access Denied</h2>
            <?php if (!empty($deniedUser)): ?>
                <p>Authenticated as: <strong><?php echo htmlspecialchars($deniedUser); ?></strong></p>
            <?php endif; ?>
            <p>You do not have access to this application. If you believe this is an error, please contact dlingley@purdue.edu.</p>
            <div class="btn-group">
                <a href="/saml/login" class="btn btn-success">Try Again</a>
                <a href="/saml/logout" class="btn btn-secondary">Log Out</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
