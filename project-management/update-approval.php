<?php
// project-management/update-approval.php

session_start();
include '../includes/database.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/error.txt');

// Set Content-Type to JSON
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendResponse($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit();
}

// Ensure user is authenticated and has the right role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    sendResponse('error', 'Unauthorized access.');
}

$db = ConnectDB();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize inputs
    $proj_ID = trim($_POST['proj_ID'] ?? '');
    $approval = trim($_POST['approval'] ?? '');

    // Validate inputs
    if (empty($proj_ID) || !in_array($approval, ['1', '0'])) {
        sendResponse('error', 'Invalid input.');
    }

    // Begin transaction
    $db->begin_transaction();

    try {
        // Update the project's approval status and status
        if ($approval === '1') {
            // Approved: set proj_isApproved = 1, proj_status = 'Ongoing'
            $updateStatusQuery = "UPDATE projects SET proj_isApproved = 1, proj_status = 'Ongoing' WHERE proj_ID = ?";
            $updateStatusStmt = $db->prepare($updateStatusQuery);
            if (!$updateStatusStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $updateStatusStmt->bind_param('s', $proj_ID);
            if (!$updateStatusStmt->execute()) {
                throw new Exception('Failed to update project approval status: ' . $updateStatusStmt->error);
            }
            $updateStatusStmt->close();

            // Reset proj_expiry_date based on proj_effect_date + proj_cont_duration + proj_unwork_days
            // Fetch proj_effect_date, proj_cont_duration, proj_unwork_days
            $projectQuery = "SELECT proj_effect_date, proj_cont_duration, proj_unwork_days FROM projects WHERE proj_ID = ?";
            $projectStmt = $db->prepare($projectQuery);
            if (!$projectStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $projectStmt->bind_param('s', $proj_ID);
            $projectStmt->execute();
            $projectResult = $projectStmt->get_result();
            $projectData = $projectResult->fetch_assoc();
            $projectStmt->close();

            if (!$projectData) {
                throw new Exception('Project not found.');
            }

            $proj_effect_date = $projectData['proj_effect_date'];
            $proj_cont_duration = intval($projectData['proj_cont_duration']);
            $proj_unwork_days = intval($projectData['proj_unwork_days']);

            // Calculate new proj_expiry_date
            $expiry_date = new DateTime($proj_effect_date);
            $expiry_date->modify("+$proj_cont_duration days");
            $expiry_date->modify("+$proj_unwork_days days");
            $new_proj_expiry_date = $expiry_date->format('Y-m-d');

            // Update proj_expiry_date
            $updateExpiryQuery = "UPDATE projects SET proj_expiry_date = ? WHERE proj_ID = ?";
            $updateExpiryStmt = $db->prepare($updateExpiryQuery);
            if (!$updateExpiryStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $updateExpiryStmt->bind_param('ss', $new_proj_expiry_date, $proj_ID);
            if (!$updateExpiryStmt->execute()) {
                throw new Exception('Failed to update project expiry date: ' . $updateExpiryStmt->error);
            }
            $updateExpiryStmt->close();

        } else { // approval === '0'
            // Not Approved: set proj_isApproved = 0, proj_status = 'Not Yet Started'
            $updateStatusQuery = "UPDATE projects SET proj_isApproved = 0, proj_status = 'Not Yet Started' WHERE proj_ID = ?";
            $updateStatusStmt = $db->prepare($updateStatusQuery);
            if (!$updateStatusStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $updateStatusStmt->bind_param('s', $proj_ID);
            if (!$updateStatusStmt->execute()) {
                throw new Exception('Failed to update project approval status: ' . $updateStatusStmt->error);
            }
            $updateStatusStmt->close();

            // Reset proj_progress to 0
            $updateProgressQuery = "UPDATE projects SET proj_progress = 0 WHERE proj_ID = ?";
            $updateProgressStmt = $db->prepare($updateProgressQuery);
            if (!$updateProgressStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $updateProgressStmt->bind_param('s', $proj_ID);
            if (!$updateProgressStmt->execute()) {
                throw new Exception('Failed to reset project progress: ' . $updateProgressStmt->error);
            }
            $updateProgressStmt->close();

            // Delete from progress table
            $deleteProgressQuery = "DELETE FROM progress WHERE proj_ID = ?";
            $deleteProgressStmt = $db->prepare($deleteProgressQuery);
            if (!$deleteProgressStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $deleteProgressStmt->bind_param('s', $proj_ID);
            if (!$deleteProgressStmt->execute()) {
                throw new Exception('Failed to delete project progress: ' . $deleteProgressStmt->error);
            }
            $deleteProgressStmt->close();

            // Delete from all section tables
            $sectionTables = [
                'contract-work-suspension',
                'contract-work-resumption',
                'contract-time-extension',
                'monthly-time-suspension-report',
                'variation-orders',
                'contract-manpower',
                'implementing-office-manpower',
                'final-completion'
            ];

            foreach ($sectionTables as $table) {
                $deleteSectionQuery = "DELETE FROM `$table` WHERE proj_ID = ?";
                $deleteSectionStmt = $db->prepare($deleteSectionQuery);
                if (!$deleteSectionStmt) {
                    throw new Exception("Database error on table $table: " . $db->error);
                }
                $deleteSectionStmt->bind_param('s', $proj_ID);
                if (!$deleteSectionStmt->execute()) {
                    throw new Exception("Failed to delete sections from $table: " . $deleteSectionStmt->error);
                }
                $deleteSectionStmt->close();
            }

            // Reset proj_expiry_date based on proj_effect_date + proj_cont_duration + proj_unwork_days
            // Fetch proj_effect_date, proj_cont_duration, proj_unwork_days
            $projectQuery = "SELECT proj_effect_date, proj_cont_duration, proj_unwork_days FROM projects WHERE proj_ID = ?";
            $projectStmt = $db->prepare($projectQuery);
            if (!$projectStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $projectStmt->bind_param('s', $proj_ID);
            $projectStmt->execute();
            $projectResult = $projectStmt->get_result();
            $projectData = $projectResult->fetch_assoc();
            $projectStmt->close();

            if (!$projectData) {
                throw new Exception('Project not found.');
            }

            $proj_effect_date = $projectData['proj_effect_date'];
            $proj_cont_duration = intval($projectData['proj_cont_duration']);
            $proj_unwork_days = intval($projectData['proj_unwork_days']);

            // Calculate new proj_expiry_date
            $expiry_date = new DateTime($proj_effect_date);
            $expiry_date->modify("+$proj_cont_duration days");
            $expiry_date->modify("+$proj_unwork_days days");
            $new_proj_expiry_date = $expiry_date->format('Y-m-d');

            // Update proj_expiry_date
            $updateExpiryQuery = "UPDATE projects SET proj_expiry_date = ? WHERE proj_ID = ?";
            $updateExpiryStmt = $db->prepare($updateExpiryQuery);
            if (!$updateExpiryStmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $updateExpiryStmt->bind_param('ss', $new_proj_expiry_date, $proj_ID);
            if (!$updateExpiryStmt->execute()) {
                throw new Exception('Failed to update project expiry date: ' . $updateExpiryStmt->error);
            }
            $updateExpiryStmt->close();
        }

        // Commit transaction
        $db->commit();
        sendResponse('success', 'Project approval status updated successfully.');
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        sendResponse('error', $e->getMessage());
    }
} else {
    sendResponse('error', 'Invalid request method.');
}
?>
