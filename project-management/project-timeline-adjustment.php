<?php
// documents.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/error.txt');

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

// Query to fetch section IDs, types, and status for the given project
$sectionsQuery = "
    SELECT cws_code AS section_ID, 'Contract Work Suspension' AS type, status FROM `contract-work-suspension` WHERE proj_ID = ?
    UNION ALL
    SELECT cwr_code AS section_ID, 'Contract Work Resumption' AS type, status FROM `contract-work-resumption` WHERE proj_ID = ?
    UNION ALL
    SELECT cte_code AS section_ID, 'Contract Time Extension' AS type, status FROM `contract-time-extension` WHERE proj_ID = ?
    UNION ALL
    SELECT mtsr_code AS section_ID, 'Monthly Time Suspension Report' AS type, status FROM `monthly-time-suspension-report` WHERE proj_ID = ?
    UNION ALL
    SELECT vo_code AS section_ID, 'Variation Order' AS type, status FROM `variation-orders` WHERE proj_ID = ?
    UNION ALL
    SELECT cm_mp_ID AS section_ID, 'Contract Manpower' AS type, status FROM `contract-manpower` WHERE proj_ID = ?
    UNION ALL
    SELECT iom_ID AS section_ID, 'Implementing Office Manpower' AS type, status FROM `implementing-office-manpower` WHERE proj_ID = ?
    UNION ALL
    SELECT fc_ID AS section_ID, 'Final Completion' AS type, status FROM `final-completion` WHERE proj_ID = ?
";

// Prepare and execute the query
$sectionsStmt = $db->prepare($sectionsQuery);
if (!$sectionsStmt) {
    die('Prepare failed: ' . htmlspecialchars($db->error));
}

// Bind the project ID for each UNIONed SELECT
$sectionsStmt->bind_param('ssssssss', $proj_ID, $proj_ID, $proj_ID, $proj_ID, $proj_ID, $proj_ID, $proj_ID, $proj_ID);
$sectionsStmt->execute();
$sectionsResult = $sectionsStmt->get_result();

