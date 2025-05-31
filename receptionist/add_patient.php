<?php
// Start session first if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is actually a receptionist
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'receptionist') {
    $page_title = 'Access Denied';
    include '../includes/header.php';
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

include '../config/db.php'; // Include database connection

$page_title = 'Add New Patient';
$message = '';
$message_class = 'alert-info';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dental_history = trim($_POST['dental_history'] ?? '');

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($contact_number)) {
        $message = "First name, last name, and contact number are required.";
        $message_class = 'alert-danger';
    } else {
        try {
            // Insert new patient into the database - removed user_id field
            $query = "
                INSERT INTO patients (first_name, last_name, contact_number, address, dental_history)
                VALUES (:first_name, :last_name, :contact_number, :address, :dental_history)
            ";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $stmt->bindParam(':dental_history', $dental_history, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $message = "Patient added successfully!";
                $message_class = 'alert-success';
                // Clear form data on success
                $_POST = [];
            } else {
                $message = "Failed to add the patient.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            $message = "Database Error: " . $e->getMessage();
            $message_class = 'alert-danger';
        }
    }
}

include '../includes/header.php'; // Include the shared header with the sidebar
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New Patient</h4>
                </div>
                <div class="card-body">
                    <!-- Success/Error Message -->
                    <?php if ($message): ?>
                        <div class="alert <?php echo htmlspecialchars($message_class); ?> alert-dismissible fade show"
                            role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Add Patient Form -->
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                    value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                    value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_number" class="form-label">Contact Number <span
                                        class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number"
                                    value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address"
                                    value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="dental_history" class="form-label">Dental History</label>
                            <textarea class="form-control" id="dental_history" name="dental_history"
                                rows="3"><?php echo htmlspecialchars($_POST['dental_history'] ?? ''); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <a href="dashboard.php" class="btn btn-secondary me-2">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Add Patient
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>