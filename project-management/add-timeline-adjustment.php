<?php
// project-management/add-section.php

// Disable error display and enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/error.txt'); // Ensure this path is correct and writable

session_start();
include '../includes/database.php';

// Set Content-Type to JSON for AJAX responses
header('Content-Type: application/json');

// Function to send JSON response and terminate the script
function sendResponse($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit();
}

// Ensure the user is authenticated and has the appropriate role (1 or 2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    sendResponse('error', 'Unauthorized access.');
}

$db = ConnectDB();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize the 'proj_ID' and 'section' from POST data
    $proj_ID = trim($_POST['proj_ID'] ?? '');
    $section = trim($_POST['section'] ?? '');

    // Validate that both 'proj_ID' and 'section' are provided
    if (empty($proj_ID) || empty($section)) {
        sendResponse('error', 'Invalid input. Project ID and section type are required.');
    }

 // Verify that 'proj_ID' exists in the 'projects' table to satisfy foreign key constraints
 $checkProjQuery = "SELECT COUNT(*) as count, proj_expiry_date FROM `projects` WHERE `proj_ID` = ?";

    $checkStmt = $db->prepare($checkProjQuery);
    if (!$checkStmt) {
        sendResponse('error', 'Database error: ' . $db->error);
    }
    $checkStmt->bind_param("s", $proj_ID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    $checkStmt->close();

    if ($checkRow['count'] == 0) {
        sendResponse('error', 'Project ID does not exist.');
    }

    $currentExpiryDate = $checkRow['proj_expiry_date'];

    // Depending on the 'section' type, process the input accordingly
    switch ($section) {
        case 'cws':
            // Contract Work Suspension
            $cws_code = trim($_POST['cws_code'] ?? '');
            $cws_lr_date = $_POST['cws_lr_date'] ?? '';
            $cws_reason = trim($_POST['cws_reason'] ?? '');
            $cws_susp_days = intval($_POST['cws_susp_days'] ?? 0);
            $cws_approved_date = $_POST['cws_approved_date'] ?? '';

            // Validate required fields
            if (empty($cws_code) || empty($cws_lr_date) || empty($cws_reason) || $cws_susp_days <= 0 || empty($cws_approved_date)) {
                sendResponse('error', 'Please fill all required fields correctly for Contract Work Suspension.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `contract-work-suspension` (proj_ID, cws_code, cws_lr_date, cws_reason, cws_susp_days, cws_approved_date, status)
                      VALUES (?, ?, ?, ?, ?, ?, 'Not Approved')";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param("ssssis", $proj_ID, $cws_code, $cws_lr_date, $cws_reason, $cws_susp_days, $cws_approved_date);

            // Execute and handle the response
            if ($stmt->execute()) {
                sendResponse('success', 'Contract Work Suspension added successfully.');
            } else {
                // Log the error for debugging
                error_log("Error adding Contract Work Suspension: " . $stmt->error);
                sendResponse('error', 'Failed to add Contract Work Suspension. Please try again.');
            }

            $stmt->close();
            break;

        case 'cwr':
            // Contract Work Resumption
            $cwr_code = trim($_POST['cwr_code'] ?? '');
            $cwr_lr_date = $_POST['cwr_lr_date'] ?? '';
            $cwr_reason = trim($_POST['cwr_reason'] ?? '');
            $cwr_susp_days = intval($_POST['cwr_susp_days'] ?? 0);
            $cwr_approved_date = $_POST['cwr_approved_date'] ?? '';

            // Validate required fields
            if (empty($cwr_code) || empty($cwr_lr_date) || empty($cwr_reason) || $cwr_susp_days <= 0 || empty($cwr_approved_date)) {
                sendResponse('error', 'Please fill all required fields correctly for Contract Work Resumption.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `contract-work-resumption` (proj_ID, cwr_code, cwr_lr_date, cwr_reason, cwr_susp_days, cwr_approved_date, status)
                      VALUES (?, ?, ?, ?, ?, ?, 'Not Approved')";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param("ssssis", $proj_ID, $cwr_code, $cwr_lr_date, $cwr_reason, $cwr_susp_days, $cwr_approved_date);

            // Execute and handle the response
            if ($stmt->execute()) {
                sendResponse('success', 'Contract Work Resumption added successfully.');
            } else {
                // Log the error for debugging
                error_log("Error adding Contract Work Resumption: " . $stmt->error);
                sendResponse('error', 'Failed to add Contract Work Resumption. Please try again.');
            }

            $stmt->close();
            break;

            case 'cte':
                // Contract Time Extension
                $cte_code = trim($_POST['cte_code'] ?? '');
                $cte_lr_date = $_POST['cte_lr_date'] ?? '';
                $cte_reason = trim($_POST['cte_reason'] ?? '');
                $cte_ext_days = intval($_POST['cte_ext_days'] ?? 0);
                $cte_approved_date = $_POST['cte_approved_date'] ?? '';
    
                // Validate required fields
                if (empty($cte_code) || empty($cte_lr_date) || empty($cte_reason) || $cte_ext_days <= 0 || empty($cte_approved_date)) {
                    sendResponse('error', 'Please fill all required fields correctly for Contract Time Extension.');
                }
    
                // Prepare the INSERT statement for Contract Time Extension
                $query = "INSERT INTO `contract-time-extension` (proj_ID, cte_code, cte_lr_date, cte_reason, cte_ext_days, cte_approved_date, status)
                          VALUES (?, ?, ?, ?, ?, ?, 'Not Approved')";
                $stmt = $db->prepare($query);
                if (!$stmt) {
                    sendResponse('error', 'Database error: ' . $db->error);
                }
    
                // Bind parameters
                $stmt->bind_param("ssssis", $proj_ID, $cte_code, $cte_lr_date, $cte_reason, $cte_ext_days, $cte_approved_date);
    
                // Execute and handle the response
                if ($stmt->execute()) {
                    // Update the project's expiry date after Contract Time Extension
                    $newExpiryDate = date('Y-m-d', strtotime($currentExpiryDate . " + $cte_ext_days days"));
                    $updateQuery = "UPDATE `projects` SET proj_expiry_date = ? WHERE proj_ID = ?";
                    $updateStmt = $db->prepare($updateQuery);
                    if (!$updateStmt) {
                        sendResponse('error', 'Database error: ' . $db->error);
                    }
                    $updateStmt->bind_param("ss", $newExpiryDate, $proj_ID);
                    if ($updateStmt->execute()) {
                        sendResponse('success', 'Contract Time Extension added and expiry date updated successfully.');
                    } else {
                        sendResponse('error', 'Failed to update the expiry date.');
                    }
                    $updateStmt->close();
                } else {
                    // Log the error for debugging
                    error_log("Error adding Contract Time Extension: " . $stmt->error);
                    sendResponse('error', 'Failed to add Contract Time Extension. Please try again.');
                }
    
                $stmt->close();
                break;
                
            
        case 'mtsr':
            // Monthly Time Suspension Report
            $mtsr_code = trim($_POST['mtsr_code'] ?? '');
            $mtsr_lr_date = $_POST['mtsr_lr_date'] ?? '';
            $mtsr_reason = trim($_POST['mtsr_reason'] ?? '');
            $mtsr_susp_days = intval($_POST['mtsr_susp_days'] ?? 0);
            $mtsr_approved_date = $_POST['mtsr_approved_date'] ?? '';

            // Validate required fields
            if (empty($mtsr_code) || empty($mtsr_lr_date) || empty($mtsr_reason) || $mtsr_susp_days <= 0 || empty($mtsr_approved_date)) {
                sendResponse('error', 'Please fill all required fields correctly for Monthly Time Suspension Report.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `monthly-time-suspension-report` (proj_ID, mtsr_code, mtsr_lr_date, mtsr_reason, mtsr_susp_days, mtsr_approved_date, status)
                      VALUES (?, ?, ?, ?, ?, ?, 'Not Approved')";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param("ssssis", $proj_ID, $mtsr_code, $mtsr_lr_date, $mtsr_reason, $mtsr_susp_days, $mtsr_approved_date);

            // Execute and handle the response
            if ($stmt->execute()) {
                sendResponse('success', 'Monthly Time Suspension Report added successfully.');
            } else {
                // Log the error for debugging
                error_log("Error adding Monthly Time Suspension Report: " . $stmt->error);
                sendResponse('error', 'Failed to add Monthly Time Suspension Report. Please try again.');
            }

            $stmt->close();
            break;

        case 'vo':
            // Variation Order
            $vo_code = trim($_POST['vo_code'] ?? '');
            $vo_date_request = $_POST['vo_date_request'] ?? '';
            $vo_reason = trim($_POST['vo_reason'] ?? '');
            $vo_amt_change = floatval($_POST['vo_amt_change'] ?? 0);
            $vo_approved_date = $_POST['vo_approved_date'] ?? '';

            // Validate required fields
            if (empty($vo_code) || empty($vo_date_request) || empty($vo_reason) || $vo_amt_change <= 0 || empty($vo_approved_date)) {
                sendResponse('error', 'Please fill all required fields correctly for Variation Order.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `variation-orders` (proj_ID, vo_code, vo_date_request, vo_reason, vo_amt_change, vo_approved_date, status)
                      VALUES (?, ?, ?, ?, ?, ?, 'Not Approved')";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param("sssdis", $proj_ID, $vo_code, $vo_date_request, $vo_reason, $vo_amt_change, $vo_approved_date);

            // Execute and handle the response
            if ($stmt->execute()) {
                sendResponse('success', 'Variation Order added successfully.');
            } else {
                // Log the error for debugging
                error_log("Error adding Variation Order: " . $stmt->error);
                sendResponse('error', 'Failed to add Variation Order. Please try again.');
            }

            $stmt->close();
            break;

        case 'fc':
            // Final Completion
            $fc_ID = trim($_POST['fc_ID'] ?? '');
            $fc_ir_date = $_POST['fc_ir_date'] ?? '';
            $fc_coc_date = $_POST['fc_coc_date'] ?? '';
            $fc_coa_date = $_POST['fc_coa_date'] ?? '';

            // Validate required fields
            if (empty($fc_ID) || empty($fc_ir_date) || empty($fc_coc_date) || empty($fc_coa_date)) {
                sendResponse('error', 'Please fill all required fields correctly for Final Completion.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `final-completion` (
                        proj_ID, 
                        fc_ID, 
                        fc_ir_date, 
                        fc_coc_date, 
                        fc_coa_date, 
                        status
                      ) VALUES (?, ?, ?, ?, ?, 'Not Approved')";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param("sssss", $proj_ID, $fc_ID, $fc_ir_date, $fc_coc_date, $fc_coa_date);

            // Execute and handle the response
            if ($stmt->execute()) {
                sendResponse('success', "Final Completion added successfully.");
            } else {
                // Log the error for debugging
                error_log("Error adding Final Completion: " . $stmt->error);
                sendResponse('error', "Failed to add Final Completion. Please try again.");
            }

            $stmt->close();
            break;

        default:
            sendResponse('error', 'Unknown section type.');
    }
} else {
    // If not a POST request, send an error response
    sendResponse('error', 'Invalid request method.');
}
?>
