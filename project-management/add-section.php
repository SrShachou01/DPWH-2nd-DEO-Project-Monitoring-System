<?php
// project-management/add-section.php

// Disable error display and enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/errorAddsecctione.txt'); // Ensure this path is correct and writable

session_start();
include '../includes/database.php';

// Set Content-Type to JSON for AJAX responses
header('Content-Type: application/json');

// Function to send JSON response and terminate the script
function sendResponse($status, $message) {
    // Store messages in session to be used on the front-end
    $_SESSION[$status . '_message'] = $message;
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
    $checkProjQuery = "SELECT COUNT(*) as count FROM `projects` WHERE `proj_ID` = ?";
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

    // Depending on the 'section' type, process the input accordingly
    switch ($section) {
        case 'cws':
            // Contract Work Suspension
            $cws_code = trim($_POST['cws_code'] ?? '');
            $cws_lr_date = $_POST['cws_lr_date'] ?? '';
            $cws_reason = trim($_POST['cws_reason'] ?? '');
            $cws_susp_days = intval($_POST['cws_susp_days'] ?? 0);
            $cws_ext_days = intval($_POST['cws_ext_days'] ?? 0);  // Default to 0 if not set
            $cws_expiry_date = $_POST['cws_expiry_date'] ?? null;  // Default to null if not set

            // Validate required fields
            if (empty($cws_code) || empty($cws_lr_date) || empty($cws_reason) || $cws_susp_days <= 0) {
                sendResponse('error', 'Please fill all required fields correctly for Contract Work Suspension.');
            }

            // Check for unique code within the same project
            $checkCodeQuery = "SELECT COUNT(*) as count FROM `contract-work-suspension` WHERE `cws_code` = ? AND `proj_ID` = ?";
            $checkCodeStmt = $db->prepare($checkCodeQuery);
            if (!$checkCodeStmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }
            $checkCodeStmt->bind_param("ss", $cws_code, $proj_ID);
            $checkCodeStmt->execute();
            $checkCodeResult = $checkCodeStmt->get_result();
            $checkCodeRow = $checkCodeResult->fetch_assoc();
            $checkCodeStmt->close();

            if ($checkCodeRow['count'] > 0) {
                sendResponse('error', 'The code provided for Contract Work Suspension already exists for this project. Please use a unique code.');
            }

            // Handle the uploaded file
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                sendResponse('error', 'Error uploading the attachment.');
            }
        
            $file = $_FILES['attachment'];
        
            // Validate file type (only PDF)
            $allowedMimeTypes = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                sendResponse('error', 'Invalid file type. Only PDF files are allowed.');
            }
        
            // Optional: Validate file size (e.g., max 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxFileSize) {
                sendResponse('error', 'File size exceeds the maximum allowed limit of 5MB.');
            }
        
            // Generate a unique file name to prevent overwriting
            $originalFileName = basename($file['name']);
            $fileName = uniqid() . '_' . $originalFileName;
            
            // Define absolute paths
            $uploadDir = __DIR__ . '/../uploads/attachments/'; // Absolute filesystem path
            $webUploadDir = '../uploads/attachments/'; // Relative web path
            $uploadPath = $webUploadDir . $fileName;
            $fullUploadPath = $uploadDir . $fileName;
        
            // Ensure the upload directory exists and is writable
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    sendResponse('error', 'Failed to create upload directory.');
                }
            }
        
            if (!is_writable($uploadDir)) {
                error_log("Upload directory is not writable: " . $uploadDir);
                sendResponse('error', 'Upload directory is not writable.');
            }
        
            // Move the uploaded file to the designated directory
            if (!move_uploaded_file($file['tmp_name'], $fullUploadPath)) {
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $fullUploadPath);
                sendResponse('error', 'Failed to move the uploaded file.');
            }

            // Add these new variables to your INSERT query
            $query = "INSERT INTO `contract-work-suspension` (cws_code, proj_ID, cws_lr_date, cws_reason, cws_susp_days, cws_attachment, cws_ext_days, cws_expiry_date)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error); 
            }
            
            // Bind parameters including new ones
            $stmt->bind_param("ssssisis", $cws_code, $proj_ID, $cws_lr_date, $cws_reason, $cws_susp_days, $uploadPath, $cws_ext_days, $cws_expiry_date);

              // Execute and handle the response
            if ($stmt->execute()) {
                // ✅ Fetch the current expiry date
                $expiryQuery = "SELECT proj_expiry_date FROM projects WHERE proj_ID = ?";
                $expiryStmt = $db->prepare($expiryQuery);
                $expiryStmt->bind_param("s", $proj_ID);
                $expiryStmt->execute();
                $expiryResult = $expiryStmt->get_result();
                $expiryRow = $expiryResult->fetch_assoc();
                $expiryStmt->close();
        
                if ($expiryRow && $expiryRow['proj_expiry_date']) {
                    $proj_expiry_date = $expiryRow['proj_expiry_date'];
                    $expiryDate = new DateTime($proj_expiry_date);
                    $expiryDate->modify("+{$cws_susp_days} days");
                    $new_expiry_date = $expiryDate->format('Y-m-d');
        
                    // ✅ Update the project's expiry date
                    $updateExpiryQuery = "UPDATE `projects` SET `proj_expiry_date` = ? WHERE `proj_ID` = ?";
                    $updateStmt = $db->prepare($updateExpiryQuery);
                    if (!$updateStmt) {
                        sendResponse('error', 'Database error: ' . $db->error);
                    }
                    $updateStmt->bind_param("ss", $new_expiry_date, $proj_ID);
        
                    if ($updateStmt->execute()) {
                        sendResponse('success', 'Contract Work Suspension added and project expiry date updated successfully.');
                    } else {
                        error_log("Error updating project expiry date: " . $updateStmt->error);
                        sendResponse('error', 'Contract Work Suspension added, but failed to update project expiry date.');
                    }
                    $updateStmt->close();
                } else {
                    sendResponse('error', 'Project expiry date not found for update.');
                }
            } else {
                error_log("Error adding Work Suspension: " . $stmt->error);
                sendResponse('error', 'Failed to add Work Resumption. Please try again.');
            }
        
            $stmt->close();
            break;


        case 'cwr':
            // Contract Work Resumption
            $cwr_code = trim($_POST['cwr_code'] ?? '');
            $cwr_lr_date = $_POST['cwr_lr_date'] ?? '';
            $cwr_reason = trim($_POST['cwr_reason'] ?? '');
            $cwr_susp_days = intval($_POST['cwr_susp_days'] ?? 0);

            // Validate required fields
            if (empty($cwr_code) || empty($cwr_reason) || $cwr_susp_days <= 0) {
                sendResponse('error', 'Please fill all required fields correctly for Contract Work Resumption.');
            }

            // Check for unique code within the same project
            $checkCodeQuery = "SELECT COUNT(*) as count FROM `contract-work-resumption` WHERE `cwr_code` = ? AND `proj_ID` = ?";
            $checkCodeStmt = $db->prepare($checkCodeQuery);
            if (!$checkCodeStmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }
            $checkCodeStmt->bind_param("ss", $cwr_code, $proj_ID);
            $checkCodeStmt->execute();
            $checkCodeResult = $checkCodeStmt->get_result();
            $checkCodeRow = $checkCodeResult->fetch_assoc();
            $checkCodeStmt->close();

            if ($checkCodeRow['count'] > 0) {
                sendResponse('error', 'The code provided for Contract Work Resumption already exists for this project. Please use a unique code.');
            }

            // Handle the uploaded file
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                sendResponse('error', 'Error uploading the attachment.');
            }
        
            $file = $_FILES['attachment'];
        
            // Validate file type (only PDF)
            $allowedMimeTypes = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                sendResponse('error', 'Invalid file type. Only PDF files are allowed.');
            }
        
            // Optional: Validate file size (e.g., max 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxFileSize) {
                sendResponse('error', 'File size exceeds the maximum allowed limit of 5MB.');
            }
        
            // Generate a unique file name to prevent overwriting
            $originalFileName = basename($file['name']);
            $fileName = uniqid() . '_' . $originalFileName;
            
            // Define absolute paths
            $uploadDir = __DIR__ . '/../uploads/attachments/'; // Absolute filesystem path
            $webUploadDir = '../uploads/attachments/'; // Relative web path
            $uploadPath = $webUploadDir . $fileName;
            $fullUploadPath = $uploadDir . $fileName;
        
            // Ensure the upload directory exists and is writable
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    sendResponse('error', 'Failed to create upload directory.');
                }
            }
        
            if (!is_writable($uploadDir)) {
                error_log("Upload directory is not writable: " . $uploadDir);
                sendResponse('error', 'Upload directory is not writable.');
            }
        
            // Move the uploaded file to the designated directory
            if (!move_uploaded_file($file['tmp_name'], $fullUploadPath)) {
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $fullUploadPath);
                sendResponse('error', 'Failed to move the uploaded file.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `contract-work-resumption` (cwr_code, proj_ID, cwr_lr_date, cwr_reason, cwr_susp_days, cwr_attachment)
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param("ssssis", $cwr_code, $proj_ID, $cwr_lr_date, $cwr_reason, $cwr_susp_days, $uploadPath);

            // Execute and handle the response
            if ($stmt->execute()) {
                // ✅ Fetch the current expiry date
                $expiryQuery = "SELECT proj_expiry_date FROM projects WHERE proj_ID = ?";
                $expiryStmt = $db->prepare($expiryQuery);
                $expiryStmt->bind_param("s", $proj_ID);
                $expiryStmt->execute();
                $expiryResult = $expiryStmt->get_result();
                $expiryRow = $expiryResult->fetch_assoc();
                $expiryStmt->close();
        
                if ($expiryRow && $expiryRow['proj_expiry_date']) {
                    $proj_expiry_date = $expiryRow['proj_expiry_date'];
                    $expiryDate = new DateTime($proj_expiry_date);
                    $expiryDate->modify("+{$cwr_susp_days} days");
                    $new_expiry_date = $expiryDate->format('Y-m-d');
        
                    // ✅ Update the project's expiry date
                    $updateExpiryQuery = "UPDATE `projects` SET `proj_expiry_date` = ? WHERE `proj_ID` = ?";
                    $updateStmt = $db->prepare($updateExpiryQuery);
                    if (!$updateStmt) {
                        sendResponse('error', 'Database error: ' . $db->error);
                    }
                    $updateStmt->bind_param("ss", $new_expiry_date, $proj_ID);
        
                    if ($updateStmt->execute()) {
                        sendResponse('success', 'Contract Work Resumption added and project expiry date updated successfully.');
                    } else {
                        error_log("Error updating project expiry date: " . $updateStmt->error);
                        sendResponse('error', 'Contract Work Resumption added, but failed to update project expiry date.');
                    }
                    $updateStmt->close();
                } else {
                    sendResponse('error', 'Project expiry date not found for update.');
                }
            } else {
                error_log("Error adding Work Resumption: " . $stmt->error);
                sendResponse('error', 'Failed to add Work Resumption. Please try again.');
            }
        
            $stmt->close();
            break;


case 'cte':
    // Contract Time Extension
    $cte_code = trim($_POST['cte_code'] ?? '');
    $cte_lr_date = $_POST['cte_lr_date'] ?? '';
    $cte_reason = trim($_POST['cte_reason'] ?? '');
    $cte_ext_days = intval($_POST['cte_ext_days'] ?? 0);

    // Validate required fields
    if (empty($cte_code) || empty($cte_reason) || $cte_ext_days <= 0) {
        sendResponse('error', 'Please fill all required fields correctly for Contract Time Extension.');
    }

    // Check for unique code within the same project
    $checkCodeQuery = "SELECT COUNT(*) as count FROM `contract-time-extension` WHERE `cte_code` = ? AND `proj_ID` = ?";
    $checkCodeStmt = $db->prepare($checkCodeQuery);
    if (!$checkCodeStmt) {
        sendResponse('error', 'Database error: ' . $db->error);
    }
    $checkCodeStmt->bind_param("ss", $cte_code, $proj_ID);
    $checkCodeStmt->execute();
    $checkCodeResult = $checkCodeStmt->get_result();
    $checkCodeRow = $checkCodeResult->fetch_assoc();
    $checkCodeStmt->close();

    if ($checkCodeRow['count'] > 0) {
        sendResponse('error', 'The code provided for Contract Time Extension already exists for this project. Please use a unique code.');
    }

    // Handle the uploaded file
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        sendResponse('error', 'Error uploading the attachment.');
    }

    $file = $_FILES['attachment'];
    $allowedMimeTypes = ['application/pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedMimeTypes)) {
        sendResponse('error', 'Invalid file type. Only PDF files are allowed.');
    }

    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxFileSize) {
        sendResponse('error', 'File size exceeds the maximum allowed limit of 5MB.');
    }

    $originalFileName = basename($file['name']);
    $fileName = uniqid() . '_' . $originalFileName;
    $uploadDir = __DIR__ . '/../uploads/attachments/';
    $webUploadDir = '../uploads/attachments/';
    $uploadPath = $webUploadDir . $fileName;
    $fullUploadPath = $uploadDir . $fileName;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        error_log("Failed to create upload directory: " . $uploadDir);
        sendResponse('error', 'Failed to create upload directory.');
    }

    if (!is_writable($uploadDir)) {
        error_log("Upload directory is not writable: " . $uploadDir);
        sendResponse('error', 'Upload directory is not writable.');
    }

    if (!move_uploaded_file($file['tmp_name'], $fullUploadPath)) {
        error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $fullUploadPath);
        sendResponse('error', 'Failed to move the uploaded file.');
    }

    // Prepare the INSERT statement
    $query = "INSERT INTO `contract-time-extension` (cte_code, proj_ID, cte_lr_date, cte_reason, cte_ext_days, cte_attachment)
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    if (!$stmt) {
        sendResponse('error', 'Database error: ' . $db->error);
    }
    $stmt->bind_param("ssssis", $cte_code, $proj_ID, $cte_lr_date, $cte_reason, $cte_ext_days, $uploadPath);

    // Execute and handle the response
    if ($stmt->execute()) {
        // ✅ Fetch the current expiry date
        $expiryQuery = "SELECT proj_expiry_date FROM projects WHERE proj_ID = ?";
        $expiryStmt = $db->prepare($expiryQuery);
        $expiryStmt->bind_param("s", $proj_ID);
        $expiryStmt->execute();
        $expiryResult = $expiryStmt->get_result();
        $expiryRow = $expiryResult->fetch_assoc();
        $expiryStmt->close();

        if ($expiryRow && $expiryRow['proj_expiry_date']) {
            $proj_expiry_date = $expiryRow['proj_expiry_date'];
            $expiryDate = new DateTime($proj_expiry_date);
            $expiryDate->modify("+{$cte_ext_days} days");
            $new_expiry_date = $expiryDate->format('Y-m-d');

            // ✅ Update the project's expiry date
            $updateExpiryQuery = "UPDATE `projects` SET `proj_expiry_date` = ? WHERE `proj_ID` = ?";
            $updateStmt = $db->prepare($updateExpiryQuery);
            if (!$updateStmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }
            $updateStmt->bind_param("ss", $new_expiry_date, $proj_ID);

            if ($updateStmt->execute()) {
                sendResponse('success', 'Contract Time Extension added and project expiry date updated successfully.');
            } else {
                error_log("Error updating project expiry date: " . $updateStmt->error);
                sendResponse('error', 'Contract Time Extension added, but failed to update project expiry date.');
            }
            $updateStmt->close();
        } else {
            sendResponse('error', 'Project expiry date not found for update.');
        }
    } else {
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

            // Validate required fields
            if (empty($mtsr_code) || empty($mtsr_reason) || $mtsr_susp_days <= 0) {
                sendResponse('error', 'Please fill all required fields correctly for Monthly Time Suspension Report.');
            }

            // Check for unique code within the same project
            $checkCodeQuery = "SELECT COUNT(*) as count FROM `monthly-time-suspension-report` WHERE `mtsr_code` = ? AND `proj_ID` = ?";
            $checkCodeStmt = $db->prepare($checkCodeQuery);
            if (!$checkCodeStmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }
            $checkCodeStmt->bind_param("ss", $mtsr_code, $proj_ID);
            $checkCodeStmt->execute();
            $checkCodeResult = $checkCodeStmt->get_result();
            $checkCodeRow = $checkCodeResult->fetch_assoc();
            $checkCodeStmt->close();

            if ($checkCodeRow['count'] > 0) {
                sendResponse('error', 'The code provided for Monthly Time Suspension Report already exists for this project. Please use a unique code.');
            }

            // Handle the uploaded file
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                sendResponse('error', 'Error uploading the attachment.');
            }
        
            $file = $_FILES['attachment'];
        
            // Validate file type (only PDF)
            $allowedMimeTypes = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                sendResponse('error', 'Invalid file type. Only PDF files are allowed.');
            }
        
            // Optional: Validate file size (e.g., max 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxFileSize) {
                sendResponse('error', 'File size exceeds the maximum allowed limit of 5MB.');
            }
        
            // Generate a unique file name to prevent overwriting
            $originalFileName = basename($file['name']);
            $fileName = uniqid() . '_' . $originalFileName;
            
            // Define absolute paths
            $uploadDir = __DIR__ . '/../uploads/attachments/'; // Absolute filesystem path
            $webUploadDir = '../uploads/attachments/'; // Relative web path
            $uploadPath = $webUploadDir . $fileName;
            $fullUploadPath = $uploadDir . $fileName;
        
            // Ensure the upload directory exists and is writable
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    sendResponse('error', 'Failed to create upload directory.');
                }
            }
        
            if (!is_writable($uploadDir)) {
                error_log("Upload directory is not writable: " . $uploadDir);
                sendResponse('error', 'Upload directory is not writable.');
            }
        
            // Move the uploaded file to the designated directory
            if (!move_uploaded_file($file['tmp_name'], $fullUploadPath)) {
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $fullUploadPath);
                sendResponse('error', 'Failed to move the uploaded file.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `monthly-time-suspension-report` (mtsr_code, proj_ID, mtsr_lr_date, mtsr_reason, mtsr_susp_days, mtsr_attachment)
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param("ssssis", $mtsr_code, $proj_ID, $mtsr_lr_date, $mtsr_reason, $mtsr_susp_days, $uploadPath);

              // Execute and handle the response
            if ($stmt->execute()) {
                // ✅ Fetch the current expiry date
                $expiryQuery = "SELECT proj_expiry_date FROM projects WHERE proj_ID = ?";
                $expiryStmt = $db->prepare($expiryQuery);
                $expiryStmt->bind_param("s", $proj_ID);
                $expiryStmt->execute();
                $expiryResult = $expiryStmt->get_result();
                $expiryRow = $expiryResult->fetch_assoc();
                $expiryStmt->close();
        
                if ($expiryRow && $expiryRow['proj_expiry_date']) {
                    $proj_expiry_date = $expiryRow['proj_expiry_date'];
                    $expiryDate = new DateTime($proj_expiry_date);
                    $expiryDate->modify("+{$mtsr_susp_days} days");
                    $new_expiry_date = $expiryDate->format('Y-m-d');
        
                    // ✅ Update the project's expiry date
                    $updateExpiryQuery = "UPDATE `projects` SET `proj_expiry_date` = ? WHERE `proj_ID` = ?";
                    $updateStmt = $db->prepare($updateExpiryQuery);
                    if (!$updateStmt) {
                        sendResponse('error', 'Database error: ' . $db->error);
                    }
                    $updateStmt->bind_param("ss", $new_expiry_date, $proj_ID);
        
                    if ($updateStmt->execute()) {
                        sendResponse('success', 'Monthly Time Suspension Report added and project expiry date updated successfully.');
                    } else {
                        error_log("Error updating project expiry date: " . $updateStmt->error);
                        sendResponse('error', 'Monthly Time Suspension Report added, but failed to update project expiry date.');
                    }
                    $updateStmt->close();
                } else {
                    sendResponse('error', 'Project expiry date not found for update.');
                }
            } else {
                error_log("Error adding Monthly Time Suspension Report: " . $stmt->error);
                sendResponse('error', 'Failed to add Monthly Time Suspension Report. Please try again.');
            }
        
            $stmt->close();
            break;

        case 'vo':
            // Variation Order
            $vo_code = trim($_POST['vo_code'] ?? '');
            $vo_date = $_POST['vo_date'] ?? '';
            $vo_add_amt = floatval($_POST['vo_add_amt'] ?? 0);
            $vo_revised_cost = floatval($_POST['vo_revised_cost'] ?? 0);
            $vo_ext_days = intval($_POST['vo_ext_days'] ?? 0);
            $vo_expiry_date = $_POST['vo_expiry_date'] ?? '';
            $vo_reason = trim($_POST['vo_reason'] ?? '');

            // Validate required fields
            if (empty($vo_code) || empty($vo_date) || empty($vo_reason) || $vo_add_amt <= 0 || $vo_revised_cost <= 0 || $vo_ext_days < 0 || empty($vo_expiry_date)) {
                sendResponse('error', 'Please fill all required fields correctly for Variation Order.');
            }

            // Check for unique code within the same project
            $checkCodeQuery = "SELECT COUNT(*) as count FROM `variation-orders` WHERE `vo_code` = ? AND `proj_ID` = ?";
            $checkCodeStmt = $db->prepare($checkCodeQuery);
            if (!$checkCodeStmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }
            $checkCodeStmt->bind_param("ss", $vo_code, $proj_ID);
            $checkCodeStmt->execute();
            $checkCodeResult = $checkCodeStmt->get_result();
            $checkCodeRow = $checkCodeResult->fetch_assoc();
            $checkCodeStmt->close();

            if ($checkCodeRow['count'] > 0) {
                sendResponse('error', 'The code provided for Variation Order already exists for this project. Please use a unique code.');
            }

            // Handle the uploaded file
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                sendResponse('error', 'Error uploading the attachment.');
            }
        
            $file = $_FILES['attachment'];
        
            // Validate file type (only PDF)
            $allowedMimeTypes = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                sendResponse('error', 'Invalid file type. Only PDF files are allowed.');
            }
        
            // Optional: Validate file size (e.g., max 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxFileSize) {
                sendResponse('error', 'File size exceeds the maximum allowed limit of 5MB.');
            }
        
            // Generate a unique file name to prevent overwriting
            $originalFileName = basename($file['name']);
            $fileName = uniqid() . '_' . $originalFileName;
            
            // Define absolute paths
            $uploadDir = __DIR__ . '/../uploads/attachments/'; // Absolute filesystem path
            $webUploadDir = '../uploads/attachments/'; // Relative web path
            $uploadPath = $webUploadDir . $fileName;
            $fullUploadPath = $uploadDir . $fileName;
        
            // Ensure the upload directory exists and is writable
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    sendResponse('error', 'Failed to create upload directory.');
                }
            }
        
            if (!is_writable($uploadDir)) {
                error_log("Upload directory is not writable: " . $uploadDir);
                sendResponse('error', 'Upload directory is not writable.');
            }
        
            // Move the uploaded file to the designated directory
            if (!move_uploaded_file($file['tmp_name'], $fullUploadPath)) {
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $fullUploadPath);
                sendResponse('error', 'Failed to move the uploaded file.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `variation-orders` (
                        vo_code, 
                        proj_ID, 
                        vo_date, 
                        vo_add_amt, 
                        vo_revised_cost, 
                        vo_ext_days, 
                        vo_expiry_date, 
                        vo_reason, 
                        vo_attachment
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }

            // Bind parameters
            $stmt->bind_param(
                "sssdiisss",
                $vo_code,
                $proj_ID,
                $vo_date,
                $vo_add_amt,
                $vo_revised_cost,
                $vo_ext_days,
                $vo_expiry_date,
                $vo_reason,
                $uploadPath
            );

              // Execute and handle the response
            if ($stmt->execute()) {
                // ✅ Fetch the current expiry date
                $expiryQuery = "SELECT proj_expiry_date FROM projects WHERE proj_ID = ?";
                $expiryStmt = $db->prepare($expiryQuery);
                $expiryStmt->bind_param("s", $proj_ID);
                $expiryStmt->execute();
                $expiryResult = $expiryStmt->get_result();
                $expiryRow = $expiryResult->fetch_assoc();
                $expiryStmt->close();
        
                if ($expiryRow && $expiryRow['proj_expiry_date']) {
                    $proj_expiry_date = $expiryRow['proj_expiry_date'];
                    $expiryDate = new DateTime($proj_expiry_date);
                    $expiryDate->modify("+{$vo_ext_days} days");
                    $new_expiry_date = $expiryDate->format('Y-m-d');
        
                    // ✅ Update the project's expiry date
                    $updateExpiryQuery = "UPDATE `projects` SET `proj_expiry_date` = ? WHERE `proj_ID` = ?";
                    $updateStmt = $db->prepare($updateExpiryQuery);
                    if (!$updateStmt) {
                        sendResponse('error', 'Database error: ' . $db->error);
                    }
                    $updateStmt->bind_param("ss", $new_expiry_date, $proj_ID);
        
                    if ($updateStmt->execute()) {
                        sendResponse('success', 'Variation Order added and project expiry date updated successfully.');
                    } else {
                        error_log("Error updating project expiry date: " . $updateStmt->error);
                        sendResponse('error', 'Variation Order added, but failed to update project expiry date.');
                    }
                    $updateStmt->close();
                } else {
                    sendResponse('error', 'Project expiry date not found for update.');
                }
            } else {
                error_log("Error adding Variation Order: " . $stmt->error);
                sendResponse('error', 'Failed to add Variation Order. Please try again.');
            }
        
            $stmt->close();
            break;

        case 'fc':
            // Final Completion does not require a Code textbox as per the instructions
            $fc_approved_date = $_POST['fc_approved_date'] ?? '';
            $fc_type = $_POST['fc_type'] ?? '';

            // Validate required fields
            if (empty($fc_type) || empty($fc_approved_date)) {
                sendResponse('error', 'Please fill all required fields correctly for Final Completion.');
            }

            // Handle the uploaded file
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                sendResponse('error', 'Error uploading the attachment.');
            }
        
            $file = $_FILES['attachment'];
        
            // Validate file type (only PDF)
            $allowedMimeTypes = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                sendResponse('error', 'Invalid file type. Only PDF files are allowed.');
            }
        
            // Optional: Validate file size (e.g., max 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxFileSize) {
                sendResponse('error', 'File size exceeds the maximum allowed limit of 5MB.');
            }
        
            // Generate a unique file name to prevent overwriting
            $originalFileName = basename($file['name']);
            $fileName = uniqid() . '_' . $originalFileName;
            
            // Define absolute paths
            $uploadDir = __DIR__ . '/../uploads/attachments/'; // Absolute filesystem path
            $webUploadDir = '../uploads/attachments/'; // Relative web path
            $uploadPath = $webUploadDir . $fileName;
            $fullUploadPath = $uploadDir . $fileName;
        
            // Ensure the upload directory exists and is writable
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    sendResponse('error', 'Failed to create upload directory.');
                }
            }
        
            if (!is_writable($uploadDir)) {
                error_log("Upload directory is not writable: " . $uploadDir);
                sendResponse('error', 'Upload directory is not writable.');
            }
        
            // Move the uploaded file to the designated directory
            if (!move_uploaded_file($file['tmp_name'], $fullUploadPath)) {
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $fullUploadPath);
                sendResponse('error', 'Failed to move the uploaded file.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `final-completion` (
                        proj_ID, 
                        fc_type, 
                        fc_approved_date, 
                        fc_attachment
                      ) VALUES (?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }
        
            // Bind parameters
            $stmt->bind_param("ssss", $proj_ID, $fc_type, $fc_approved_date, $uploadPath);
        
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

            case 'od':
            // Final Completion does not require a Code textbox as per the instructions
            $od_title_name = $_POST['od_title_name'] ?? '';
            $od_attachment_type = $_POST['od_attachment_type'] ?? '';

            // Validate required fields
            if (empty($od_title_name) || empty($od_attachment_type)) {
                sendResponse('error', 'Please fill all required fields correctly for new document.');
            }

            // Handle the uploaded file
            if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                sendResponse('error', 'Error uploading the attachment.');
            }
        
            $file = $_FILES['attachment'];
        
            // Validate file type (only PDF)
            $allowedMimeTypes = ['application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimeTypes)) {
                sendResponse('error', 'Invalid file type. Only PDF files are allowed.');
            }
        
            // Optional: Validate file size (e.g., max 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxFileSize) {
                sendResponse('error', 'File size exceeds the maximum allowed limit of 5MB.');
            }
        
            // Generate a unique file name to prevent overwriting
            $originalFileName = basename($file['name']);
            $fileName = uniqid() . '_' . $originalFileName;
            
            // Define absolute paths
            $uploadDir = __DIR__ . '/../uploads/attachments/'; // Absolute filesystem path
            $webUploadDir = '../uploads/attachments/'; // Relative web path
            $uploadPath = $webUploadDir . $fileName;
            $fullUploadPath = $uploadDir . $fileName;
        
            // Ensure the upload directory exists and is writable
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: " . $uploadDir);
                    sendResponse('error', 'Failed to create upload directory.');
                }
            }
        
            if (!is_writable($uploadDir)) {
                error_log("Upload directory is not writable: " . $uploadDir);
                sendResponse('error', 'Upload directory is not writable.');
            }
        
            // Move the uploaded file to the designated directory
            if (!move_uploaded_file($file['tmp_name'], $fullUploadPath)) {
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $fullUploadPath);
                sendResponse('error', 'Failed to move the uploaded file.');
            }

            // Prepare the INSERT statement
            $query = "INSERT INTO `other-documents` (
                        proj_ID, 
                        od_title_name, 
                        od_attachment_type, 
                        od_attachment
                      ) VALUES (?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            if (!$stmt) {
                sendResponse('error', 'Database error: ' . $db->error);
            }
        
            // Bind parameters
            $stmt->bind_param("ssss", $proj_ID, $od_title_name, $od_attachment_type, $uploadPath);
        
            // Execute and handle the response
            if ($stmt->execute()) {
                sendResponse('success', "New Document added successfully.");
            } else {
                // Log the error for debugging
                error_log("Error adding Other Documents: " . $stmt->error);
                sendResponse('error', "Failed to add Other Documents. Please try again.");
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
