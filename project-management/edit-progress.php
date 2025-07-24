<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/errorprogs.txt');

include '../includes/database.php';
session_start();

$db = ConnectDB();
$response = ['success' => false, 'message' => ''];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You need to log in first.';
    $_SESSION['error_message'] = $response['message'];
    echo json_encode($response);
    exit();
}

// Validate input
if (empty($_POST['prog_ID']) || empty($_POST['prog_percentage']) || empty($_POST['prog_desc'])) {
    $response['message'] = 'Missing required fields.';
    $_SESSION['error_message'] = $response['message'];
    echo json_encode($response);
    exit();
}

$prog_ID = $_POST['prog_ID'];
$prog_percentage = $_POST['prog_percentage'];
$prog_desc = $_POST['prog_desc'];
$prog_issue = isset($_POST['prog_issue']) ? $_POST['prog_issue'] : '';
$prog_photos = ''; // Will handle photo uploads below
$remove_existing_photos = isset($_POST['remove_existing_photos']) ? $_POST['remove_existing_photos'] : false;

// Handle approval/rejection action for Project Engineer
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'approve') {
        // Update status to 'Approved'
        $update_query = "UPDATE progress SET prog_status = 'Approved' WHERE prog_ID = ?";
        $stmt = $db->prepare($update_query);
        $stmt->bind_param('s', $prog_ID);
        if ($stmt->execute()) {
            // Once approved, update the project progress
            $stmt2 = $db->prepare("UPDATE projects SET proj_progress = ? WHERE proj_ID = (SELECT proj_ID FROM progress WHERE prog_ID = ?)");
            $stmt2->bind_param('ds', $prog_percentage, $prog_ID);
            if ($stmt2->execute()) {
                $_SESSION['success_message'] = 'Progress has been approved and project progress updated.';
            } else {
                $_SESSION['error_message'] = 'Failed to update project progress after approval.';
            }
        } else {
            $_SESSION['error_message'] = 'Failed to approve progress.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'reject') {
        // Update status to 'Denied'
        $update_query = "UPDATE progress SET prog_status = 'Denied' WHERE prog_ID = ?";
        $stmt = $db->prepare($update_query);
        $stmt->bind_param('s', $prog_ID);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Progress has been rejected.';
        } else {
            $_SESSION['error_message'] = 'Failed to reject progress.';
        }
    }
}

// Handle photo uploads
if (isset($_FILES['prog_photos']) && !empty($_FILES['prog_photos']['name'][0])) {
    // Define the target directory
    $upload_dir = "../uploads/progress_photos/";

    // Ensure the directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $uploads = [];
    foreach ($_FILES['prog_photos']['name'] as $index => $filename) {
        $file_tmp = $_FILES['prog_photos']['tmp_name'][$index];
        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
        $file_new_name = uniqid('photo_', true) . '.' . $file_ext;

        // Move uploaded file to the target directory
        if (move_uploaded_file($file_tmp, $upload_dir . $file_new_name)) {
            $uploads[] = $file_new_name;
        } else {
            $response['message'] = 'Failed to upload file: ' . $filename;
            echo json_encode($response);
            exit();
        }
    }
    $prog_photos = implode(',', $uploads);
}

// Check if we need to remove existing photos
if ($remove_existing_photos) {
    // Fetch existing photos
    $stmt = $db->prepare("SELECT prog_photos FROM progress WHERE prog_ID = ?");
    $stmt->bind_param('s', $prog_ID);
    $stmt->execute();
    $stmt->bind_result($existing_photos);
    $stmt->fetch();
    $stmt->close();

    // Delete the existing files
    if (!empty($existing_photos)) {
        $existing_photos_arr = explode(',', $existing_photos);
        foreach ($existing_photos_arr as $photo) {
            $photo_path = "../uploads/progress_photos/$photo";
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
        }
    }
    $prog_photos = ''; // Remove all existing photos
}

// Update progress record in the database
$stmt = $db->prepare("UPDATE progress SET prog_percentage = ?, prog_desc = ?, prog_issue = ?, prog_photos = ? WHERE prog_ID = ?");
$stmt->bind_param('sssss', $prog_percentage, $prog_desc, $prog_issue, $prog_photos, $prog_ID);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Progress updated successfully.';
    $_SESSION['success_message'] = $response['message'];
} else {
    $response['message'] = 'Failed to update progress.';
    $_SESSION['error_message'] = $response['message'];
}

$stmt->close();
echo json_encode($response);
exit();
?>

