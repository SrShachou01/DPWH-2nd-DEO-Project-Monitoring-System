<?php
session_start();
include_once "../includes/database.php";

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/errorprogs.txt');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];
$position = $_SESSION['user_position'];

// Retrieve proj_ID from GET parameters
$proj_ID = isset($_GET['proj_ID']) ? $_GET['proj_ID'] : '';

if (empty($proj_ID)) {
    echo "Invalid Project ID.";
    exit();
}

// Connect to the database
$db = ConnectDB();

// Fetch project details
$query = "SELECT proj_ID, proj_cont_name, proj_cont_loc, proj_progress, proj_status FROM projects WHERE proj_ID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('s', $proj_ID);
$stmt->execute();
$stmt->bind_result($proj_ID, $proj_cont_name, $proj_cont_loc, $proj_progress, $proj_status);
if (!$stmt->fetch()) {
    echo "Project not found.";
    exit();
}
$stmt->close();

// Get current date
$currentDate = new DateTime();
$today = $currentDate->format('Y-m-d');

// Check if progress has already been added today for this project
$checkProgressQuery = "SELECT COUNT(*) FROM progress WHERE proj_ID = ? AND DATE(prog_date) = ?";
$checkStmt = $db->prepare($checkProgressQuery);
$checkStmt->bind_param('ss', $proj_ID, $today);
$checkStmt->execute();
$checkStmt->bind_result($progressCountToday);
$checkStmt->fetch();
$checkStmt->close();

$canUpload = false;

// Allow upload if no progress has been added today and project is not completed
if ($progressCountToday == 0 && $proj_progress < 100) {
    $canUpload = true;
}

// Disable upload if project progress is already at 100%
if ($proj_progress == 100) {
    $canUpload = false;
}

// Set the button state based on upload permission
$buttonState = $canUpload ? '' : 'disabled';

// Fetch progress entries
$progress_query = "SELECT prog_ID, prog_date, prog_desc, prog_percentage, prog_issue, prog_photos, prog_status 
                   FROM progress WHERE proj_ID = ? ORDER BY prog_percentage DESC";
$progress_stmt = $db->prepare($progress_query);
$progress_stmt->bind_param('s', $proj_ID);
$progress_stmt->execute();
$progress_result = $progress_stmt->get_result();

// Function to escape output
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Progress - <?= escape($proj_cont_name); ?></title>
    <!-- Include Bootstrap CSS and other required stylesheets -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Include your custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <link rel="stylesheet" href="../css/styles-for-progress.css">
    <style>
        /* Additional Styles */

        /* Custom Button Color */
        .btn-orange {
            background-color: #ff7f00; /* Vibrant Orange */
            color: #fff;
            border: none;
        }
        .btn-orange:hover {
            background-color: #e67300;
            color: #fff;
        }
        label {
            color: white;
        }
        .col-actions .dropdown-toggle::after {
            display: none;
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
        /* Style for the progress text */
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #000; /* Adjust color for better visibility */
            font-weight: bold;
            pointer-events: none;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.7); /* Improves readability */
            white-space: nowrap; /* Prevent text wrapping */
        }
    </style>
</head>
<body>

