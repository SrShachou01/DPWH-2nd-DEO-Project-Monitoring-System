<?php
include '../includes/database.php';
session_start();
$db = ConnectDB();

// Admin check
if ($_SESSION['role_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

$request_id = $_POST['request_id'];
$proj_ID = $_POST['proj_ID'];
$action = $_POST['action'];  // Approve or Deny


// Update the request status
$query = "UPDATE `edit-request` SET request_status = ? WHERE request_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("si", $action, $request_id);

if ($stmt->execute()) {
    // Set success message in session
    $_SESSION['success_message'] = 'Request status updated successfully.';
} else {
    $_SESSION['error_message'] = 'Failed to update request status.';
}

header("Location: ../pages/edit-requests.php");
exit();
?>
