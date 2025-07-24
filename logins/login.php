<?php
// logins/login.php

session_start(); // Start the session

require_once('../includes/database.php');

// Function to check if the account is locked
function isAccountLocked($db, $username) {
    $stmt = $db->prepare("SELECT lock_time FROM `login-attempts` WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($lock_time);
        if ($stmt->fetch()) {
            if ($lock_time) {
                $current_time = new DateTime();
                $lock_time_dt = new DateTime($lock_time);
                if ($current_time < $lock_time_dt) {
                    $remaining = $lock_time_dt->getTimestamp() - $current_time->getTimestamp();
                    $stmt->close();
                    return $remaining; // Return remaining lock time in seconds
                }
            }
        }
        $stmt->close();
    }
    return false;
}

// Function to record a failed login attempt
function recordFailedAttempt($db, $username) {
    $current_time = new DateTime();
    $stmt = $db->prepare("SELECT failed_attempts FROM `login-attempts` WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($failed_attempts);
        if ($stmt->fetch()) {
            $failed_attempts++;
            $stmt->close();

            if ($failed_attempts >= 5) {
                // Lock the account for 2 minutes
                $lock_time = $current_time->add(new DateInterval('PT2M'))->format('Y-m-d H:i:s');
                $update_stmt = $db->prepare("UPDATE `login-attempts` SET failed_attempts = ?, last_failed_attempt = ?, lock_time = ? WHERE username = ?");
                if ($update_stmt) {
                    $last_failed = (new DateTime())->format('Y-m-d H:i:s');
                    $update_stmt->bind_param("isss", $failed_attempts, $last_failed, $lock_time, $username);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            } else {
                // Update failed_attempts and last_failed_attempt
                $last_failed = $current_time->format('Y-m-d H:i:s');
                $update_stmt = $db->prepare("UPDATE `login-attempts` SET failed_attempts = ?, last_failed_attempt = ? WHERE username = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("iss", $failed_attempts, $last_failed, $username);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            }
        } else {
            $stmt->close();
            // Insert a new record for the username
            $failed_attempts = 1;
            $lock_time = NULL;
            $last_failed = $current_time->format('Y-m-d H:i:s');
            $insert_stmt = $db->prepare("INSERT INTO `login-attempts` (username, failed_attempts, last_failed_attempt, lock_time) VALUES (?, ?, ?, ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param("siss", $username, $failed_attempts, $last_failed, $lock_time);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
        }
    }
}

// Function to reset failed attempts on successful login
function resetFailedAttempts($db, $username) {
    $stmt = $db->prepare("DELETE FROM `login-attempts` WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db = ConnectDB();

    // Sanitize user inputs to prevent SQL injection
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Check if the account is locked
    $lock_remaining = isAccountLocked($db, $username);
    if ($lock_remaining !== false) {
        $_SESSION['error_message'] = "Account locked due to multiple failed login attempts. Please try again in " . ceil($lock_remaining / 60) . " minute(s) and " . ($lock_remaining % 60) . " second(s).";
        header("Location: ../index.php");
        exit;
    }

    // Sanitize for SQL
    $sanitized_username = $db->real_escape_string($username);
    $sanitized_password = $db->real_escape_string($password);

    // Query to get the user by username
    $sql = "SELECT * FROM users WHERE user_username='$sanitized_username'";
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Verify the password using password_verify
        if (password_verify($password, $row['user_password'])) {
            // Login successful
            // Check if the role_id is 4 (admin approval required)
            if ($row['role_id'] == 4) {
                $_SESSION['error_message'] = "Your account is currently under review by admin. Once approved, you will be able to log in and access the system. Thank you for your patience.";
                header("Location: ../index.php"); // Redirect to index.php with the message
                exit;
            }

            // Set session variables for the logged-in user
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['user_username'] = $row['user_username'];
            $_SESSION['user_first_name'] = $row['user_first_name'];
            $_SESSION['user_middle_initial'] = $row['user_middle_initial']; // Added
            $_SESSION['user_last_name'] = $row['user_last_name'];
            $_SESSION['user_suffix'] = $row['user_suffix']; // New session variable
            $_SESSION['user_photo'] = $row['user_photo'];
            $_SESSION['user_email'] = $row['user_email'];
            $_SESSION['user_position'] = $row['user_position'];
            $_SESSION['user_id_type'] = $row['user_id_type']; // Ensure these are set
            $_SESSION['user_id_number'] = $row['user_id_number'];
            $_SESSION['role_id'] = $row['role_id'];

            // Reset failed attempts
            resetFailedAttempts($db, $username);

            // Redirect to dashboard
            header("Location: ../pages/dashboard");
            exit;
        } else {
            // Login failed
            recordFailedAttempt($db, $username);
            $_SESSION['error_message'] = "Invalid username or password.";
            header("Location: ../index.php"); // Adjust the path if necessary
            exit;
        }
    } else {
        // User not found
        $_SESSION['error_message'] = "Invalid username or password.";
        header("Location: ../index.php"); // Adjust the path if necessary
        exit;
    }
}
