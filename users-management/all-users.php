<?php
// users-management/all-users.php

include "../includes/database.php";
session_start();

// Check if the user is logged in and has a role_id
if (!isset($_SESSION['role_id'])) {
    // Redirect to login page or show an error
    header("Location: ../index.php");
    exit();
}

// Retrieve the current user's role_id from the session
$currentUserRoleId = $_SESSION['role_id'];

// Establish the database connection
$conn = ConnectDB();

// Fetch all users with their roles from the database using prepared statements
$query = "SELECT 
            users.user_id, 
            users.user_username, 
            users.user_first_name, 
            users.user_middle_initial, 
            users.user_last_name, 
            users.user_suffix, 
            users.user_email, 
            users.user_position, 
            users.user_id_type, 
            users.user_id_number, 
            users.user_photo, 
            roles.role_id, 
            roles.role_name 
          FROM users 
          LEFT JOIN roles ON users.role_id = roles.role_ID
          ORDER BY users.user_first_name ASC";
$result = mysqli_query($conn, $query);

// Check for query execution errors
if (!$result) {
    die("Database query failed: " . mysqli_error($conn));
}

// Function to escape output
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users List</title>
    <!-- Bootstrap 4.5.2 CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        /* Existing styles remain unchanged */

        .add-user-btn {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
            padding-top: 30px;
        }
        .table-wrapper {
            height: 100%; /* Adjust as needed */
        }
        table th {
            position: sticky;
            top: 0;
            background: #ff7f00; /* Vibrant Orange */
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
        .alert {
            margin-top: 20px;
        }
        /* Optional: Adjust modal z-index if necessary */
        .modal {
            z-index: 1050;
        }
        /* Style for profile pictures */
        .profile-pic {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            margin-right: 10px;
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

    <div id="content" class="container-fluid" style="padding-top: 100px;">
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
            <div class="add-user-btn">
                <button type="button" class="btn btn-orange" data-toggle="modal" data-target="#addUserModal" title = "Add New User">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="table-wrapper">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>User Details</th>
                        <th>Email</th>
                        <th>Position</th>
                        <th>Role</th>
                        <?php if ($currentUserRoleId == 1): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td>                                
                                <?php
                                    // Determine the profile picture path
                                    $profilePicPath = htmlspecialchars($row['user_photo']);
                                    // Check if the profile picture exists, else use a default image
                                    if (!empty($row['user_photo']) && file_exists($profilePicPath)) {
                                        $imgSrc = $profilePicPath;
                                    } else {
                                        $imgSrc = '../images/default-pic.jpg'; // Ensure this default image exists
                                    }
                                ?>
                                <img src="<?php echo $imgSrc; ?>" alt="Profile Picture" class="profile-pic">
                                <strong><?php echo htmlspecialchars($row['user_username']); ?></strong><br>
                            </td>
                            <td>
                                <?php 
                                    // Initialize the full name with the first name
                                    $fullName = htmlspecialchars($row['user_first_name']);
                                    
                                    // Check if the middle initial exists and is not empty
                                    if (!empty($row['user_middle_initial'])) {
                                        // Append the middle initial with a dot
                                        $fullName .= ' ' . htmlspecialchars($row['user_middle_initial']) . '.';
                                    }
                                    
                                    // Append the last name
                                    $fullName .= ' ' . htmlspecialchars($row['user_last_name']);
                                    
                                    // Append the suffix if not 'None'
                                    if (!empty($row['user_suffix']) && $row['user_suffix'] !== 'None') {
                                        $fullName .= ', ' . htmlspecialchars($row['user_suffix']);
                                    }
                                    
                                    // Display the full name
                                    echo $fullName;
                                ?><br>
                                <?php echo htmlspecialchars($row['user_id_type'] ?? ''); ?>: <?php echo htmlspecialchars($row['user_id_number'] ?? ''); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['user_email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['user_position'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['role_name'] ?? ''); ?></td>
                            <?php if ($currentUserRoleId == 1): ?>
                                <td>
                                    <div class="dropdown" title = "Select the button for more options" data-toggle="tooltip">
                                        <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" id="actionsMenu<?php echo $row['user_id']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="actionsMenu<?php echo $row['user_id']; ?>">
                                            <a class="dropdown-item edit-btn" href="#" 
                                                data-id="<?php echo htmlspecialchars($row['user_id']); ?>"
                                                data-username="<?php echo htmlspecialchars($row['user_username']); ?>"
                                                data-firstname="<?php echo htmlspecialchars($row['user_first_name']); ?>"
                                                data-middleinitial="<?php echo htmlspecialchars($row['user_middle_initial']); ?>"
                                                data-lastname="<?php echo htmlspecialchars($row['user_last_name']); ?>"
                                                data-suffix="<?php echo htmlspecialchars($row['user_suffix']); ?>"
                                                data-email="<?php echo htmlspecialchars($row['user_email'] ?? ''); ?>"
                                                data-position="<?php echo htmlspecialchars($row['user_position'] ?? ''); ?>"
                                                data-id-type="<?php echo htmlspecialchars($row['user_id_type'] ?? ''); ?>"
                                                data-id-number="<?php echo htmlspecialchars($row['user_id_number'] ?? ''); ?>"
                                                data-role-id="<?php echo htmlspecialchars($row['role_id'] ?? ''); ?>"
                                                data-role-name="<?php echo htmlspecialchars($row['role_name'] ?? ''); ?>"
                                                data-user-photo="<?php echo htmlspecialchars($row['user_photo'] ?? ''); ?>"
                                                title = "Edit Existing User"
                                                data-toggle="tooltip">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a class="dropdown-item delete-btn" href="#" 
                                                data-id="<?php echo htmlspecialchars($row['user_id']); ?>"
                                                data-username="<?php echo htmlspecialchars($row['user_username']); ?>"
                                                title = "Delete the user" data-toggle="tooltip">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                            <!-- User Profile Link -->
                                            <a class="dropdown-item view-btn" href="#" 
                                                data-id="<?php echo htmlspecialchars($row['user_id']); ?>"
                                                data-toggle="modal" data-target="#userProfileModal"
                                                title = "Open the User's info and their projects" data-toggle="tooltip">
                                                <i class="fas fa-user"></i> User Profile
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <?php if ($currentUserRoleId == 1): ?>
                                <td colspan="6" class="text-center">No users found.</td>
                            <?php else: ?>
                                <td colspan="5" class="text-center">No users found.</td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add User Modal -->
    <?php if ($currentUserRoleId == 1): ?>
        <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg modal-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="../users-management/add-user" method="POST" enctype="multipart/form-data" id="addUserForm">
                            <!-- First row: First Name, Middle Initial, Last Name, and Suffix -->
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <input type="text" class="form-control" placeholder="First Name" name="user_first_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <input type="text" class="form-control" placeholder="Middle Initial" name="user_middle_initial" maxlength="1">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <input type="text" class="form-control" placeholder="Last Name" name="user_last_name" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select class="form-control" name="user_suffix">
                                        <option value="None" selected>None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                        <!-- Add more suffixes as needed -->
                                    </select>
                                </div>
                            </div>

                            <!-- Second row: Username and Password -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <input type="text" class="form-control" placeholder="Username" name="user_username" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="input-group">
                                        <input type="password" class="form-control" placeholder="Password" name="user_password" id="addPassword" required>
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                                <span toggle="#addPassword" class="fas fa-eye toggle-password" style="cursor: pointer;"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Leave blank to keep current password.</small>
                                </div>
                            </div>

                            <!-- Third row: Email -->
                            <div class="input-group mb-3">
                                <input type="email" class="form-control" placeholder="Email" name="user_email" required>
                            </div>

                            <!-- Fourth row: ID Type and ID Number -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <select id="addIdType" class="form-control" name="id_type" onchange="toggleAddIdNumber()" required>
                                        <option value="" disabled selected>Select ID Type</option>
                                        <option value="PRC ID">PRC ID</option>
                                        <option value="ME ID">ME ID</option>
                                        <option value="Accreditation Number">Accreditation Number</option>
                                        <option value="Others">Others</option>
                                        <option value="None">None</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <input type="text" id="addIdNumber" class="form-control" name="id_number" placeholder="Enter ID Number" required>
                                </div>
                            </div>

                            <!-- Fifth row: Position and Role -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <select id="addPosition" class="form-control" name="user_position" required onchange="toggleAddOtherPosition(this)">
                                        <option value="" disabled selected>Select Position</option>
                                        <option value="None">None</option>
                                        <option value="Project Engineer">Project Engineer</option>
                                        <option value="Project Inspector">Project Inspector</option>
                                        <option value="Materials Engineer">Materials Engineer</option>
                                        <option value="Contractor">Contractor</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <select class="form-control" id="addRole" name="role_id" required>
                                        <option value="" disabled selected>Select Role</option>
                                        <option value="1">Admin</option>
                                        <option value="2">Member</option>
                                        <option value="3">Guest</option>
                                        <option value="4">New</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Hidden textbox for 'Other' position -->
                            <div class="input-group mb-3" id="addOtherPositionDiv" style="display: none;">
                                <input type="text" class="form-control" placeholder="Enter Your Position" name="other_position">
                            </div>

                            <!-- Enhanced Image upload field -->
                            <div class="form-group">
                                <label for="addImageInput"><strong>Upload Profile Picture</strong></label>
                                <div class="custom-file">
                                    <input type="file" name="imageUpload" class="custom-file-input" id="addImageInput" accept="image/*" required>
                                    <label class="custom-file-label" for="addImageInput">Choose Profile Picture</label>
                                </div>
                                <small class="form-text text-muted">Supported formats: JPEG, PNG, GIF. Maximum size: 2MB.</small>
                            </div>

                            <!-- Image preview and cropper -->
                            <div class="mb-3">
                                <img id="addImagePreview" style="max-width: 100%; display: none;" alt="Profile Picture Preview">
                            </div>

                            <!-- Hidden field to store the cropped image -->
                            <input type="hidden" name="croppedImageData" id="addCroppedImageData">
                            <button type="submit" name="register" class="btn btn-orange btn-block">Add User</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit User Modal -->
    <?php if ($currentUserRoleId == 1): ?>
        <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg modal-centered" role="document">
                <div class="modal-content">
                    <form method="post" action="../users-management/edit-user" enctype="multipart/form-data" id="editUserForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                            <div class="modal-body">
                                <input type="hidden" name="user_id" id="editUserId">

                                <!-- First row: First Name, Middle Initial, Last Name, and Suffix -->
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <input type="text" class="form-control" placeholder="First Name" name="user_first_name" id="editFirstName" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <input type="text" class="form-control" placeholder="Middle Initial" name="user_middle_initial" id="editMiddleInitial" maxlength="1">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <input type="text" class="form-control" placeholder="Last Name" name="user_last_name" id="editLastName" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <select class="form-control" name="user_suffix" id="editUserSuffix">
                                            <option value="None">None</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                            <!-- Add more suffixes as needed -->
                                        </select>
                                    </div>
                                </div>

                                <!-- Second row: Username and Password -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <input type="text" class="form-control" placeholder="Username" name="user_username" id="editUsername" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="input-group">
                                            <input type="password" class="form-control" placeholder="Password" name="user_password" id="editPassword" placeholder="Leave blank to keep current password">
                                            <div class="input-group-append">
                                                <div class="input-group-text">
                                                    <span toggle="#editPassword" class="fas fa-eye toggle-password" style="cursor: pointer;"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Leave blank if you do not wish to change the password.</small>
                                    </div>
                                </div>

                                <!-- Third row: Email -->
                                <div class="input-group mb-3">
                                    <input type="email" class="form-control" placeholder="Email" name="user_email" id="editEmail" required>
                                </div>

                                <!-- Fourth row: ID Type and ID Number -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <select id="editIdType" class="form-control" name="id_type" onchange="toggleEditIdNumber()" required>
                                            <option value="" disabled>Select ID Type</option>
                                            <option value="PRC ID">PRC ID</option>
                                            <option value="ME ID">ME ID</option>
                                            <option value="Accreditation Number">Accreditation Number</option>
                                            <option value="Others">Others</option>
                                            <option value="None">None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <input type="text" id="editIdNumber" class="form-control" name="id_number" placeholder="Enter ID Number" required>
                                    </div>
                                </div>

                                <!-- Fifth row: Position and Role -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <select id="editPosition" class="form-control" name="user_position" required onchange="toggleEditOtherPosition(this)">
                                            <option value="" disabled>Select Position</option>
                                            <option value="None">None</option>
                                            <option value="Project Engineer">Project Engineer</option>
                                            <option value="Project Inspector">Project Inspector</option>
                                            <option value="Materials Engineer">Materials Engineer</option>
                                            <option value="Contractor">Contractor</option>
                                            <option value="Others">Others</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <select class="form-control" id="editRole" name="role_id" required>
                                            <option value="" disabled>Select Role</option>
                                            <option value="1">Admin</option>
                                            <option value="2">Member</option>
                                            <option value="3">Guest</option>
                                            <option value="4">New</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Hidden textbox for 'Other' position -->
                                <div class="input-group mb-3" id="editOtherPositionDiv" style="display: none;">
                                    <input type="text" class="form-control" placeholder="Enter Your Position" name="other_position" id="editOtherPosition">
                                </div>

                                <!-- Enhanced Image upload field -->
                                <div class="form-group">
                                    <label for="editImageInput"><strong>Upload Profile Picture</strong></label>
                                    <div class="custom-file">
                                        <input type="file" name="imageUpload" class="custom-file-input" id="editImageInput" accept="image/*">
                                        <label class="custom-file-label" for="editImageInput">Choose Profile Picture</label>
                                    </div>
                                    <small class="form-text text-muted">Supported formats: JPEG, PNG, GIF. Maximum size: 2MB.</small>
                                </div>

                                <!-- Image preview and cropper -->
                                <div class="mb-3">
                                    <img id="editImagePreview" style="max-width: 100%; display: none;" alt="Profile Picture Preview">
                                </div>

                                <!-- Hidden field to store the cropped image -->
                                <input type="hidden" name="croppedImageData" id="editCroppedImageData">
                            </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-orange">Update Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Delete User Modal -->
    <?php if ($currentUserRoleId == 1): ?>
        <div class="modal fade" id="deleteUserModal" tabindex="-1" role="dialog" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="POST" action="../users-management/delete-user">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete the user <strong id="deleteUsername"></strong>?</p>
                                <input type="hidden" id="deleteUserId" name="user_id">
                            </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

<!-- User Profile Modal with Projects -->
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <!-- Increased size for better layout -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="userProfileContainer">
                    <div class="row">
                        <!-- User Information Column -->
                        <div class="col-md-6">
                            <div id="userInfo" class="mb-4"  style = "color: white;">
                                <!-- User profile details will be injected here using JavaScript -->
                            </div>
                        </div>
                        <!-- Projects Section Column -->
                        <div class="col-md-6">
                            <!-- Projects Created -->
                            <div id="createdProjectsSection" class="mb-4">
                                <h5 style = "color: white;">Projects Created</h5>
                                <ul id="createdProjectsList" class="list-group">
                                    <!-- Created projects will be injected here -->
                                </ul>
                            </div>

                            <!-- Projects Collaborated On -->
                            <div id="collabProjectsSection">
                                <h5>Projects Collaborated On</h5>
                                <ul id="collabProjectsList" class="list-group">
                                    <!-- Collaborated projects will be injected here -->
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- JavaScript dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Include Popper.js before Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap 4.5.2 JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- Cropper.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <!-- bsCustomFileInput -->
    <script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
    <!-- Custom Scripts -->
    <script src="../js/script-sidebar.js"></script>
    <!-- JavaScript for modals and form handling -->
    <script>
        $(document).ready(function() {
            // Initialize bsCustomFileInput
            bsCustomFileInput.init();
            $('[data-toggle="tooltip"]').tooltip();
            $('[data-toggle="modal"]').tooltip();

            // Edit Button Click Handler
            $('.edit-btn').on('click', function(e) {
                e.preventDefault();

                // Fetch data attributes
                var userId = $(this).data('id');
                var username = $(this).data('username');
                var firstName = $(this).data('firstname');
                var middleInitial = $(this).data('middleinitial');
                var lastName = $(this).data('lastname');
                var suffix = $(this).data('suffix');
                var email = $(this).data('email');
                var idType = $(this).data('id-type');
                var idNumber = $(this).data('id-number');
                var position = $(this).data('position');
                var roleId = $(this).data('role-id');
                var roleName = $(this).data('role-name');
                var userPhoto = $(this).data('user-photo');

                // Populate the modal fields
                $('#editUserId').val(userId);
                $('#editUsername').val(username);
                $('#editFirstName').val(firstName);
                $('#editMiddleInitial').val(middleInitial);
                $('#editLastName').val(lastName);
                $('#editUserSuffix').val(suffix);
                $('#editEmail').val(email);
                $('#editIdType').val(idType);
                $('#editIdNumber').val(idNumber);
                $('#editPosition').val(position);
                $('#editRole').val(roleId);

                // Show or hide 'Other' Position textbox
                if (position === "Others") {
                    $('#editOtherPositionDiv').show();
                } else {
                    $('#editOtherPositionDiv').hide();
                }

                // Update profile picture
                var profilePicPath = userPhoto;
                if (userPhoto && userPhoto.trim() !== "") {
                    var img = new Image();
                    img.onload = function() {
                        $('#editImagePreview').attr('src', profilePicPath).show();
                    };
                    img.onerror = function() {
                        $('#editImagePreview').attr('src', '../images/default-pic.jpg').show();
                    };
                    img.src = profilePicPath;
                } else {
                    $('#editImagePreview').attr('src', '../images/default-pic.jpg').show();
                }

                // Disable ID Number if ID Type is 'None'
                if (idType === "None") {
                    $('#editIdNumber').prop('disabled', true).val("");
                } else {
                    $('#editIdNumber').prop('disabled', false);
                }

                // Log the populated data for debugging
                console.log({
                    userId: userId,
                    username: username,
                    firstName: firstName,
                    lastName: lastName,
                    middleInitial: middleInitial,
                    suffix: suffix,
                    email: email,
                    idType: idType,
                    idNumber: idNumber,
                    position: position,
                    roleId: roleId,
                    roleName: roleName,
                    userPhoto: userPhoto
                });

                // Show the modal
                $('#editUserModal').modal('show');
            });

            // Delete Button Click Handler
            $('.delete-btn').on('click', function(e) {
                e.preventDefault();
                var userId = $(this).data('id');
                var username = $(this).data('username');

                $('#deleteUserId').val(userId);
                $('#deleteUsername').text(username);
                
                $('#deleteUserModal').modal('show');
            });

            // View Button Click Handler
            $(".view-btn").on("click", function() {
                var userId = $(this).data('id');

                // Clear previous content
                $("#userInfo").html('<p>Loading...</p>');
                $("#createdProjectsList").empty();
                $("#collabProjectsList").empty();

                // AJAX request to fetch user details and projects
                $.ajax({
                    url: '../users-management/get-user-details',
                    type: 'POST',
                    data: { user_id: userId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            var user = response.user;
                            var createdProjects = response.created_projects;
                            var collabProjects = response.collab_projects;

                            // Build User Info HTML
                            var userInfoHtml = `
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <img src="${user.user_photo && user.user_photo.trim() !== '' ? user.user_photo : '../images/default-pic.jpg'}" alt="Profile Picture" class="img-fluid rounded-circle mb-2" style="max-width: 150px;">
                                    </div>
                                    <div class="col-md-8">
                                        <h3>${user.user_first_name} ${user.user_middle_initial ? user.user_middle_initial + '.' : ''} ${user.user_last_name}${user.user_suffix && user.user_suffix !== 'None' ? ', ' + user.user_suffix : ''}</h3>
                                        <p><strong>Username:</strong> ${user.user_username}</p>
                                        <p><strong>Email:</strong> ${user.user_email}</p>
                                        <p><strong>Position:</strong> ${user.user_position}</p>
                                        <p><strong>Role:</strong> ${user.role_name}</p>
                                        <p><strong>ID Type:</strong> ${user.user_id_type}</p>
                                        <p><strong>ID Number:</strong> ${user.user_id_number}</p>
                                    </div>
                                </div>
                            `;
                            $("#userInfo").html(userInfoHtml);

                            // Populate Created Projects
                            if (createdProjects.length > 0) {
                                createdProjects.forEach(function(proj) {
                                    var projectItem = `
                                        <li class="list-group-item">
                                            <strong>ID:</strong> ${proj.proj_ID} <br>
                                            <strong>Project Name:</strong> ${proj.proj_cont_name} <br>
                                            <strong>Status:</strong> ${proj.proj_status} <br>
                                            <strong>Progress:</strong> ${proj.proj_progress}% <br>
                                            <strong>Uploaded On:</strong> ${proj.proj_uploaded}
                                        </li>
                                    `;
                                    $("#createdProjectsList").append(projectItem);
                                });
                            } else {
                                $("#createdProjectsList").append('<li class="list-group-item">No projects created.</li>');
                            }

                            // Populate Collaborated Projects
                            if (collabProjects.length > 0) {
                                collabProjects.forEach(function(proj) {
                                    var projectItem = `
                                        <li class="list-group-item">
                                            <strong>ID:</strong> ${proj.proj_ID} <br>
                                            <strong>Project Name:</strong> ${proj.proj_cont_name} <br>
                                            <strong>Status:</strong> ${proj.proj_status} <br>
                                            <strong>Progress:</strong> ${proj.proj_progress}% <br>
                                            <strong>Uploaded On:</strong> ${proj.proj_uploaded}
                                        </li>
                                    `;
                                    $("#collabProjectsList").append(projectItem);
                                });
                            } else {
                                $("#collabProjectsList").append('<li class="list-group-item">No collaborated projects.</li>');
                            }

                        } else {
                            $("#userInfo").html('<p class="text-danger">Error fetching user details.</p>');
                            $("#createdProjectsList").append('<li class="list-group-item">N/A</li>');
                            $("#collabProjectsList").append('<li class="list-group-item">N/A</li>');
                        }
                    },
                    error: function() {
                        $("#userInfo").html('<p class="text-danger">An error occurred while fetching user details.</p>');
                        $("#createdProjectsList").append('<li class="list-group-item">N/A</li>');
                        $("#collabProjectsList").append('<li class="list-group-item">N/A</li>');
                    }
                });
            });

            // Handle User Profile View with Projects
            $(".view-btn").on("click", function() {
                var userId = $(this).data('id');

                // Clear previous content
                $("#userInfo").html('<p>Loading...</p>');
                $("#createdProjectsList").empty();
                $("#collabProjectsList").empty();

                // Make AJAX request to fetch user details and projects
                $.ajax({
                    url: '../users-management/get-user-details',
                    type: 'POST',
                    data: { user_id: userId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            var user = response.user;
                            var createdProjects = response.created_projects;
                            var collabProjects = response.collab_projects;

                            // Build User Info HTML
                            var userInfoHtml = `
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <img src="${user.user_photo && user.user_photo.trim() !== '' ? user.user_photo : '../images/default-pic.jpg'}" alt="Profile Picture" class="img-fluid rounded-circle mb-2" style="max-width: 150px;">
                                    </div>
                                    <div class="col-md-8">
                                        <h3>${user.user_first_name} ${user.user_middle_initial ? user.user_middle_initial + '.' : ''} ${user.user_last_name}${user.user_suffix && user.user_suffix !== 'None' ? ', ' + user.user_suffix : ''}</h3>
                                        <p><strong>Username:</strong> ${user.user_username}</p>
                                        <p><strong>Email:</strong> ${user.user_email}</p>
                                        <p><strong>Position:</strong> ${user.user_position}</p>
                                        <p><strong>Role:</strong> ${user.role_name}</p>
                                        <p><strong>ID Type:</strong> ${user.user_id_type}</p>
                                        <p><strong>ID Number:</strong> ${user.user_id_number}</p>
                                    </div>
                                </div>
                            `;
                            $("#userInfo").html(userInfoHtml);

                            // Populate Created Projects
                            if (createdProjects.length > 0) {
                                createdProjects.forEach(function(proj) {
                                    var projectItem = `
                                        <li class="list-group-item">
                                            <strong>ID:</strong> ${proj.proj_ID} <br>
                                            <strong>Project Name:</strong> ${proj.proj_cont_name} <br>
                                            <strong>Status:</strong> ${proj.proj_status} <br>
                                            <strong>Progress:</strong> ${proj.proj_progress}% <br>
                                            <strong>Uploaded On:</strong> ${proj.proj_uploaded}
                                        </li>
                                    `;
                                    $("#createdProjectsList").append(projectItem);
                                });
                            } else {
                                $("#createdProjectsList").append('<li class="list-group-item">No projects created.</li>');
                            }

                            // Populate Collaborated Projects
                            if (collabProjects.length > 0) {
                                collabProjects.forEach(function(proj) {
                                    var projectItem = `
                                        <li class="list-group-item">
                                            <strong>ID:</strong> ${proj.proj_ID} <br>
                                            <strong>Project Name:</strong> ${proj.proj_cont_name} <br>
                                            <strong>Status:</strong> ${proj.proj_status} <br>
                                            <strong>Progress:</strong> ${proj.proj_progress}% <br>
                                            <strong>Uploaded On:</strong> ${proj.proj_uploaded}
                                        </li>
                                    `;
                                    $("#collabProjectsList").append(projectItem);
                                });
                            } else {
                                $("#collabProjectsList").append('<li class="list-group-item">No collaborated projects.</li>');
                            }

                        } else {
                            $("#userInfo").html('<p class="text-danger">Error fetching user details.</p>');
                            $("#createdProjectsList").append('<li class="list-group-item">N/A</li>');
                            $("#collabProjectsList").append('<li class="list-group-item">N/A</li>');
                        }
                    },
                    error: function() {
                        $("#userInfo").html('<p class="text-danger">An error occurred while fetching user details.</p>');
                        $("#createdProjectsList").append('<li class="list-group-item">N/A</li>');
                        $("#collabProjectsList").append('<li class="list-group-item">N/A</li>');
                    }
                });
            });

            // Image Cropping and Other Functions
            // Add User Modal Image Cropping
            let addCropperInstance;
            const addImageInput = document.getElementById('addImageInput');
            const addImagePreview = document.getElementById('addImagePreview');
            const addCroppedImageDataInput = document.getElementById('addCroppedImageData');

            addImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Client-side validation
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/jpg'];
                    const maxSize = 2 * 1024 * 1024; // 2MB

                    if (!allowedTypes.includes(file.type)) {
                        alert('Please upload an image file (JPEG, PNG, GIF).');
                        addImageInput.value = '';
                        $('.custom-file-label[for="addImageInput"]').text('Choose Profile Picture');
                        return;
                    }

                    if (file.size > maxSize) {
                        alert('File size exceeds 2MB.');
                        addImageInput.value = '';
                        $('.custom-file-label[for="addImageInput"]').text('Choose Profile Picture');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        addImagePreview.src = e.target.result;
                        addImagePreview.style.display = 'block';

                        // Initialize Cropper.js with 1x1 aspect ratio
                        if (addCropperInstance) {
                            addCropperInstance.destroy(); // Destroy previous cropper instance
                        }
                        addCropperInstance = new Cropper(addImagePreview, {
                            aspectRatio: 1, // 1x1 aspect ratio
                            viewMode: 1,
                            preview: '.img-preview',
                            crop(event) {
                                const canvas = addCropperInstance.getCroppedCanvas({
                                    width: 300, // Output size
                                    height: 300,
                                });
                                addCroppedImageDataInput.value = canvas.toDataURL('image/jpeg'); // Store base64 in hidden input
                            }
                        });
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Edit User Modal Image Cropping
            let editCropperInstance;
            const editImageInput = document.getElementById('editImageInput');
            const editImagePreview = document.getElementById('editImagePreview');
            const editCroppedImageDataInput = document.getElementById('editCroppedImageData');

            editImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Client-side validation
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/jpg'];
                    const maxSize = 2 * 1024 * 1024; // 2MB

                    if (!allowedTypes.includes(file.type)) {
                        alert('Please upload an image file (JPEG, PNG, GIF).');
                        editImageInput.value = '';
                        $('.custom-file-label[for="editImageInput"]').text('Choose Profile Picture');
                        return;
                    }

                    if (file.size > maxSize) {
                        alert('File size exceeds 2MB.');
                        editImageInput.value = '';
                        $('.custom-file-label[for="editImageInput"]').text('Choose Profile Picture');
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        editImagePreview.src = e.target.result;
                        editImagePreview.style.display = 'block';

                        // Initialize Cropper.js with 1x1 aspect ratio
                        if (editCropperInstance) {
                            editCropperInstance.destroy(); // Destroy previous cropper instance
                        }
                        editCropperInstance = new Cropper(editImagePreview, {
                            aspectRatio: 1, // 1x1 aspect ratio
                            viewMode: 1,
                            preview: '.img-preview',
                            crop(event) {
                                const canvas = editCropperInstance.getCroppedCanvas({
                                    width: 300, // Output size
                                    height: 300,
                                });
                                editCroppedImageDataInput.value = canvas.toDataURL('image/jpeg'); // Store base64 in hidden input
                            }
                        });
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Function to toggle 'Other' Position textbox in Add User Modal
            window.toggleAddOtherPosition = function(selectElement) {
                const otherPositionDiv = document.getElementById('addOtherPositionDiv');
                if (selectElement.value === 'Others') {
                    otherPositionDiv.style.display = 'flex';
                } else {
                    otherPositionDiv.style.display = 'none';
                }
            }

            // Function to toggle 'Other' Position textbox in Edit User Modal
            window.toggleEditOtherPosition = function(selectElement) {
                const otherPositionDiv = document.getElementById('editOtherPositionDiv');
                if (selectElement.value === 'Others') {
                    otherPositionDiv.style.display = 'flex';
                } else {
                    otherPositionDiv.style.display = 'none';
                }
            }

            // Function to toggle ID Number in Add User Modal
            window.toggleAddIdNumber = function() {
                var idType = document.getElementById("addIdType").value;
                var idNumber = document.getElementById("addIdNumber");

                if (idType === "None") {
                    idNumber.disabled = true;
                    idNumber.value = ""; // Clear the value if disabled
                } else {
                    idNumber.disabled = false;
                }
            }

            // Function to toggle ID Number in Edit User Modal
            window.toggleEditIdNumber = function() {
                var idType = document.getElementById("editIdType").value;
                var idNumber = document.getElementById("editIdNumber");

                if (idType === "None") {
                    idNumber.disabled = true;
                    idNumber.value = ""; // Clear the value if disabled
                } else {
                    idNumber.disabled = false;
                }
            }

            // Function to toggle password visibility
            function togglePasswordVisibility(event) {
                const toggleIcon = event.target;
                const inputSelector = toggleIcon.getAttribute('toggle');
                const passwordInput = document.querySelector(inputSelector);
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Toggle the eye slash icon
                toggleIcon.classList.toggle('fa-eye');
                toggleIcon.classList.toggle('fa-eye-slash');
            }

            // Attach event listeners to all elements with class 'toggle-password'
            document.querySelectorAll('.toggle-password').forEach(function(element) {
                element.addEventListener('click', togglePasswordVisibility);
            });
        });
    </script>
</body>
</html>

<?php
// Close the database connection
mysqli_close($conn);
?>
