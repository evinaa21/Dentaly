<?php
// Define page title *before* including header
$page_title = 'My Profile';
include '../includes/header.php'; // Include the shared header with the sidebar

// --- Check Role ---
// This file is in the receptionist folder, so check for receptionist role
if ($_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// --- Initialize Variables ---
$user_id = $_SESSION['user_id']; // Use generic user_id
$error_message = '';
$user_info = null;

// --- Fetch Current User Information ---
try {
    // Select user details based on session user_id and role
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = :id AND role = 'receptionist'"); // Check for receptionist
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        // Use a more generic error message
        throw new Exception("User profile not found.");
    }
} catch (Exception $e) {
    $error_message = "Error fetching profile data: " . $e->getMessage();
    // error_log("Profile Fetch Error (Receptionist): " . $e->getMessage());
}

// --- Handle Password Change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirmation password do not match.";
    } elseif (strlen($new_password) < 6) { // Basic length check
        $error_message = "New password must be at least 6 characters long.";
    } else {
        try {
            // Verify current password
            $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
            $pass_stmt->bindParam(':id', $user_id, PDO::PARAM_INT); // Use user_id
            $pass_stmt->execute();
            $user = $pass_stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($current_password, $user['password'])) {
                // Hash new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password in database
                $update_pass_stmt = $conn->prepare("UPDATE users SET password = :new_password WHERE id = :id");
                $update_pass_stmt->bindParam(':new_password', $new_password_hash, PDO::PARAM_STR);
                $update_pass_stmt->bindParam(':id', $user_id, PDO::PARAM_INT); // Use user_id
                $update_pass_stmt->execute();

                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Password changed successfully!'];
                header("Location: my_profile.php"); // Redirect to the same page
                exit;

            } else {
                $error_message = "Incorrect current password.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error changing password.'];
            // error_log("Password Change Error (Receptionist): " . $e->getMessage());
            header("Location: my_profile.php"); // Redirect even on error to show flash
            exit;
        }
    }
    // If validation fails, set flash message and redirect
    if ($error_message) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $error_message];
        header("Location: my_profile.php");
        exit;
    }
}


// --- Display Flash Message ---
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear message after displaying
}

?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-user-edit me-2 text-primary"></i> My Profile</h2>
    <!-- Optional: Add relevant button if needed -->
</div>

<!-- Display Messages -->
<?php if (!empty($flash_message)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show"
        role="alert">
        <?php echo htmlspecialchars($flash_message['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error_message && empty($flash_message)): // Show general error only if no flash message ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>


<?php if ($user_info): // Check if user_info was fetched ?>
    <div class="row">
        <!-- Profile Information Card -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> Profile Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control"
                                value="<?php echo htmlspecialchars($user_info['name']); ?>" readonly disabled>
                        </div>
                        <small class="text-muted">Name cannot be changed here.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control"
                                value="<?php echo htmlspecialchars($user_info['email']); ?>" readonly disabled>
                        </div>
                        <small class="text-muted">Email cannot be changed here.</small>
                    </div>
                    <p class="text-muted mt-3"><small>To change Name or Email, please contact an administrator.</small></p>
                </div>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i> Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="my_profile.php">
                        <input type="hidden" name="change_password" value="1"> <!-- Identifier for the form -->
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password"
                                    required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required
                                    minlength="6">
                            </div>
                            <small class="text-muted">Minimum 6 characters.</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                    required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 mt-3">
                            <i class="fas fa-sync-alt me-2"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if (empty($error_message)): // Show default error if no specific one was set ?>
        <div class="alert alert-danger">Could not load user profile information.</div>
    <?php endif; ?>
<?php endif; ?>


<?php
include '../includes/footer.php'; // Include the shared footer
?>