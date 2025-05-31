<?php
// Include database connection
include '../config/db.php';

// Define page title *before* including header
$page_title = 'Edit Appointment'; // Default title
include '../includes/header.php';

// Check if the user is actually an admin - redirect if not
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$appointment_id = null;
$appointment = null;
$patient_name = ''; // To store patient name
$form_data = []; // For repopulating form on error
$message = '';
$message_class = 'alert-danger'; // Default to danger

if (!isset($_GET['id']) || !($appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) || $appointment_id <= 0) {
    $message = "Invalid or missing appointment ID.";
} else {
    // Keep title as "Edit Appointment" without showing the ID number

    try {
        // Fetch current appointment details including patient name
        $query = "
            SELECT
                a.id AS appointment_id,
                a.patient_id,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                a.doctor_id,
                a.service_id,
                a.appointment_date,
                a.status,
                a.notes
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            WHERE a.id = :appointment_id
        ";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        $form_data = $appointment; // Initialize form_data with fetched details

        if (!$appointment) {
            $message = "Appointment not found.";
            $appointment = null;
        } else {
            $patient_name = $appointment['patient_name'];

            // Fetch doctors and services for dropdowns
            $doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor'")->fetchAll(PDO::FETCH_ASSOC);
            $services = $conn->query("SELECT id, service_name FROM services")->fetchAll(PDO::FETCH_ASSOC);
        }

        // Handle form submission only if appointment was found
        if ($appointment && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get and sanitize input using basic PHP functions
            $form_data = array_merge($form_data, $_POST);

            $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
            $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
            $appointment_date_str = trim($_POST['appointment_date'] ?? '');
            $status = trim($_POST['status'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            // Basic validation
            $valid_statuses = ['pending', 'completed', 'canceled'];
            $datetime_valid = false;

            // Check datetime format
            if (!empty($appointment_date_str) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $appointment_date_str)) {
                try {
                    $test_date = new DateTime($appointment_date_str);
                    $datetime_valid = true;
                } catch (Exception $e) {
                    $datetime_valid = false;
                }
            }

            if (!$doctor_id || !$service_id || !$datetime_valid || !in_array($status, $valid_statuses)) {
                $message = "Invalid form data submitted. Please check all fields.";
                $message_class = 'alert-danger';
            } else {
                // Convert datetime-local string to SQL DATETIME format
                try {
                    $appointment_datetime_obj = new DateTime($appointment_date_str);
                    $datetime_sql_format = $appointment_datetime_obj->format('Y-m-d H:i:s');
                    $current_datetime_obj = new DateTime();
                    $current_datetime_obj->modify('-5 minutes'); // 5 minute buffer for current appointments

                    // Enhanced validation logic
                    if ($status === 'pending' && $appointment_datetime_obj < $current_datetime_obj) {
                        $message = "Pending appointments cannot be set to a past date/time.";
                        $message_class = 'alert-danger';
                    } elseif ($status === 'completed' && $appointment_datetime_obj > new DateTime()) {
                        $message = "Cannot mark future appointments as completed. The appointment must have already occurred.";
                        $message_class = 'alert-danger';
                    } elseif ($status === 'pending' && (($appointment_hour = (int) $appointment_datetime_obj->format('H')) < 8 || $appointment_hour >= 18)) {
                        $message = "Pending appointments must be scheduled between 8:00 AM and 5:59 PM.";
                        $message_class = 'alert-danger';
                    } else {
                        // Update the appointment - validation passed
                        $update_query = "
                            UPDATE appointments
                            SET doctor_id = :doctor_id,
                                service_id = :service_id,
                                appointment_date = :appointment_date,
                                status = :status,
                                notes = :notes
                            WHERE id = :appointment_id
                        ";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
                        $update_stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
                        $update_stmt->bindParam(':appointment_date', $datetime_sql_format, PDO::PARAM_STR);
                        $update_stmt->bindParam(':status', $status, PDO::PARAM_STR);
                        $update_stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
                        $update_stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);

                        if ($update_stmt->execute()) {
                            $message = "Appointment updated successfully!";
                            $message_class = 'alert-success';
                            // Refresh appointment details after update
                            $stmt->execute();
                            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                            $form_data = $appointment;
                            $patient_name = $appointment['patient_name'];
                        } else {
                            $message = "Failed to update appointment. Please check the data and try again.";
                            $message_class = 'alert-danger';
                        }
                    }
                } catch (Exception $e) {
                    $message = "Invalid date or time format provided for appointment.";
                    $message_class = 'alert-danger';
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Database Query Failed: " . $e->getMessage();
        $appointment = null;
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
    <div class="alert <?php echo htmlspecialchars($message_class); ?> alert-dismissible fade show" role="alert"
        data-auto-dismiss="7000">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($appointment): ?>
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
                                <option value="<?php echo $doctor['id']; ?>" <?php echo ($doctor['id'] == ($form_data['doctor_id'] ?? '')) ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $service['id']; ?>" <?php echo ($service['id'] == ($form_data['service_id'] ?? '')) ? 'selected' : ''; ?>>
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
                            value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($form_data['appointment_date'] ?? 'now'))); ?>"
                            required step="900">
                        <small class="form-text text-muted">Clinic hours: 8 AM - 6 PM.</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pending" <?php echo (($form_data['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="completed" <?php echo (($form_data['status'] ?? '') === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="canceled" <?php echo (($form_data['status'] ?? '') === 'canceled') ? 'selected' : ''; ?>>
                                Canceled</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes"
                        rows="3"><?php echo htmlspecialchars($form_data['notes'] ?? ''); ?></textarea>
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
        </div>
    </div>
<?php else: ?>
    <?php if (!$message): ?>
        <div class="alert alert-warning" role="alert">
            Could not load appointment data. It may have been deleted or the ID is incorrect.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>