// Fetch all sections into an array
$optionalInfos = [];
while ($row = $sectionsResult->fetch_assoc()) {
    $optionalInfos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Documents - <?= htmlspecialchars($project['proj_cont_name']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <!-- Documents CSS -->
    <link rel="stylesheet" href="../css/styles-for-docs.css">
    <style>
        /* Additional inline styles if necessary */
        .status-approved {
            background-color: #d4edda;
        }
        .status-not-approved {
            background-color: #f8d7da;
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid documents-container">
<?php include "../includes/navbar.php"; ?>

    <!-- Status Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

<!-- Button container with flexbox to align the buttons -->
<div class="d-flex justify-content-between mb-3" style = "padding-top: 20px;">
    <!-- Back to Projects Button aligned to the left -->
    <div class="back-button">
        <button onclick="window.location.href='../pages/projects.php'" type="button" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </button>
    </div>

    <!-- Add Project Timeline Adjustment Button aligned to the right -->
    <div class="add-button">
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" id="addDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Add Project Timeline Adjustment
            </button>
            <div class="dropdown-menu" aria-labelledby="addDropdown">
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#cwsModal">Contract Work Suspension</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#cwrModal">Contract Work Resumption</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#cteModal">Contract Time Extension</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#mtsrModal">Monthly Time Suspension Report</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#voModal">Variation Order</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#fcModal">Final Completion</a>
            </div>
        </div>
    </div>
</div>

<!-- Project Information Section -->
<div class="mb-4">
    <h3>Project: <?= htmlspecialchars($project['proj_cont_name'] . ' - ' . $project['proj_cont_loc']); ?></h3>
</div>



    <?php if (empty($optionalInfos)): ?>
        <p>No document sections found for this project.</p>
    <?php else: ?>
        <div class="table-responsive documents-table-container">
            <table class="table table-striped documents-table">

                <thead>
                    <tr>
                        <th>ID</th>                        
                        <th>Type</th>
                        <th class="col-doc-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($optionalInfos as $info): ?>
                        <tr>
                            <td class="col-doc-id"><?= htmlspecialchars($info['section_ID']); ?></td>
                            <td class="col-doc-type"><?= htmlspecialchars($info['type']); ?></td>
                            <td class="col-doc-actions">
                                <button class="btn btn-info view-btn" data-id="<?= htmlspecialchars($info['section_ID']); ?>" data-type="<?= htmlspecialchars($info['type']); ?>" data-toggle="tooltip" title="View" aria-label="View Section">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Disable Delete and PDF buttons if not approved -->
                                <button class="btn btn-danger delete-btn" 
                                        data-id="<?= htmlspecialchars($info['section_ID']); ?>" 
                                        data-type="<?= htmlspecialchars($info['type']); ?>" 
                                        data-toggle="tooltip" 
                                        title="'Delete Section'" 
                                        aria-label="Delete Section">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <button 
                                    class="btn btn-secondary pdf-btn" 
                                    data-id="<?= htmlspecialchars($info['section_ID']); ?>" 
                                    data-type="<?= htmlspecialchars($info['type']); ?>" 
                                    data-toggle="tooltip" 
                                    title="<?= !$isApproved ? 'Cannot generate PDF because the status is not approved.' : 'Generate PDF'; ?>"
                                    onclick="generatePDF('<?= htmlspecialchars($info['section_ID']); ?>', '<?= htmlspecialchars($info['type']); ?>')" 
                                    <?= !$isApproved ? 'disabled' : ''; ?>>
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modals for Adding Sections -->
<!-- Contract Work Suspension Modal -->
<div class="modal fade" id="cwsModal" tabindex="-1" aria-labelledby="cwsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="cwsForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Contract Work Suspension</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Form fields -->
          <div class="form-group">
            <label for="cws_code">Contract Work Suspension Code</label>
            <input type="text" class="form-control" id="cws_code" name="cws_code" required>
          </div>
          <div class="form-group">
            <label for="cws_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="cws_lr_date" name="cws_lr_date" required>
          </div>
          <div class="form-group">
            <label for="cws_reason">Reason for Suspension</label>
            <textarea class="form-control" id="cws_reason" name="cws_reason" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label for="cws_susp_days">Suspension Days</label>
            <input type="number" class="form-control" id="cws_susp_days" name="cws_susp_days" required>
          </div>
          <div class="form-group">
            <label for="cws_approved_date">Approved Date</label>
            <input type="date" class="form-control" id="cws_approved_date" name="cws_approved_date" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="cws">
          <input type="hidden" name="proj_ID" value="<?= htmlspecialchars($proj_ID); ?>">
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
    <form id="cwrForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Contract Work Resumption</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Form fields -->
          <div class="form-group">
            <label for="cwr_code">Contract Work Resumption Code</label>
            <input type="text" class="form-control" id="cwr_code" name="cwr_code" required>
          </div>
          <div class="form-group">
            <label for="cwr_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="cwr_lr_date" name="cwr_lr_date" required>
          </div>
          <div class="form-group">
            <label for="cwr_reason">Reason for Resumption</label>
            <textarea class="form-control" id="cwr_reason" name="cwr_reason" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label for="cwr_susp_days">Suspension Days</label>
            <input type="number" class="form-control" id="cwr_susp_days" name="cwr_susp_days" required>
          </div>
          <div class="form-group">
            <label for="cwr_approved_date">Approved Date</label>
            <input type="date" class="form-control" id="cwr_approved_date" name="cwr_approved_date" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="cwr">
          <input type="hidden" name="proj_ID" value="<?= htmlspecialchars($proj_ID); ?>">
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
    <form id="cteForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Contract Time Extension</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Form fields -->
          <div class="form-group">
            <label for="cte_code">Contract Time Extension Code</label>
            <input type="text" class="form-control" id="cte_code" name="cte_code" required>
          </div>
          <div class="form-group">
            <label for="cte_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="cte_lr_date" name="cte_lr_date" required>
          </div>
          <div class="form-group">
            <label for="cte_reason">Reason for Extension</label>
            <textarea class="form-control" id="cte_reason" name="cte_reason" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label for="cte_ext_days">Extension Days</label>
            <input type="number" class="form-control" id="cte_ext_days" name="cte_ext_days" required>
          </div>
          <div class="form-group">
            <label for="cte_approved_date">Approved Date</label>
            <input type="date" class="form-control" id="cte_approved_date" name="cte_approved_date" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="cte">
          <input type="hidden" name="proj_ID" value="<?= htmlspecialchars($proj_ID); ?>">
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
    <form id="mtsrForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Monthly Time Suspension Report</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Form fields -->
          <div class="form-group">
            <label for="mtsr_code">Monthly Time Suspension Report Code</label>
            <input type="text" class="form-control" id="mtsr_code" name="mtsr_code" required>
          </div>
          <div class="form-group">
            <label for="mtsr_lr_date">Letter Received Date</label>
            <input type="date" class="form-control" id="mtsr_lr_date" name="mtsr_lr_date" required>
          </div>
          <div class="form-group">
            <label for="mtsr_reason">Reason for Suspension</label>
            <textarea class="form-control" id="mtsr_reason" name="mtsr_reason" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label for="mtsr_susp_days">Suspension Days</label>
            <input type="number" class="form-control" id="mtsr_susp_days" name="mtsr_susp_days" required>
          </div>
          <div class="form-group">
            <label for="mtsr_approved_date">Approved Date</label>
            <input type="date" class="form-control" id="mtsr_approved_date" name="mtsr_approved_date" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="mtsr">
          <input type="hidden" name="proj_ID" value="<?= htmlspecialchars($proj_ID); ?>">
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
    <form id="voForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Variation Order</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Form fields -->
          <div class="form-group">
            <label for="vo_code">Variation Order Code</label>
            <input type="text" class="form-control" id="vo_code" name="vo_code" required>
          </div>
          <div class="form-group">
            <label for="vo_date_request">Date of Request</label>
            <input type="date" class="form-control" id="vo_date_request" name="vo_date_request" required>
          </div>
          <div class="form-group">
            <label for="vo_reason">Reason for Variation</label>
            <textarea class="form-control" id="vo_reason" name="vo_reason" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label for="vo_amt_change">Amount Change</label>
            <input type="number" step="0.01" class="form-control" id="vo_amt_change" name="vo_amt_change" required>
          </div>
          <div class="form-group">
            <label for="vo_approved_date">Approved Date</label>
            <input type="date" class="form-control" id="vo_approved_date" name="vo_approved_date" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="vo">
          <input type="hidden" name="proj_ID" value="<?= htmlspecialchars($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- Final Completion Modal -->
<div class="modal fade" id="fcModal" tabindex="-1" aria-labelledby="fcModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="fcForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Final Completion</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <div class="modal-body">
          <!-- Form fields -->
          <div class="form-group">
            <label for="fc_ID">Final Completion ID</label>
            <input type="text" class="form-control" id="fc_ID" name="fc_ID" required>
          </div>
          <div class="form-group">
            <label for="fc_ir_date">Inspection Report Date</label>
            <input type="date" class="form-control" id="fc_ir_date" name="fc_ir_date" required>
          </div>
          <div class="form-group">
            <label for="fc_coc_date">Certificate of Completion Date</label>
            <input type="date" class="form-control" id="fc_coc_date" name="fc_coc_date" required>
          </div>
          <div class="form-group">
            <label for="fc_coa_date">Certificate of Acceptance Date</label>
            <input type="date" class="form-control" id="fc_coa_date" name="fc_coa_date" required>
          </div>
          <!-- Hidden inputs -->
          <input type="hidden" name="section" value="fc">
          <input type="hidden" name="proj_ID" value="<?= htmlspecialchars($proj_ID); ?>">
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Section</button>
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
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="$('#pdfIframe').attr('src', '');">Close</button>
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

    function submitForm(form, modalId) {
        $.ajax({
            url: '../project-management/add-timeline-adjustment.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    form[0].reset();
                    $(modalId).modal('hide');
                    location.reload(); // Reload the page to update the sections list
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred: ' + error);
            }
        });
    }

    // View Section
    $(document).on('click', '.view-btn', function () {
        var sectionID = $(this).data('id');
        var sectionType = $(this).data('type');

        // Construct the URL to generate and display the PDF
        var pdfURL = '../project-management/generate_pdf.php?section_ID=' + encodeURIComponent(sectionID) + '&section_type=' + encodeURIComponent(sectionType) + '&view=true';

        // Set the iframe source to the PDF URL
        $('#pdfIframe').attr('src', pdfURL);

        // Show the modal
        $('#viewModal').modal('show');
    });

    // Delete Section
    var deleteSectionID = null;
    var deleteSectionType = null;

    $(document).on('click', '.delete-btn', function () {
        deleteSectionID = $(this).data('id');
        deleteSectionType = $(this).data('type');
        $('#deleteSectionModal').modal('show');
    });

    $('#confirmDeleteSection').on('click', function () {
        if (deleteSectionID && deleteSectionType) {
            $.ajax({
                url: '../project-management/delete-section.php',
                type: 'POST',
                data: {
                    section_ID: deleteSectionID,
                    section_type: deleteSectionType,
                    proj_ID: '<?= htmlspecialchars($proj_ID); ?>'
                },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        $('#deleteSectionModal').modal('hide');
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function () {
                    alert('An error occurred while deleting the section.');
                }
            });
        }
    });

    function resetDeleteModal() {
        deleteSectionID = null;
        deleteSectionType = null;
    }

    // Function to generate PDF
    function generatePDF(sectionID, sectionType) {
        // Encode parameters to be URL-safe
        const encodedSectionID = encodeURIComponent(sectionID);
        const encodedSectionType = encodeURIComponent(sectionType);

        // Construct the URL
        const url = `../project-management/generate_pdf.php?section_ID=${encodedSectionID}&section_type=${encodedSectionType}`;

        // Open the PDF in a new tab
        window.open(url, '_blank');
    }

});
</script>

</body>
</html>
