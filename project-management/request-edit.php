<?php
session_start();
include '../includes/database.php';
error_log("Received proj_ID: " . $proj_ID . " and user_ID: " . $user_id);

$db = ConnectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the project ID, user ID, and reason for the edit request
    $proj_ID = $_POST['proj_ID'];
    $reason = $_POST['reason'];  // Get the reason for the request
    $user_id = $_SESSION['user_id'];

    if (!$proj_ID || !$user_id || !$reason) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        exit();
    }

    // Check if there is an existing pending edit request for this user and project
    $stmt = $db->prepare("SELECT * FROM `edit-request` WHERE proj_ID = ? AND user_ID = ? AND request_status = 'Pending'");
    $stmt->bind_param('si', $proj_ID, $user_id);  
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // If there is a pending request, return an error
        echo json_encode(['status' => 'error', 'message' => 'You already have a pending request to edit this project.']);
    } else {
        // Insert new edit request with the reason provided
        $stmt = $db->prepare("INSERT INTO `edit-request` (proj_ID, user_ID, request_status, request_reason) VALUES (?, ?, 'Pending', ?)");
        $stmt->bind_param('sis', $proj_ID, $user_id, $reason);  // Insert new request with reason
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Your edit request has been sent.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send request']);
        }
    }
}

?>
