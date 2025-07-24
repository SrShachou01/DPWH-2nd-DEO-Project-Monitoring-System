<?php
// index.php

session_start(); // Start the session at the very beginning

require_once('includes/database.php');

// Initialize messages
$success_message = "";
$error_message = "";

// Retrieve and unset error messages from session (if any)
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear the message after displaying
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}

// Function to add a new position to the user_position enum
function addNewPositionToEnum($db, $new_position) {
    // Prevent adding 'Admin' via this function
    if (strcasecmp($new_position, 'Admin') === 0) {
        return false;
    }

    // Fetch current enum values
    $result = $db->query("SHOW COLUMNS FROM users LIKE 'user_position'");
    if ($result && $row = $result->fetch_assoc()) {
        $enum = $row['Type']; // e.g., enum('None','Project Engineer',...)
        preg_match("/^enum\((.*)\)$/", $enum, $matches);
        $enum_values = explode(",", $matches[1]);
        $enum_values = array_map(function($value) {
            return trim($value, "'");
        }, $enum_values);

        // Check if the new position already exists
        if (!in_array($new_position, $enum_values)) {
            // Add the new position to the enum list
            $enum_values[] = $new_position;
            $new_enum = "enum('" . implode("','", $enum_values) . "')";
            $alter_sql = "ALTER TABLE users MODIFY user_position $new_enum NOT NULL";

            if ($db->query($alter_sql) === TRUE) {
                return true;
            } else {
                // Log the error or handle it accordingly
                return false;
            }
        }
    }
    return true; // Position already exists
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $db = ConnectDB();

    // Sanitize and retrieve form inputs
    $user_username = trim($_POST['username']);
    $user_password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $user_first_name = trim($_POST['fname']);
    $user_middle_initial = isset($_POST['user_middle_initial']) ? trim($_POST['user_middle_initial']) : '';
    $user_last_name = trim($_POST['lname']);
    $user_suffix = isset($_POST['user_suffix']) ? trim($_POST['user_suffix']) : 'None'; // New field
    $user_email = trim($_POST['email']);
    $user_id_type = trim($_POST['id_type']);
    $role_id = 4; // Default role ID for new users (guest)

    // Validate middle initial (optional, must be a single character if provided)
    if (!empty($user_middle_initial) && strlen($user_middle_initial) !== 1) {
        $error_message = "Middle initial must be a single character.";
    }

    // Handle Position
    if ($_POST['position'] === 'Others') {
        if (!empty(trim($_POST['other_position']))) {
            $new_position = trim($_POST['other_position']);

            // **Prevent setting position to 'Admin' via 'Others'**
            if (strcasecmp($new_position, 'Admin') === 0) {
                $error_message = "Invalid position selected.";
            } else {
                // Add the new position to the enum
                if (addNewPositionToEnum($db, $new_position)) {
                    $user_position = $new_position;
                } else {
                    $error_message = "Failed to add the new position. Please try again.";
                }
            }
        } else {
            $error_message = "Please specify your position.";
        }
    } else {
        $user_position = trim($_POST['position']);
    }

    // **Ensure 'Admin' cannot be set directly via form manipulation**
    if ($user_position === 'Admin') {
        $error_message = "You are not authorized to set the position to Admin.";
    }

    // Handle ID Number
    if (isset($_POST['id_number']) && $user_id_type !== 'None') {
        $user_id_number = trim($_POST['id_number']);
    } else {
        $user_id_number = 'None';
    }

    // Handle Image Upload
    $user_photo = 'images/default-pic.jpg'; // Default picture
    if (isset($_POST['croppedImageData']) && !empty($_POST['croppedImageData'])) {
        $croppedImageData = $_POST['croppedImageData'];

        // Decode base64 string
        $imageData = explode(',', $croppedImageData)[1];
        $imageData = base64_decode($imageData);

        // Generate a unique file name
        $fileName = 'cropped_' . time() . '.jpg';
        $filePath = 'uploads/profile_pics/' . $fileName; // Absolute path for local testing

        // Ensure the uploads directory exists
        if (!file_exists('uploads/profile_pics/')) {
            mkdir('uploads/profile_pics/', 0755, true);
        }

        // Save the cropped image
        if (file_put_contents($filePath, $imageData)) {
            // Set the image path for the database (relative path)
            $user_photo = '../uploads/profile_pics/' . $fileName;
        } else {
            $error_message = "Failed to upload the profile picture.";
        }
    } elseif (isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] === UPLOAD_ERR_OK) {
        // Server-side validation for image upload
        $fileTmpPath = $_FILES['imageUpload']['tmp_name'];
        $fileType = mime_content_type($fileTmpPath);
        $fileSize = $_FILES['imageUpload']['size'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($fileType, $allowed_types)) {
            $error_message = "Unsupported image format. Please upload JPEG, PNG, or GIF.";
        } elseif ($fileSize > $max_size) {
            $error_message = "Image size exceeds 2MB.";
        } else {
            // Generate a unique file name
            $fileExtension = pathinfo($_FILES['imageUpload']['name'], PATHINFO_EXTENSION);
            $fileName = 'profile_' . time() . '.' . $fileExtension;
            $filePath = 'uploads/profile_pics/' . $fileName; // Absolute path for local testing

            // Ensure the uploads directory exists
            if (!file_exists('uploads/profile_pics/')) {
                mkdir('uploads/profile_pics/', 0755, true);
            }

            // Move the uploaded file
            if (move_uploaded_file($fileTmpPath, $filePath)) {
                // Set the image path for the database (relative path)
                $user_photo = '../uploads/profile_pics/' . $fileName;
            } else {
                $error_message = "Failed to upload the profile picture.";
            }
        }
    }


    // **Check for unique ID number**
    if (empty($error_message) && $user_id_number !== 'None') {
        // Prepare a statement to check if the ID number already exists
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE user_id_number = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $user_id_number);
            $stmt_check->execute();
            $stmt_check->bind_result($count);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($count > 0) {
                $error_message = "ID number must be unique.";
            }
        } else {
            $error_message = "Database error: " . $db->error;
        }
    }


    // Proceed only if there are no errors
    if (empty($error_message)) {
        // Prepare an SQL statement to prevent SQL injection
        $stmt = $db->prepare("INSERT INTO users (user_username, user_password, user_first_name, user_middle_initial, user_last_name, user_suffix, user_email, role_id, user_id_type, user_id_number, user_position, user_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            // Bind parameters with correct types
            $stmt->bind_param(
                "sssssssissss",
                $user_username,
                $user_password, // Store the hashed password
                $user_first_name,
                $user_middle_initial,
                $user_last_name,
                $user_suffix, // New parameter
                $user_email,
                $role_id,
                $user_id_type,
                $user_id_number,
                $user_position,
                $user_photo
            );


            // Execute the statement
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Registration successful! Please login.";
                // Redirect back to index.php after successful registration
                header("Location: index.php");
                exit;
            } else {
                $error_message = "Registration failed: " . $stmt->error;
            }

            // Close the statement
            $stmt->close();
        } else {
            $error_message = "Database error: " . $db->error;
        }
    }

    // If there was an error during registration, set the error message in session
    if (!empty($error_message)) {
        $_SESSION['error_message'] = $error_message;
        header("Location: index.php");
        exit;
    }

    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login & Registration</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"> 
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.1/dist/css/adminlte.min.css">
    <style>
        body {
            background: url('images/dpwh-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            display: flex;
            height: 100vh;
        }

        .sidebar {
            width: 30%;
            background-color: #3b7ce6;
            padding: 20px;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            text-align: center;
            opacity: 0.8;
        }

        .sidebar img {
            width: 100px;
            height: auto;
            margin-bottom: 20px;
        }
        .sidebar h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .sidebar p {
            font-size: 1rem;
            line-height: 1.5;
        }
        .content {
            margin-left: 30%; /* This leaves space for the sidebar */
            width: 70%;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .card {
            width: 400px;
            background-color: rgba(255, 255, 255, 0.0);
            padding: 20px;
            border-radius: 10px;
            box-shadow: none;
            position: relative;
            left: 50px; /* Adjust as needed */
        }

        .login-card-body {
            text-align: center;
            background-color: rgba(255, 255, 255, 0.0); /* Transparent background */
        }
        .modal-dialog {
            max-width: 600px;
        }
        .modal-centered {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        /* Styles for the toggle password icon */
        .toggle-password {
            font-size: 1rem;
            color: #6c757d;
        }
        .toggle-password:hover {
            color: #495057;
        }

        /* Remove the disabled-overlay styles as we are using a modal now */
        /* .disabled-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
            font-size: 1.2rem;
            color: #333;
            display: none; /* Hidden by default */
        /* } */
    </style>
</head>
<body class="hold-transition login-page">

    <div class="content">
        <div class="card position-relative">
            <!-- Lockout Modal -->
            <div class="modal fade" id="lockoutModal" tabindex="-1" role="dialog" aria-labelledby="lockoutModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="lockoutModalLabel">Account Locked</h5>
                        </div>
                        <div class="modal-body">
                            <p>Too many failed login attempts. Please try again in <span id="countdown">120</span> seconds.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body login-card-body">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" id="errorMessage">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <p class="login-box-msg" style="color: #1a4160; font-size: 35px; font-weight: bold;">Welcome Back!</p>

                <form action="logins/login" method="POST" id="loginForm">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Username" name="username" id="username" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user"></span>
                            </div>
                        </div>
                    </div>
                    <!-- Modified Password Field with Show Password Icon -->
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" placeholder="Password" name="password" id="loginPassword" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span toggle="#loginPassword" class="fas fa-eye toggle-password" style="cursor: pointer;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block" id="loginButton" style="background-color: #1e4564; border-color: #1e4564;">Login</button>
                        </div>
                    </div>
                </form>
                <p class="mb-0">
                <div class="text-center" style="color: white;">
                    <span class="mr-2">Not a member?</span>
                    <a href="#" data-toggle="modal" data-target="#registerModal" style="color: #173e5d;">Signup now</a>
                </div>
                </p>
            </div>
        </div>
    </div>
   
   <!-- Registration Modal -->
   <div class="modal fade" id="registerModal" tabindex="-1" role="dialog" aria-labelledby="registerModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create an Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <!-- First row: First Name, Middle Initial, Last Name, and Suffix -->
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <input type="text" class="form-control" placeholder="First Name" name="fname" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <input type="text" class="form-control" placeholder="Middle Initial" name="user_middle_initial" maxlength="1">
                            </div>
                            <div class="col-md-3 mb-3">
                                <input type="text" class="form-control" placeholder="Last Name" name="lname" required>
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
                                <input type="text" class="form-control" placeholder="Username" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="input-group">
                                    <input type="password" class="form-control" placeholder="Password" name="password" id="registerPassword" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <span toggle="#registerPassword" class="fas fa-eye toggle-password" style="cursor: pointer;"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Third row: Email -->
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Email" name="email" required>
                        </div>

                        <!-- Fourth row: ID Type and ID Number -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <select id="id_type" class="form-control" name="id_type" onchange="toggleIdNumber()" required>
                                    <option value="" disabled selected>Select ID Type</option>
                                    <option value="PRC ID">PRC ID</option>
                                    <option value="ME ID">ME ID</option>
                                    <option value="Accreditation Number">Accreditation Number</option>
                                    <option value="Others">Others</option>
                                    <option value="None">None</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <input type="text" id="id_number" class="form-control" name="id_number" placeholder="Enter ID Number">
                            </div>
                        </div>

                        <!-- Fifth row: Position -->
                        <div class="input-group mb-3">
                            <select id="position" class="form-control" name="position" required onchange="toggleOtherPosition(this)">
                                <option value="" disabled selected>Select Position</option>
                                <option value="None">None</option>
                                <option value="Project Engineer">Project Engineer</option>
                                <option value="Project Inspector">Project Inspector</option>
                                <option value="Materials Engineer">Materials Engineer</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>

                        <!-- Hidden textbox for 'Other' position -->
                        <div class="input-group mb-3" id="otherPositionDiv" style="display: none;">
                            <input type="text" class="form-control" placeholder="Enter Your Position" name="other_position">
                        </div>

                        <!-- Enhanced Image upload field -->
                        <div class="form-group">
                            <label for="imageInput"><strong>Upload Profile Picture</strong></label>
                            <div class="custom-file">
                                <input type="file" name="imageUpload" class="custom-file-input" id="imageInput" accept="image/*">
                                <label class="custom-file-label" for="imageInput">Choose Profile Picture</label>
                            </div>
                            <small class="form-text text-muted">Supported formats: JPEG, PNG, GIF. Maximum size: 2MB.</small>
                        </div>

                        <!-- Image preview and cropper -->
                        <div class="mb-3">
                            <img id="imagePreview" style="max-width: 100%; display: none;" alt="Profile Picture Preview">
                        </div>

                        <!-- Hidden field to store the cropped image -->
                        <input type="hidden" name="croppedImageData" id="croppedImageData">
                        <button type="submit" name="register" class="btn btn-primary btn-block">Register</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Lockout Modal (Bootstrap Modal) -->
    <div class="modal fade" id="lockoutModal" tabindex="-1" role="dialog" aria-labelledby="lockoutModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lockoutModalLabel">Account Locked</h5>
                </div>
                <div class="modal-body">
                    <p>Too many failed login attempts. Please try again in <span id="countdown">120</span> seconds.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS, jQuery, Popper.js, Cropper.js, and bsCustomFileInput -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.1/dist/js/adminlte.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- CropperJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <!-- bsCustomFileInput -->
    <script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>

    <script>
        $(document).ready(function () {
            bsCustomFileInput.init();

            <?php if (!empty($error_message) && strpos($error_message, 'locked') !== false): ?>
                // Extract remaining time from the error message
                var match = "<?php echo addslashes($error_message); ?>".match(/(\d+) minute\(s\) and (\d+) second\(s\)/);
                if (match) {
                    var minutes = parseInt(match[1]);
                    var seconds = parseInt(match[2]);
                    var totalSeconds = minutes * 60 + seconds;

                    disableLoginForm(totalSeconds);
                }
            <?php endif; ?>
        });

        function disableLoginForm(seconds) {
            $('#lockoutModal').modal('show');
            $('#loginButton').prop('disabled', true);
            $('#username').prop('disabled', true);
            $('#loginPassword').prop('disabled', true);

            var countdownElement = document.getElementById('countdown');
            var remainingSeconds = seconds;

            var countdownInterval = setInterval(function() {
                if (remainingSeconds <= 0) {
                    clearInterval(countdownInterval);
                    $('#lockoutModal').modal('hide');
                    $('#loginButton').prop('disabled', false);
                    $('#username').prop('disabled', false);
                    $('#loginPassword').prop('disabled', false);
                } else {
                    countdownElement.textContent = remainingSeconds;
                    remainingSeconds--;
                }
            }, 1000);
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

        // Function to toggle 'Other' Position textbox
        function toggleOtherPosition(selectElement) {
            const otherPositionDiv = document.getElementById('otherPositionDiv');
            if (selectElement.value === 'Others') {
                otherPositionDiv.style.display = 'flex';
            } else {
                otherPositionDiv.style.display = 'none';
            }
        }

        // Function to toggle ID Number input based on ID Type
        function toggleIdNumber() {
            var idType = document.getElementById("id_type").value;
            var idNumber = document.getElementById("id_number");

            if (idType === "None") {
                idNumber.disabled = true;
                idNumber.value = ""; // Clear the value if disabled
            } else {
                idNumber.disabled = false;
            }
        }

        // Image Cropper Initialization
        let cropper;
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        const croppedImageDataInput = document.getElementById('croppedImageData');

        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Client-side validation
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/jpg'];
                const maxSize = 2 * 1024 * 1024; // 2MB

                if (!allowedTypes.includes(file.type)) {
                    alert('Please upload an image file (JPEG, PNG, GIF).');
                    imageInput.value = '';
                    $('.custom-file-label').text('Choose Profile Picture');
                    return;
                }

                if (file.size > maxSize) {
                    alert('File size exceeds 2MB.');
                    imageInput.value = '';
                    $('.custom-file-label').text('Choose Profile Picture');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';

                    // Initialize Cropper.js with 1x1 aspect ratio
                    if (cropper) {
                        cropper.destroy(); // Destroy previous cropper instance
                    }
                    cropper = new Cropper(imagePreview, {
                        aspectRatio: 1, // 1x1 aspect ratio
                        viewMode: 1,
                        preview: '.img-preview',
                        crop(event) {
                            const canvas = cropper.getCroppedCanvas({
                                width: 300, // Output size
                                height: 300,
                            });
                            croppedImageDataInput.value = canvas.toDataURL('image/jpeg'); // Store base64 in hidden input
                        }
                    });
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
