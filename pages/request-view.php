<?php
// request-view.php
include '../includes/database.php';
session_start();

// Only allow logged-in Admin users to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

$db = ConnectDB();

// Query all new accounts (role_id = 4)
$query = "SELECT user_id, user_username, user_first_name, user_middle_initial, user_last_name, user_suffix, user_email, user_position, user_photo 
          FROM users WHERE role_id = 4";
$result = $db->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Account Requests</title>
    <!-- Bootstrap 4.5.2 CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Main stylesheet (matching report.php) -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        /* Custom styles to match the report.php layout */
        .btn {
            border-radius: 20px;
        }
        .table-container {
            flex: 1 1 auto;
            overflow-x: auto;
            overflow-y: auto;
            height: auto;
            position: relative;
        }
        .table th, .table td {
            padding: 10px;
            vertical-align: top;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
        }
        .table th {
            background-color: #E67040;
            color: #fff;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 99;
        }
        .table-container::-webkit-scrollbar {
            height: 10px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background-color: #aaa;
            border-radius: 6px;
        }
        .table-container::-webkit-scrollbar-track {
            background-color: #f1f1f1;
        }
        .col-actions {
            width: 100px;
            min-width: 100px;
            overflow: visible;
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>

    <div id="content" class="container-fluid">
        <?php include "../includes/navbar.php"; ?>


        <div class="table-container mt-3">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Position</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()) { 
                        // Build the full name with middle initial and suffix if available
                        $fullName = $row['user_first_name'];
                        if (!empty($row['user_middle_initial'])) {
                            $fullName .= ' ' . strtoupper($row['user_middle_initial']) . '.';
                        }
                        $fullName .= ' ' . $row['user_last_name'];
                        if (!empty($row['user_suffix']) && $row['user_suffix'] !== 'None') {
                            $fullName .= ', ' . htmlspecialchars($row['user_suffix']);
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($fullName); ?></td>
                        <td><?php echo htmlspecialchars($row['user_email']); ?></td>
                        <td><?php echo htmlspecialchars($row['user_position']); ?></td>
                        <td class="col-actions">
                            <!-- Approve button triggers the modal -->
                            <button type="button" class="btn btn-primary approveBtn" 
                                    data-user-id="<?php echo htmlspecialchars($row['user_id']); ?>" 
                                    data-toggle="modal" data-target="#confirmApproveModal"
                                    data-placement="top" title="Approve Account">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </td>
                    </tr>
                    <?php } ?>
                    <?php if ($result->num_rows === 0) { ?>
                    <tr>
                        <td colspan="5" class="text-center">No new account requests found.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmApproveModal" tabindex="-1" role="dialog" aria-labelledby="confirmApproveModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form method="post" action="../pages/approve-user" id="approveForm">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="confirmApproveModalLabel">Confirm Approval</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              Are you sure you want to approve this account?
              <input type="hidden" name="user_id" id="modalUserId" value="">
              <input type="hidden" name="approve" value="1">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Yes, Approve</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- JavaScript dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../js/script-for-main.js"></script>
    <script src="../js/script-sidebar.js"></script>
    <script>
        $(document).ready(function() {
            // When an approve button is clicked, set the user ID in the modal
            $('.approveBtn').on('click', function() {
                var userId = $(this).data('user-id');
                $('#modalUserId').val(userId); // Set the user_id to the hidden input
                console.log("User ID set to: " + userId);  // Debug the value being set
            });
        
            // Log form data submission to check if it's being passed correctly
            $('#approveForm').on('submit', function(e) {
                e.preventDefault(); // Prevent the default form submission for debugging
                console.log("Form data being submitted:", $(this).serialize());
                this.submit();  // Manually submit the form after logging
            });
        });
        </script>
</body>
</html>
