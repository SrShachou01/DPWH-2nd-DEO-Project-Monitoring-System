<?php
include '../includes/database.php';
session_start();
// Temporarily enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Keep logging errors
ini_set('log_errors', 1);
ini_set('error_log', '../pages/erroradd.txt');

$db = ConnectDB();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Fetch contractors for the dropdown
$contractors_query = "SELECT cont_ID, cont_name FROM contractors WHERE cont_isDeleted = 0";
$contractors_result = $db->query($contractors_query);
if (!$contractors_result) {
    die('Contractors query failed: ' . htmlspecialchars($db->error));
}
$contractors = $contractors_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Allowed statuses
    $allowedStatuses = ['Ongoing', 'Not Yet Started', 'Completed', 'Suspended'];

    // Gather form data with validation and sanitization
    $proj_ID = trim($_POST['proj_ID']);
    $proj_cont_name = trim($_POST['proj_cont_name']);
    $proj_comp_ID = trim($_POST['proj_comp_ID']);
    $proj_cont_loc = trim($_POST['proj_cont_loc']);
    $proj_cont_amt = floatval($_POST['proj_cont_amt']);
    $proj_effect_date = $_POST['proj_effect_date'];
    $proj_cont_duration = intval($_POST['proj_cont_duration']);
    // Unworkable days is set to zero by default
    $proj_unwork_days = 0;
    $proj_NOA = $_POST['proj_NOA'] ?? null;
    $proj_NOP = $_POST['proj_NOP'] ?? null;
    $proj_status = trim($_POST['proj_status']);
    $user_id = $_SESSION['user_id'];

    // Get the array of contractor IDs from the form
    $proj_contractor_ids = $_POST['proj_contractors'] ?? [];

    // Validate that at least one contractor is selected
    if (empty($proj_contractor_ids)) {
        $_SESSION['error_message'] = 'Please select at least one Contractor.';
        header("Location: ../pages/add-project.php"); // Update with your actual add project page
        exit();
    }

    // Validate each contractor ID to ensure it exists and is not deleted
    $placeholders = implode(',', array_fill(0, count($proj_contractor_ids), '?'));
    $types = str_repeat('i', count($proj_contractor_ids)); // Assuming cont_ID is integer
    $stmt_validate = $db->prepare("SELECT cont_ID FROM contractors WHERE cont_ID IN ($placeholders) AND cont_isDeleted = 0");
    if ($stmt_validate === false) {
        die('Prepare failed: ' . htmlspecialchars($db->error));
    }
    $stmt_validate->bind_param($types, ...$proj_contractor_ids);
    $stmt_validate->execute();
    $result_validate = $stmt_validate->get_result();
    if ($result_validate->num_rows !== count($proj_contractor_ids)) {
        $_SESSION['error_message'] = 'One or more selected Contractors are invalid.';
        header("Location: ../pages/add-project.php"); // Update with your actual add project page
        exit();
    }

    // Set default status if none is provided or invalid status is provided
    if (empty($proj_status) || !in_array($proj_status, $allowedStatuses)) {
        $proj_status = 'Not Yet Started';
    }

    // Calculate total_duration without unworkable days
    $total_duration = $proj_cont_duration;

    // Calculate Expiry Date: Effectivity Date + total_duration
    $effectivity_timestamp = strtotime($proj_effect_date);
    if ($effectivity_timestamp === false) {
        die('Invalid Effectivity Date.');
    }
    $expiry_timestamp = strtotime("+$total_duration days", $effectivity_timestamp);
    if ($expiry_timestamp === false) {
        die('Error calculating Expiry Date.');
    }
    $proj_expiry_date = date('Y-m-d', $expiry_timestamp);

    // Collect Contractors Manpower data (Expiry dates removed)
    $cm_am_officer = trim($_POST['cm_am_officer'] ?? '');
    $cm_pm_name = trim($_POST['cm_pm_name'] ?? '');
    $cm_pm_prc_me_id = trim($_POST['cm_pm_prc_me_id'] ?? '');
    $cm_pe_name = trim($_POST['cm_pe_name'] ?? '');
    $cm_pe_prc_me_id = trim($_POST['cm_pe_prc_me_id'] ?? '');
    $cm_me_name = trim($_POST['cm_me_name'] ?? '');
    $cm_me_prc_me_id = trim($_POST['cm_me_prc_me_id'] ?? '');
    $cm_const_foreman = trim($_POST['cm_const_foreman'] ?? '');
    $cm_csh_officer = trim($_POST['cm_csh_officer'] ?? '');

    // Collect Implementing Office Manpower data (Expiry dates removed)
    $iom_pe_name = trim($_POST['iom_pe_name'] ?? '');
    $iom_pe_prc_me_ID = trim($_POST['iom_pe_prc_me_ID'] ?? '');
    $iom_pi_name = trim($_POST['iom_pi_name'] ?? '');
    $iom_pi_prc_me_ID = trim($_POST['iom_pi_prc_me_ID'] ?? '');
    $iom_me_name = trim($_POST['iom_me_name'] ?? '');
    $iom_me_prc_me_ID = trim($_POST['iom_me_prc_me_ID'] ?? '');
    $iom_mic_name = trim($_POST['iom_mic_name'] ?? '');
    $iom_mic_prc_me_ID = trim($_POST['iom_mic_prc_me_ID'] ?? '');
    $iom_pi_pcma_name = trim($_POST['iom_pi_pcma_name'] ?? '');
    $iom_pi_pcma_prc_me_ID = trim($_POST['iom_pi_pcma_prc_me_ID'] ?? '');

    // Begin transaction
    $db->begin_transaction();

    try {
        // Prepare SQL statement to insert project
        $query1 = "INSERT INTO projects (proj_ID, proj_cont_name, proj_comp_ID, proj_cont_loc, proj_cont_amt, proj_cont_duration, proj_unwork_days, proj_NOA, proj_NOP, proj_effect_date, proj_expiry_date, proj_status, user_ID, proj_progress) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
        $stmt1 = $db->prepare($query1);
        if ($stmt1 === false) {
            throw new Exception('Prepare failed: ' . htmlspecialchars($db->error));
        }
        $stmt1->bind_param("ssssdiisssssi", $proj_ID, $proj_cont_name, $proj_comp_ID, $proj_cont_loc, $proj_cont_amt, $proj_cont_duration, $proj_unwork_days, $proj_NOA, $proj_NOP, $proj_effect_date, $proj_expiry_date, $proj_status, $user_id);
        $result = $stmt1->execute();

        if (!$result) {
            throw new Exception('Execute failed: ' . htmlspecialchars($stmt1->error));
        }

        // Insert into 'project_contractors' table
        $query_pc = "INSERT INTO project_contractors (proj_ID, cont_ID) VALUES (?, ?)";
        $stmt_pc = $db->prepare($query_pc);
        if (!$stmt_pc) {
            throw new Exception('Prepare failed: ' . htmlspecialchars($db->error));
        }

        foreach ($proj_contractor_ids as $cont_ID) {
            $stmt_pc->bind_param("si", $proj_ID, $cont_ID);
            $execute_pc = $stmt_pc->execute();
            if (!$execute_pc) {
                throw new Exception('Execute failed: ' . htmlspecialchars($stmt_pc->error));
            }
        }

        // Insert into 'contract-manpower' table without expiry dates
        $query_cm = "INSERT INTO `contract-manpower` (proj_ID, cm_am_officer, cm_pm_name, cm_pm_prc_me_id,
            cm_pe_name, cm_pe_prc_me_id, cm_me_name, cm_me_prc_me_id,
            cm_const_foreman, cm_csh_officer
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_cm = $db->prepare($query_cm);
        if (!$stmt_cm) {
            throw new Exception('Prepare failed: ' . htmlspecialchars($db->error));
        }
        $stmt_cm->bind_param(
            "ssssssssss",
            $proj_ID, $cm_am_officer, $cm_pm_name, $cm_pm_prc_me_id,
            $cm_pe_name, $cm_pe_prc_me_id, $cm_me_name, $cm_me_prc_me_id,
            $cm_const_foreman, $cm_csh_officer
        );
        $result_cm = $stmt_cm->execute();

        if (!$result_cm) {
            throw new Exception('Execute failed: ' . htmlspecialchars($stmt_cm->error));
        }

        // Insert into 'implementing-office-manpower' table without expiry dates
        $query_iom = "INSERT INTO `implementing-office-manpower` (
            proj_ID, iom_pe_name, iom_pe_prc_me_ID, iom_pi_name, iom_pi_prc_me_ID,
            iom_me_name, iom_me_prc_me_ID, iom_mic_name, iom_mic_prc_me_ID, iom_pi_pcma_name, iom_pi_pcma_prc_me_ID
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_iom = $db->prepare($query_iom);
        if (!$stmt_iom) {
            throw new Exception('Prepare failed: ' . htmlspecialchars($db->error));
        }
        $stmt_iom->bind_param(
            "sssssssssss",
            $proj_ID, $iom_pe_name, $iom_pe_prc_me_ID,
            $iom_pi_name, $iom_pi_prc_me_ID,
            $iom_me_name, $iom_me_prc_me_ID, $iom_mic_name, $iom_mic_prc_me_ID, $iom_pi_pcma_name, $iom_pi_pcma_prc_me_ID
        );
        $result_iom = $stmt_iom->execute();

        if (!$result_iom) {
            throw new Exception('Execute failed: ' . htmlspecialchars($stmt_iom->error));
        }

        // Commit transaction
        $db->commit();

        $_SESSION['success_message'] = 'Project created successfully.';
        header("Location: ../pages/projects.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        $_SESSION['error_message'] = 'Failed to save the project: ' . $e->getMessage();
        header("Location: ../pages/projects.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Project</title>
    <!-- Include Bootstrap CSS and Font Awesome -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Include Select2 CSS (Optional) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <!-- Include custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        /* Existing CSS styles */
        #content {
            min-height: 100%;
            background-color: #E4E5DF;
        }
        .form-group {
            margin-bottom: 10px;
        }
        .form-container {
            padding: 10px;
        }
        .project-details-section,
        .manpower-section {
            border: 2px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #1a476f;
            color: white;
        }
        label {
            font-size: 14px;
        }
        .form-control-sm {
            font-size: 14px;
            padding: 5px;
            height: auto;
        }
        /* Custom Styles for Manpower Sections */
        .manpower-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .manpower-table th, .manpower-table td {
            padding: 8px;
            text-align: left;
            color: white;
        }
        .manpower-table th {
            background-color: #1a4a88;
        }
        .form-control {
            width: 100%;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .required-field {
            color: red;
        }
        /* Sidebar Buttons */
        .sidebar-buttons {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        /* Sidebar Navigation Buttons */
        .sidebar-navigation {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        .sidebar-navigation button {
            flex: 1;
        }
        /* Custom CSS for Edit Profile Button */
        .edit-profile-button {
            text-align: center;
        }
        .btn-warning:disabled
        {
            background-color: grey;
            border: 1px solid darkgray; /* Optional: You can match the border to the background color */
            color: white;
        }
        .btn-warning
        {
            background-color: grey;
            border-color: grey;
            color: white;
        }

        .btn-primary
        {
            background-color: #E67040;
            border-color: #E67040;
            color: white;
        }

        .btn-primary:hover
        {
            background-color: orangered;
            border-color: orangered;
        }

        .btn-primary:active,
        .btn-primary.active,
        .btn-primary:focus
        {
            background-color: orangered;
            border-color: orangered;
        }

        .btn-success
        {
            background-color: #E67040;
            border-color: #E67040;
            color: white;
        }

        .btn-success:hover
        {
            background-color: orangered;
            border-color: orangered;
        }

        .btn-success:active,
        .btn-success.active,
        .btn-success:focus
        {
            background-color: orangered;
            border-color: orangered;
        }
        /* Additional Styles for Preview Section */
        .preview-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .preview-section h3 {
            margin-top: 20px;
            margin-bottom: 15px;
        }
        .preview-item {
            margin-bottom: 10px;
        }
        .preview-label {
            font-weight: bold;
        }
        /* Styles for Back to Edit Button */
        .back-to-edit-button {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <!-- Sidebar inclusion -->
    <?php include "../includes/sidebar.php"; ?>

    <div id="content">
        <!-- Navbar inclusion -->
        <?php include "../includes/navbar.php"; ?>

        <!-- Main container for the multi-step form -->
        <div class="container-fluid">
            <div class="row">
                <!-- Form container -->
                <div class="col-md-12">
                    <div class="form-container">
                        <!-- Back to Projects Button -->
                        <div class="back-button mb-3">
                            <button onclick="window.location.href='../pages/projects.php'" type="button" class="btn btn-secondary" data-toggle="tooltip" title="Click to go Back to Projects">
                                <i class="fas fa-arrow-left"></i> Back to Projects
                            </button>
                            <!-- Back to Edit Button (Initially Hidden) -->
                            <button id="back-to-edit-button" type="button" class="btn btn-secondary back-to-edit-button" style="display: none;" data-toggle="tooltip" title="Click to go Back to edit the project details">
                                <i class="fas fa-edit"></i> Back to Edit
                            </button>
                        </div>
                        <!-- Add Project Form -->
                        <form id="addProjectForm" method="POST" novalidate>
                            <!-- Step 1: Project Description -->
                            <div class="form-step" id="step-1">
                                <div class="project-details-section">
                                    <h5>Project Details</h5>
                                    <div class="row">
                                        <!-- Project ID -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="proj_ID">Project ID <span class="required-field">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="proj_ID" name="proj_ID" required data-toggle="tooltip" title="Enter the unique Project ID" value="<?php echo htmlspecialchars($_POST['proj_ID'] ?? ''); ?>">
                                                <div class="invalid-feedback">
                                                    Please provide a Project ID.
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Component ID -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="proj_comp_ID">Component ID <span class="required-field">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="proj_comp_ID" name="proj_comp_ID" required data-toggle="tooltip" title="Enter the Component ID" value="<?php echo htmlspecialchars($_POST['proj_comp_ID'] ?? ''); ?>">
                                                <div class="invalid-feedback">
                                                    Please provide a Component ID.
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Project Status -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="proj_status">Project Status <span class="required-field">*</span></label>
                                                <select class="form-control form-control-sm" id="proj_status" name="proj_status" required data-toggle="tooltip" title="Select the Project Status">
                                                    <option value="">Select Status</option>
                                                    <option value="Not Yet Started" <?php if(isset($_POST['proj_status']) && $_POST['proj_status'] === 'Not Yet Started') echo 'selected'; ?>>Not Yet Started</option>
                                                    <option value="Ongoing" <?php if(isset($_POST['proj_status']) && $_POST['proj_status'] === 'Ongoing') echo 'selected'; ?>>Ongoing</option>
                                                    <option value="Completed" <?php if(isset($_POST['proj_status']) && $_POST['proj_status'] === 'Completed') echo 'selected'; ?>>Completed</option>
                                                    <option value="Suspended" <?php if(isset($_POST['proj_status']) && $_POST['proj_status'] === 'Suspended') echo 'selected'; ?>>Suspended</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Please select a Project Status.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <!-- Project Name -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="proj_cont_name">Project Name <span class="required-field">*</span></label>
                                                <textarea class="form-control form-control-sm" id="proj_cont_name" name="proj_cont_name" required data-toggle="tooltip" title="Enter the Project Name"><?php echo htmlspecialchars($_POST['proj_cont_name'] ?? ''); ?></textarea>
                                                <div class="invalid-feedback">
                                                    Please provide a Project Name.
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Contract Location -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="proj_cont_loc">Contract Location</label>
                                                <textarea class="form-control form-control-sm" id="proj_cont_loc" name="proj_cont_loc" data-toggle="tooltip" title="Enter the Contract Location"><?php echo htmlspecialchars($_POST['proj_cont_loc'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <!-- Contractors (Multiple Selection) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="proj_contractors">Contractors <span class="required-field">*</span></label>
                                                <select class="form-control form-control-sm" id="proj_contractors" name="proj_contractors[]" multiple required data-toggle="tooltip" title="Select one or more Contractors">
                                                    <?php foreach ($contractors as $contractor): ?>
                                                        <option value="<?= htmlspecialchars($contractor['cont_ID']) ?>" <?php if(isset($_POST['proj_contractors']) && in_array($contractor['cont_ID'], $_POST['proj_contractors'])) echo 'selected'; ?>>
                                                            <?= htmlspecialchars($contractor['cont_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="form-text text-muted">Hold down the Ctrl (windows) or Command (Mac) button to select multiple options.</small>
                                                <div class="invalid-feedback">
                                                    Please select at least one Contractor.
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Contract Amount -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="proj_cont_amt">Contract Amount <span class="required-field">*</span></label>
                                                <input type="text" step="0.01" class="form-control form-control-sm" id="proj_cont_amt" name="proj_cont_amt" required data-toggle="tooltip" title="Enter the Contract Amount" value="<?php echo htmlspecialchars($_POST['proj_cont_amt'] ?? ''); ?>">
                                                <div class="invalid-feedback">
                                                    Please provide a valid Contract Amount.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <!-- Notice of Award (NOA) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="proj_NOA">Notice of Award (NOA)</label>
                                                <input type="date" class="form-control form-control-sm" id="proj_NOA" name="proj_NOA" data-toggle="tooltip" title="Enter the Notice of Award date" value="<?php echo htmlspecialchars($_POST['proj_NOA'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <!-- Notice to Proceed (NOP) -->
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="proj_NOP">Notice to Proceed (NOP)</label>
                                                <input type="date" class="form-control form-control-sm" id="proj_NOP" name="proj_NOP" data-toggle="tooltip" title="Enter the Notice to Proceed date" value="<?php echo htmlspecialchars($_POST['proj_NOP'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <!-- Effectivity Date -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="proj_effect_date">Effectivity Date <span class="required-field">*</span></label>
                                                <input type="date" class="form-control form-control-sm" id="proj_effect_date" name="proj_effect_date" required data-toggle="tooltip" title="Enter the Effectivity Date" value="<?php echo htmlspecialchars($_POST['proj_effect_date'] ?? ''); ?>">
                                                <div class="invalid-feedback">
                                                    Please provide an Effectivity Date.
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Contract Duration -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="proj_cont_duration">Contract Duration (days) <span class="required-field">*</span></label>
                                                <input type="text" class="form-control form-control-sm" id="proj_cont_duration" name="proj_cont_duration" required data-toggle="tooltip" title="Enter the Contract Duration in days" value="<?php echo htmlspecialchars($_POST['proj_cont_duration'] ?? ''); ?>">
                                                <div class="invalid-feedback">
                                                    Please provide a Contract Duration.
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Expiry Date -->
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="proj_expiry_date">Expiry Date</label>
                                                <input type="date" class="form-control form-control-sm" id="proj_expiry_date" name="proj_expiry_date" value="<?php echo htmlspecialchars($_POST['proj_expiry_date'] ?? ''); ?>" required data-toggle="tooltip" title="Expiry Date is calculated automatically based on Effectivity Date and Contract Duration" readonly>
                                                <div class="invalid-feedback">
                                                    Please provide an Expiry Date.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> <!-- End of project-details-section -->
                            </div>

                            <!-- Step 2: Contractor's Manpower -->
                            <div class="form-step" id="step-2" style="display: none;">
                                <div class="manpower-section">
                                    <h5>Contractor's Manpower</h5>
                                    <div class="table-responsive">
                                        <table class="manpower-table">
                                            <thead>
                                                <tr>
                                                    <th>Position</th>
                                                    <th>Name <span class="required-field">*</span></th>
                                                    <th>PRC/ME ID <span class="required-field">*</span></th>
                                                    <!-- Removed Expiry Date Column -->
                                                </tr>
                                            </thead>
                                            <tbody style="color: black;">
                                                <!-- Project Manager -->
                                                <tr>
                                                    <td>Project Manager</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="cm_pm_name" name="cm_pm_name" required data-toggle="tooltip" title="Enter the Project Manager's Name" value="<?php echo htmlspecialchars($_POST['cm_pm_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Manager's Name.
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="cm_pm_prc_me_id" name="cm_pm_prc_me_id" required data-toggle="tooltip" title="Enter the Project Manager's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['cm_pm_prc_me_id'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Manager's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                                <!-- Project Engineer -->
                                                <tr>
                                                    <td>Project Engineer</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="cm_pe_name" name="cm_pe_name" required data-toggle="tooltip" title="Enter the Project Engineer's Name" value="<?php echo htmlspecialchars($_POST['cm_pe_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Engineer's Name.
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="cm_pe_prc_me_id" name="cm_pe_prc_me_id" required data-toggle="tooltip" title="Enter the Project Engineer's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['cm_pe_prc_me_id'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Engineer's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                                <!-- Materials Engineer -->
                                                <tr>
                                                    <td>Materials Engineer</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="cm_me_name" name="cm_me_name" required data-toggle="tooltip" title="Enter the Materials Engineer's Name" value="<?php echo htmlspecialchars($_POST['cm_me_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Materials Engineer's Name.
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="cm_me_prc_me_id" name="cm_me_prc_me_id" required data-toggle="tooltip" title="Enter the Materials Engineer's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['cm_me_prc_me_id'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Materials Engineer's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                                <!-- Personnel (Authorized Managing Officer, Construction Foreman, and Safety Officer) -->
                                                <tr>
                                                    <td>Personnel</td>
                                                    <td colspan="2">
                                                        <div class="form-group">
                                                            <div class="d-flex">
                                                                <!-- Authorized Managing Officer -->
                                                                <div class="form-group-sm mr-3" style="flex: 1;">
                                                                    <label for="cm_am_officer">Authorized Managing Officer</label>
                                                                    <input type="text" class="form-control form-control-sm" id="cm_am_officer" name="cm_am_officer" required data-toggle="tooltip" title="Enter the Authorized Managing Officer" value="<?php echo htmlspecialchars($_POST['cm_am_officer'] ?? ''); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please provide the Authorized Managing Officer's Name.
                                                                    </div>
                                                                </div>
                                                                <!-- Construction Foreman -->
                                                                <div class="form-group-sm mr-3" style="flex: 1;">
                                                                    <label for="cm_const_foreman">Construction Foreman</label>
                                                                    <input type="text" class="form-control form-control-sm" id="cm_const_foreman" name="cm_const_foreman" required data-toggle="tooltip" title="Enter the Construction Foreman's Name" value="<?php echo htmlspecialchars($_POST['cm_const_foreman'] ?? ''); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please provide the Construction Foreman's Name.
                                                                    </div>
                                                                </div>
                                                                <!-- Construction Safety & Health Officer -->
                                                                <div class="form-group-sm" style="flex: 1;">
                                                                    <label for="cm_csh_officer">Construction Safety & Health Officer</label>
                                                                    <input type="text" class="form-control form-control-sm" id="cm_csh_officer" name="cm_csh_officer" required data-toggle="tooltip" title="Enter the Construction Safety & Health Officer's Name" value="<?php echo htmlspecialchars($_POST['cm_csh_officer'] ?? ''); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please provide the Construction Safety & Health Officer's Name.
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Implementing Office Manpower -->
                            <div class="form-step" id="step-3" style="display: none;">
                                <div class="manpower-section">
                                    <h5>Implementing Office Manpower</h5>
                                    <div class="table-responsive">
                                        <table class="manpower-table">
                                            <thead>
                                                <tr>
                                                    <th>Position</th>
                                                    <th>Name <span class="required-field">*</span></th>
                                                    <th>PRC/ME ID <span class="required-field">*</span></th>
                                                    <!-- Removed Expiry Date Column -->
                                                </tr>
                                            </thead>
                                            <tbody style="color: black;">
                                                <!-- Project Engineer -->
                                                <tr>
                                                    <td>Project Engineer</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_pe_name" name="iom_pe_name" required data-toggle="tooltip" title="Enter the Project Engineer's Name" value="<?php echo htmlspecialchars($_POST['iom_pe_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Engineer's Name.
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_pe_prc_me_ID" name="iom_pe_prc_me_ID" required data-toggle="tooltip" title="Enter the Project Engineer's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['iom_pe_prc_me_ID'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Engineer's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                                <!-- Project Inspector -->
                                                <tr>
                                                    <td>Project Inspector</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_pi_name" name="iom_pi_name" required data-toggle="tooltip" title="Enter the Project Inspector's Name" value="<?php echo htmlspecialchars($_POST['iom_pi_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Inspector's Name.
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_pi_prc_me_ID" name="iom_pi_prc_me_ID" required data-toggle="tooltip" title="Enter the Project Inspector's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['iom_pi_prc_me_ID'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Inspector's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                                <tr>
                                                    <td>Project Inspector PCMA</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_pi_pcma_name" name="iom_pi_pcma_name" required data-toggle="tooltip" title="Enter the Project Inspector's Name" value="<?php echo htmlspecialchars($_POST['iom_pi_pcma_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Inspector's Name (PCMA).
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_pi_pcma_prc_me_ID" name="iom_pi_pcma_prc_me_ID" required data-toggle="tooltip" title="Enter the Project Inspector's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['iom_pi_pcma_prc_me_ID'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Project Inspector's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                                <!-- Materials Engineer -->
                                                <tr>
                                                    <td>Materials Engineer</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_me_name" name="iom_me_name" required data-toggle="tooltip" title="Enter the Materials Engineer's Name" value="<?php echo htmlspecialchars($_POST['iom_me_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Materials Engineer's Name.
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_me_prc_me_ID" name="iom_me_prc_me_ID" required data-toggle="tooltip" title="Enter the Materials Engineer's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['iom_me_prc_me_ID'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Materials Engineer's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                                <!-- Materials In-Charge -->
                                                <tr>
                                                    <td>Materials In-Charge</td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_mic_name" name="iom_mic_name" required data-toggle="tooltip" title="Enter the Materials In-Charge's Name" value="<?php echo htmlspecialchars($_POST['iom_mic_name'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Materials In-Charge's Name.
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" id="iom_mic_prc_me_ID" name="iom_mic_prc_me_ID" required data-toggle="tooltip" title="Enter the Materials In-Charge's PRC/ME ID" value="<?php echo htmlspecialchars($_POST['iom_mic_prc_me_ID'] ?? ''); ?>">
                                                        <div class="invalid-feedback">
                                                            Please provide the Materials In-Charge's PRC/ME ID.
                                                        </div>
                                                    </td>
                                                    <!-- Removed Expiry Date Input -->
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Preview -->
                            <div class="form-step" id="step-4" style="display: none;">
                                <!-- Project Details Preview -->
                                <div class="manpower-section">
                                    <h3>Project Details</h3>
                                    <div class="preview-item">
                                        <span class="preview-label">Project ID:</span> <span id="preview-proj_ID"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Project Name:</span> <span id="preview-proj_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Project Component ID:</span> <span id="preview-proj_comp_ID"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Project Contract Location:</span> <span id="preview-proj_cont_loc"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Contractor Names:</span> <span id="preview-proj_contractor_names"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Project Contract Amount:</span> <span id="preview-proj_cont_amt"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Notice of Award (NOA):</span> <span id="preview-proj_NOA"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Notice to Proceed (NOP):</span> <span id="preview-proj_NOP"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Project Status:</span> <span id="preview-proj_status"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Project Effectivity Date:</span> <span id="preview-proj_effect_date"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Contract Duration (days):</span> <span id="preview-proj_cont_duration"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">Expiry Date:</span> <span id="preview-proj_expiry_date"></span>
                                    </div>
                                </div>

                                <!-- Contractor's Manpower Preview -->
                                <div class="manpower-section">
                                    <h3>Contractor's Manpower</h3>

                                    <!-- Project Manager -->
                                    <div class="preview-item">
                                        <span class="preview-label">Project Manager:</span> <span id="preview-cm_pm_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID:</span> <span id="preview-cm_pm_prc_me_id"></span>
                                    </div>

                                    <!-- Project Engineer -->
                                    <div class="preview-item">
                                        <span class="preview-label">Project Engineer:</span> <span id="preview-cm_pe_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID:</span> <span id="preview-cm_pe_prc_me_id"></span>
                                    </div>

                                    <!-- Materials Engineer -->
                                    <div class="preview-item">
                                        <span class="preview-label">Materials Engineer:</span> <span id="preview-cm_me_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID:</span> <span id="preview-cm_me_prc_me_id"></span>
                                    </div>

                                    <!-- Authorized Managing Officer -->
                                    <div class="preview-item">
                                        <span class="preview-label">Authorized Managing Officer:</span> <span id="preview-cm_am_officer"></span>
                                    </div>

                                    <!-- Construction Foreman -->
                                    <div class="preview-item">
                                        <span class="preview-label">Construction Foreman:</span> <span id="preview-cm_const_foreman"></span>
                                    </div>

                                    <!-- Construction Safety & Health Officer -->
                                    <div class="preview-item">
                                        <span class="preview-label">Construction Safety & Health Officer:</span> <span id="preview-cm_csh_officer"></span>
                                    </div>
                                </div>

                                <!-- Implementing Officer's Manpower Preview -->
                                <div class="manpower-section">
                                    <h3>Implementing Officer's Manpower</h3>

                                    <!-- Project Engineer -->
                                    <div class="preview-item">
                                        <span class="preview-label">Project Engineer:</span> <span id="preview-iom_pe_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID:</span> <span id="preview-iom_pe_prc_me_ID"></span>
                                    </div>

                                    <!-- Project Inspector -->
                                    <div class="preview-item">
                                        <span class="preview-label">Project Inspector:</span> <span id="preview-iom_pi_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID:</span> <span id="preview-iom_pi_prc_me_ID"></span>
                                    </div>
                                    
                                    <!-- Project Inspector (PCMA) -->
                                    <div class="preview-item">
                                        <span class="preview-label">Project Inspector(PCMA):</span> <span id="preview-iom_pi_pcma_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID (PCMA):</span> <span id="preview-iom_pi_pcma_prc_me_ID"></span>
                                    </div>

                                    <!-- Materials Engineer -->
                                    <div class="preview-item">
                                        <span class="preview-label">Materials Engineer:</span> <span id="preview-iom_me_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID:</span> <span id="preview-iom_me_prc_me_ID"></span>
                                    </div>

                                    <!-- Materials In-Charge -->
                                    <div class="preview-item">
                                        <span class="preview-label">Materials In-Charge:</span> <span id="preview-iom_mic_name"></span>
                                    </div>
                                    <div class="preview-item">
                                        <span class="preview-label">PRC/ME ID:</span> <span id="preview-iom_mic_prc_me_ID"></span>
                                    </div>
                                </div>
                            </div>
                            <!-- Sidebar Buttons -->
                            <div class="sidebar-buttons">
                                <!-- Navigation Buttons -->
                                <div class="sidebar-navigation">
                                    <button id="sidebar-back-button" type="button" class="btn btn-warning" disabled data-toggle="tooltip" title="Click to go Back">Back</button>
                                    <button id="sidebar-next-button" type="button" class="btn btn-primary"data-toggle="tooltip" title="Click to go Next">Next</button>
                                    <!-- Submit Button (hidden initially) -->
                                    <button id="sidebar-submit-button" type="submit" class="btn btn-success" style="display: none;" data-toggle="tooltip" title="Click to Add new project">Create Project</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div> <!-- End of col-md-12 -->
            </div> <!-- End of row -->
        </div> <!-- End of container-fluid -->
    </div> <!-- End of content -->

    <!-- Export Contractors as JavaScript Array -->
    <script>
        var contractors = <?php echo json_encode($contractors); ?>;
    </script>

    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Include Popper.js and Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Include Select2 JS (Optional) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="../js/script-sidebar.js"></script>
    <!-- Initialize tooltips -->
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip()
        })
    </script>

    <!-- Initialize Select2 (Optional) -->
    <script>
        $(document).ready(function() {
            $('#proj_contractors').select2({
                placeholder: "Select Contractors",
                allowClear: true
            });
        });
    </script>

    <!-- Multi-step form script -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        let currentStep = 1;
        const totalSteps = 4; // Total steps updated to 4
        const formSteps = document.querySelectorAll(".form-step");
        const backButtonSidebar = document.getElementById("sidebar-back-button");
        const nextButtonSidebar = document.getElementById("sidebar-next-button");
        const submitButtonSidebar = document.getElementById("sidebar-submit-button");
        const form = document.getElementById("addProjectForm");
        const backToEditButton = document.getElementById("back-to-edit-button"); // New Button

        // Elements for date and duration calculations
        const effectivityDate = document.getElementById('proj_effect_date');
        const contractDuration = document.getElementById('proj_cont_duration');
        const expiryDate = document.getElementById('proj_expiry_date');

        console.log('Initializing form scripts...');

        // Function to calculate expiry date
        function calculateExpiryDate() {
            console.log('Calculating Expiry Date...');
            const effectivityValue = effectivityDate.value;
            const durationValue = contractDuration.value;

            console.log('Effectivity Date Value:', effectivityValue);
            console.log('Contract Duration Value:', durationValue);

            if (effectivityValue && durationValue) {
                // Parse the effectivity date
                const [year, month, day] = effectivityValue.split('-').map(Number);
                const effectivity = new Date(year, month - 1, day); // Months are 0-indexed
                const duration = parseInt(durationValue, 10);

                console.log('Parsed Effectivity Date:', effectivity);
                console.log('Parsed Contract Duration:', duration);

                if (isNaN(effectivity.getTime()) || isNaN(duration) || duration <= 0) {
                    console.error('Invalid Effectivity Date or Contract Duration');
                    expiryDate.value = '';
                    alert('Please enter a valid Effectivity Date and a positive Contract Duration.');
                    return;
                }

                // Add duration in days
                const expiry = new Date(effectivity);
                expiry.setDate(expiry.getDate() + duration);
                console.log('New Expiry Date:', expiry);

                // Format the date to YYYY-MM-DD
                const formattedExpiry = expiry.toISOString().split('T')[0];
                console.log('Formatted Expiry Date:', formattedExpiry);

                expiryDate.value = formattedExpiry;
                console.log('Expiry Date Field Updated:', expiryDate.value);
            } else {
                console.warn('Effectivity Date or Contract Duration is missing');
                expiryDate.value = '';
            }
        }

        // Attach event listeners with confirmation
        console.log('Attaching event listeners...');
        if (effectivityDate) {
            effectivityDate.addEventListener('change', calculateExpiryDate);
            console.log('Effectivity Date change listener attached.');
        } else {
            console.error('Effectivity Date element not found.');
        }

        if (contractDuration) {
            contractDuration.addEventListener('input', calculateExpiryDate);
            console.log('Contract Duration input listener attached.');
        } else {
            console.error('Contract Duration element not found.');
        }

        // Initial calculation in case fields are pre-filled
        calculateExpiryDate();

        // Show the current form step
        function showStep(step) {
            formSteps.forEach((formStep, index) => {
                formStep.style.display = (index + 1 === step) ? "block" : "none";
            });

            if (step === 1) {
                backButtonSidebar.style.display = "none";
                backButtonSidebar.disabled = true;
                backToEditButton.style.display = "none"; // Hide "Back to Edit" button
            } else {
                backButtonSidebar.style.display = "inline-block";
                backButtonSidebar.disabled = false;
                backToEditButton.style.display = "none"; // Hide "Back to Edit" button
            }

            if (step === totalSteps) {
                nextButtonSidebar.style.display = "none";
                submitButtonSidebar.style.display = "inline-block";
                backToEditButton.style.display = "inline-block"; // Show "Back to Edit" button
                populatePreview(); // Populate the preview with user-entered data
            } else {
                nextButtonSidebar.style.display = "inline-block";
                submitButtonSidebar.style.display = "none";
            }
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleDateString('en-US', options);
        }

        // Populate the Preview Step with user-entered data
        function populatePreview() {
            // Project Details
            document.getElementById('preview-proj_ID').innerText = document.getElementById('proj_ID').value;
            document.getElementById('preview-proj_name').innerText = document.getElementById('proj_cont_name').value;
            document.getElementById('preview-proj_comp_ID').innerText = document.getElementById('proj_comp_ID').value;
            document.getElementById('preview-proj_cont_loc').innerText = document.getElementById('proj_cont_loc').value;
            document.getElementById('preview-proj_cont_amt').innerText = document.getElementById('proj_cont_amt').value;
            document.getElementById('preview-proj_NOA').innerText = document.getElementById('proj_NOA').value;
            document.getElementById('preview-proj_NOP').innerText = document.getElementById('proj_NOP').value;
            document.getElementById('preview-proj_status').innerText = document.getElementById('proj_status').value;
            document.getElementById('preview-proj_effect_date').innerText = formatDate(document.getElementById('proj_effect_date').value);
            document.getElementById('preview-proj_cont_duration').innerText = document.getElementById('proj_cont_duration').value;
            document.getElementById('preview-proj_expiry_date').innerText = formatDate(document.getElementById('proj_expiry_date').value);

            // Contractor Names
            var selectedContractorIDs = $('#proj_contractors').val(); // Using jQuery for Select2 compatibility
            var selectedContractors = contractors.filter(c => selectedContractorIDs.includes(c.cont_ID.toString()));
            var contractorNames = selectedContractors.map(c => c.cont_name).join(', ');
            document.getElementById('preview-proj_contractor_names').innerText = contractorNames;

            // Contractor's Manpower
            document.getElementById('preview-cm_pm_name').innerText = document.getElementById('cm_pm_name').value;
            document.getElementById('preview-cm_pm_prc_me_id').innerText = document.getElementById('cm_pm_prc_me_id').value;
            document.getElementById('preview-cm_pe_name').innerText = document.getElementById('cm_pe_name').value;
            document.getElementById('preview-cm_pe_prc_me_id').innerText = document.getElementById('cm_pe_prc_me_id').value;
            document.getElementById('preview-cm_me_name').innerText = document.getElementById('cm_me_name').value;
            document.getElementById('preview-cm_me_prc_me_id').innerText = document.getElementById('cm_me_prc_me_id').value;
            document.getElementById('preview-cm_am_officer').innerText = document.getElementById('cm_am_officer').value;
            document.getElementById('preview-cm_const_foreman').innerText = document.getElementById('cm_const_foreman').value;
            document.getElementById('preview-cm_csh_officer').innerText = document.getElementById('cm_csh_officer').value;

            // Implementing Office Manpower
            document.getElementById('preview-iom_pe_name').innerText = document.getElementById('iom_pe_name').value;
            document.getElementById('preview-iom_pe_prc_me_ID').innerText = document.getElementById('iom_pe_prc_me_ID').value;

            document.getElementById('preview-iom_pi_name').innerText = document.getElementById('iom_pi_name').value;
            document.getElementById('preview-iom_pi_prc_me_ID').innerText = document.getElementById('iom_pi_prc_me_ID').value;

            document.getElementById('preview-iom_me_name').innerText = document.getElementById('iom_me_name').value;
            document.getElementById('preview-iom_me_prc_me_ID').innerText = document.getElementById('iom_me_prc_me_ID').value;

            document.getElementById('preview-iom_mic_name').innerText = document.getElementById('iom_mic_name').value;
            document.getElementById('preview-iom_mic_prc_me_ID').innerText = document.getElementById('iom_mic_prc_me_ID').value;
            
            document.getElementById('preview-iom_pi_pcma_name').innerText = document.getElementById('iom_pi_pcma_name').value;
            document.getElementById('preview-iom_pi_pcma_prc_me_ID').innerText = document.getElementById('iom_pi_pcma_prc_me_ID').value;
        }

        // Event listener for Back button
        backButtonSidebar.addEventListener("click", function() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        });

        // Event listener for Next button
        nextButtonSidebar.addEventListener("click", function() {
            if (currentStep < totalSteps) {
                let currentFormStep = document.getElementById(`step-${currentStep}`);
                if (validateStep(currentFormStep)) {
                    currentStep++;
                    showStep(currentStep);
                }
            }
        });

        // Event listener for Back to Edit button
        backToEditButton.addEventListener("click", function() {
            if (currentStep === totalSteps) {
                currentStep = 1; // Navigate back to Step 1 (Project Details)
                showStep(currentStep);
            }
        });

        // Form validation for each step
        function validateStep(formStep) {
            const inputs = formStep.querySelectorAll('input, textarea, select');
            let valid = true;
            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    valid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                }
            });
            return valid;
        }

        // Handle form submission
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add('was-validated');
                let firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    let stepToShow = 1;
                    if (firstInvalid.closest("#step-2")) {
                        stepToShow = 2;
                    } else if (firstInvalid.closest("#step-3")) {
                        stepToShow = 3;
                    }
                    if (stepToShow !== currentStep) {
                        currentStep = stepToShow;
                        showStep(currentStep);
                    }
                    firstInvalid.focus();
                }
            }
        });

        // Initialize the form to show the first step
        showStep(currentStep);
    });
    </script>

</body>
</html>
