<?php
include '../includes/database.php';
session_start();  // Start the session at the very top

// Temporarily enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Keep logging errors
ini_set('log_errors', 1);
ini_set('error_log', '../pages/requestow.txt');

// Check if the user is logged in and if role_id and user_id are properly set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    header("Location: ../index.php");
    exit();
}

$db = ConnectDB();
$user_id = $_SESSION['user_id'];  // Get the logged-in user ID
$role_id = $_SESSION['role_id'];  // Get the role ID of the user

$message = isset($_GET['message']) ? $_GET['message'] : '';

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// If the role_id is not Admin (1), redirect to index page
if ($role_id != 1) {
    header("Location: ../index.php");
    exit();
}

// Fetch requests for editing projects
$stmt = $db->prepare("SELECT er.request_id, er.proj_ID, p.proj_cont_name, p.proj_cont_loc, u.user_first_name, u.user_last_name, er.request_status, er.request_reason
                      FROM `edit-request` er
                      JOIN projects p ON er.proj_ID = p.proj_ID
                      JOIN users u ON er.user_ID = u.user_id
                      WHERE er.request_status = 'Pending'");
$stmt->execute();
$result = $stmt->get_result();
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Requests</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        .table th {
            background-color: #E67040;  /* Same orange background */
            color: #fff; /* White text */
            text-align: left;
            position: sticky; /* Sticky header */
            top: 0;
            z-index: 2; /* Ensure headers stay above cells */
        }
        /* Make the actions column sticky */
        .table .col-actions {
            position: sticky;
            right: 0;
            background-color: white;
            text-align: center;
            overflow: visible; /* Ensure the dropdown is visible */
        }
        
        .table th.col-actions {
            position: sticky;
            right: 0;
            background-color: #E67040;
            overflow: visible; /* Ensure the dropdown is visible */
        }
        
        /* Adjust the dropdown menu to overlay correctly */
        .col-actions .dropdown-menu {
            position: absolute; /* Position relative to the button */
            min-width: 160px; /* Optional: adjust based on the button size */
            display: none; /* Hide initially, shown on hover or click */
        }
        
        .col-actions .dropdown-toggle {
            border-radius: 50%; /* Makes the button circular */
            width: 40px; /* Adjust size */
            height: 40px; /* Adjust size */
            padding: 0; /* Remove inner padding */
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .col-actions .dropdown-menu.show {
            display: block;
            z-index: 9999; /* Ensure dropdown appears above other elements */
        }
        
        .col-actions .dropdown-toggle::after {
            display: none;
        }

    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>
    <div id="content" class="container-fluid">
        <?php include "../includes/navbar.php"; ?>
        
        <!-- Status Messages -->
        <?php if ($message): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    showCustomAlert("DPWH Project Monitoring System", "<?= escape($message); ?>");
                    <?php if (!empty($file)): ?>
                        // Optionally handle file download links here
                    <?php endif; ?>
                });
            </script>
        <?php endif; ?>
    
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<script>showCustomAlert("DPWH Project Monitoring System", "' . escape($_SESSION['success_message']) . '");</script>';
            unset($_SESSION['success_message']);
        }
    
        if (isset($_SESSION['error_message'])) {
            echo '<script>showCustomAlert("DPWH Project Monitoring System", "' . escape($_SESSION['error_message']) . '");</script>';
            unset($_SESSION['error_message']);
        }
        ?>

        <div class="table-container mt-3">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Project ID</th>
                        <th>Project Name</th>
                        <th>Requesting User</th>
                        <th>Reason for Editing Request</th>
                        <th>Request Status</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['proj_ID']); ?></td>
                            <td><?= htmlspecialchars($row['proj_cont_name']); ?>, <?= htmlspecialchars($row['proj_cont_loc']); ?></td>
                            <td><?= htmlspecialchars($row['user_first_name']) . ' ' . htmlspecialchars($row['user_last_name']); ?></td>
                            <td><?= htmlspecialchars($row['request_reason']); ?></td>
                            <td><?= htmlspecialchars($row['request_status']); ?></td>
                            <!-- Action Column Dropdown -->
                            <td class="col-actions">
                                <div class="dropdown" title="Select the button for more options" data-toggle="tooltip">
                                    <button 
                                    class="btn btn-secondary dropdown-toggle btn-sm" 
                                    type="button" id="actionsMenu<?= $row['request_id']; ?>" 
                                    data-toggle="dropdown" 
                                    aria-haspopup="true" 
                                    aria-expanded="false" 
                                    data-boundary="viewport"
                                    data-toggle="tooltip" 
                                    title="Click to view available actions for this project."
                                    aria-label="Actions Dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="actionsMenu<?= $row['request_id']; ?>">
                                        <a class="dropdown-item approve-btn" href="#" data-request-id="<?= $row['request_id']; ?>" data-project-id="<?= $row['proj_ID']; ?>" data-status="Approved" data-toggle="modal" data-target="#confirmModal">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a class="dropdown-item deny-btn" href="#" data-request-id="<?= $row['request_id']; ?>" data-project-id="<?= $row['proj_ID']; ?>" data-status="Denied" data-toggle="modal" data-target="#confirmModal">
                                            <i class="fas fa-times"></i> Deny
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="../project-management/approve-edit-request">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to <span id="actionText"></span> this request?</p>
                        <input type="hidden" name="request_id" id="modalRequestId">
                        <input type="hidden" name="proj_ID" id="modalProjId">
                        <input type="hidden" name="action" id="modalAction">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Custom Alert Modal -->
    <div id="customAlert" class="modal" tabindex="-1" role="dialog" aria-labelledby="customAlertLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="customAlertTitle" class="modal-title">DPWH Project Monitoring System</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeCustomAlert()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="customAlertMessage">This is a custom alert message.</p>
                </div>
                <div class="modal-footer" id="customAlertFooter">
                    <button type="button" class="btn btn-primary" onclick="closeCustomAlert()">OK</button>
                </div>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });


        $(document).ready(function() {
            // When the approve/deny button in the dropdown is clicked
            $('.approve-btn, .deny-btn').on('click', function() {
                var requestId = $(this).data('request-id');
                var projectId = $(this).data('project-id');
                var status = $(this).data('status');

                // Set the modal text and hidden inputs
                $('#actionText').text(status + ' this request');
                $('#modalRequestId').val(requestId);
                $('#modalProjId').val(projectId);
                $('#modalAction').val(status);
            });
        });
        
        // Function to show the custom alert modal
        function showCustomAlert(title, message) {
            document.getElementById('customAlertTitle').innerText = title;
            document.getElementById('customAlertMessage').innerText = message;
            $('#customAlert').modal('show');
        }
        
        // Function to close the custom alert modal
        function closeCustomAlert() {
            $('#customAlert').modal('hide');
        }


        // Function to show a custom confirm modal
        function showCustomConfirm(title, message) {
            return new Promise((resolve, reject) => {
                document.getElementById('customAlertTitle').innerText = title;
                document.getElementById('customAlertMessage').innerText = message;

                // Modify the modal footer to include Confirm and Cancel buttons
                const footer = document.getElementById('customAlertFooter');
                footer.innerHTML = `
                    <button type="button" class="btn btn-secondary" onclick="rejectCustomConfirm()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="resolveCustomConfirm()">OK</button>
                `;

                // Show the modal
                $('#customAlert').modal('show');

                // Define resolve and reject functions
                window.resolveCustomConfirm = function() {
                    $('#customAlert').modal('hide');
                    resolve(true);
                };

                window.rejectCustomConfirm = function() {
                    $('#customAlert').modal('hide');
                    reject(false);
                };
            });
        }
    </script>
</body>
</html>
