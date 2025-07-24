<?php
// documents.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/errordocss.txt');

include '../includes/database.php';
session_start();
$db = ConnectDB();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Retrieve project ID from GET or POST
$proj_ID = isset($_GET['proj_ID']) ? $_GET['proj_ID'] : (isset($_POST['proj_ID']) ? $_POST['proj_ID'] : null);

if ($proj_ID === null) {
    $_SESSION['error_message'] = "No project selected.";
    header("Location: ../pages/projects.php");
    exit();
}

// Fetch project details for display
$projectQuery = "SELECT proj_cont_name, proj_cont_loc FROM projects WHERE proj_ID = ?";
$projStmt = $db->prepare($projectQuery);
$projStmt->bind_param('s', $proj_ID);
$projStmt->execute();
$projResult = $projStmt->get_result();
$project = $projResult->fetch_assoc();

if (!$project) {
    $_SESSION['error_message'] = "Project not found.";
    header("Location: ../pages/projects.php");
    exit();
}

$sectionsQuery = "
    SELECT 
    cws_code AS section_ID, 
    'Contract Work Suspension' AS type, 
    cws_uploaded_date AS date_uploaded, 
    cws_attachment AS attachment,
    cws_reason AS reason,
    NULL AS vo_add_amt,
    NULL AS vo_revised_cost,
    NULL AS vo_ext_days,
    NULL AS vo_expiry_date
FROM `contract-work-suspension` 
WHERE proj_ID = ?

UNION ALL

SELECT 
    cwr_code AS section_ID, 
    'Contract Work Resumption' AS type, 
    cwr_uploaded_date AS date_uploaded, 
    cwr_attachment AS attachment,
    cwr_reason AS reason,
    NULL AS vo_add_amt,
    NULL AS vo_revised_cost,
    NULL AS vo_ext_days,
    NULL AS vo_expiry_date
FROM `contract-work-resumption` 
WHERE proj_ID = ?

UNION ALL

SELECT 
    cte_code AS section_ID, 
    'Contract Time Extension' AS type,
    cte_uploaded_date AS date_uploaded, 
    cte_attachment AS attachment,
    cte_reason AS reason,
    NULL AS vo_add_amt,
    NULL AS vo_revised_cost,
    cte_ext_days AS vo_ext_days,
    NULL AS vo_expiry_date
FROM `contract-time-extension` 
WHERE proj_ID = ?

UNION ALL

SELECT 
    mtsr_code AS section_ID, 
    'Monthly Time Suspension Report' AS type, 
    mtsr_uploaded_date AS date_uploaded,
    mtsr_attachment AS attachment,
    mtsr_reason AS reason,
    NULL AS vo_add_amt,
    NULL AS vo_revised_cost,
    NULL AS vo_ext_days,
    NULL AS vo_expiry_date
FROM `monthly-time-suspension-report` 
WHERE proj_ID = ?

UNION ALL

SELECT 
    vo_code AS section_ID, 
    'Variation Order' AS type,
    vo_uploaded_date AS date_uploaded, 
    vo_attachment AS attachment,
    vo_reason AS reason,
    vo_add_amt,
    vo_revised_cost,
    vo_ext_days,
    vo_expiry_date
FROM `variation-orders` 
WHERE proj_ID = ?

UNION ALL

SELECT 
    fc_ID AS section_ID, 
    CONCAT('Final Completion', ' (', fc_type, ')') AS type,
    fc_uploaded_date AS date_uploaded, 
    fc_attachment AS attachment,
    '' AS reason, -- No reason for final completion
    NULL AS vo_add_amt,
    NULL AS vo_revised_cost,
    NULL AS vo_ext_days,
    NULL AS vo_expiry_date
FROM `final-completion` 
WHERE proj_ID = ?

UNION ALL

SELECT 
    od_ID AS section_ID, 
    CONCAT('Other Documents', ' (', od_attachment_type, ')') AS type,
    od_uploaded_date AS date_uploaded,
    od_attachment AS attachment,
    '' AS reason, -- No reason for other documents
    NULL AS vo_add_amt,
    NULL AS vo_revised_cost,
    NULL AS vo_ext_days,
    NULL AS vo_expiry_date
