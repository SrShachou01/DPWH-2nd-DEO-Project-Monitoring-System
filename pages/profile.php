<?php
// profile.php

require_once "../includes/database.php";
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Retrieve the current user's role_id from the session
$currentUserRoleId = $_SESSION['role_id'] ?? 3; // Default to 3 (Guest) if not set

$conn = ConnectDB();

// Fetch user projects
$query = "SELECT proj_ID, proj_cont_name, proj_effect_date, proj_expiry_date, proj_status 
          FROM projects 
          WHERE user_ID = ? AND proj_isDeleted = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Fetch user information (assuming it's stored in session)
$user_photo = htmlspecialchars($_SESSION['user_photo'] ?? '../images/default-pic.jpg');
$user_first_name = htmlspecialchars($_SESSION['user_first_name'] ?? 'First Name');
$user_middle_initial = htmlspecialchars($_SESSION['user_middle_initial'] ?? ''); // Added
$user_last_name = htmlspecialchars($_SESSION['user_last_name'] ?? 'Last Name');
$user_email = htmlspecialchars($_SESSION['user_email'] ?? 'Email not set');
$user_id_type = htmlspecialchars($_SESSION['user_id_type'] ?? 'ID Type not set');
$user_id_number = htmlspecialchars($_SESSION['user_id_number'] ?? 'ID Number not set');
$user_position = htmlspecialchars($_SESSION['user_position'] ?? 'Position not set');

// Determine role name
switch ($currentUserRoleId) {
    case 1:
        $roleName = 'Admin';
        break;
    case 2:
        $roleName = 'Member';
        break;
    case 3:
    default:        
        $roleName = 'Guest';
        break;
}

// Construct full name with middle initial if available
$full_name = $user_first_name;
if (!empty($user_middle_initial)) {
    $full_name .= ' ' . strtoupper($user_middle_initial) . '.';
}
$full_name .= ' ' . $user_last_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../css/styles-for-profile.css" rel="stylesheet">
    <link href="../css/styles-for-main.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid main-content">
    <?php include "../includes/navbar.php"; ?>
    
    <div class="row">
        <!-- Profile Information Section -->
        <section class="col-lg-4 col-md-5 col-12 profile-section">
            <div class="section-header text-white text-center">
                Profile Information
            </div>
            <div class="section-body">
                <div class="row align-items-center profile-details">
                    <!-- Centering the Profile Image -->
                    <div class="col-12 mb-4 text-center">
                        <div class="profile-img-container">
                        <img src="<?php echo $user_photo; ?>" alt="Profile Picture" class="profile-img border-<?php echo strtolower($roleName); ?>" data-toggle="tooltip" title="<?php echo $roleName; ?>">
                        </div>
                    </div>
                    <div class="col-12 text-center mt-2">
                        <h5 class="section-title mt-2">
                            <?php echo htmlspecialchars($full_name); ?>
                        </h5>
                        <p class="section-text mb-2"><?php echo $user_position; ?></p>
                        <div class="additional-info mt-3"> 
                            <p><strong>Email:</strong> <?php echo $user_email; ?></p>
                            <p><strong>ID Type:</strong> <?php echo $user_id_type; ?></p>
                            <p><strong>ID Number:</strong> <?php echo $user_id_number; ?></p>
                        </div>
                        <div class="mt-5 mb-5 edit-profile-button" data-toggle="tooltip" title="Edit Profile">
                            <a href="#" data-toggle="modal" data-target="#editProfileModal" class="btn btn-sm">Edit Profile</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Your Projects Section -->
        <section class="col-lg-8 col-md-7 col-12 projects-section" >
            <div class="section-header text-white text-center" style="background-color: #E67040;">
                Your Projects
            </div>
            <div class="section-body">
                <!-- Updated Projects Table Container with Specific Class -->
                <div class="table-responsive projects-table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Project ID</th>
                                <th>Project Name</th>
                                <th>Effectivity Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                            <tbody style="color: black;">
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><a href="../project-management/edit-project.php?proj_ID=<?php echo urlencode($row['proj_ID']); ?>&step=4"><?php echo htmlspecialchars($row['proj_ID']); ?></a></td>
                                            <td><?php echo htmlspecialchars($row['proj_cont_name']); ?></td>
                                            <td><?php echo date("F d, Y", strtotime($row['proj_effect_date'])); ?></td>
                                            <td><?php echo date("F d, Y", strtotime($row['proj_expiry_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['proj_status']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No projects found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                    </table>
                </div>
                <?php if ($currentUserRoleId == 1 || $currentUserRoleId == 2): ?>
                <div class="mt-3 profile-button" style="padding-bottom: 10px; color: white;" data-toggle="tooltip" title="Add new project">
                    <a href="../project-management/add-project.php" class="btn btn-primary btn-sm" style="color: white;">Add New Project</a>
                </div>
                <?php endif; ?>
            </div>
        </section>  
    </div>

<!-- Modal for Editing Profile Information -->
<div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Edit Profile Information</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row">
                    <!-- Vertical Tabs Navigation -->
                    <div class="col-md-3 mb-3">
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                            <a class="nav-link active" id="v-pills-change-photo-tab" data-toggle="pill" href="#v-pills-change-photo" role="tab" aria-controls="v-pills-change-photo" aria-selected="true">
                                <i class="fas fa-camera mr-2"></i>Change Photo
                            </a>
                            <a class="nav-link" id="v-pills-change-info-tab" data-toggle="pill" href="#v-pills-change-info" role="tab" aria-controls="v-pills-change-info" aria-selected="false">
                                <i class="fas fa-user mr-2"></i>Change Profile Info
                            </a>
                            <a class="nav-link" id="v-pills-change-password-tab" data-toggle="pill" href="#v-pills-change-password" role="tab" aria-controls="v-pills-change-password" aria-selected="false">
                                <i class="fas fa-lock mr-2"></i>Change Password
                            </a>
                        </div>
                    </div>
                    <!-- Tabs Content -->
                    <div class="col-md-9">
                        <div class="tab-content" id="v-pills-tabContent">
                            <!-- Change Photo Tab -->
                            <div class="tab-pane fade show active" id="v-pills-change-photo" role="tabpanel" aria-labelledby="v-pills-change-photo-tab">
                                <form id="cropImageForm" method="post" enctype="multipart/form-data">
                                    <div class="form-group" style = "color: white;">
                                        <label for="profilePicInput"><strong>Upload New Profile Picture</strong></label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="profilePicInput" name="profilePic" accept="image/*" required>
                                            <label class="custom-file-label" for="profilePicInput">Choose Profile Picture</label>
                                        </div>
                                        <small class="form-text">Supported formats: JPEG, PNG, GIF. Maximum size: 2MB.</small>
                                    </div>
                                    <!-- Image preview and cropper -->
                                    <div class="mb-3">
                                        <img id="profileImagePreview" style="max-width: 100%; display: none;" alt="Profile Picture Preview">
                                    </div>
                                    <div class="text-right">
                                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            <!-- Change Profile Info Tab -->
                            <div class="tab-pane fade" id="v-pills-change-info" role="tabpanel" aria-labelledby="v-pills-change-info-tab" style = "color: white;">
                                <form action="../profile-management/change-profile-info" method="POST">
                                    <!-- First Row: First Name, Middle Initial, Last Name -->
                                    <div class="form-row" style = "color: white;">
                                        <div class="form-group col-md-4">
                                            <label for="firstName">First Name</label>
                                            <input type="text" class="form-control form-control-sm" id="firstName" name="firstName" value="<?php echo $user_first_name; ?>" required>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="middleInitial">Middle Initial</label>
                                            <input type="text" class="form-control form-control-sm" id="middleInitial" name="middleInitial" value="<?php echo $user_middle_initial; ?>" maxlength="1">
                                        </div>
                                        <div class="form-group col-md-5">
                                            <label for="lastName">Last Name</label>
                                            <input type="text" class="form-control form-control-sm" id="lastName" name="lastName" value="<?php echo $user_last_name; ?>" required>
                                        </div>
                                    </div>
                                    <!-- Second Row: Email -->
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" class="form-control form-control-sm" id="email" name="email" value="<?php echo $user_email; ?>" required>
                                    </div>
                                    <!-- Third Row: ID Type and ID Number -->
                                    <div class="form-row">
                                        <div class="form-group col-md-6"  >
                                            <label for="idType">ID Type</label>
                                            <select class="form-control form-control-sm" id="idType" name="idType" required onchange="toggleIdNumberInfo()">
                                                <option value="PRC ID" <?php echo ($user_id_type == 'PRC ID') ? 'selected' : ''; ?>>PRC ID</option>
                                                <option value="ME ID" <?php echo ($user_id_type == 'ME ID') ? 'selected' : ''; ?>>ME ID</option>
                                                <option value="Others" <?php echo ($user_id_type == 'Others') ? 'selected' : ''; ?>>Others</option>
                                                <option value="None" <?php echo ($user_id_type == 'None') ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="idNumber">ID Number</label>
                                            <input type="text" class="form-control form-control-sm" id="idNumber" name="idNumber" value="<?php echo $user_id_number; ?>" <?php echo ($user_id_type == 'None') ? 'disabled' : 'required'; ?>>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            <!-- Change Password Tab -->
                            <div class="tab-pane fade" id="v-pills-change-password" role="tabpanel" aria-labelledby="v-pills-change-password-tab" style = "color: white;">
                                <form action="../profile-management/change-username-password" method="POST">
                                    <!-- Current Password -->
                                    <div class="form-group">
                                        <label for="currentPassword">Enter Current Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control form-control-sm" id="currentPassword" name="currentPassword" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text toggle-password" data-target="#currentPassword">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- New Password -->
                                    <div class="form-group">
                                        <label for="newPassword">Enter New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control form-control-sm" id="newPassword" name="newPassword" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text toggle-password" data-target="#newPassword">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Confirm New Password -->
                                    <div class="form-group">
                                        <label for="confirmPassword">Re-enter New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control form-control-sm" id="confirmPassword" name="confirmPassword" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text toggle-password" data-target="#confirmPassword">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
             </div>
    </div>
</div>

<!-- Modal for Editing Profile Information End -->

<!-- Bootstrap JS, jQuery, Popper.js, Cropper.js, and bsCustomFileInput -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.1/dist/js/adminlte.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="../js/script-sidebar.js"></script>

<!-- CropperJS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
<!-- bsCustomFileInput -->
<script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>

<script>
    $(document).ready(function () {
        bsCustomFileInput.init();
        // Initialize Bootstrap tooltips
        $('[data-toggle="tooltip"]').tooltip();
    });
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="modal"]').tooltip();
    // Toggle Password Visibility
    function togglePasswordVisibility(event) {
        const toggleIcon = event.target.closest('.toggle-password');
        if (!toggleIcon) return;
        const inputSelector = toggleIcon.getAttribute('data-target');
        const passwordInput = document.querySelector(inputSelector);
        if (!passwordInput) return;
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        // Toggle the eye slash icon
        toggleIcon.classList.toggle('fa-eye');
        toggleIcon.classList.toggle('fa-eye-slash');
    }

    // Attach event listeners to all elements with class 'toggle-password'
    document.addEventListener('click', togglePasswordVisibility);

    // Handle ID Number Enable/Disable
    function toggleIdNumberInfo() {
        var idType = document.getElementById("idType").value;
        var idNumber = document.getElementById("idNumber");

        if (idType === "None") {
            idNumber.disabled = true;
            idNumber.value = ""; // Clear the value if disabled
        } else {
            idNumber.disabled = false;
        }
    }

    // Initial check on page load
    document.addEventListener('DOMContentLoaded', toggleIdNumberInfo);

    // Add event listener for ID Type change
    document.getElementById("idType").addEventListener("change", toggleIdNumberInfo);

    // Handle Profile Picture Change in Modal
    let cropperProfile;
    const profilePicInput = document.getElementById('profilePicInput');
    const profileImagePreview = document.getElementById('profileImagePreview');

    profilePicInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Client-side validation
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/jpg'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!allowedTypes.includes(file.type)) {
                alert('Please upload an image file (JPEG, PNG, GIF).');
                profilePicInput.value = '';
                $('.custom-file-label[for="profilePicInput"]').text('Choose Profile Picture');
                return;
            }

            if (file.size > maxSize) {
                alert('File size exceeds 2MB.');
                profilePicInput.value = '';
                $('.custom-file-label[for="profilePicInput"]').text('Choose Profile Picture');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                profileImagePreview.src = e.target.result;
                profileImagePreview.style.display = 'block';

                // Initialize Cropper.js with 1x1 aspect ratio
                if (cropperProfile) {
                    cropperProfile.destroy(); // Destroy previous cropper instance
                }
                cropperProfile = new Cropper(profileImagePreview, {
                    aspectRatio: 1, // 1x1 aspect ratio
                    viewMode: 1,
                    preview: '.img-preview',
                    crop(event) {
                        // You can handle real-time cropping if needed
                    }
                });
            };
            reader.readAsDataURL(file);
        }
    });

    // Handle Crop and Upload in Modal
    document.getElementById('cropImageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (cropperProfile) {
            cropperProfile.getCroppedCanvas({
                width: 200,
                height: 200,
            }).toBlob(function(blob) {
                const formData = new FormData();
                formData.append('profilePic', blob, 'cropped.jpg');

                // Send cropped image to the server
                fetch('../profile-management/upload-profile-pic', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Handle success/failure
                    alert(data);
                    window.location.reload(); // Reload the page to reflect changes
                })
                .catch(error => console.error('Error:', error));
            });
        } else {
            alert('Please select and crop an image.');
        }
    });
</script>
</body>
</html>
