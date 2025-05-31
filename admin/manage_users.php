<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Manage Users';
include '../includes/header.php'; // Include the header with the sidebar

// Check if the user is actually an admin - redirect if not
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$message = '';
$message_class = 'alert-info'; // Default message class

// Handle Add User
if (isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = trim($_POST['role']);

    // Basic validation using PHP functions
    $email_valid = filter_var($email, FILTER_VALIDATE_EMAIL);

    if (empty($name) || !$email_valid || strlen($password) < 8 || !in_array($role, ['admin', 'doctor', 'receptionist'])) {
        $message = "Invalid input. Please check all fields. Password must be at least 8 characters.";
        $message_class = 'alert-danger';
    } else {
        // Check if email already exists
        $check_email_stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $check_email_stmt->bindParam(':email', $email);
        $check_email_stmt->execute();
        if ($check_email_stmt->fetch()) {
            $message = "Email address already in use. Please choose a different email.";
            $message_class = 'alert-danger';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            try {
                $query = "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':role', $role);
                if ($stmt->execute()) {
                    $message = "User added successfully!";
                    $message_class = 'alert-success';
                } else {
                    $message = "Failed to add user.";
                    $message_class = 'alert-danger';
                }
            } catch (PDOException $e) {
                $message = "Error adding user: " . $e->getMessage();
                $message_class = 'alert-danger';
            }
        }
    }
}

// Handle Delete User
if (isset($_POST['delete_user'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        $message = "Invalid user ID for deletion.";
        $message_class = 'alert-danger';
    } elseif ($id == $_SESSION['user_id']) { // Prevent admin from deleting themselves
        $message = "You cannot delete your own account.";
        $message_class = 'alert-danger';
    } else {
        try {
            $query = "DELETE FROM users WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $message = "User successfully deleted!";
                    $message_class = 'alert-success';
                } else {
                    $message = "User not found or already deleted.";
                    $message_class = 'alert-warning';
                }
            } else {
                $message = "Failed to delete user.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            $message = "Error deleting user: " . $e->getMessage();
            // Check for foreign key constraint violation
            if ($e->getCode() == '23000') {
                $message = "Cannot delete user. They may have associated records (e.g., appointments). Please reassign or delete those records first.";
            }
            $message_class = 'alert-danger';
        }
    }
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = $_POST['password']; // Optional: new password

    // Basic validation using PHP functions
    $email_valid = filter_var($email, FILTER_VALIDATE_EMAIL);

    if (!$id || empty($name) || !$email_valid || !in_array($role, ['admin', 'doctor', 'receptionist'])) {
        $message = "Invalid input for editing user. Please check Name, Email, and Role.";
        $message_class = 'alert-danger';
    } else {
        // Check if email already exists for another user
        $check_email_stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $check_email_stmt->bindParam(':email', $email);
        $check_email_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_email_stmt->execute();

        if ($check_email_stmt->fetch()) {
            $message = "Email address already in use by another user.";
            $message_class = 'alert-danger';
        } else {
            try {
                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $message = "Password must be at least 8 characters long.";
                        $message_class = 'alert-danger';
                        goto end_edit_user_logic;
                    }
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET name = :name, email = :email, role = :role, password = :password WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':password', $hashedPassword);
                } else {
                    $query = "UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id";
                    $stmt = $conn->prepare($query);
                }
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $message = "User updated successfully!";
                    $message_class = 'alert-success';
                } else {
                    $message = "Failed to update user.";
                    $message_class = 'alert-danger';
                }
            } catch (PDOException $e) {
                $message = "Error updating user: " . $e->getMessage();
                $message_class = 'alert-danger';
            }
        }
    }
    end_edit_user_logic: // Label for goto
}