<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid">
    <?php include "../includes/navbar.php"; ?>

    <div class="back-button" style="padding-top: 20px;">
        <button onclick="window.location.href='../pages/projects.php'" type="button" class="btn btn-secondary" data-toggle="tooltip" title="Click to go back to projects.">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </button>
    </div>

    <?php
    // Display Success Message
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show mt-2" role="alert">' . escape($_SESSION['success_message']) . '
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        </div>';
        unset($_SESSION['success_message']);
    }

    // Display Error Message
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show mt-2" role="alert">' . escape($_SESSION['error_message']) . '
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        </div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="d-flex justify-content-between flex-column align-items-start">
        <!-- Project Information -->
        <div class="mb-0">
            <h2 style="color: white;">Progress for Project: <?= escape($proj_cont_name . ' - ' . $proj_cont_loc); ?></h2>
        </div>

        <!-- Add Progress Button (Disabled if no progress can be added) -->
        <?php if ($role_id == 1 || $role_id == 2): ?>
            <div class="mb-2" style="width: 100%; text-align: right;">
                <button type="button" class="btn btn-orange" data-toggle="modal" data-target="#addProgressModal" <?= $buttonState ?> title="Click to add progress to the project.">
                    <i class="fas fa-plus-circle"></i> Add Progress
                </button>
            </div>
        <?php endif; ?>

        <!-- Progress Bar -->
        <div class="mb-2" style="width: 100%;">
            <div class="progress position-relative" style="height: 30px; border: 2px solid #007bff; border-radius: 5px;">
                <div 
                    class="progress-bar" 
                    role="progressbar" 
                    style="width: <?= escape($proj_progress); ?>%;" 
                    aria-valuenow="<?= escape($proj_progress); ?>" 
                    aria-valuemin="0" 
                    aria-valuemax="100">
                </div>
                <span class="progress-text"><?= escape($proj_progress); ?>%</span>
            </div>
        </div>
    </div>

    <!-- Progress Entries Table -->
    <div class="table-container mt-3">
        <table class="table table-striped table-bordered" style="width: 100%;">
            <thead class="thead-orange">
                <tr>
                    <th style="width: 50px;">Date</th>
                    <th style="width: 150px;">Description</th>
                    <th style="width: 100px;">Status</th> <!-- New Status column -->
                    <th style="width: 50px;">Completion Percentage (%)</th>
                    <th style="width: 100px;">Issues</th>
                    <th style="width: 50px;">Photos</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>    
            <tbody>
                <?php while ($row = $progress_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= date("F d, Y", strtotime($row['prog_date'])); ?></td>
                        <td><?= escape($row['prog_desc']); ?></td>
                        <td><?= escape($row['prog_status']); ?></td> <!-- Display Status -->
                        <td><?= escape($row['prog_percentage']); ?>%</td>
                        <td><?= escape($row['prog_issue']); ?></td>
                        <td>
                            <?php 
                            $photos = explode(',', $row['prog_photos']);
                            foreach ($photos as $photo) {
                                if (!empty($photo)) {
                                    echo "<a href='../uploads/progress_$proj_ID/$photo' target='_blank'>View Photo</a><br>";
                                }
                            }
                            ?>
                        </td>
                        <td class="col-actions">
                            <div class="dropdown" data-toggle="tooltip" title="Click for more options">
                                <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                                    <!-- Edit Button (Project Inspector) -->
                                    <?php if ($role_id == 1 || ($position == 'Project Inspector' && $row['prog_status'] != 'Approved')): ?>
                                        <a class="dropdown-item btn-edit" href="#" data-prog-id="<?= escape($row['prog_ID']); ?>" data-toggle="modal" data-target="#editProgressModal">
                                           <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>

                                    <!-- Approve Button (Project Engineer) -->
                                    <?php if ($role_id == 1 || ($position == 'Project Engineer' && $row['prog_status'] == 'Pending')): ?>
                                        <a class="dropdown-item approve-progress" href="#" data-prog-id="<?= escape($row['prog_ID']); ?>" data-action="approve">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                    <?php endif; ?>

                                    <!-- Reject Button (Project Engineer) -->
                                    <?php if ($role_id == 1 || ($position == 'Project Engineer' && $row['prog_status'] == 'Pending')): ?>
                                        <a class="dropdown-item reject-progress" href="#" data-prog-id="<?= escape($row['prog_ID']); ?>" data-action="reject">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    <?php endif; ?>

                                    <!-- Delete Button -->
                                    <a class="dropdown-item delete-progress" href="#" data-toggle="modal" data-target="#deleteProgressModal" 
                                        data-prog-id="<?= escape($row['prog_ID']); ?>" data-proj-id="<?= escape($proj_ID); ?>">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($progress_result->num_rows == 0): ?>
                    <tr>
                        <td colspan="6" class="text-center">No progress entries found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Progress Modal -->
    <div class="modal fade" id="addProgressModal" tabindex="-1" role="dialog" aria-labelledby="addProgressModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="progressForm" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 style="color: white;" class="modal-title">Add Project Progress</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                            <div class="form-group">
                                <label for="proj_ID">Project ID</label>
                                <input type="text" id="proj_ID" class="form-control" value="<?= escape($proj_ID); ?>" disabled>
                                <input type="hidden" id="proj_ID_hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
                            </div>

                            <div class="form-group">
                                <label for="prog_date">Date</label>
                                <input type="date" class="form-control" id="prog_date" name="prog_date" value="<?= date('Y-m-d'); ?>">
                            </div>

                            <!-- New Field: Completion Percentage -->
                            <div class="form-group">
                                <label for="prog_percentage">Completion Percentage (%)</label>
                                <input type="text" class="form-control" id="prog_percentage" name="prog_percentage" min="0" max="100" required>
                            </div>

                            <div class="form-group">
                                <label for="prog_desc">Description</label>
                                <textarea class="form-control" id="prog_desc" name="prog_desc" required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="prog_issue">Issues</label>
                                <textarea class="form-control" id="prog_issue" name="prog_issue"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="prog_photos">Upload Photos</label>
                                <input style="color: white;" type="file" class="form-control-file" id="prog_photos" name="prog_photos[]" multiple>
                            </div>
                            
                            <!-- Add Progress Bar (Hidden by default) -->
                            <div id="uploadProgress" style="display:none; width: 100%; margin-top: 10px;">
                                <div class="progress">
                                    <div id="uploadBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <span id="uploadText" style="color: white; text-align: center; display:block;">Uploading...</span>
                            </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Add Progress</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Progress Modal -->
    <div class="modal fade" id="editProgressModal" tabindex="-1" role="dialog" aria-labelledby="editProgressModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="editProgressForm" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 style="color: white;" class="modal-title">Edit Project Progress</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_prog_ID" name="prog_ID">

                        <div class="form-group">
                            <label for="edit-proj_ID">Project ID</label>
                            <input type="text" id="edit-proj_ID" class="form-control" value="<?= escape($proj_ID); ?>" disabled>
                            <input type="hidden" id="edit-proj_ID_hidden" name="proj_ID" value="<?= escape($proj_ID); ?>">
                        </div>

                        <div class="form-group">
                            <label for="edit_prog_date">Date</label>
                            <input type="date" class="form-control" id="edit_prog_date" name="prog_date">
                        </div>

                        <div class="form-group">
                            <label for="edit_prog_percentage">Completion Percentage (%)</label>
                            <input type="text" class="form-control" id="edit_prog_percentage" name="prog_percentage" min="0" max="100" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_prog_desc">Description</label>
                            <textarea class="form-control" id="edit_prog_desc" name="prog_desc" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="edit_prog_issue">Issues</label>
                            <textarea class="form-control" id="edit_prog_issue" name="prog_issue"></textarea>
                        </div>
                        
                        <!-- Add Progress Bar (Hidden by default) -->
                        <div id="uploadProgress" style="display:none; width: 100%; margin-top: 10px;">
                            <div class="progress">
                                <div id="uploadBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span id="uploadText" style="color: white; text-align: center; display:block;">Uploading...</span>
                        </div>


                        <!-- Display Existing Photos -->
                        <div class="form-group">
                            <label for="existing_photos">Existing Photos</label>
                            <div id="existing-photos"></div>
                        </div>

                        <div class="form-group">
                            <label for="edit_prog_photos_new">Upload New Photos</label>
                            <input style="color: white;" type="file" class="form-control-file" id="edit_prog_photos_new" name="prog_photos[]" multiple>
                        </div>

                        <div class="form-group">
                            <input type="checkbox" id="remove_existing_photos" name="remove_existing_photos">
                            <label for="remove_existing_photos">Remove Existing Photos</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal for Approve/Reject -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" role="dialog" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmActionModalLabel">Confirm Action</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to <span id="confirmActionType"></span> this progress entry?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmActionBtn" class="btn btn-primary">Confirm</button>
                </div>
            </div>
        </div>
    </div>


</div>

<div class="modal fade" id="deleteProgressModal" tabindex="-1" aria-labelledby="deleteProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="color: white;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProgressModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this progress entry? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Include JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- Include your custom JS -->
<script src="../js/script-for-main.js"></script>
<script src="../js/script-sidebar.js"></script>

<!-- Inline JavaScript for Handling Form Submission -->
<script>
    
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="modal"]').tooltip();

