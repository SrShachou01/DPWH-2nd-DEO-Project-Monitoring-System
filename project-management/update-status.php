<?php
// project-management/update-status.php

session_start();
include '../includes/database.php';

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
    $section_ID = trim($_POST['section_ID'] ?? '');
    $section_type = trim($_POST['section_type'] ?? '');
    $status = trim($_POST['status'] ?? '');

    // Validate inputs
    if (empty($section_ID) || empty($section_type) || empty($status)) {
        sendResponse('error', 'Invalid input.');
    }

    // Validate status value
    $validStatuses = ['Approved', 'Not Approved'];
    if (!in_array($status, $validStatuses)) {
        sendResponse('error', 'Invalid status value.');
    }

    // Determine the table and primary key based on section_type
    switch ($section_type) {
        case 'Contract Work Suspension':
            $table = 'contract-work-suspension';
            $primaryKey = 'cws_code';
            break;
        case 'Contract Work Resumption':
            $table = 'contract-work-resumption';
            $primaryKey = 'cwr_code';
            break;
        case 'Contract Time Extension':
            $table = 'contract-time-extension';
            $primaryKey = 'cte_code';
            break;
        case 'Monthly Time Suspension Report':
            $table = 'monthly-time-suspension-report';
            $primaryKey = 'mtsr_code';
            break;
        case 'Variation Order':
            $table = 'variation-orders';
            $primaryKey = 'vo_code';
            break;
        case 'Contract Manpower':
            $table = 'contract-manpower';
            $primaryKey = 'cm_mp_ID';
            break;
        case 'Implementing Office Manpower':
            $table = 'implementing-office-manpower';
            $primaryKey = 'iom_ID';
            break;
        case 'Final Completion':
            $table = 'final-completion';
            $primaryKey = 'fc_ID';
            break;
        default:
            sendResponse('error', 'Invalid section type.');
    }

    // Begin transaction
    $db->begin_transaction();

    try {
        // Retrieve proj_ID based on section_ID and section_type
        $getProjIDQuery = "SELECT proj_ID FROM `$table` WHERE `$primaryKey` = ?";
        $getProjIDStmt = $db->prepare($getProjIDQuery);
        if (!$getProjIDStmt) {
            throw new Exception('Database error: ' . $db->error);
        }
        $getProjIDStmt->bind_param('s', $section_ID);
        $getProjIDStmt->execute();
        $getProjIDResult = $getProjIDStmt->get_result();
        $projData = $getProjIDResult->fetch_assoc();

        if (!$projData) {
            throw new Exception('Section not found.');
        }

        $proj_ID = $projData['proj_ID'];
        $getProjIDStmt->close();

        // Update the status in the respective table
        $updateQuery = "UPDATE `$table` SET `status` = ? WHERE `$primaryKey` = ? AND proj_ID = ?";
        $updateStmt = $db->prepare($updateQuery);
        if (!$updateStmt) {
            throw new Exception('Database error: ' . $db->error);
        }
        $updateStmt->bind_param('sss', $status, $section_ID, $proj_ID);
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update status: ' . $updateStmt->error);
        }
        $updateStmt->close();

        // Additional logic for specific sections
        if ($section_type === 'Contract Time Extension') {
            if ($status === 'Approved') {
                // Fetch total approved extension days
                $totalCTEQuery = "SELECT IFNULL(SUM(cte_ext_days), 0) as total_ext_days FROM `contract-time-extension` WHERE proj_ID = ? AND status = 'Approved'";
                $totalCTEStmt = $db->prepare($totalCTEQuery);
                if (!$totalCTEStmt) {
                    throw new Exception('Database error: ' . $db->error);
                }
                $totalCTEStmt->bind_param('s', $proj_ID);
                $totalCTEStmt->execute();
                $totalCTEResult = $totalCTEStmt->get_result();
                $totalCTERow = $totalCTEResult->fetch_assoc();
                $total_cte_ext_days = intval($totalCTERow['total_ext_days']);
                $totalCTEStmt->close();

                // Get current project details
                $projectQuery = "SELECT proj_cont_duration, proj_unwork_days, proj_effect_date FROM projects WHERE proj_ID = ?";
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

                $proj_cont_duration = intval($projectData['proj_cont_duration']);
                $proj_unwork_days = intval($projectData['proj_unwork_days']);
                $proj_effect_date = $projectData['proj_effect_date'];

                // Calculate total duration
                $total_duration = $proj_cont_duration + $proj_unwork_days + $total_cte_ext_days;

                // Calculate new proj_expiry_date
                $expiry_date = new DateTime($proj_effect_date);
                $expiry_date->modify("+$total_duration days");
                $new_proj_expiry_date = $expiry_date->format('Y-m-d');

                // Update the proj_expiry_date in projects table
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
            } else {
                // If 'Not Approved', ensure that no extension days are added
                // Recalculate the total approved extension days and update expiry date
                $totalCTEQuery = "SELECT IFNULL(SUM(cte_ext_days), 0) as total_ext_days FROM `contract-time-extension` WHERE proj_ID = ? AND status = 'Approved'";
                $totalCTEStmt = $db->prepare($totalCTEQuery);
                if (!$totalCTEStmt) {
                    throw new Exception('Database error: ' . $db->error);
                }
                $totalCTEStmt->bind_param('s', $proj_ID);
                $totalCTEStmt->execute();
                $totalCTEResult = $totalCTEStmt->get_result();
                $totalCTERow = $totalCTEResult->fetch_assoc();
                $total_cte_ext_days = intval($totalCTERow['total_ext_days']);
                $totalCTEStmt->close();

                // Get current project details
                $projectQuery = "SELECT proj_cont_duration, proj_unwork_days, proj_effect_date FROM projects WHERE proj_ID = ?";
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

                $proj_cont_duration = intval($projectData['proj_cont_duration']);
                $proj_unwork_days = intval($projectData['proj_unwork_days']);
                $proj_effect_date = $projectData['proj_effect_date'];

                // Calculate total duration
                $total_duration = $proj_cont_duration + $proj_unwork_days + $total_cte_ext_days;

                // Calculate new proj_expiry_date
                $expiry_date = new DateTime($proj_effect_date);
                $expiry_date->modify("+$total_duration days");
                $new_proj_expiry_date = $expiry_date->format('Y-m-d');

                // Update the proj_expiry_date in projects table
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
        }

        // Commit transaction
        $db->commit();
        sendResponse('success', 'Status updated successfully.');
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        sendResponse('error', $e->getMessage());
    }
} else {
    sendResponse('error', 'Invalid request method.');
}
?>
