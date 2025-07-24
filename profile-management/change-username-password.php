<?php
require_once "../includes/database.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$conn = ConnectDB();

// Capture the form data
$currentPassword = $_POST['currentPassword'];
$newPassword = $_POST['newPassword'];
$confirmPassword = $_POST['confirmPassword'];

// Check if new password and confirm password match
if ($newPassword !== $confirmPassword) {
    echo "Passwords do not match!";
    exit();
}

// Validate the current password
$query = "SELECT user_password FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($userPassword);
$stmt->fetch();
$stmt->close();

// Verify the current password using password_verify
if (!password_verify($currentPassword, $userPassword)) {
    echo "Current password is incorrect!";
    exit();
}

// Hash the new password before storing
$hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update with the new hashed password
$updateQuery = "UPDATE users SET user_password = ? WHERE user_id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("si", $hashedNewPassword, $_SESSION['user_id']);
if ($stmt->execute()) {
    echo "Password updated successfully!";
    $stmt->close();
    header('Location: ../index.php'); // Redirect to index after password update
    exit();
} else {
    echo "Failed to update password!";
    $stmt->close();
}
?>
