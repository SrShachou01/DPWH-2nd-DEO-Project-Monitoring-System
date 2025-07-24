<?php
// remove-collaborator.php
session_start();
include '../includes/database.php';

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

// Retrieve POST data
$proj_ID = isset($_POST['proj_ID']) ? trim($_POST['proj_ID']) : '';
$collab_user_ID = isset($_POST['user_ID']) ? intval($_POST['user_ID']) : 0;

// Validate inputs
if (empty($proj_ID) || $collab_user_ID <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid project or user ID.']);
    exit();
}

// Connect to the database
$db = ConnectDB();

// Check if the current user is Admin or Uploader of the project
$check_owner_query = "SELECT user_ID FROM projects WHERE proj_ID = ?";
$stmt = $db->prepare($check_owner_query);
$stmt->bind_param('s', $proj_ID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Project not found.']);
    exit();
}

$project = $result->fetch_assoc();
$uploader_id = $project['user_ID'];

if ($role_id != 1 && $user_id != $uploader_id) {
    echo json_encode(['status' => 'error', 'message' => 'You are not authorized to remove collaborators from this project.']);
    exit();
}


// Check if the user is a collaborator
$check_collab_query = "SELECT * FROM `project-collaborators` WHERE proj_ID = ? AND user_ID = ?";
$check_collab_stmt = $db->prepare($check_collab_query);
$check_collab_stmt->bind_param('si', $proj_ID, $collab_user_ID);
$check_collab_stmt->execute();
$check_collab_result = $check_collab_stmt->get_result();

if ($check_collab_result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'User is not a collaborator for this project.']);
    exit();
}

// Remove the collaborator
$remove_collab_query = "DELETE FROM `project-collaborators` WHERE proj_ID = ? AND user_ID = ?";
$remove_collab_stmt = $db->prepare($remove_collab_query);
$remove_collab_stmt->bind_param('si', $proj_ID, $collab_user_ID);

if ($remove_collab_stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Collaborator removed successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove collaborator.']);
}

?>
