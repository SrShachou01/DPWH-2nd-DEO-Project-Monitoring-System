<?php
include '../includes/database.php';
session_start();
$db = ConnectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$proj_ID = $_GET['proj_ID'] ?? null;

if ($proj_ID) {
    // Fetch project details using the proj_ID
    $query = "SELECT * FROM projects WHERE proj_ID = ?;";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $proj_ID);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();

    if (!$project) {
        echo "Project not found!";
        exit();
    }
} else {
    error_log("Error: proj_ID is null or not found in projects table");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Gather form data
    $proj_ID = $_POST['proj_ID'] ?? null;
    $proj_cont_name = $_POST['proj_cont_name'] ?? null;
    $proj_comp_ID = $_POST['proj_comp_ID'] ?? null;
    $proj_cont_ID = $_POST['proj_cont_ID'] ?? null;
    $proj_cont_loc = $_POST['proj_cont_loc'] ?? null;
    $proj_contractor = $_POST['proj_contractor'] ?? null;
    $proj_cont_amt = $_POST['proj_cont_amt'] ?? null;
    // Calculate contract duration based on dates
    $proj_effect_date = $_POST['proj_effect_date'];
    $proj_expiry_date = $_POST['proj_expiry_date'];
    $proj_cont_duration = (strtotime($proj_expiry_date) - strtotime($proj_effect_date)) / (60 * 60 * 24);

    // Check if unworkable days option was selected
    if (isset($_POST['unworkable_days_option']) && $_POST['unworkable_days_option'] === 'yes') {
        $proj_unwork_days = $_POST['proj_unwork_days'] ?? 0;  // Use the provided value
        $proj_cont_duration += $proj_unwork_days;  // Add unworkable days to the contract duration
    } else {
        $proj_unwork_days = 0;  // No unworkable days if 'no' is selected
    }


    $proj_NOA = $_POST['proj_NOA'] ?? null;
    $proj_NOP = $_POST['proj_NOP'] ?? null;
    $proj_status = $_POST['proj_status'] ?? null;

    // Update main project details in the database
    $query = "UPDATE projects 
              SET proj_cont_name = ?, proj_comp_ID = ?, proj_cont_ID = ?, proj_cont_loc = ?, proj_contractor = ?, proj_cont_amt = ?, proj_cont_duration = ?, proj_unwork_days = ?, proj_NOA = ?, proj_NOP = ?, proj_effect_date = ?, proj_expiry_date = ?, proj_status = ? 
              WHERE proj_ID = ?";
    $stmt = $db->prepare($query);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($db->error));
    }

    $stmt->bind_param("sssssdisssssss", $proj_cont_name, $proj_comp_ID, $proj_cont_ID, $proj_cont_loc, $proj_contractor, $proj_cont_amt, $proj_cont_duration, $proj_unwork_days, $proj_NOA, $proj_NOP, $proj_effect_date, $proj_expiry_date, $proj_status, $proj_ID);

    if (!$stmt->execute()) {
        error_log("Error updating project: " . $stmt->error);
        echo "An error occurred while updating the project: " . $stmt->error;
        exit();
    } else {
        echo "Project updated successfully!";
    }



    if (isset($_POST['enable_cws'])) {
        $cws_code = $_POST['cws_code'] ?? null;
        $cws_lr_date = $_POST['cws_lr_date'] ?? null;
        $cws_reason = $_POST['cws_reason'] ?? null;
        $cws_susp_days = $_POST['cws_susp_days'] ?? null;
        $cws_approved_date = $_POST['cws_approved_date'] ?? null;
        $proj_ID = $_GET['proj_ID'] ?? null;

        $query = "INSERT INTO `contract-work-suspension` (cws_code, cws_lr_date, cws_reason, cws_susp_days, cws_approved_date, proj_ID) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssiss", $cws_code, $cws_lr_date, $cws_reason, $cws_susp_days, $cws_approved_date, $proj_ID);

        if (!$stmt->execute()) {
            error_log("Error inserting contract-work-suspension: " . $stmt->error);
        } else {
            echo "<script>alert('Contract Work Suspension created successfully.');</script>";
        }
    }

    if (isset($_POST['enable_cwr'])) {
        $cwr_code = $_POST['cwr_code'] ?? null;
        $cwr_lr_date = $_POST['cwr_lr_date'] ?? null;
        $cwr_reason = $_POST['cwr_reason'] ?? null;
        $cwr_susp_days = $_POST['cwr_susp_days'] ?? null;
        $cwr_approved_date = $_POST['cwr_approved_date'] ?? null;
    
        $query = "INSERT INTO `contract-work-resumption` (cwr_code, cwr_lr_date, cwr_reason, cwr_susp_days, cwr_approved_date, proj_ID) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssiss", $cwr_code, $cwr_lr_date, $cwr_reason, $cwr_susp_days, $cwr_approved_date, $proj_ID);
    
        if (!$stmt->execute()) {
            error_log("Error inserting contract-work-resumption: " . $stmt->error);
        } else {
            echo "<script>alert('Contract Work Resumption created successfully.');</script>";
        }
    }

    if (isset($_POST['enable_cte'])) {
        $cte_code = $_POST['cte_code'] ?? null;
        $cte_lr_date = $_POST['cte_lr_date'] ?? null;
        $cte_reason = $_POST['cte_reason'] ?? null;
        $cte_ext_days = $_POST['cte_ext_days'] ?? null;
        $cte_approved_date = $_POST['cte_approved_date'] ?? null;
    
        $query = "INSERT INTO `contract-time-extension` (cte_code, cte_lr_date, cte_reason, cte_ext_days, cte_approved_date, proj_ID) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssisi", $cte_code, $cte_lr_date, $cte_reason, $cte_ext_days, $cte_approved_date, $proj_ID);
    
        if (!$stmt->execute()) {
            error_log("Error inserting contract-time-extension: " . $stmt->error);
        } else {
            echo "<script>alert('Contract Time Extension created successfully.');</script>";
        }
    }

    if (isset($_POST['enable_mtsr'])) {
        $mtsr_code = $_POST['mtsr_code'] ?? null;
        $mtsr_lr_date = $_POST['mtsr_lr_date'] ?? null;
        $mtsr_reason = $_POST['mtsr_reason'] ?? null;
        $mtsr_susp_days = $_POST['mtsr_susp_days'] ?? null;
        $mtsr_approved_date = $_POST['mtsr_approved_date'] ?? null;
    
        $query = "INSERT INTO `monthly-time-suspension-report` (mtsr_code, mtsr_lr_date, mtsr_reason, mtsr_susp_days, mtsr_approved_date, proj_ID) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssiss", $mtsr_code, $mtsr_lr_date, $mtsr_reason, $mtsr_susp_days, $mtsr_approved_date, $proj_ID);
    
        if (!$stmt->execute()) {
            error_log("Error inserting monthly-time-suspension-report: " . $stmt->error);
        } else {
            echo "<script>alert('Monthly Time Suspension Report created successfully.');</script>";
        }
    }

    if (isset($_POST['enable_cm'])) {
        $cm_am_officer = $_POST['cm_am_officer'] ?? null;
        $cm_pm_name = $_POST['cm_pm_name'] ?? null;
        $cm_pm_prc_ID = $_POST['cm_pm_prc_ID'] ?? null;
        $cm_pm_expiry = $_POST['cm_pm_expiry'] ?? null;
        $cm_pe_prc_ID = $_POST['cm_pe_prc_ID'] ?? null;
        $cm_pe_expiry = $_POST['cm_pe_expiry'] ?? null;
        $cm_me_prc_ID = $_POST['cm_me_prc_ID'] ?? null;
        $cm_me_me_ID = $_POST['cm_me_me_ID'] ?? null;
        $cm_me_expiry = $_POST['cm_me_expiry'] ?? null;
        $cm_const_foreman = $_POST['cm_const_foreman'] ?? null;
        $cm_csh_officer = $_POST['cm_csh_officer'] ?? null;
    
        $query = "INSERT INTO `contract-manpower` (cm_am_officer, cm_pm_name, cm_pm_prc_ID, cm_pm_expiry, cm_pe_prc_ID, cm_pe_expiry, cm_me_prc_ID, cm_me_me_ID, cm_me_expiry, cm_const_foreman, cm_csh_officer, proj_ID) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssssssssssss", $cm_am_officer, $cm_pm_name, $cm_pm_prc_ID, $cm_pm_expiry, $cm_pe_prc_ID, $cm_pe_expiry, $cm_me_prc_ID, $cm_me_me_ID, $cm_me_expiry, $cm_const_foreman, $cm_csh_officer, $proj_ID);
    
        if (!$stmt->execute()) {
            error_log("Error inserting contractors-manpower: " . $stmt->error);
        } else {
            echo "<script>alert('Contractor’s Manpower created successfully.');</script>";
        }
    }
    
    if (isset($_POST['enable_vo'])) {
        $vo_code = $_POST['vo_code'] ?? null;
        $vo_date_request = $_POST['vo_date_request'] ?? null;
        $vo_reason = $_POST['vo_reason'] ?? null;
        $vo_amt_change = $_POST['vo_amt_change'] ?? null;
        $vo_approval_date = $_POST['vo_approval_date'] ?? null;
        $proj_ID = $_GET['proj_ID'] ?? null;
    
        $query = "INSERT INTO `variation-orders` (vo_code, vo_date_request, vo_reason, vo_amt_change, vo_approval_date, proj_ID) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssiss", $vo_code, $vo_date_request, $vo_reason, $vo_amt_change, $vo_approval_date, $proj_ID);
    
        if (!$stmt->execute()) {
            error_log("Error inserting variation-orders: " . $stmt->error);
        } else {
            echo "<script>alert('Variation Orders created successfully.');</script>";
        }
    }
    
    if (isset($_POST['enable_iom'])) {
        $iom_pe_prc_acc = $_POST['iom_pe_prc_acc'] ?? null;
        $iom_pi_prc_ID = $_POST['iom_pi_prc_ID'] ?? null;
        $iom_me_prc_ID = $_POST['iom_me_prc_ID'] ?? null;
        $iom_mr_accr = $_POST['iom_mr_accr'] ?? null;
        $iom_mic_prc_ID = $_POST['iom_mic_prc_ID'] ?? null;
        $proj_ID = $_GET['proj_ID'] ?? null;
    
        $query = "INSERT INTO `implementing-office-manpower` 
                  (proj_ID, iom_pe_prc_acc, iom_pi_prc_ID, iom_me_prc_ID, iom_mr_accr, iom_mic_prc_ID) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssssss", $proj_ID, $iom_pe_prc_acc, $iom_pi_prc_ID, $iom_me_prc_ID, $iom_mr_accr, $iom_mic_prc_ID);
    
        if (!$stmt->execute()) {
            error_log("Error inserting Implementing Office Manpower: " . $stmt->error);
        } else {
            echo "<script>alert('Implementing Office Manpower created successfully.');</script>";
        }
    }
    
    
    if (isset($_POST['enable_fc'])) {
        $fc_ID = $_POST['fc_ID'] ?? null;
        $fc_ir_date = $_POST['fc_ir_date'] ?? null;
        $fc_coc_date = $_POST['fc_coc_date'] ?? null;
        $fc_coa_date = $_POST['fc_coa_date'] ?? null;
    
        $query = "INSERT INTO `final-completion` (fc_ID, fc_ir_date, fc_coc_date, fc_coa_date, proj_ID) 
                  VALUES (?, ?, ?, ?, ?)";
    
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssss", $fc_ID, $fc_ir_date, $fc_coc_date, $fc_coa_date, $proj_ID);
    
        if (!$stmt->execute()) {
            error_log("Error inserting final-completion: " . $stmt->error);
        } else {
            echo "<script>alert('Final Completion created successfully.');</script>";
        }
    }
    
    
    // Redirect to projects.php with a success message
    $_SESSION['success_message'] = "Project updated successfully!";
    header("Location: ../pages/projects.php");
    exit();

}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects List</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        .table-actions button {
            margin-right: 5px;
        }
        .add-project-btn {
            margin-bottom: 20px;
        }
        .form-control-sm {
            width: 90%;
        }
        .table-wrapper {
            overflow-y: auto;
            max-height: 400px; /* Adjust based on your layout */
        }
        table th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }
        .bordered-section {
        border: 1px solid #ccc;
        padding: 15px;
        margin-bottom: 20px;
    }

    .section-content[disabled] {
        opacity: 0.5;
    }

    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>

    <div id="content" class="container-fluid">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <button type="button" id="sidebarCollapse" class="btn btn-orange">
                <i id="toggleIcon" class="fas fa-bars toggle-icon"></i>
            </button>
            <span class="navbar-text ml-3">Projects List</span>
        </nav>

        <form action="edit-project.php?proj_ID=<?php echo htmlspecialchars($proj_ID); ?>" method="POST">
        <h5>Edit Project</h5>
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="proj_ID">Project ID</label>
                                <input type="text" class="form-control form-control-sm" id="proj_ID" name="proj_ID" value="<?php echo htmlspecialchars($project['proj_ID']); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="proj_cont_name">Project Name</label>
                                <textarea class="form-control" class="form-control form-control-sm" id="proj_cont_name" name="proj_cont_name" required><?php echo htmlspecialchars($project['proj_cont_name']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_comp_ID">Component ID</label>
                                <input type="text" class="form-control form-control-sm" id="proj_comp_ID" name="proj_comp_ID" value="<?php echo htmlspecialchars($project['proj_comp_ID']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_cont_ID">Contract ID</label>
                                <input type="text" class="form-control form-control-sm" id="proj_cont_ID" name="proj_cont_ID" value="<?php echo htmlspecialchars($project['proj_cont_ID']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_cont_loc">Contract Location</label>
                                <input type="text" class="form-control form-control-sm" id="proj_cont_loc" name="proj_cont_loc" value="<?php echo htmlspecialchars($project['proj_cont_loc']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_contractor">Contractor</label>
                                <input type="text" class="form-control form-control-sm" id="proj_contractor" name="proj_contractor" value="<?php echo htmlspecialchars($project['proj_contractor']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_cont_amt">Contract Amount</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="proj_cont_amt" name="proj_cont_amt" value="<?php echo htmlspecialchars($project['proj_cont_amt']); ?>">
                            </div>
                        </div>
                        <div class="col-md-12">
            <div class="form-group flex-group">
                <div>
                    <label>Are there predetermined unworkable days?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="unworkable_days_option" id="unworkable_days_yes" value="yes" <?php if ($project['proj_unwork_days']) echo 'checked'; ?>>
                        <label class="form-check-label" for="unworkable_days_yes">Yes</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="unworkable_days_option" id="unworkable_days_no" value="no" <?php if (!$project['proj_unwork_days']) echo 'checked'; ?>>
                        <label class="form-check-label" for="unworkable_days_no">No</label>
                    </div>
                </div>
                <div class="form-group" id="unworkable_days_group">
                    <label for="proj_unwork_days">Number of Unworkable Days</label>
                    <input type="number" class="form-control form-control-sm" id="proj_unwork_days" name="proj_unwork_days" value="<?php echo htmlspecialchars($project['proj_unwork_days']); ?>" <?php if (!$project['proj_unwork_days']) echo 'disabled'; ?>>
                </div>
            </div>
        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_NOA">Notice of Award (NOA)</label>
                                <input type="text" class="form-control form-control-sm" id="proj_NOA" name="proj_NOA" value="<?php echo htmlspecialchars($project['proj_NOA']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_NOP">Notice to Proceed (NOP)</label>
                                <input type="text" class="form-control form-control-sm" id="proj_NOP" name="proj_NOP" value="<?php echo htmlspecialchars($project['proj_NOP']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_effect_date">Effectivity Date</label>
                                <input type="date" class="form-control form-control-sm" id="proj_effect_date" name="proj_effect_date" value="<?php echo htmlspecialchars($project['proj_effect_date']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_expiry_date">Expiry Date</label>
                                <input type="date" class="form-control form-control-sm" id="proj_expiry_date" name="proj_expiry_date" value="<?php echo htmlspecialchars($project['proj_expiry_date']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="proj_cont_duration">Contract Duration (Days)</label>
                                <input type="number" class="form-control form-control-sm" id="proj_cont_duration" name="proj_cont_duration" value="<?php echo htmlspecialchars($project['proj_cont_duration']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label for="proj_status">Project Status</label>
                                    <select class="form-control form-control-sm" id="proj_status" name="proj_status">
                                        <option value="ongoing" <?php if ($project['proj_status'] == 'Ongoing') echo 'selected'; ?>>Ongoing</option>
                                        <option value="completed" <?php if ($project['proj_status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                                        <option value="terminated" <?php if ($project['proj_status'] == 'Terminated') echo 'selected'; ?>>Terminated</option>
                                    </select>
                            </div>
                        </div>
                        
                    </div>
                </div>


<!-- Contract Work Suspension and Contract Work Resumption Sections in One Row -->
<div class="row">
    <div class="col-md-6">
        <div class="bordered-section">
            <input type="checkbox" id="enable_cws" class="section-checkbox" name="enable_cws">
            <label for="enable_cws">Contract Work Suspension</label>
            <div class="container-fluid section-content" id="cws_section" disabled>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cws_code">Contract Work Suspension Code</label>
                        <input type="text" class="form-control" id="cws_code" name="cws_code" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cws_lr_date">Contract Work Suspension Letter Received Date</label>
                        <input type="date" class="form-control" id="cws_lr_date" name="cws_lr_date" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cws_reason">Reason for Suspension</label>
                    <textarea class="form-control" id="cws_reason" name="cws_reason" rows="3" disabled></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cws_susp_days">Suspension Days</label>
                        <input type="number" class="form-control" id="cws_susp_days" name="cws_susp_days" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cws_approved_date">Approved Date</label>
                        <input type="date" class="form-control" id="cws_approved_date" name="cws_approved_date" disabled>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="bordered-section">
            <input type="checkbox" id="enable_cwr" class="section-checkbox" name="enable_cwr">
            <label for="enable_cwr">Contract Work Resumption</label>
            <div class="container-fluid section-content" id="cwr_section" disabled>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cwr_code">Contract Work Resumption Code</label>
                        <input type="text" class="form-control" id="cwr_code" name="cwr_code" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cwr_lr_date">Contract Work Resumption Letter Received Date</label>
                        <input type="date" class="form-control" id="cwr_lr_date" name="cwr_lr_date" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cwr_reason">Reason for Resumption</label>
                    <textarea class="form-control" id="cwr_reason" name="cwr_reason" rows="3" disabled></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cwr_susp_days">Suspension Days</label>
                        <input type="number" class="form-control" id="cwr_susp_days" name="cwr_susp_days" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cwr_approved_date">Approved Date</label>
                        <input type="date" class="form-control" id="cwr_approved_date" name="cwr_approved_date" disabled>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contract Time Extension and Monthly Time Suspension Report Sections in One Row -->
<div class="row">
    <div class="col-md-6">
        <div class="bordered-section">
            <input type="checkbox" id="enable_cte" class="section-checkbox" name="enable_cte">
            <label for="enable_cte">Contract Time Extension</label>
            <div class="container-fluid section-content" id="cte_section" disabled>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cte_code">Contract Time Extension Code</label>
                        <input type="text" class="form-control" id="cte_code" name="cte_code" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cte_lr_date">Contract Time Extension Letter Received Date</label>
                        <input type="date" class="form-control" id="cte_lr_date" name="cte_lr_date" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cte_reason">Reason for Extension</label>
                    <textarea class="form-control" id="cte_reason" name="cte_reason" rows="3" disabled></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="cte_ext_days">Extension Days</label>
                        <input type="number" class="form-control" id="cte_ext_days" name="cte_ext_days" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cte_approved_date">Approved Date</label>
                        <input type="date" class="form-control" id="cte_approved_date" name="cte_approved_date" disabled>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="bordered-section">
            <input type="checkbox" id="enable_mtsr" class="section-checkbox" name="enable_mtsr">
            <label for="enable_mtsr">Monthly Time Suspension Report</label>
            <div class="container-fluid section-content" id="mtsr_section" disabled>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="mtsr_code">Monthly Time Suspension Report Code</label>
                        <input type="text" class="form-control" id="mtsr_code" name="mtsr_code" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="mtsr_lr_date">Monthly Time Suspension Report Letter Received Date</label>
                        <input type="date" class="form-control" id="mtsr_lr_date" name="mtsr_lr_date" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label for="mtsr_reason">Monthly Time Suspension Report Reason</label>
                    <textarea class="form-control" id="mtsr_reason" name="mtsr_reason" rows="3" disabled></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="mtsr_ext_days">Monthly Time Suspension Report Extension Days</label>
                        <input type="number" class="form-control" id="mtsr_ext_days" name="mtsr_ext_days" disabled>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="mtsr_approved_date">Monthly Time Suspension Report Approved Date</label>
                        <input type="date" class="form-control" id="mtsr_approved_date" name="mtsr_approved_date" disabled>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Variation Orders Section -->
<div class="bordered-section">
    <input type="checkbox" id="enable_vo" class="section-checkbox" name="enable_vo">
    <label for="enable_vo">Variation Orders</label>
    <div class="container-fluid section-content" id="vo_section" disabled>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="vo_code">Variation Order Code</label>
                <input type="text" class="form-control" id="vo_code" name="vo_code" disabled>
            </div>
            <div class="form-group col-md-6">
                <label for="vo_lr_date">Letter Received Date</label>
                <input type="date" class="form-control" id="vo_lr_date" name="vo_lr_date" disabled>
            </div>
        </div>
        <div class="form-group">
            <label for="vo_reason">Reason for Variation</label>
            <textarea class="form-control" id="vo_reason" name="vo_reason" rows="3" disabled></textarea>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="vo_approved_date">Approved Date</label>
                <input type="date" class="form-control" id="vo_approved_date" name="vo_approved_date" disabled>
            </div>
            <div class="form-group col-md-6">
                <label for="vo_amount">Variation Order Amount</label>
                <input type="number" class="form-control" id="vo_amount" name="vo_amount" disabled>
            </div>
        </div>
    </div>
</div>

<div class="bordered-section">
    <input type="checkbox" id="enable_cm" class="section-checkbox" name="enable_cm">
    <label for="enable_cm">Contractor's Manpower</label>
    <div class="container-fluid section-content" id="cm_section" disabled>
        <!-- First Row: Authorized Managing Officer -->
        <div class="form-row">
            <div class="form-group col-md-12">
                <label for="cm_am_officer">Authorized Managing Officer</label>
                <input type="text" class="form-control" id="cm_am_officer" name="cm_am_officer" disabled>
            </div>
        </div>
        <!-- Second Row: Project Manager Name, Project Manager PRC ID, Project Manager PRC Expiry -->
        <div class="form-row">
            <div class="form-group col-md-4">
                <label for="cm_pm_name">Project Manager Name</label>
                <input type="text" class="form-control" id="cm_pm_name" name="cm_pm_name" disabled>
            </div>
            <div class="form-group col-md-4">
                <label for="cm_pm_prc_ID">Project Manager PRC ID</label>
                <input type="text" class="form-control" id="cm_pm_prc_ID" name="cm_pm_prc_ID" disabled>
            </div>
            <div class="form-group col-md-4">
                <label for="cm_pm_expiry">Project Manager PRC Expiry</label>
                <input type="date" class="form-control" id="cm_pm_expiry" name="cm_pm_expiry" disabled>
            </div>
        </div>
        <!-- Third Row: Project Engineer PRC ID, Project Engineer PRC Expiry -->
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="cm_pe_prc_ID">Project Engineer PRC ID</label>
                <input type="text" class="form-control" id="cm_pe_prc_ID" name="cm_pe_prc_ID" disabled>
            </div>
            <div class="form-group col-md-6">
                <label for="cm_pe_expiry">Project Engineer PRC Expiry</label>
                <input type="date" class="form-control" id="cm_pe_expiry" name="cm_pe_expiry"  disabled>
            </div>
        </div>
        <!-- Fourth Row: Materials Engineer PRC ID, Materials Engineer ME ID, Materials Engineer Expiry -->
        <div class="form-row">
            <div class="form-group col-md-4">
                <label for="cm_me_prc_ID">Materials Engineer PRC ID</label>
                <input type="text" class="form-control" id="cm_me_prc_ID" name="cm_me_prc_ID" disabled>
            </div>
            <div class="form-group col-md-4">
                <label for="cm_me_me_ID">Materials Engineer ME ID</label>
                <input type="text" class="form-control" id="cm_me_me_ID" name="cm_me_me_ID" disabled>
            </div>
            <div class="form-group col-md-4">
                <label for="cm_me_expiry">Materials Engineer Expiry</label>
                <input type="date" class="form-control" id="cm_me_expiry" name="cm_me_expiry" disabled>
            </div>
        </div>
        <!-- Final Row: Construction Foreman, Construction Safety & Health Officer -->
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="cm_const_foreman">Construction Foreman</label>
                <input type="text" class="form-control" id="cm_const_foreman" name="cm_const_foreman" disabled>
            </div>
            <div class="form-group col-md-6">
                <label for="cm_csh_officer">Construction Safety & Health Officer</label>
                <input type="text" class="form-control" id="cm_csh_officer" name="cm_csh_officer" disabled>
            </div>
        </div>
    </div>
</div>


            <!-- Combined Sections -->
            <div class="row">
                <!-- Implementing Office Manpower Section -->
<div class="bordered-section" style="width: 60%; margin-right: 5%; margin-left: 15px;">
    <input type="checkbox" id="enable_iom" class="section-checkbox" name="enable_iom">
    <label for="enable_iom">Implementing Office Manpower</label>
    <div class="container-fluid section-content" id="iom_section" disabled>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="iom_pe_prc_acc">Project Engineer PRC ACC</label>
                <input type="text" class="form-control" id="iom_pe_prc_acc" name="iom_pe_prc_acc" disabled>
            </div>
            <div class="form-group col-md-6">
                <label for="iom_pi_prc_ID">Project Inspector PRC ID</label>
                <input type="text" class="form-control" id="iom_pi_prc_ID" name="iom_pi_prc_ID" disabled>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="iom_me_prc_ID">Materials Engineer PRC ID</label>
                <input type="text" class="form-control" id="iom_me_prc_ID" name="iom_me_prc_ID" disabled>
            </div>
            <div class="form-group col-md-6">
                <label for="iom_mr_accr">Materials Engineer Accreditation</label>
                <input type="text" class="form-control" id="iom_mr_accr" name="iom_mr_accr" disabled>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-12">
                <label for="iom_mic_prc_ID">Materials In-Charge PRC ID</label>
                <input type="text" class="form-control" id="iom_mic_prc_ID" name="iom_mic_prc_ID" disabled>
            </div>
        </div>
    </div>
</div>


                <!-- Final Completion Section -->
                <div class="bordered-section" style="width: 30%; margin-left: 20px;">
                    <input type="checkbox" id="enable_fc" class="section-checkbox" name="enable_fc">
                    <label for="enable_fc">Final Completion</label>
                    <div class="container-fluid section-content" id="fc_section" disabled>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="fc_ID">Final Completion ID</label>
                                <input type="text" class="form-control" id="fc_ID" name="fc_ID" disabled>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="fc_ir_date">Inspection Report Date</label>
                                <input type="date" class="form-control" id="fc_ir_date" name="fc_ir_date" disabled>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="fc_coc_date">Certificate of Completion Date</label>
                                <input type="date" class="form-control" id="fc_coc_date" name="fc_coc_date" disabled>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="fc_coa_date">Certificate of Acceptance Date</label>
                                <input type="date" class="form-control" id="fc_coa_date" name="fc_coa_date" disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <div class="modal-footer">
            <button id="saveButton" type="submit" class="btn btn-primary">Update Project</button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
    </form>
</div>
 
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../js/script-for-main.js"></script>
    <script src="../js/script-sidebar.js"></script>



<script>
    // Flag to track whether changes have been made
    let isFormDirty = false;

    // Mark the form as dirty (meaning changes were made)
    const formElements = document.querySelectorAll('input, select, textarea');

    formElements.forEach(element => {
        element.addEventListener('change', () => {
            isFormDirty = true;
        });
    });

    // Beforeunload event to show confirmation if the form is dirty
    window.addEventListener('beforeunload', function (event) {
        if (isFormDirty) {
            const message = "Are you sure you want to exit? You will lose your unsaved changes.";
            event.preventDefault(); // For modern browsers
            event.returnValue = message;  // For modern browsers
            return message;  // For older browsers
        }
    });

    // Reset the dirty flag when saving
    document.getElementById('saveButton').addEventListener('click', () => {
        isFormDirty = false;
    });
</script>
</body>
</html>