// Optional: Move dropdown menus for .col-actions only to the body to prevent clipping
$('.col-actions .dropdown').on('show.bs.dropdown', function () {
    var $dropdown = $(this).find('.dropdown-menu');
    var button = $(this).find('.dropdown-toggle');
    var offset = button.offset();
    var height = button.outerHeight();
    var width = button.outerWidth();

    // Append dropdown to body and position it correctly
    $('body').append($dropdown.detach());
});

$('.col-actions .dropdown').on('hide.bs.dropdown', function () {
    var $dropdown = $(this).find('.dropdown-menu');
    $(this).append($dropdown.detach());
});

$(document).ready(function() {
    // Handle adding progress via the modal
    $('#progressForm').on('submit', function(e) {
        e.preventDefault(); // Prevent form submission

        var formData = new FormData(this); // Create a FormData object to handle file uploads

        // Show the upload indicator (Progress Bar)
        $('#uploadProgress').show();
        $('#uploadBar').css('width', '0%');  // Reset the progress bar
        $('#uploadText').text('Uploading...');

        $.ajax({
            url: '../project-management/add-progress', // PHP file to handle the request
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function() {
                var xhr = new XMLHttpRequest();
                // Listen to the upload progress event
                xhr.upload.addEventListener('progress', function(event) {
                    if (event.lengthComputable) {
                        var percent = (event.loaded / event.total) * 100; // Calculate the percentage
                        $('#uploadBar').css('width', percent + '%'); // Update progress bar
                        $('#uploadText').text('Uploading... ' + Math.round(percent) + '%'); // Update text
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#uploadProgress').hide(); // Hide progress bar when upload is done
                $('#addProgressModal').modal('hide'); // Close the modal
                location.reload(); // Reload the page to see updates
            },
            error: function() {
                alert('Error saving progress.');
                $('#uploadProgress').hide(); // Hide progress bar if an error occurs
            }
        });
    });
});


$(document).on('click', '.btn-edit', function() {
    var prog_ID = $(this).data('prog-id'); // Get the prog_ID from the button's data attribute

    $.ajax({
        url: '../project-management/fetch-progress-details', // Fetch progress details by prog_ID
        type: 'GET',
        data: { prog_ID: prog_ID },
        success: function(response) {
            var data = JSON.parse(response);

            if (data.success) {
                // Populate the edit modal with the progress data
                $('#edit_prog_ID').val(data.progress.prog_ID);
                $('#edit_prog_date').val(data.progress.prog_date);
                $('#edit_prog_percentage').val(data.progress.prog_percentage);
                $('#edit_prog_desc').val(data.progress.prog_desc);
                $('#edit_prog_issue').val(data.progress.prog_issue);
                $('#edit-proj_ID').val(data.progress.proj_ID);

                // Display existing photos in the modal
                if (data.progress.prog_photos) {
                    var photos = data.progress.prog_photos.split(',');
                    var photoList = '';
                    photos.forEach(function(photo) {
                        if (photo) {
                            photoList += `<div class="existing-photo">
                                            <a href="../uploads/progress_${data.progress.proj_ID}/${photo}" target="_blank">${photo}</a>
                                          </div>`;
                        }
                    });
                    $('#existing-photos').html(photoList);
                }

                $('#editProgressModal').modal('show'); // Show the modal
            } else {
                alert(data.message); // Display error message
            }
        },
        error: function() {
            alert('Error fetching progress data.');
        }
    });
});

$(document).ready(function() {
    // Handle editing progress via the modal
    $('#editProgressForm').on('submit', function(e) {
        e.preventDefault(); // Prevent form submission

        var formData = new FormData(this); // Create a FormData object to handle file uploads

        // Show the upload indicator (Progress Bar)
        $('#uploadProgress').show(); // Show the progress bar
        $('#uploadBar').css('width', '0%');  // Reset the progress bar to 0%
        $('#uploadText').text('Uploading...'); // Initial text

        $.ajax({
            url: '../project-management/edit-progress',  // PHP file to handle the request
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function() {
                var xhr = new XMLHttpRequest();
                // Listen to the upload progress event
                xhr.upload.addEventListener('progress', function(event) {
                    if (event.lengthComputable) {
                        var percent = (event.loaded / event.total) * 100; // Calculate the percentage
                        $('#uploadBar').css('width', percent + '%'); // Update progress bar width
                        $('#uploadText').text('Uploading... ' + Math.round(percent) + '%'); // Update text with the percentage
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#uploadProgress').hide(); // Hide progress bar when upload is done
                $('#editProgressModal').modal('hide'); // Close the modal
                location.reload(); // Reload the page to see updates
            },
            error: function() {
                alert('Error saving progress.');
                $('#uploadProgress').hide(); // Hide progress bar if an error occurs
            }
        });
    });
});

// Get the delete button and handle click event
$(document).on('click', '.dropdown-item.delete-progress', function() {
    var prog_ID = $(this).data('prog-id');
    var proj_ID = $(this).data('proj-id');
    
    // Store the prog_ID and proj_ID in the modal's confirm button
    $('#confirmDeleteBtn').data('prog-id', prog_ID);
    $('#confirmDeleteBtn').data('proj-id', proj_ID);
});

// Handle confirm delete button click
$('#confirmDeleteBtn').on('click', function() {
    var prog_ID = $(this).data('prog-id');
    var proj_ID = $(this).data('proj-id');

    $.ajax({
        url: '../project-management/delete-progress',
        type: 'POST',
        data: {
            prog_ID: prog_ID,
            proj_ID: proj_ID
        },
        success: function(response) {
            // Optionally, handle the response
            // Hide the modal
            $('#deleteProgressModal').modal('hide');
            
            // Reload the page to show the success message
            location.reload();
        },
        error: function() {
            alert('Error deleting progress.');
        }
    });
});

$(document).on('click', '.approve-progress, .reject-progress', function() {
    var action = $(this).data('action');  // Get 'approve' or 'reject'
    var prog_ID = $(this).data('prog-id'); // Get progress ID

    // Set the confirmation message
    if (action == 'approve') {
        $('#confirmActionType').text('approve');
    } else {
        $('#confirmActionType').text('reject');
    }

    // Show the confirmation modal
    $('#confirmActionModal').modal('show');

    // Store the action and prog_ID in the modal
    $('#confirmActionBtn').data('action', action);
    $('#confirmActionBtn').data('prog-id', prog_ID);
});


$('#confirmActionBtn').on('click', function() {
    var action = $(this).data('action');
    var prog_ID = $(this).data('prog-id');

    console.log('Action:', action);  // Log the action and prog_ID for debugging
    console.log('Progress ID:', prog_ID);

    // Make the AJAX request to update progress
    $.ajax({
        url: '../project-management/approve-reject-progress',  // Correct path to the PHP file
        type: 'POST',
        data: {
            prog_ID: prog_ID,
            action: action
        },
        success: function(response) {
            var data = JSON.parse(response);
            if (data.success) {
                // Close the modal and reload the page to reflect changes
                $('#confirmActionModal').modal('hide');
                location.reload();
            } else {
                alert(data.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error processing the action: ' + error);
        }
    });
});



</script>
</body>
</html>