// Fetch all users AFTER potential add/delete/update
try {
    $users = $conn->query("SELECT id, name, email, role FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching users: " . $e->getMessage();
    $message_class = 'alert-danger';
    $users = [];
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo $message_class; ?> alert-dismissible fade show" role="alert" data-auto-dismiss="7000">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Add User Form Card (Collapsible) -->
<div class="accordion mb-4" id="addUserAccordion">
    <div class="accordion-item card shadow-sm border-0">
        <h2 class="accordion-header card-header bg-light border-bottom-0" id="headingAddUser">
            <button class="accordion-button collapsed bg-transparent text-dark fw-semibold" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseAddUser" aria-expanded="false"
                aria-controls="collapseAddUser">
                <i class="fas fa-user-plus me-2 text-primary"></i> Add New User
            </button>
        </h2>
        <div id="collapseAddUser" class="accordion-collapse collapse <?php if (isset($_POST['add_user']) && !empty($message) && $message_class === 'alert-danger')
            echo 'show'; ?>" aria-labelledby="headingAddUser" data-bs-parent="#addUserAccordion">
            <div class="accordion-body card-body pt-2">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="off">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_name" class="form-label">Full Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="name" id="add_name" class="form-control" required
                                value="<?php echo isset($_POST['name']) && $message_class === 'alert-danger' ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_email" class="form-label">Email Address <span
                                    class="text-danger">*</span></label>
                            <input type="email" name="email" id="add_email" class="form-control" required
                                value="<?php echo isset($_POST['email']) && $message_class === 'alert-danger' ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_password" class="form-label">Password <span
                                    class="text-danger">*</span></label>
                            <input type="password" name="password" id="add_password" class="form-control" required
                                minlength="8">
                            <small class="form-text text-muted">Minimum 8 characters.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="add_role" class="form-select" required>
                                <option value="">Select Role...</option>
                                <option value="admin" <?php echo isset($_POST['role']) && $_POST['role'] == 'admin' && $message_class === 'alert-danger' ? 'selected' : ''; ?>>Admin</option>
                                <option value="doctor" <?php echo isset($_POST['role']) && $_POST['role'] == 'doctor' && $message_class === 'alert-danger' ? 'selected' : ''; ?>>Doctor</option>
                                <option value="receptionist" <?php echo isset($_POST['role']) && $_POST['role'] == 'receptionist' && $message_class === 'alert-danger' ? 'selected' : ''; ?>>Receptionist</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary" name="add_user">
                            <i class="fas fa-plus me-2"></i>Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Users Table Card -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="fas fa-users-cog me-2"></i> Users List
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <p class="text-center text-muted p-4">No users found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php
                                    switch ($user['role']) {
                                        case 'admin':
                                            echo 'danger';
                                            break;
                                        case 'doctor':
                                            echo 'primary';
                                            break;
                                        case 'receptionist':
                                            echo 'info';
                                            break;
                                        default:
                                            echo 'secondary';
                                    }
                                    ?>"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $user['id']; ?>" data-bs-tooltip="tooltip"
                                        title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): // Prevent deleting self via this button ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST"
                                            class="d-inline confirm-delete-form">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user"
                                                class="btn btn-sm btn-outline-danger confirm-delete" data-bs-tooltip="tooltip"
                                                title="Delete User"
                                                data-message="Are you sure you want to delete user <?php echo htmlspecialchars($user['name']); ?>? This action cannot be undone.">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div> <!-- /card-body -->
</div> <!-- /card -->


<!-- Edit Modals (One per user) -->
<?php foreach ($users as $user): ?>
    <div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1"
        aria-labelledby="editModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?php echo $user['id']; ?>"><i
                                class="fas fa-user-edit me-2"></i>Edit User: <?php echo htmlspecialchars($user['name']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <div class="mb-3">
                            <label for="edit_name_<?php echo $user['id']; ?>" class="form-label">Full Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_name_<?php echo $user['id']; ?>" class="form-control"
                                value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email_<?php echo $user['id']; ?>" class="form-label">Email Address <span
                                    class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_email_<?php echo $user['id']; ?>" class="form-control"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password_<?php echo $user['id']; ?>" class="form-label">New Password</label>
                            <input type="password" name="password" id="edit_password_<?php echo $user['id']; ?>"
                                class="form-control" minlength="8">
                            <small class="form-text text-muted">Leave blank to keep current password. Minimum 8 characters
                                if changing.</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role_<?php echo $user['id']; ?>" class="form-label">Role <span
                                    class="text-danger">*</span></label>
                            <select name="role" id="edit_role_<?php echo $user['id']; ?>" class="form-select" required <?php if ($user['id'] == $_SESSION['user_id'] && $user['role'] == 'admin')
                                   echo 'disabled'; ?>>
                                <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin
                                </option>
                                <option value="doctor" <?php echo ($user['role'] == 'doctor') ? 'selected' : ''; ?>>Doctor
                                </option>
                                <option value="receptionist" <?php echo ($user['role'] == 'receptionist') ? 'selected' : ''; ?>>Receptionist</option>
                            </select>
                            <?php if ($user['id'] == $_SESSION['user_id'] && $user['role'] == 'admin'): ?>
                                <small class="form-text text-muted">Admins cannot change their own role.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times me-2"></i>Cancel</button>
                        <button type="submit" name="edit_user" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save
                            Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Initialize Bootstrap Tooltips and custom confirm delete -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-tooltip="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });

    // Custom confirm for delete forms to prevent accidental deletion
    const confirmDeleteForms = document.querySelectorAll('.confirm-delete-form');
    confirmDeleteForms.forEach(form => {
        form.addEventListener('submit', function (event) {
            const button = form.querySelector('.confirm-delete');
            const message = button.dataset.message || 'Are you sure you want to delete this item? This action cannot be undone.';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
</script>

<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>

<style>
    /* --- Ultra-Premium Manage Users Layout & Table --- */
    .card,
    .accordion-item.card {
        border-radius: 1.25rem;
        box-shadow: 0 8px 32px 0 rgba(13, 110, 253, 0.09), 0 1.5px 6px 0 rgba(0, 0, 0, 0.04);
        background: linear-gradient(120deg, #fff 80%, #f0f4ff 100%);
        border: none;
        overflow: hidden;
        position: relative;
        margin-bottom: 2.5rem;
    }

    .card-header,
    .accordion-header.card-header {
        background: linear-gradient(90deg, #fff 80%, #f0f4ff 100%);
        border-radius: 1rem 1rem 0 0;
        font-weight: 700;
        font-size: 1.13rem;
        letter-spacing: 0.3px;
        border-bottom: 1px solid #e5e5e5;
        padding: 1.1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: relative;
        z-index: 2;
    }

    .accordion-button {
        border-radius: 1rem !important;
        font-weight: 600;
        font-size: 1.08rem;
        letter-spacing: 0.2px;
        transition: background 0.18s, box-shadow 0.18s;
    }

    .accordion-button:not(.collapsed) {
        background: #f0f4ff;
        color: #0d6efd;
        box-shadow: 0 2px 8px 0 rgba(13, 110, 253, 0.06);
    }

    .accordion-item {
        border-radius: 1.25rem !important;
        overflow: hidden;
        margin-bottom: 1.2rem;
        border: none;
    }

    .accordion-body {
        background: rgba(255, 255, 255, 0.92);
        border-radius: 0 0 1.25rem 1.25rem;
    }

    .table-responsive {
        border-radius: 0 0 1.25rem 1.25rem;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(2px);
    }

    .table {
        border-radius: 1rem;
        overflow: hidden;
        margin-bottom: 0;
        background: transparent;
    }

    .table thead th {
        border-top: none;
        font-weight: 700;
        letter-spacing: 0.5px;
        background: #f0f4ff;
        color: #0d6efd;
        font-size: 1.04rem;
        padding-top: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e5e5;
        position: sticky;
        top: 0;
        z-index: 3;
    }

    .table-striped>tbody>tr:nth-of-type(odd) {
        background-color: #f8fafd;
    }

    .table-hover>tbody>tr:hover {
        background: #f0f4ff;
        box-shadow: 0 6px 24px 0 rgba(13, 110, 253, 0.13), 0 1.5px 6px 0 rgba(0, 0, 0, 0.04);
        transform: scale(1.012);
        transition: box-shadow 0.22s, transform 0.22s, background 0.18s;
        z-index: 2;
        position: relative;
    }

    .table td,
    .table th {
        vertical-align: middle;
        padding-top: 0.95rem;
        padding-bottom: 0.95rem;
        padding-left: 1.1rem;
        padding-right: 1.1rem;
        background: transparent;
    }

    .table td.text-center,
    .table th.text-center {
        text-align: center !important;
    }

    .badge {
        font-size: 1em;
        padding: 0.55em 1.1em;
        letter-spacing: 0.3px;
        box-shadow: 0 1px 4px 0 rgba(13, 110, 253, 0.06);
        font-weight: 600;
        border-radius: 1.2em;
        background-clip: padding-box;
        border: 1px solid #e5e5e5;
        backdrop-filter: blur(1.5px);
    }

    .btn-outline-warning,
    .btn-outline-danger {
        border-radius: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        transition: box-shadow 0.18s, transform 0.18s, background 0.18s, color 0.18s;
        padding: 0.45rem 0.7rem;
        margin-right: 0.15rem;
        margin-bottom: 0.1rem;
        box-shadow: 0 1px 4px 0 rgba(13, 110, 253, 0.04);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .btn-outline-warning:last-child,
    .btn-outline-danger:last-child {
        margin-right: 0;
    }

    .btn-outline-warning:hover,
    .btn-outline-danger:hover {
        box-shadow: 0 6px 20px 0 rgba(13, 110, 253, 0.13);
        transform: scale(1.13) rotate(-4deg);
        background: #f0f4ff;
        color: #0d6efd !important;
    }

    .btn-outline-danger:hover {
        background: #f8d7da;
        color: #b02a37 !important;
    }

    .btn-outline-warning:hover {
        background: #fff3cd;
        color: #856404 !important;
    }

    .btn-outline-warning i,
    .btn-outline-danger i {
        transition: transform 0.18s;
    }

    .btn-outline-warning:hover i,
    .btn-outline-danger:hover i {
        transform: scale(1.18) rotate(-8deg);
    }

    .modal-content {
        border-radius: 1.25rem;
        box-shadow: 0 8px 32px 0 rgba(13, 110, 253, 0.09), 0 1.5px 6px 0 rgba(0, 0, 0, 0.04);
        border: none;
    }

    .modal-header {
        border-radius: 1.25rem 1.25rem 0 0;
        background: linear-gradient(90deg, #fff 80%, #f0f4ff 100%);
        border-bottom: 1px solid #e5e5e5;
    }

    .modal-footer {
        border-radius: 0 0 1.25rem 1.25rem;
        background: #f8fafd;
    }

    .form-control,
    .form-select {
        border-radius: 0.7rem;
        box-shadow: 0 1px 4px 0 rgba(13, 110, 253, 0.04);
        transition: box-shadow 0.18s, border-color 0.18s;
    }

    .form-control:focus,
    .form-select:focus {
        box-shadow: 0 4px 16px 0 rgba(13, 110, 253, 0.10);
        border-color: #0d6efd;
    }

    @media (max-width: 991.98px) {

        .card,
        .card-header,
        .table,
        .table-responsive,
        .modal-content {
            border-radius: 1rem;
        }

        .card-header {
            font-size: 1.05rem;
            padding: 1rem 1rem;
        }

        .table td,
        .table th {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
    }

    @media (max-width: 575.98px) {
        .card-header {
            font-size: 1rem;
            padding: 0.85rem 0.7rem;
        }

        .table td,
        .table th {
            font-size: 0.97rem;
            padding: 0.5rem 0.3rem;
        }

        .btn {
            font-size: 0.95rem;
            padding: 0.35rem 0.5rem;
        }
    }

    /* Subtle animated floating circle for premium feel */
    .card::after,
    .accordion-item.card::after {
        content: '';
        position: absolute;
        right: -40px;
        top: -40px;
        width: 80px;
        height: 80px;
        background: rgba(13, 110, 253, 0.07);
        border-radius: 50%;
        z-index: 0;
        animation: floatBg 6s ease-in-out infinite alternate;
        pointer-events: none;
    }

    @keyframes floatBg {
        0% {
            transform: translateY(0) scale(1);
        }

        100% {
            transform: translateY(12px) scale(1.08);
        }
    }
</style>