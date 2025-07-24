<?php
include '../includes/database.php';
session_start();
$db = ConnectDB();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/errorprogs.txt');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Assign role_id = 1 if the position is "Project Engineer" or "Project Inspector"
if (isset($_SESSION['user_position']) && 
    ($_SESSION['user_position'] == 'Project Engineer' || $_SESSION['user_position'] == 'Project Inspector')) {
    $_SESSION['role_id'] = 1; // Admin role
}

$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];
$position = $_SESSION['user_position'];

// Retrieve and sanitize input data
$proj_ID = trim($_POST['proj_ID']);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize input data
    $proj_ID = trim($_POST['proj_ID']);
    $prog_desc = trim($_POST['prog_desc']);
    $prog_issue = trim($_POST['prog_issue']);
    $prog_percentage = trim($_POST['prog_percentage']);

    // Validate required fields
    if (empty($proj_ID) || empty($prog_desc) || $prog_percentage === '') {
        $_SESSION['error_message'] = 'Project ID, Description, and Completion Percentage are required.';
        header('Location: ../project-management/progress.php?proj_ID=' . urlencode($proj_ID));
        exit();
    }

    // Validate prog_percentage
    if (!is_numeric($prog_percentage) || $prog_percentage < 0 || $prog_percentage > 100) {
        $_SESSION['error_message'] = 'Completion Percentage must be a number between 0 and 100.';
        header('Location: ../project-management/progress.php?proj_ID=' . urlencode($proj_ID));
        exit();
    }

    $prog_percentage = (int)$prog_percentage;

    // Check if proj_ID exists in the projects table and retrieve current proj_progress
    $check_proj_query = "SELECT proj_progress, proj_status FROM projects WHERE proj_ID = ?";
    $check_stmt = $db->prepare($check_proj_query);
    $check_stmt->bind_param('s', $proj_ID);
    $check_stmt->execute();
    $check_stmt->bind_result($current_proj_progress, $proj_status);
    if (!$check_stmt->fetch()) {
        // If proj_ID does not exist in the projects table
        $_SESSION['error_message'] = 'Invalid Project ID: No matching project found in the database.';
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();

    // Check if the new progress percentage is greater than the current progress
    if ($prog_percentage <= $current_proj_progress) {
        $_SESSION['error_message'] = 'New completion percentage must be greater than the current progress (' . $current_proj_progress . '%).';
        exit();
    }

    // Check if progress has already been added today for this project
    $currentDate = new DateTime();
    $today = $currentDate->format('Y-m-d');

    $checkProgressQuery = "SELECT COUNT(*) FROM progress WHERE proj_ID = ? AND DATE(prog_date) = ?";
    $checkProgressStmt = $db->prepare($checkProgressQuery);
    $checkProgressStmt->bind_param('ss', $proj_ID, $today);
    $checkProgressStmt->execute();
    $checkProgressStmt->bind_result($progressCountToday);
    $checkProgressStmt->fetch();
    $checkProgressStmt->close();

    if ($progressCountToday > 0) {
        $_SESSION['error_message'] = 'You have already added progress for today. Please come back tomorrow to add new progress.';
        exit();
    }

    // Start transaction
    $db->begin_transaction();

    try {
        // Create folder if not exists
        $upload_dir = "../uploads/progress_" . $proj_ID;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Handle file uploads
        $prog_photos = array();
        if (!empty($_FILES['prog_photos']['name'][0])) {
            foreach ($_FILES['prog_photos']['tmp_name'] as $key => $tmp_name) {
                $file_name = basename($_FILES['prog_photos']['name'][$key]);
                $target_file = $upload_dir . '/' . $file_name;
                // Validate file type (optional but recommended)
                $file_type = pathinfo($target_file, PATHINFO_EXTENSION);
                $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
                if (in_array(strtolower($file_type), $allowed_types)) {
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $prog_photos[] = $file_name; // Store photo filename
                    } else {
                        throw new Exception('Failed to upload photo: ' . $file_name);
                    }
                } else {
                    throw new Exception('Invalid file type for photo: ' . $file_name);
                }
            }
        }

        // Save progress details in the progress table
        $photos_str = implode(',', $prog_photos); // Convert photos array to a string
        $insert_query = "INSERT INTO progress (proj_ID, prog_date, prog_desc, prog_percentage, prog_issue, prog_photos, prog_status)
                         VALUES (?, NOW(), ?, ?, ?, ?, 'Pending')";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bind_param('ssiss', $proj_ID, $prog_desc, $prog_percentage, $prog_issue, $photos_str);

        if (!$insert_stmt->execute()) {
            throw new Exception('Error adding progress: ' . $insert_stmt->error);
        }

        // Commit transaction
        $db->commit();

        // Set success message
        $_SESSION['success_message'] = 'Progress added successfully. It is awaiting approval to update the project progress.';  
        // Redirect back to progress.php with proj_ID
        header('Location: ../project-management/progress.php?proj_ID=' . urlencode($proj_ID));
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header('Location: ../project-management/progress.php?proj_ID=' . urlencode($proj_ID));
        exit();
    }
}
?>
