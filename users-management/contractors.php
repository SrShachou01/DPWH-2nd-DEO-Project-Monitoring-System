<?php
require_once "../includes/database.php";
session_start();
$db = ConnectDB();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if the user is logged in and has a role_id
if (!isset($_SESSION['role_id'])) {
    // Redirect to login page or show an error
    header("Location: ../login.php");
    exit();
}

// Retrieve the current user's role_id from the session
$currentUserRoleId = $_SESSION['role_id'];

// Fetch all contractors where cont_isDeleted = 0
$query_contractors = "SELECT * FROM contractors WHERE cont_isDeleted = 0 ORDER BY cont_isBlocklisted, cont_name ASC";
$result_contractors = $db->query($query_contractors);

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contractors List</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        .add-contractor-btn {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .table-wrapper {
            height: 100%; /* Increased height to accommodate more rows */
        }
        table th {
            position: sticky;
            top: 0;
            background: #ff7f00; /* Same orange as btn-orange */
            color: #fff;
            z-index: 1;
        }
        .btn {
            border-radius: 20px;
        }
        /* Custom Button Color */
        .btn-orange {
            background-color: #E67040; /* Vibrant Orange */
            color: #fff;
            border: none;
        }
        .btn-orange:hover {
            background-color: #e67300;
            color: #fff;
        }
        /* Adjust modal content for better display */
        .modal-lg {
            max-width: 90% !important;
        }
        .projects-section {
            margin-top: 20px;
        }
        .projects-section h5 {
            margin-bottom: 15px;
        }
        .projects-table th, .projects-table td {
            vertical-align: middle !important;
        }
        /* Decrease width of contractor info table */
        .contractor-info-table th, .contractor-info-table td {
            padding: 8px;
        }
        /* Ensure projects tables fit beside contractor info */
        @media (min-width: 768px) {
            .contractor-info-col {
                max-width: 35%;
                flex: 0 0 35%;
            }
            .projects-col {
                max-width: 65%;
                flex: 0 0 65%;
            }
        }
        .table th
        {
            background-color: #E67040;
        }
        td .dropdown-toggle::after {
            display: none;
        }
        td .dropdown-toggle {
            border-radius: 50%; /* Makes the button circular */
            width: 40px; /* Adjust size */
            height: 40px; /* Adjust size */
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .btn-secondary {
            background-color: darkgray;
            border: 1px solid darkgray; /* Optional: You can match the border to the background color */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Add shadow */
        }

        .btn-secondary:hover {
            background-color: gray; /* Darken the background on hover */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2); /* Increase shadow intensity on hover */
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid">
    <?php include "../includes/navbar.php"; ?>

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

    <!-- Add User Button (Visible Only to Admins) -->
    <?php if ($currentUserRoleId == 1): ?>
        <!-- Add Contractor Button -->
        <div class="add-contractor-btn">
            <button type="button" class="btn btn-orange mt-3" data-toggle="modal" data-target="#addContractorModal" title="Click to Add new Contractor">
                <i class="fas fa-plus"></i> Add Contractor
            </button>
        </div>
    <?php endif; ?>

    <!-- Contractors Table -->
    <div class="table-wrapper mt-3">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th style="width: 30%;">Contractor Details</th>
                    <th style="width: 15%;">Owner</th>
                    <th style="width: 15%;">Status</th> <!-- Added Status column -->
                    <?php if ($currentUserRoleId == 1): ?>
                    <th style="width: 8%;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_contractors->num_rows > 0): ?>
                    <?php while ($row = $result_contractors->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['cont_name']); ?></strong><br>
                                <?php echo htmlspecialchars($row['cont_location']); ?><br>
                                <?php echo htmlspecialchars($row['cont_phone']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['cont_owner']); ?></td>
                            <td>
                                <?php if ($row['cont_isBlocklisted'] == 1): ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($currentUserRoleId == 1): ?>
                            <td class="table-actions">
                                <!-- Dropdown Menu -->
                                <div class="dropdown" data-toggle="tooltip" title="Click for more options">
                                    <button class="btn btn-secondary dropdown-toggle" type="button" id="actionsMenu<?php echo $row['cont_ID']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="actionsMenu<?php echo $row['cont_ID']; ?>">
                                        <a class="dropdown-item edit-contractor-btn" href="#"
                                            data-id="<?php echo htmlspecialchars($row['cont_ID']); ?>"
                                            data-name="<?php echo htmlspecialchars($row['cont_name']); ?>"
                                            data-location="<?php echo htmlspecialchars($row['cont_location']); ?>"
                                            data-owner="<?php echo htmlspecialchars($row['cont_owner']); ?>"
                                            data-phone="<?php echo htmlspecialchars($row['cont_phone']); ?>"
                                            data-toggle="modal"
                                            data-target="#editContractorModal"
                                            title="Click to edit Contractor">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($row['cont_isBlocklisted'] == 1): ?>
                                            <a class="dropdown-item unblock-contractor-btn" href="#"
                                                data-id="<?php echo htmlspecialchars($row['cont_ID']); ?>"
                                                data-action="unblock"
                                                data-toggle="modal"
                                                data-target="#blocklistContractorModal"
                                                title="Click to Enable the Contractor">
                                                <i class="fas fa-undo"></i> Enable
                                            </a>
                                        <?php else: ?>
                                            <a class="dropdown-item blocklist-contractor-btn" href="#"
                                                data-id="<?php echo htmlspecialchars($row['cont_ID']); ?>"
                                                data-action="block"
                                                data-toggle="modal"
                                                data-target="#blocklistContractorModal"
                                                title="Click to Disable the Contractor">
                                                <i class="fas fa-ban"></i> Disable
                                            </a>
                                        <?php endif; ?>
                                        <a class="dropdown-item profile-contractor-btn" href="#"
                                            data-id="<?php echo htmlspecialchars($row['cont_ID']); ?>"
                                            data-name="<?php echo htmlspecialchars($row['cont_name']); ?>"
                                            data-location="<?php echo htmlspecialchars($row['cont_location']); ?>"
                                            data-owner="<?php echo htmlspecialchars($row['cont_owner']); ?>"
                                            data-phone="<?php echo htmlspecialchars($row['cont_phone']); ?>"
                                            data-isblocklisted="<?php echo htmlspecialchars($row['cont_isBlocklisted']); ?>"
                                            data-isdeleted="<?php echo htmlspecialchars($row['cont_isDeleted']); ?>"
                                            data-toggle="modal"
                                            data-target="#contractorProfileModal"
                                            title="Click to View Contractor Profile and their projects involved">
                                            <i class="fas fa-user"></i> Contractor Profile
                                        </a>
                                    </div>
                                </div>
                                <!-- End Dropdown Menu -->
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No contractors found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals -->

<!-- Add Contractor Modal -->
<div class="modal fade" id="addContractorModal" tabindex="-1" role="dialog" aria-labelledby="addContractorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="../users-management/contractor-actions?action=add">
                <div class="modal-header">
                    <h5 class="modal-title" id="addContractorModalLabel">Add Contractor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="cont_name">Contractor Name</label>
                            <input type="text" class="form-control" id="cont_name" name="cont_name" required>
                        </div>
                        <div class="form-group">
                            <label for="cont_location">Location</label>
                            <input type="text" class="form-control" id="cont_location" name="cont_location" required>
                        </div>
                        <div class="form-group">
                            <label for="cont_owner">Owner</label>
                            <input type="text" class="form-control" id="cont_owner" name="cont_owner" required>
                        </div>
                        <div class="form-group">
                            <label for="cont_phone">Phone</label>
                            <input type="tel" class="form-control" id="cont_phone" name="cont_phone" pattern="[0-9\-+() ]{7,15}" required>
                        </div>
                        <input type="hidden" name="cont_isDeleted" value="0">
                        <input type="hidden" name="cont_isBlocklisted" value="0">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-orange">Save Contractor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Contractor Modal -->
<div class="modal fade" id="editContractorModal" tabindex="-1" role="dialog" aria-labelledby="editContractorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="../users-management/contractor-actions?action=edit">
                <div class="modal-header">
                    <h5 class="modal-title" id="editContractorModalLabel">Edit Contractor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_cont_ID_hidden" name="cont_ID">
                        <div class="form-group">
                            <label for="edit_cont_name">Contractor Name</label>
                            <input type="text" class="form-control" id="edit_cont_name" name="cont_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_cont_location">Location</label>
                            <input type="text" class="form-control" id="edit_cont_location" name="cont_location" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_cont_owner">Owner</label>
                            <input type="text" class="form-control" id="edit_cont_owner" name="cont_owner" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_cont_phone">Phone</label>
                            <input type="tel" class="form-control" id="edit_cont_phone" name="cont_phone" pattern="[0-9\-+() ]{7,15}" required>
                        </div>
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-orange">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Blocklist/Unblock Contractor Modal -->
<div class="modal fade" id="blocklistContractorModal" tabindex="-1" role="dialog" aria-labelledby="blocklistContractorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="../users-management/contractor-actions?action=blocklist">
                <div class="modal-header">
                    <h5 class="modal-title" id="blocklistContractorModalLabel">Disable Contractor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                    <div class="modal-body">
                        <p id="blocklistMessage">Are you sure you want to disable this contractor?</p>
                        <input type="hidden" id="blocklist_cont_ID" name="cont_ID">
                        <input type="hidden" id="blocklist_action" name="block_action">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="confirmBlocklistBtn">Yes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contractor Profile Modal -->
<div class="modal fade" id="contractorProfileModal" tabindex="-1" role="dialog" aria-labelledby="contractorProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"> <!-- Use modal-lg for larger size if needed -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contractor Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Contractor Profile Details -->
                <div class="container-fluid">
                    <div class="row">
                        <!-- Contractor Information Column -->
                        <div class="col-md-4 contractor-info-col">
                            <h5>Contractor Information</h5>
                            <table class="table table-bordered contractor-info-table">
                                <tbody>
                                    <tr>
                                        <th>Name</th>
                                        <td id="profile_cont_name"></td>
                                    </tr>
                                    <tr>
                                        <th>Location</th>
                                        <td id="profile_cont_location"></td>
                                    </tr>
                                    <tr>
                                        <th>Owner</th>
                                        <td id="profile_cont_owner"></td>
                                    </tr>
                                    <tr>
                                        <th>Phone</th>
                                        <td id="profile_cont_phone"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Projects Column -->
                        <div class="col-md-8 projects-col">
                            <!-- Completed Projects -->
                            <div class="projects-section">
                                <h5>Completed Projects</h5>
                                <table class="table table-striped projects-table" id="completedProjectsTable">
                                    <thead>
                                        <tr>
                                            <th>Project ID</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Completed projects will be inserted here via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                            <!-- Delayed Projects -->
                            <div class="projects-section">
                                <h5>Delayed Projects</h5>
                                <table class="table table-striped projects-table" id="delayedProjectsTable">
                                    <thead>
                                        <tr>
                                            <th>Project ID</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Delayed projects will be inserted here via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <!-- Optionally, you can add buttons here -->
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- End Contractor Profile Modal -->

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="../js/script-for-main.js"></script>
<script src="../js/script-sidebar.js"></script>

<script>
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="modal"]').tooltip();
    // Set data for Edit Modal
    $('.edit-contractor-btn').on('click', function(e) {
        e.preventDefault(); // Prevent default anchor behavior
        var id = $(this).data('id');
        var name = $(this).data('name');
        var location = $(this).data('location');
        var owner = $(this).data('owner');
        var phone = $(this).data('phone');

        // Populate the modal fields with the data from the button
        $('#edit_cont_ID').val(id);
        $('#edit_cont_ID_hidden').val(id);
        $('#edit_cont_name').val(name);
        $('#edit_cont_location').val(location);
        $('#edit_cont_owner').val(owner);
        $('#edit_cont_phone').val(phone);
    });

    // Set data for Blocklist/Unblock Modal
    $('.blocklist-contractor-btn, .unblock-contractor-btn').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var action = $(this).data('action'); // 'block' or 'unblock'
        $('#blocklist_cont_ID').val(id);
        $('#blocklist_action').val(action);

        if(action === 'block') {
            $('#blocklistMessage').text('Are you sure you want to blocklist this contractor?');
            $('#blocklistContractorModalLabel').text('Blocklist Contractor');
            $('#confirmBlocklistBtn').removeClass('btn-success').addClass('btn-primary').text('Blocklist');
        } else if(action === 'unblock') {
            $('#blocklistMessage').text('Are you sure you want to remove this contractor from the blocklist?');
            $('#blocklistContractorModalLabel').text('Unblock Contractor');
            $('#confirmBlocklistBtn').removeClass('btn-primary').addClass('btn-success').text('Unblock');
        }
    });

    // Set data for Contractor Profile Modal
    $('.profile-contractor-btn').on('click', function(e) {
        e.preventDefault();
        var contractorId = $(this).data('id');
        var name = $(this).data('name');
        var location = $(this).data('location');
        var owner = $(this).data('owner');
        var phone = $(this).data('phone');
        var isBlocklisted = $(this).data('isblocklisted') == 1 ? 'Yes' : 'No';
        var isDeleted = $(this).data('isdeleted') == 1 ? 'Yes' : 'No';

        // Populate the profile details
        $('#profile_cont_name').text(name);
        $('#profile_cont_location').text(location);
        $('#profile_cont_owner').text(owner);
        $('#profile_cont_phone').text(phone);
        // Assuming you have these fields; if not, you can remove them
        // $('#profile_cont_isBlocklisted').text(isBlocklisted);
        // $('#profile_cont_isDeleted').text(isDeleted);

        // Clear previous projects data
        $('#completedProjectsTable tbody').empty();
        $('#delayedProjectsTable tbody').empty();

        // Fetch projects via AJAX
        $.ajax({
            url: '../users-management/get-contractors-project',
            type: 'GET',
            data: { contractor_id: contractorId },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    console.log(response);
                    // Populate Completed Projects
                    if(response.completed_projects.length > 0) {
                        $.each(response.completed_projects, function(index, project) {
                            $('#completedProjectsTable tbody').append(
                                '<tr>' +
                                    '<td>' + project.proj_ID + '</td>' +
                                    '<td>' + project.proj_cont_loc + '</td>' +
                                '</tr>'
                            );
                        });
                    } else {
                        $('#completedProjectsTable tbody').append(
                            '<tr><td colspan="3" class="text-center">No completed projects.</td></tr>'
                        );
                    }

                    // Populate Delayed Projects
                    if(response.delayed_projects.length > 0) {
                        $.each(response.delayed_projects, function(index, project) {
                            $('#delayedProjectsTable tbody').append(
                                '<tr>' +
                                    '<td>' + project.proj_ID + '</td>' +
                                    '<td>' + project.proj_cont_loc + '</td>' +
                                '</tr>'
                            );
                        });
                    } else {
                        $('#delayedProjectsTable tbody').append(
                            '<tr><td colspan="3" class="text-center">No delayed projects.</td></tr>'
                        );
                    }
                } else {
                    // Handle error
                    $('#completedProjectsTable tbody').append(
                        '<tr><td colspan="3" class="text-center text-danger">Failed to load projects.</td></tr>'
                    );
                    $('#delayedProjectsTable tbody').append(
                        '<tr><td colspan="3" class="text-center text-danger">Failed to load projects.</td></tr>'
                    );
                }
            },
            error: function() {
                // Handle AJAX error
                $('#completedProjectsTable tbody').append(
                    '<tr><td colspan="3" class="text-center text-danger">Error fetching projects.</td></tr>'
                );
                $('#delayedProjectsTable tbody').append(
                    '<tr><td colspan="3" class="text-center text-danger">Error fetching projects.</td></tr>'
                );
            }
        });
    });
</script>
</body>
</html>
