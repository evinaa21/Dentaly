<?php
// Define page title *before* including header
$page_title = 'Edit Appointment';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a receptionist
if ($_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger m-3">Invalid appointment ID provided.</div>';
    include '../includes/footer.php';
    exit;
}

$appointment_id = intval($_GET['id']);
$appointment = null;
$services = [];
$doctors = [];
$error_message = '';

// --- Fetch current appointment, services, and doctors ---
try {
    // Fetch appointment details including patient and doctor ID
    $query = "
        SELECT
            a.id AS appointment_id,
            a.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            a.doctor_id,
            a.service_id,
            a.appointment_date,
            a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = :appointment_id
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception("Appointment with ID " . $appointment_id . " not found.");
    }

    // Fetch services for dropdown
    $services = $conn->query("SELECT id, service_name FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);
    // Fetch doctors for dropdown
    $doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
    // error_log("Edit Appointment Fetch Error: " . $e->getMessage());
}

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appointment) { // Only process if appointment was found
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $status = $_POST['status'] ?? '';

    // Basic Validation
    if (!$service_id || !$doctor_id || empty($appointment_date) || !in_array($status, ['pending', 'completed', 'canceled'])) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid data submitted. Please check all fields.'];
    } else {
        try {
            // Update the appointment
            $update_query = "
                UPDATE appointments
                SET service_id = :service_id,
                    doctor_id = :doctor_id,
                    appointment_date = :appointment_date,
                    status = :status
                WHERE id = :appointment_id
            ";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':appointment_date', $appointment_date, PDO::PARAM_STR);
            $update_stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $update_stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);

            if ($update_stmt->execute()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Appointment updated successfully!'];
                // Redirect back to manage page after successful update
                header("Location: manage_appointments.php");
                exit;
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to update appointment. Database error.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error updating appointment.'];
            // error_log("Edit Appointment Update Error: " . $e->getMessage());
        }
    }
    // Redirect back to edit page if validation failed or DB error occurred during update
    header("Location: edit_appointment.php?id=" . $appointment_id);
    exit;
}

// --- Display Flash Message if redirected back ---
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear message after displaying
}

?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-edit me-2 text-warning"></i> Edit Appointment</h2>
    <a href="manage_appointments.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
</div>

<!-- Display Errors / Messages -->
<?php if ($flash_message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show"
        role="alert">
        <?php echo htmlspecialchars($flash_message['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error_message): // Show fetch error only if no flash message ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>


<?php if ($appointment && !$error_message): // Only show form if appointment data was loaded successfully ?>
    <!-- Edit Form Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-calendar-day me-2"></i> Update Details for Appointment
                #<?php echo htmlspecialchars($appointment['appointment_id']); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="edit_appointment.php?id=<?php echo $appointment_id; ?>">
                <div class="mb-3">
                    <label class="form-label">Patient</label>
                    <input type="text" class="form-control"
                        value="<?php echo htmlspecialchars($appointment['patient_name']); ?>" readonly disabled>
                    <small class="text-muted">Patient cannot be changed from this screen.</small>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="doctor_id" class="form-label">Doctor <span class="text-danger">*</span></label>
                        <select name="doctor_id" id="doctor_id" class="form-select" required>
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor['id'] == $appointment['doctor_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="service_id" class="form-label">Service <span class="text-danger">*</span></label>
                        <select name="service_id" id="service_id" class="form-select" required>
                            <option value="">-- Select Service --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>" <?php echo $service['id'] == $appointment['service_id'] ? 'selected' : ''; ?>>
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
                            <option value="pending" <?php echo $appointment['status'] === 'pending' ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>
                                Completed</option>
                            <option value="canceled" <?php echo $appointment['status'] === 'canceled' ? 'selected' : ''; ?>>
                                Canceled</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <a href="manage_appointments.php" class="btn btn-secondary me-2"><i class="fas fa-times me-1"></i>
                        Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update
                        Appointment</button>
                </div>
            </form>
        </div> <!-- /card-body -->
    </div> <!-- /card -->
<?php elseif (!$error_message): ?>
    <div class="alert alert-warning">Could not load appointment data to edit.</div>
<?php endif; ?>


<?php
include '../includes/footer.php'; // Include the shared footer
?>