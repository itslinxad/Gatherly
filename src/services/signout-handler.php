<?php

session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
    <script>
        // Clear PIN verification so it asks again after re-login
        localStorage.removeItem('gatherly_pin_verified');
        // Also clear E2EE session data
        sessionStorage.clear();
    </script>
    <meta http-equiv="refresh" content="0;url=../../public/pages/signin.php">
</head>
<body>
    <p>Logging out... <a href="../../public/pages/signin.php">Click here if not redirected</a></p>
</body>
</html>