FROM `other-documents` 
WHERE proj_ID = ?


ORDER BY date_uploaded DESC;
";

// Prepare and execute the query
$sectionsStmt = $db->prepare($sectionsQuery);
if (!$sectionsStmt) {
    die('Prepare failed: ' . htmlspecialchars($db->error));
}

// Bind the project ID for each UNIONed SELECT
$sectionsStmt->bind_param('sssssss', $proj_ID, $proj_ID, $proj_ID, $proj_ID, $proj_ID, $proj_ID, $proj_ID);
$sectionsStmt->execute();
$sectionsResult = $sectionsStmt->get_result();

// Fetch all sections into an array
$optionalInfos = [];
while ($row = $sectionsResult->fetch_assoc()) {
    $optionalInfos[] = $row;
}

function escape($string) {
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Documents - <?= escape($project['proj_cont_name']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <!-- Documents CSS -->
    <link rel="stylesheet" href="../css/styles-for-docs.css">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid documents-container">
<?php include "../includes/navbar.php"; ?>

    <div class="back-button" style="padding-top: 20px;">
    <button onclick="window.location.href='../pages/projects.php'" type="button" class="btn btn-secondary" data-toggle="tooltip" title="Click to go back to projects.">
        <i class="fas fa-arrow-left"></i> Back to Projects
    </button>
</div>

<?php
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show mt-2" role="alert">' . escape($_SESSION['success_message']) . '
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    </div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">' . escape($_SESSION['error_message']) . '
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    </div>';
    unset($_SESSION['error_message']);
}
?>

<div class="d-flex flex-column" style="padding-top: 10px;">
    <!-- First Row: Title aligned to the left -->
    <div class="d-flex justify-content-start mb-2">
        <h3 style="color: white;">Project: <?= escape($project['proj_cont_name'] . ' - ' . $project['proj_cont_loc']); ?></h3>
    </div>
    
    <div class="d-flex justify-content-between mb-2">
    <!-- Filter Section (Aligned to the left) -->
    <div class="d-flex justify-content-start">
        <select id="sectionFilter" class="form-control w-auto mr-2" data-toggle="tooltip" title="Select one to filter">
            <option value="">Filter by Section</option>
            <option value="Contract Work Suspension" data-toggle="tooltip" title="Click to select Contract Work Suspension">Contract Work Suspension</option>
            <option value="Contract Work Resumption" data-toggle="tooltip" title="Click to select Contract Work Resumption">Contract Work Resumption</option>
            <option value="Contract Time Extension" data-toggle="tooltip" title="Click to select Contract Time Extension">Contract Time Extension</option>
            <option value="Monthly Time Suspension Report" data-toggle="tooltip" title="Click to select Monthly Time Suspension Report">Monthly Time Suspension Report</option>
            <option value="Variation Order" data-toggle="tooltip" title="Click to select Variation Order">Variation Order</option>
            <option value="Final Completion" data-toggle="tooltip" title="Click to select Final Completion">Final Completion</option>
            <option value="Other Documents" data-toggle="tooltip" title="Click to select Other Documents">Other Documents</option>
        </select>
    </div>

    <!-- Add Documents button aligned to the right -->
    <div class="d-flex justify-content-end">
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" id="addDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Add Documents
            </button>
            <div class="dropdown-menu" aria-labelledby="addDropdown">
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#cwsModal" title="Add Contract Work Suspension">Contract Work Suspension</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#cwrModal" title="Add Contract Work Resumption">Contract Work Resumption</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#cteModal" title="Add Contract Time Extension">Contract Time Extension</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#mtsrModal" title="Add Monthly Time Suspension Report">Monthly Time Suspension Report</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#voModal" title="Add Variation Order">Variation Order</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#fcModal" title="Add Final Completion">Final Completion</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#odModal" title="Add Other Documents">Other Documents</a>
            </div>
        </div>
    </div>
</div>


<?php if (empty($optionalInfos)): ?>
    <p>No document sections found for this project.</p>
<?php else: ?>
    <div class="table-responsive documents-table-container">
        <table class="table table-striped documents-table">
        <thead>
            <tr>
                <th style="width: 160px;">Type</th>
                <th style="width: 150px;">Date Uploaded</th>
                <th style="width: 200px;">Reason</th>
                <th style="width: 150px;">Additive Amount</th>
                <th style="width: 150px;">Revised Cost</th>
                <th style="width: 150px;">Extension Days</th>
                <th style="width: 150px;">Expiry Date</th>
                <th style="width: 300px;">Attachment</th>
                <th class="col-doc-actions">Actions</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <?php foreach ($optionalInfos as $info): ?>
                <?php
                    // Format Dates
                    $dateUploaded = !empty($info['date_uploaded']) ? date("F d, Y", strtotime($info['date_uploaded'])) : 'N/A';
                    $reason = !empty($info['reason']) ? htmlspecialchars($info['reason']) : 'N/A';

                    // Initialize Variation Order specific fields
                    $vo_add_amt = isset($info['vo_add_amt']) && !is_null($info['vo_add_amt']) ? number_format($info['vo_add_amt'], 2) : 'N/A';
                    $vo_revised_cost = isset($info['vo_revised_cost']) && !is_null($info['vo_revised_cost']) ? number_format($info['vo_revised_cost'], 2) : 'N/A';
                    $vo_ext_days = isset($info['vo_ext_days']) && !is_null($info['vo_ext_days']) ? intval($info['vo_ext_days']) : 'N/A';
                    $vo_expiry_date = isset($info['vo_expiry_date']) && !empty($info['vo_expiry_date']) ? date("F d, Y", strtotime($info['vo_expiry_date'])) : 'N/A';
                ?>
                <tr class="doc-row" data-type="<?= escape($info['type']); ?>">
                    <td><?= escape($info['type']); ?></td>
                    <td><?= escape($dateUploaded); ?></td>
                    <td><?= escape($reason); ?></td>
                    <?php if ($info['type'] === 'Variation Order'): ?>
                        <td><?= escape($vo_add_amt); ?></td>
                        <td><?= escape($vo_revised_cost); ?></td>
                        <td><?= escape($vo_ext_days); ?></td>
                        <td><?= escape($vo_expiry_date); ?></td>
                    <?php else: ?>
                        <td>N/A</td>
                        <td>N/A</td>
                        <td>N/A</td>
                        <td>N/A</td>
                    <?php endif; ?>
                    <td class="col-doc-attachment">
                        <?php if (!empty($info['attachment'])): ?>
                            <?php
                                // Optionally display original filename if stored
                                $attachmentFullName = basename($info['attachment']);
                                $displayFileName = strstr($attachmentFullName, '_') ? substr($attachmentFullName, strpos($attachmentFullName, '_') + 1) : $attachmentFullName;
                            ?>
                            <a href="<?= escape($info['attachment']); ?>" target="_blank"><?= escape($displayFileName); ?></a>
                        <?php else: ?>
                            No attachment
                        <?php endif; ?>
                    </td>
                    <td class="col-doc-actions">
                        <div class="dropdown" data-toggle = "tooltip">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title = "Click the options section to choose">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                                <!-- View Button -->
                                <a class="dropdown-item view-btn" href="#" data-file="<?= escape($info['attachment']); ?>" data-toggle="tooltip" title="View" aria-label="View Section">
                                    <i class="fas fa-eye"></i> View
                                </a>

                                <!-- Delete Button -->
                                <a class="dropdown-item delete-btn" href="#" data-id="<?= escape($info['section_ID']); ?>" data-type="<?= escape($info['type']); ?>" data-toggle="tooltip" title="Delete Section" aria-label="Delete Section">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modals for Adding Sections -->
<!-- Contract Work Suspension Modal -->
<div class="modal fade" id="cwsModal" tabindex="-1" aria-labelledby="cwsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="cwsForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 style="color: white;" class="modal-title">Add Contract Work Suspension</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Retain CWS Code Input -->
          <div class="form-group">
            <label for="cws_code">Code for Contract Work Suspension</label>
            <input type="text" class="form-control" id="cws_code" name="cws_code" required>
          </div>
          <div class="form-group">
            <label for="cws_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="cws_lr_date" name="cws_lr_date">
          </div>
        <div class="form-group">
            <label for="cws_reason">Reason for Suspension</label>
            <select class="form-control" id="cws_reason" name="cws_reason" required>
                <option value="Calamity">Calamity</option>
                <option value="Delays">Delays</option>
                <option value="Unforeseen Events">Unforeseen Events</option>
                <option value="Resource Shortage">Resource Shortage</option>
                <option value="Labor Issues">Labor Issues</option>
                <option value="Government Regulations">Government Regulations</option>
                <option value="Other">Other</option>
            </select>
        </div>
          <div class="form-group">
            <label for="cws_susp_days">Suspension Days</label>
            <input type="text" class="form-control" id="cws_susp_days" name="cws_susp_days" required>
          </div>
          <div class="form-group">
            <label for="cws_ext_days">Number of Calendar Days of CTE</label>
            <input type="text" class="form-control" id="cws_ext_days" name="cws_ext_days" required>
          </div>
          <div class="form-group">
            <label for="cws_expiry_date">Revised Contract Expiry Date</label>
            <input type="date" class="form-control" id="cws_expiry_date" name="cws_expiry_date" required>
          </div>
          <div class="form-group">
            <label for="cws_attachment">Attachment (PDF)</label>
            <input style="color: white;" type="file" class="form-control-file" id="cws_attachment" name="attachment" accept="application/pdf" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="cws">
          <input type="hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Contract Work Resumption Modal -->
<div class="modal fade" id="cwrModal" tabindex="-1" aria-labelledby="cwrModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="cwrForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 style="color: white;" class="modal-title">Add Contract Work Resumption</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- New cwr_code Textbox -->
          <div class="form-group">
            <label for="cwr_code">Code for Contract Work Resumption</label>
            <input type="text" class="form-control" id="cwr_code" name="cwr_code" required>
          </div>
          
          <div class="form-group">
            <label for="cwr_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="cwr_lr_date" name="cwr_lr_date">
          </div>
            <div class="form-group">
                <label for="cwr_reason">Reason for Resumption</label>
                <select class="form-control" id="cwr_reason" name="cwr_reason" required>
                    <option value="Calamity">Calamity</option>
                    <option value="Delays">Delays</option>
                    <option value="Resolved Issues">Resolved Issues</option>
                    <option value="Project Restart">Project Restart</option>
                    <option value="Other">Other</option>
                </select>
            </div>
          <div class="form-group">
            <label for="cwr_susp_days">Suspension Days</label>
            <input type="text" class="form-control" id="cwr_susp_days" name="cwr_susp_days" required>
          </div>
          <div class="form-group">
            <label for="cwr_attachment">Attachment (PDF)</label>
            <input style="color: white;" type="file" class="form-control-file" id="cwr_attachment" name="attachment" accept="application/pdf" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="cwr">
          <input type="hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Contract Time Extension Modal -->
<div class="modal fade" id="cteModal" tabindex="-1" aria-labelledby="cteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="cteForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 style="color: white;" class="modal-title">Add Contract Time Extension</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- New cte_code Textbox -->
          <div class="form-group">
            <label for="cte_code">Code for Contract Time Extension</label>
            <input type="text" class="form-control" id="cte_code" name="cte_code" required>
          </div>
          
          <div class="form-group">
            <label for="cte_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="cte_lr_date" name="cte_lr_date">
          </div>
        <div class="form-group">
            <label for="cte_reason">Reason for Extension</label>
            <select class="form-control" id="cte_reason" name="cte_reason" required>
                <option value="Calamity">Calamity</option>
                <option value="Delays">Delays</option>
                <option value="Resource Shortage">Resource Shortage</option>
                <option value="Regulatory Approvals">Regulatory Approvals</option>
                <option value="Other">Other</option>
            </select>
        </div>
          <div class="form-group">
            <label for="cte_ext_days">Extension Days</label>
            <input type="text" class="form-control" id="cte_ext_days" name="cte_ext_days" required>
          </div>
          <div class="form-group">
            <label for="cte_attachment">Attachment (PDF)</label>
            <input style="color: white;" type="file" class="form-control-file" id="cte_attachment" name="attachment" accept="application/pdf" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="cte">
          <input type="hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Monthly Time Suspension Report Modal -->
<div class="modal fade" id="mtsrModal" tabindex="-1" aria-labelledby="mtsrModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="mtsrForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 style="color: white;" class="modal-title">Add Monthly Time Suspension Report</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- New mtsr_code Textbox -->
          <div class="form-group">
            <label for="mtsr_code">Code for Monthly Time Suspension Report</label>
            <input type="text" class="form-control" id="mtsr_code" name="mtsr_code" required>
          </div>
          
          <div class="form-group">
            <label for="mtsr_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="mtsr_lr_date" name="mtsr_lr_date">
          </div>
            <div class="form-group">
                <label for="mtsr_reason">Reason for Suspension</label>
                <select class="form-control" id="mtsr_reason" name="mtsr_reason" required>
                    <option value="Calamity">Calamity</option>
                    <option value="Delays">Delays</option>
                    <option value="Project Interruption">Project Interruption</option>
                    <option value="Labor Issues">Labor Issues</option>
                    <option value="Other">Other</option>
                </select>
            </div>
          <div class="form-group">
            <label for="mtsr_susp_days">Suspension Days</label>
            <input type="text" class="form-control" id="mtsr_susp_days" name="mtsr_susp_days" required>
          </div>
          <div class="form-group">
            <label for="mtsr_attachment">Attachment (PDF)</label>
            <input style="color: white;" type="file" class="form-control-file" id="mtsr_attachment" name="attachment" accept="application/pdf" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="mtsr">
          <input type="hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Variation Order Modal -->
<div class="modal fade" id="voModal" tabindex="-1" aria-labelledby="voModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="voForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 style="color: white;" class="modal-title">Add Variation Order</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label for="vo_code">Code for Variation Order</label>
            <input type="text" class="form-control" id="vo_code" name="vo_code" required>
          </div>
          <div class="form-group">
            <label for="vo_date">Letter Date Received</label>
            <input type="date" class="form-control" id="vo_date" name="vo_date">
          </div>
          <div class="form-group">
            <label for="vo_add_amt">Additive Amount</label>
            <input type="text" step="0.01" class="form-control" id="vo_add_amt" name="vo_add_amt" required>
          </div>
          <div class="form-group">
            <label for="vo_revised_cost">Revised Contract Cost</label>
            <input type="text" step="0.01" class="form-control" id="vo_revised_cost" name="vo_revised_cost" required>
          </div>
          <div class="form-group">
            <label for="vo_ext_days">Additional Time Extension Days</label>
            <input type="text" class="form-control" id="vo_ext_days" name="vo_ext_days" required>
          </div>
          <div class="form-group">
            <label for="vo_expiry_date">Revised Contract Expiry Date</label>
            <input type="date" class="form-control" id="vo_expiry_date" name="vo_expiry_date">
          </div>
        <div class="form-group">
            <label for="vo_reason">Reason for Variation</label>
            <select class="form-control" id="vo_reason" name="vo_reason" required>
                <option value="Design Change">Design Change</option>
                <option value="Unforeseen Conditions">Unforeseen Conditions</option>
                <option value="Material Availability">Material Availability</option>
                <option value="Regulatory Requirements">Regulatory Requirements</option>
                <option value="Other">Other</option>
            </select>
        </div>
          <div class="form-group">
            <label for="vo_attachment">Attachment (PDF)</label>
            <input style="color: white;" type="file" class="form-control-file" id="vo_attachment" name="attachment" accept="application/pdf" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="vo">
          <input type="hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Variation Order</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Final Completion Modal -->
<div class="modal fade" id="fcModal" tabindex="-1" aria-labelledby="fcModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="fcForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 style="color: white;" class="modal-title">Add Final Completion</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label for="fc_approved_date">Approved Date</label>
            <input type="date" class="form-control" id="fc_approved_date" name="fc_approved_date" required>
          </div>
          
          <div class="form-group">
            <label for="fc_type">Final Completion Type</label>
            <select class="form-control" id="fc_type" name="fc_type" required>
              <option value="Inspection Report">Inspection Report</option>
              <option value="Certificate of Completion">Certificate of Completion</option>
              <option value="Certificate of Acceptance">Certificate of Acceptance</option>
            </select>
          </div>
          
          <!-- Attachment -->
          <div class="form-group">
            <label for="fc_attachment">Attachment (PDF)</label>
            <input style="color: white;" type="file" class="form-control-file" id="fc_attachment" name="attachment" accept="application/pdf" required>
          </div>
          
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="fc">
          <input type="hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Final Completion</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Other Documents Modal -->
<div class="modal fade" id="odModal" tabindex="-1" aria-labelledby="odModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="odForm" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header">
          <h5 style="color: white;" class="modal-title">Add Other Documents</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
        <div class="form-group">
            <label for="od_title_name">Document Name</label>
            <input type="text" class="form-control" id="od_title_name" name="od_title_name" required>
        </div>
          
          <div class="form-group">
            <label for="od_attachment_type">Final Completion Type</label>
            <select class="form-control" id="od_attachment_type" name="od_attachment_type" required>
              <option value="Document File">Document File</option>
              <option value="Spreadsheet File">Spreadsheet File</option>
              <option value="Powerpoint File">Powerpoint File</option>
              <option value="Image File">Image File</option>
              <option value="Text File">Text File</option>
              <option value="PDF File">PDF File</option>
              <option value="Other">Other</option>
            </select>
          </div>
          
          <!-- Attachment -->
          <div class="form-group">
            <label for="od_attachment">Attachment (PDF)</label>
            <input style="color: white;" type="file" class="form-control-file" id="od_attachment" name="attachment" accept="application/pdf" required>
          </div>
          
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="od">
          <input type="hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Other Documents</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- View Section Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Section Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="$('#pdfIframe').attr('src', '');">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- PDF iframe to display section-specific PDF -->
                <iframe id="pdfIframe" style="width: 100%; height: 600px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <a href="#" id="downloadPdfBtn" class="btn btn-primary" download>Download PDF</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1" aria-labelledby="deleteSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Confirm Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="resetDeleteModal();">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this section?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="resetDeleteModal();">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteSection">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- PDF View Modal -->
<div class="modal fade" id="pdfViewModal" tabindex="-1" role="dialog" aria-labelledby="pdfViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">View Attachment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="pdfViewModalClose">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <iframe id="pdfFrame" src="" width="100%" height="600px"></iframe>
            </div>
            <div class="modal-footer">
                <a href="#" id="downloadPdfBtn" class="btn btn-primary" download>Download PDF</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include jQuery, Popper.js, and Bootstrap JS first -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<!-- Ensure you use the correct Popper.js version compatible with Bootstrap 4.5.2 -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<!-- Include your custom JS -->
<script src="../js/script-for-main.js"></script>
<script src="../js/script-sidebar.js"></script>
<!-- Custom JS -->
<script>
$(document).ready(function () {
    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="modal"]').tooltip();

    // Handle form submissions via AJAX
    $('#cwsForm').submit(function(e) {
        e.preventDefault();
        submitForm($(this), '#cwsModal');
    });

    $('#cwrForm').submit(function(e) {
        e.preventDefault();
        submitForm($(this), '#cwrModal');
    });

    $('#cteForm').submit(function(e) {
        e.preventDefault();
        submitForm($(this), '#cteModal');
    });

    $('#mtsrForm').submit(function(e) {
        e.preventDefault();
        submitForm($(this), '#mtsrModal');
    });

    $('#voForm').submit(function(e) {
        e.preventDefault();
        submitForm($(this), '#voModal');
    });

    $('#fcForm').submit(function(e) {
        e.preventDefault();
        submitForm($(this), '#fcModal');
    });
    
    $('#odForm').submit(function(e) {
        e.preventDefault();
        submitForm($(this), '#odModal');
    });

    function submitForm(form, modalId) {
        var formData = new FormData(form[0]);
        $.ajax({
            url: '../project-management/add-section',
            type: 'POST',
            data: formData,
            contentType: false, // Important for file upload
            processData: false, // Important for file upload
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    form[0].reset();
                    $(modalId).modal('hide');
                    location.reload(); // Reload the page to update the sections list and display success message
                } else {
                    location.reload(); // Reload the page to display error message
                }
            },
            error: function(xhr, status, error) {
                location.reload(); // Reload the page to display error message
            }
        });
    }

    // Delete Section
    var deleteSectionID = null;
    var deleteSectionType = null;

    $(document).on('click', '.delete-btn', function () {
        deleteSectionID = $(this).data('id');
        deleteSectionType = $(this).data('type');

        // If the section type is "Final Completion (type)", strip the extra part
        if (deleteSectionType.includes('Final Completion')) {
            deleteSectionType = 'Final Completion';
        }
        if (deleteSectionType.includes('Other Documents')) {
            deleteSectionType = 'Other Documents';
        }

        $('#deleteSectionModal').modal('show');
    });

    $('#confirmDeleteSection').on('click', function () {
        if (deleteSectionID && deleteSectionType) {
            $.ajax({
                url: '../project-management/delete-section',
                type: 'POST',
                data: {
                    section_ID: deleteSectionID,
                    section_type: deleteSectionType,
                    proj_ID: '<?= escape($proj_ID); ?>'
                },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        $('#deleteSectionModal').modal('hide');
                        location.reload(); // Reload to reflect deletion and display success message
                    } else {
                        location.reload(); // Reload to display error message
                    }
                },
                error: function () {
                    location.reload(); // Reload to display error message
                }
            });
        }
    });

    function resetDeleteModal() {
        deleteSectionID = null;
        deleteSectionType = null;
    }

    // Handle view button click (now consolidated to open modal)
    $(document).on('click', '.view-btn', function() {
        var filePath = $(this).data('file');
        var sectionType = $(this).closest('tr').find('.doc-row').data('type'); // Get the document type from the table row

        if (filePath) {
            // Open the document in a new tab
            window.open(filePath, '_blank');
        }
        // No alert is shown if no document is available to view
    });

    // Filtering functionality
    document.getElementById('sectionFilter').addEventListener('change', function() {
        const selectedFilter = this.value.toLowerCase();
        const rows = document.querySelectorAll('.doc-row');
        
        rows.forEach(row => {
            const rowType = row.dataset.type.toLowerCase(); // Gets the type value from the row's data-type attribute
            
            if (selectedFilter === "" || rowType.includes(selectedFilter)) {
                row.style.display = ""; // Show row
            } else {
                row.style.display = "none"; // Hide row
            }
        });
    });
});

// Optional: Move dropdown menus for .col-doc-actions only to the body to prevent clipping
$('.col-doc-actions .dropdown').on('show.bs.dropdown', function () {
    var $dropdown = $(this).find('.dropdown-menu');
    var button = $(this).find('.dropdown-toggle');
    var offset = button.offset();
    var height = button.outerHeight();
    var width = button.outerWidth();

    // Append dropdown to body and position it correctly
    $('body').append($dropdown.detach());

    // Set dropdown positioning
    $dropdown.css({
        'display': 'block',
        'position': 'absolute',
        'top': offset.top + height,  // Adjust top position for visibility
        'left': offset.left,         // Align to button left
        'z-index': 3000              // Ensure it appears on top
    });
});

$('.col-doc-actions .dropdown').on('hide.bs.dropdown', function () {
    var $dropdown = $(this).find('.dropdown-menu');
    $(this).append($dropdown.detach());
});
</script>

</body>
</html>
