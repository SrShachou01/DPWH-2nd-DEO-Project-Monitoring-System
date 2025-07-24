<?php
// project-management/delete-section.php

session_start();
include '../includes/database.php';

// Set Content-Type to JSON
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendResponse($status, $message = '') {
    $_SESSION[$status . '_message'] = $message;
    echo json_encode(['status' => $status, 'message' => $message]);
    exit();
}

// Ensure user is authenticated and has the right role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    sendResponse('error', 'Unauthorized access.');
}

$db = ConnectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_ID = trim($_POST['section_ID'] ?? '');
    $section_type = trim($_POST['section_type'] ?? '');
    $proj_ID = trim($_POST['proj_ID'] ?? '');

    if (empty($section_ID) || empty($section_type) || empty($proj_ID)) {
        sendResponse('error', 'Invalid input.');
    }

    switch ($section_type) {
        case 'Contract Work Suspension':
            $table = 'contract-work-suspension';
            $primaryKey = 'cws_code';
            $daysField = 'cws_susp_days';
            break;
        case 'Contract Work Resumption':
            $table = 'contract-work-resumption';
            $primaryKey = 'cwr_code';
            $daysField = 'cwr_susp_days';
            break;
        case 'Contract Time Extension':
            $table = 'contract-time-extension';
            $primaryKey = 'cte_code';
            $daysField = 'cte_ext_days';
            break;
        case 'Monthly Time Suspension Report':
            $table = 'monthly-time-suspension-report';
            $primaryKey = 'mtsr_code';
            break;
        case 'Variation Order':
            $table = 'variation-orders';
            $primaryKey = 'vo_code';
            $daysField = 'vo_ext_days';
            break;
        case 'Final Completion':
            if (strpos($section_type, 'Final Completion') === 0) {
                $table = 'final-completion';
                $primaryKey = 'fc_ID';
            } else {
                sendResponse('error', 'Invalid section type.');
            }
            break;
        case 'Other Documents':
            if (strpos($section_type, 'Other Documents') === 0) {
                $table = 'other-documents';
                $primaryKey = 'od_ID';
            } else {
                sendResponse('error', 'Invalid section type.');
            }
            break;
        default:
            sendResponse('error', 'Invalid section type.');
    }

    $db->begin_transaction();

    try {
        // Handle expiry date adjustments for VO, CTE, CWS, and CWR
        if (isset($daysField)) {
            $fetchQuery = "SELECT $daysField FROM `$table` WHERE `$primaryKey` = ? AND `proj_ID` = ?";
            $fetchStmt = $db->prepare($fetchQuery);
            if (!$fetchStmt) throw new Exception('Database error: ' . $db->error);
            $fetchStmt->bind_param('ss', $section_ID, $proj_ID);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result()->fetch_assoc();
            $fetchStmt->close();

            if (!$result) throw new Exception('Section data not found.');
            $ext_days = intval($result[$daysField]);

            $expiryQuery = "SELECT proj_expiry_date FROM `projects` WHERE `proj_ID` = ?";
            $expiryStmt = $db->prepare($expiryQuery);
            if (!$expiryStmt) throw new Exception('Database error: ' . $db->error);
            $expiryStmt->bind_param('s', $proj_ID);
            $expiryStmt->execute();
            $expiryStmt->bind_result($current_expiry_date);
            $expiryStmt->fetch();
            $expiryStmt->close();

            if (!$current_expiry_date) throw new Exception('Current project expiry date not found.');

            $expiryDate = new DateTime($current_expiry_date);
            $expiryDate->modify("-{$ext_days} days");
            $new_expiry_date = $expiryDate->format('Y-m-d');

            $updateExpiryQuery = "UPDATE `projects` SET `proj_expiry_date` = ? WHERE `proj_ID` = ?";
            $updateExpiryStmt = $db->prepare($updateExpiryQuery);
            if (!$updateExpiryStmt) throw new Exception('Database error: ' . $db->error);
            $updateExpiryStmt->bind_param('ss', $new_expiry_date, $proj_ID);
            if (!$updateExpiryStmt->execute()) throw new Exception('Failed to update project expiry date.');
            $updateExpiryStmt->close();
        }

        $deleteQuery = "DELETE FROM `$table` WHERE `$primaryKey` = ? AND proj_ID = ?";
        $deleteStmt = $db->prepare($deleteQuery);
        if (!$deleteStmt) throw new Exception('Database error: ' . $db->error);
        $deleteStmt->bind_param('ss', $section_ID, $proj_ID);
        if (!$deleteStmt->execute()) throw new Exception('Failed to delete section: ' . $deleteStmt->error);
        $deleteStmt->close();

        $db->commit();
        sendResponse('success', 'Section deleted and expiry date updated successfully.');
    } catch (Exception $e) {
        $db->rollback();
        sendResponse('error', $e->getMessage());
    }
} else {
    sendResponse('error', 'Invalid request method.');
}
?>
