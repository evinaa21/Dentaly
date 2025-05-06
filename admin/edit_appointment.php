<?php
// Include database connection
include '../config/db.php';

// Define page title *before* including header
$page_title = 'Edit Appointment'; // Default title
include '../includes/header.php';

// Check if the user is actually an admin - redirect if not
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$appointment_id = null;
$appointment = null;
$patient_name = ''; // To store patient name
$message = '';
$message_class = 'alert-danger'; // Default to danger

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $message = "Invalid or missing appointment ID.";
} else {
    $appointment_id = intval($_GET['id']);
    $page_title = "Edit Appointment #{$appointment_id}"; // Update title if ID is valid

    try {
        // Fetch current appointment details including patient name
        $query = "
            SELECT
                a.id AS appointment_id,
                a.patient_id,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name, -- Get patient name
                a.doctor_id,
                a.service_id,
                a.appointment_date,
                a.status
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id -- Join to get patient name
            WHERE a.id = :appointment_id
        ";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            $message = "Appointment with ID $appointment_id not found.";
            $appointment = null; // Ensure appointment is null if not found
        } else {
            $patient_name = $appointment['patient_name']; // Store patient name

            // Fetch doctors and services for dropdowns
            $doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor'")->fetchAll(PDO::FETCH_ASSOC);
            $services = $conn->query("SELECT id, service_name FROM services")->fetchAll(PDO::FETCH_ASSOC);
        }

        // Handle form submission only if appointment was found
        if ($appointment && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Sanitize and validate input
            $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
            $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
            $appointment_date = $_POST['appointment_date']; // Basic validation needed here if required
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);

            // Basic validation example
            if (!$doctor_id || !$service_id || empty($appointment_date) || !in_array($status, ['pending', 'completed', 'canceled'])) {
                $message = "Invalid form data submitted.";
                $message_class = 'alert-danger';
            } else {
                // Update the appointment
                $update_query = "
                    UPDATE appointments
                    SET doctor_id = :doctor_id,
                        service_id = :service_id,
                        appointment_date = :appointment_date,
                        status = :status
                    WHERE id = :appointment_id
                ";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':appointment_date', $appointment_date, PDO::PARAM_STR);
                $update_stmt->bindParam(':status', $status, PDO::PARAM_STR);
                $update_stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);

                if ($update_stmt->execute()) {
                    $message = "Appointment updated successfully!";
                    $message_class = 'alert-success';
                    // Refresh appointment details after update
                    $stmt->execute(); // Re-run the select query
                    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                    $patient_name = $appointment['patient_name'];
                } else {
                    $message = "Failed to update appointment. Please check the data and try again.";
                    $message_class = 'alert-danger';
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Database Query Failed: " . $e->getMessage();
        // error_log("Edit Appointment Error: " . $e->getMessage()); // Log error in production
        $appointment = null; // Ensure appointment is null on DB error
    }
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
    <a href="manage_appointments.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to List
    </a>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo $message_class; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($appointment): // Only show form if appointment exists ?>
    <div class="card shadow-sm">
        <div class="card-header">
            <i class="fas fa-edit me-2"></i> Edit Appointment Details
        </div>
        <div class="card-body">
            <form method="POST"
                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . htmlspecialchars($appointment_id); ?>">
                <!-- Patient Name (Read Only) -->
                <div class="mb-3">
                    <label class="form-label">Patient</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($patient_name); ?>" readonly
                        disabled>
                    <small class="text-muted">Patient cannot be changed from this form.</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="doctor_id" class="form-label">Doctor <span class="text-danger">*</span></label>
                        <select name="doctor_id" id="doctor_id" class="form-select" required>
                            <option value="">Select Doctor...</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" <?php echo ($doctor['id'] == $appointment['doctor_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="service_id" class="form-label">Service <span class="text-danger">*</span></label>
                        <select name="service_id" id="service_id" class="form-select" required>
                            <option value="">Select Service...</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo ($service['id'] == $appointment['service_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="appointment_date" class="form-label">Appointment Date & Time <span
                                class="text-danger">*</span></label>
                        <input type="datetime-local" name="appointment_date" id="appointment_date" class="form-control"
                            value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($appointment['appointment_date']))); ?>"
                            required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pending" <?php echo ($appointment['status'] === 'pending') ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="completed" <?php echo ($appointment['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="canceled" <?php echo ($appointment['status'] === 'canceled') ? 'selected' : ''; ?>>
                                Canceled</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <a href="view_appointment.php?id=<?php echo htmlspecialchars($appointment_id); ?>"
                        class="btn btn-secondary me-2">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Appointment
                    </button>
                </div>
            </form>
        </div> <!-- /card-body -->
    </div> <!-- /card -->
<?php else: ?>
    <?php if (!$message): // Show a generic message if no specific error was set but appointment is null ?>
        <div class="alert alert-warning" role="alert">
            Could not load appointment data. It may have been deleted or the ID is incorrect.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>