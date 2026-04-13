<?php

session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'dbconnect.php';

// Check if database connection was successful
if (!isset($conn)) {
    die("ERROR: Database connection failed. The \$conn variable is not set. Check dbconnect.php file.");
}

if ($conn->connect_error) {
    die("ERROR: Database connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Check if email and password are provided
    if (empty($email)) {
        $_SESSION['error'] = "DEBUG: Email field is empty!";
        header("Location: ../../public/pages/signin.php");
        exit();
    }

    if (empty($password)) {
        $_SESSION['error'] = "Password is empty.";
        header("Location: ../../public/pages/signin.php");
        exit();
    }

    // Prepare the SQL statement
    $sql = "SELECT user_id, password, role, first_name, last_name FROM users WHERE email = ?";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("ERROR: Failed to prepare statement: " . $conn->error . "<br>SQL: $sql");
    }

    $stmt->bind_param("s", $email);

    if (!$stmt->execute()) {
        die("ERROR: Failed to execute statement: " . $stmt->error);
    }

    $stmt->store_result();

    if ($stmt->num_rows > 0) {

        if (!$stmt->bind_result($user_id, $password_hash, $role, $first_name, $last_name)) {
            die("ERROR: Failed to bind result: " . $stmt->error);
        }

        if (!$stmt->fetch()) {
            die("ERROR: Failed to fetch result: " . $stmt->error);
        }

        if (password_verify($password, $password_hash)) {

            // Successful login - store user info in session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $role;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;

            // Store password temporarily for E2EE key derivation (cleared after key setup)
            $_SESSION['password'] = $password;

            // Clear any previous error
            if (isset($_SESSION['error'])) {
                unset($_SESSION['error']);
            }

            $stmt->close();

            // Check if user has E2EE keys set up
            $keyCheckSql = "SELECT encrypted_private_key, public_key, key_salt, key_version 
                           FROM user_keys 
                           WHERE user_id = ? 
                           ORDER BY key_version DESC 
                           LIMIT 1";

            $keyStmt = $conn->prepare($keyCheckSql);
            $keyStmt->bind_param("i", $user_id);
            $keyStmt->execute();
            $keyResult = $keyStmt->get_result();

            if ($keyResult->num_rows > 0) {
                // User has E2EE keys - store them in session for client-side decryption
                $keyData = $keyResult->fetch_assoc();
                $_SESSION['e2ee_encrypted_private_key'] = $keyData['encrypted_private_key'];
                $_SESSION['e2ee_public_key'] = $keyData['public_key'];
                $_SESSION['e2ee_salt'] = $keyData['key_salt'];
                $_SESSION['e2ee_key_version'] = $keyData['key_version'];
                $_SESSION['e2ee_needs_decrypt'] = true;

                $keyStmt->close();
                $conn->close();

                // Redirect to role-based dashboard (will trigger key decryption on page load)
                switch ($role) {
                    case 'administrator':
                        header("Location: ../../public/pages/admin/admin-dashboard.php");
                        break;
                    case 'manager':
                        header("Location: ../../public/pages/manager/manager-dashboard.php");
                        break;
                    case 'organizer':
                        header("Location: ../../public/pages/organizer/organizer-dashboard.php");
                        break;
                    case 'supplier':
                        header("Location: ../../public/pages/supplier/supplier-dashboard.php");
                        break;
                    default:
                        header("Location: ../../index.php");
                }
            } else {
                // User doesn't have E2EE keys - redirect to setup page
                $keyStmt->close();
                $conn->close();
                header("Location: ../../public/pages/setup-keys.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Incorrect Password.";
            $stmt->close();
            $conn->close();
            header("Location: ../../public/pages/signin.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "DEBUG: No user found with email: " . htmlspecialchars($email) . ". Check if the user exists in the 'users' table.";
        $stmt->close();
        $conn->close();
        header("Location: ../../public/pages/signin.php");
        exit();
    }
} else {
    die("ERROR: Invalid request method. Expected POST, got: " . $_SERVER['REQUEST_METHOD']);
}
