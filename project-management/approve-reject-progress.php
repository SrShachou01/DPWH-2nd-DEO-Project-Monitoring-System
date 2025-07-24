<?php
include '../includes/database.php';
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/errorprogs.txt');

$db = ConnectDB();
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You need to log in first.';
    echo json_encode($response);
    exit();
}

// Validate the request data
if (empty($_POST['prog_ID']) || empty($_POST['action'])) {
    $response['message'] = 'Missing required data.';
    echo json_encode($response);
    exit();
}

$prog_ID = $_POST['prog_ID'];
$action = $_POST['action']; // 'approve' or 'reject'

// Ensure action is either approve or reject
if ($action !== 'approve' && $action !== 'reject') {
    $response['message'] = 'Invalid action.';
    echo json_encode($response);
    exit();
}

$prog_status = ($action == 'approve') ? 'Approved' : 'Denied';

// Update progress status based on the action
$update_query = "UPDATE progress SET prog_status = ? WHERE prog_ID = ?";
$stmt = $db->prepare($update_query);

// Correct binding
if (!$stmt) {
    $response['message'] = 'SQL error: ' . $db->error; // Log the error
    echo json_encode($response);
    exit();
}

$stmt->bind_param('ss', $prog_status, $prog_ID);

if ($stmt->execute()) {
    // Optionally, update the project progress if approved
    if ($action == 'approve') {
        // Get the progress percentage from the progress table
        $query = "SELECT prog_percentage FROM progress WHERE prog_ID = ?";
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            $response['message'] = 'SQL error: ' . $db->error; // Log the error
            echo json_encode($response);
            exit();
        }

        $stmt->bind_param('s', $prog_ID);
        $stmt->execute();
        $stmt->bind_result($prog_percentage);
        $stmt->fetch();
        $stmt->close(); // Close the result set before moving on

        // Update the project progress in the projects table
        $update_proj_query = "UPDATE projects SET proj_progress = ? WHERE proj_ID = (SELECT proj_ID FROM progress WHERE prog_ID = ?)";
        $update_proj_stmt = $db->prepare($update_proj_query);

        if (!$update_proj_stmt) {
            $response['message'] = 'SQL error: ' . $db->error; // Log the error
            echo json_encode($response);
            exit();
        }

        $update_proj_stmt->bind_param('ds', $prog_percentage, $prog_ID);
        $update_proj_stmt->execute();
        $update_proj_stmt->close(); // Close the second statement as well
    }

    $response['success'] = true;
    $response['message'] = 'Progress has been ' . ($action == 'approve' ? 'approved' : 'rejected') . '.';
} else {
    $response['message'] = 'Error updating progress status: ' . $stmt->error; // Log the error
}

echo json_encode($response);
exit();

?>
