<?php
// profile-management/upload-profile-pic.php

require_once "../includes/database.php";
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profilePic']['tmp_name'];
        $fileName = $_FILES['profilePic']['name'];
        $fileSize = $_FILES['profilePic']['size'];
        $fileType = $_FILES['profilePic']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Allowed file extensions
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'jpeg'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Define maximum file size (2MB)
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($fileSize > $maxSize) {
                echo "File size exceeds the 2MB limit.";
                exit();
            }

            // Directory in which to save the uploaded file
            $uploadFileDir = '../uploads/profile_pics/';
            if (!file_exists($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Create a unique file name to prevent overwriting
            $newFileName = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            // Move the file to the uploads directory
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                // Update user_photo in the database
                $conn = ConnectDB();
                $user_photo = $dest_path; // Relative path to the image

                $sql = "UPDATE users SET user_photo = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $user_photo, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    // Update session variable
                    $_SESSION['user_photo'] = $user_photo;
                    echo "Profile picture updated successfully.";
                } else {
                    echo "Database update failed.";
                }
                $stmt->close();
                $conn->close();
            } else {
                echo "There was an error moving the uploaded file.";
            }
        } else {
            echo "Upload failed. Allowed file types: " . implode(", ", $allowedfileExtensions);
        }
    } else {
        echo "No file uploaded or there was an upload error.";
    }
} else {
    echo "Invalid request method.";
}
?>
