<?php
// users-management/edit-user.php

include "../includes/database.php";
session_start();

// Function to sanitize input data
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Function to validate image
function validate_image($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/jpg'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "Error uploading image.";
    }

    // Check MIME type
    if (!in_array($file['type'], $allowedTypes)) {
        return "Unsupported image type. Please upload JPEG, PNG, GIF, WEBP, or AVIF images.";
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        return "Image size exceeds 2MB.";
    }

    // Check if file is an actual image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return "Uploaded file is not a valid image.";
    }

    return true;
}

// Ensure the user is an admin (role_id == 1)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to dashboard or show an error message
    header("Location: ../pages/dashboard.php");
    exit();
}

// Check if the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Establish the database connection
    $conn = ConnectDB();

    // Retrieve and sanitize form inputs
    $user_id = intval($_POST['user_id']);
    $username = sanitize_input($_POST['user_username']);
    $password = sanitize_input($_POST['user_password']); // Optional
    $firstName = sanitize_input($_POST['user_first_name']);
    $middleInitial = isset($_POST['user_middle_initial']) ? sanitize_input($_POST['user_middle_initial']) : '';
    $lastName = sanitize_input($_POST['user_last_name']);
    $suffix = sanitize_input($_POST['user_suffix']); // New field
    $email = sanitize_input($_POST['user_email']);
    $idType = sanitize_input($_POST['id_type']);
    $idNumber = sanitize_input($_POST['id_number']);
    $position = sanitize_input($_POST['user_position']);
    $role_id = intval($_POST['role_id']);
    $other_position = isset($_POST['other_position']) ? sanitize_input($_POST['other_position']) : '';
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Handle ID Number: Set to 'None' if ID Type is 'None'
    if ($idType === 'None') {
        $idNumber = 'None';
    }

    // If position is 'Others', use the other_position input
    if ($position === 'Others' && !empty($other_position)) {
        $position = $other_position;
    }

    // Initialize variables for password update
    $updatePassword = false;

    // Check if password needs to be updated
    if (!empty($password)) {
        $updatePassword = true;
        // Password hashing removed as per request
    }

    // Handle Image Upload
    $new_photo_path = '';
    $uploadDirectory = '../uploads/profile_pics/';

    if (isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imageValidation = validate_image($_FILES['imageUpload']);
        if ($imageValidation !== true) {
            $_SESSION['error_message'] = $imageValidation;
            header("Location: ../users-management/all-users.php");
            exit();
        }

        // Generate a unique file name
        $fileName = 'profile_' . uniqid() . '.' . pathinfo($_FILES['imageUpload']['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDirectory . $fileName;

        // Ensure the uploads directory exists
        if (!file_exists($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0755, true)) {
                $_SESSION['error_message'] = "Failed to create upload directory.";
                header("Location: ../users-management/all-users.php");
                exit();
            }
        }

        // Move the uploaded file to the desired directory
        if (move_uploaded_file($_FILES['imageUpload']['tmp_name'], $filePath)) {
            $new_photo_path = $filePath;
        } else {
            $_SESSION['error_message'] = "Failed to upload the image.";
            header("Location: ../users-management/all-users.php");
            exit();
        }
    }

    // Validate required fields
    if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($idType) || empty($position) || empty($role_id)) {
        $_SESSION['error_message'] = "All fields except password are required.";
        // If a new image was uploaded, delete it since the operation failed
        if (!empty($new_photo_path)) {
            unlink($new_photo_path);
        }
        header("Location: ../users-management/all-users.php");
        exit();
    }

    // Validate middle initial length (if provided)
    if (!empty($middleInitial) && strlen($middleInitial) != 1) {
        $_SESSION['error_message'] = "Middle Initial must be a single character.";
        // If a new image was uploaded, delete it since the operation failed
        if (!empty($new_photo_path)) {
            unlink($new_photo_path);
        }
        header("Location: ../users-management/all-users.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format.";
        // If a new image was uploaded, delete it since the operation failed
        if (!empty($new_photo_path)) {
            unlink($new_photo_path);
        }
        header("Location: ../users-management/all-users.php");
        exit();
    }

    // Check if the username or email already exists for another user
    $checkQuery = "SELECT user_id FROM users WHERE (user_username = ? OR user_email = ?) AND user_id != ?";
    if ($stmt = mysqli_prepare($conn, $checkQuery)) {
        mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $_SESSION['error_message'] = "Username or Email already exists.";
            mysqli_stmt_close($stmt);
            // If a new image was uploaded, delete it since the operation failed
            if (!empty($new_photo_path)) {
                unlink($new_photo_path);
            }
            header("Location: ../users-management/all-users.php");
            exit();
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Database error: " . mysqli_error($conn);
        // If a new image was uploaded, delete it since the operation failed
        if (!empty($new_photo_path)) {
            unlink($new_photo_path);
        }
        header("Location: ../users-management/all-users.php");
        exit();
    }

    // Retrieve current user data to handle old image deletion if necessary
    $currentPhoto = '';
    $currentPhotoQuery = "SELECT user_photo FROM users WHERE user_id = ?";
    if ($stmt = mysqli_prepare($conn, $currentPhotoQuery)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $currentPhoto);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }

    // Build the update query dynamically based on whether password and/or photo are being updated
    if ($updatePassword && !empty($new_photo_path)) {
        // Both password and photo are being updated
        $updateQuery = "UPDATE users SET user_username = ?, user_password = ?, user_first_name = ?, user_middle_initial = ?, user_last_name = ?, 
                       user_suffix = ?, user_email = ?, role_id = ?, user_id_type = ?, user_id_number = ?, user_position = ?, user_photo = ? 
                       WHERE user_id = ?";
    } elseif ($updatePassword) {
        // Only password is being updated
        $updateQuery = "UPDATE users SET user_username = ?, user_password = ?, user_first_name = ?, user_middle_initial = ?, user_last_name = ?, 
                       user_suffix = ?, user_email = ?, role_id = ?, user_id_type = ?, user_id_number = ?, user_position = ? 
                       WHERE user_id = ?";
    } elseif (!empty($new_photo_path)) {
        // Only photo is being updated
        $updateQuery = "UPDATE users SET user_username = ?, user_first_name = ?, user_middle_initial = ?, user_last_name = ?, 
                       user_suffix = ?, user_email = ?, role_id = ?, user_id_type = ?, user_id_number = ?, user_position = ?, user_photo = ? 
                       WHERE user_id = ?";
    } else {
        // Neither password nor photo is being updated
        $updateQuery = "UPDATE users SET user_username = ?, user_first_name = ?, user_middle_initial = ?, user_last_name = ?, 
                       user_suffix = ?, user_email = ?, role_id = ?, user_id_type = ?, user_id_number = ?, user_position = ? 
                       WHERE user_id = ?";
    }

    if ($stmt = mysqli_prepare($conn, $updateQuery)) {
        if ($updatePassword && !empty($new_photo_path)) {
            // Both password and photo are being updated
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssissssi", // Type string: s=string, i=integer
                $username,
                $hashedPassword,
                $firstName,
                $middleInitial,
                $lastName,
                $suffix,
                $email,
                $role_id,
                $idType,
                $idNumber,
                $position,
                $new_photo_path,
                $user_id
            );
        } elseif ($updatePassword) {
            // Only password is being updated
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssisssi", // Type string: s=string, i=integer
                $username,
                $hashedPassword,
                $firstName,
                $middleInitial,
                $lastName,
                $suffix,
                $email,
                $role_id,
                $idType,
                $idNumber,
                $position,
                $user_id
            );
        } elseif (!empty($new_photo_path)) {
            // Only photo is being updated
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssissi", // Type string: s=string, i=integer
                $username,
                $firstName,
                $middleInitial,
                $lastName,
                $suffix,
                $email,
                $role_id,
                $idType,
                $idNumber,
                $position,
                $new_photo_path,
                $user_id
            );
        } else {
            // Neither password nor photo is being updated
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssssi", // Type string: s=string, i=integer
                $username,
                $firstName,
                $middleInitial,
                $lastName,
                $suffix,
                $email,
                $role_id,
                $idType,
                $idNumber,
                $position,
                $user_id
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "User updated successfully.";

            // If a new photo was uploaded and there was an old photo that's not default, delete the old photo
            if (!empty($new_photo_path) && !empty($currentPhoto) && $currentPhoto !== '../images/default-pic.jpg') {
                if (file_exists($currentPhoto)) {
                    unlink($currentPhoto);
                }
            }
        } else {
            $_SESSION['error_message'] = "Error updating user: " . mysqli_stmt_error($stmt);
            // If a new image was uploaded, delete it since the operation failed
            if (!empty($new_photo_path)) {
                unlink($new_photo_path);
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Database error: " . mysqli_error($conn);
        // If a new image was uploaded, delete it since the operation failed
        if (!empty($new_photo_path)) {
            unlink($new_photo_path);
        }
    }

    // Close the database connection
    mysqli_close($conn);

    // Redirect back to the users management page
    header("Location: ../users-management/all-users.php");
    exit();
} else {
    // If not a POST request, redirect to the users management page
    header("Location: ../users-management/all-users.php");
    exit();
}
?>
