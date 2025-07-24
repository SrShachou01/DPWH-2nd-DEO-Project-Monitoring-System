<?php
// profile-management/change-profile-info.php

session_start();

require_once('../includes/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $db = ConnectDB();

    // Retrieve and sanitize inputs
    $firstName = trim($_POST['firstName']);
    $middleInitial = isset($_POST['middleInitial']) ? trim($_POST['middleInitial']) : '';
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $idType = trim($_POST['idType']);
    $idNumber = trim($_POST['idNumber']);

    // Validate middle initial
    if (!empty($middleInitial) && strlen($middleInitial) !== 1) {
        $_SESSION['error_message'] = "Middle initial must be a single character.";
        header('Location: ../profile.php');
        exit();
    }

    // Handle ID Number based on ID Type
    if ($idType === 'None') {
        $idNumber = 'None';
    }

    // Update user information in the database
    $stmt = $db->prepare("UPDATE users SET user_first_name = ?, user_middle_initial = ?, user_last_name = ?, user_email = ?, user_id_type = ?, user_id_number = ? WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssssssi", $firstName, $middleInitial, $lastName, $email, $idType, $idNumber, $_SESSION['user_id']);
        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['user_first_name'] = $firstName;
            $_SESSION['user_middle_initial'] = $middleInitial;
            $_SESSION['user_last_name'] = $lastName;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_id_type'] = $idType;
            $_SESSION['user_id_number'] = $idNumber;

            $_SESSION['success_message'] = "Profile updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update profile: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Database error: " . $db->error;
    }

    $db->close();

    header('Location: ../pages/profile.php');
    exit();
}
?>
