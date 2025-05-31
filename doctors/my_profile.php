<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../config/db.php'; // Include database connection

// Check if the user is actually a doctor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    // Handle access denied without including header yet
    $access_denied = true;
} else {
    $access_denied = false;
    $doctor_id = $_SESSION['user_id'];
    $error_message = '';
    $success_message = '';
    $doctor_info = null;

    // --- Handle Password Change FIRST (before any output) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'All password fields are required.'];
            header("Location: my_profile.php");
            exit;
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'New password and confirmation password do not match.'];
            header("Location: my_profile.php");
            exit;
        } elseif (strlen($new_password) < 6) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'New password must be at least 6 characters long.'];
            header("Location: my_profile.php");
            exit;
        } else {
            try {
                // Verify current password
                $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
                $pass_stmt->bindParam(':id', $doctor_id, PDO::PARAM_INT);
                $pass_stmt->execute();
                $user = $pass_stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($current_password, $user['password'])) {
                    // Hash new password
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password in database
                    $update_pass_stmt = $conn->prepare("UPDATE users SET password = :new_password WHERE id = :id");
                    $update_pass_stmt->bindParam(':new_password', $new_password_hash, PDO::PARAM_STR);
                    $update_pass_stmt->bindParam(':id', $doctor_id, PDO::PARAM_INT);
                    $update_pass_stmt->execute();

                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Password changed successfully!'];
                    header("Location: my_profile.php");
                    exit;
                } else {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Incorrect current password.'];
                    header("Location: my_profile.php");
                    exit;
                }
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error changing password.'];
                header("Location: my_profile.php");
                exit;
            }
        }
    }

    // --- Fetch Current Doctor Information ---
    try {
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = :id AND role = 'doctor'");
        $stmt->bindParam(':id', $doctor_id, PDO::PARAM_INT);
        $stmt->execute();
        $doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doctor_info) {
            throw new Exception("Doctor profile not found.");
        }
    } catch (Exception $e) {
        $error_message = "Error fetching profile data: " . $e->getMessage();
    }

    // --- Display Flash Message ---
    if (isset($_SESSION['flash_message'])) {
        $flash_message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    }
}

// NOW include header after all processing is complete
$page_title = 'My Profile';
include '../includes/header.php';

// Handle access denied AFTER header is included
if ($access_denied) {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-user-edit me-2 text-primary"></i> My Profile</h2>
</div>

<!-- Display Messages -->
<?php if (!empty($flash_message)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show"
        role="alert">
        <?php echo htmlspecialchars($flash_message['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error_message && empty($flash_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<?php if ($doctor_info): ?>
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
                        <input type="text" class="form-control"
                            value="<?php echo htmlspecialchars($doctor_info['name']); ?>" readonly disabled>
                        <small class="text-muted">Name cannot be changed here.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control"
                            value="<?php echo htmlspecialchars($doctor_info['email']); ?>" readonly disabled>
                        <small class="text-muted">Email cannot be changed here.</small>
                    </div>
                    <p class="text-muted mt-3"><small>To change Name or Email, please contact an administrator.</small></p>
                    <p class="text-muted"><small>Contact number and specialization are not stored in the main user
                            profile.</small></p>
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
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password"
                                    required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required
                                    minlength="6">
                            </div>
                            <small class="text-muted">Minimum 6 characters.</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                    required>
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning w-100">
                            <i class="fas fa-sync-alt me-2"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if (empty($error_message)): ?>
        <div class="alert alert-danger">Could not load doctor profile information.</div>
    <?php endif; ?>
<?php endif; ?>

<?php
include '../includes/footer.php';
?>