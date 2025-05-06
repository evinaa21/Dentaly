<?php
// Define page title *before* including header
$page_title = 'Add New Appointment';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a receptionist
if ($_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$patients = [];
$doctors = [];
$services = [];
$error_message = ''; // For fetch errors

// --- Fetch data for dropdowns ---
try {
    $patients = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) AS full_name FROM patients ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
    $doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $services = $conn->query("SELECT id, service_name FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching data for dropdowns: " . $e->getMessage();
    // error_log("Add Appointment Fetch Error: " . $e->getMessage());
}

// --- Handle form submission for adding an appointment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_form'])) {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $status = $_POST['status'] ?? 'pending'; // Default to pending

    // Basic Validation
    if (!$patient_id || !$doctor_id || !$service_id || empty($appointment_date) || !in_array($status, ['pending', 'completed', 'canceled'])) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid data submitted. Please check all fields.'];
    } else {
        try {
            // Insert the new appointment
            $insert_query = "
                INSERT INTO appointments (patient_id, doctor_id, service_id, appointment_date, status)
                VALUES (:patient_id, :doctor_id, :service_id, :appointment_date, :status)
            ";
            $stmt = $conn->prepare($insert_query);
            $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
            $stmt->bindParam(':appointment_date', $appointment_date, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Appointment added successfully!'];
                // Redirect to manage page after successful addition
                header("Location: manage_appointments.php");
                exit;
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to add the appointment. Database error.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error adding appointment.'];
            // error_log("Add Appointment Insert Error: " . $e->getMessage());
        }
    }
    // Redirect back to the add form if validation failed or DB error occurred
    header("Location: add_appointment.php");
    exit;
}

// --- Handle form submission for adding a new patient (from modal) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_patient_form'])) {
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

    // Basic Validation
    if (empty($first_name) || empty($last_name) || empty($contact_number)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'First Name, Last Name, and Contact Number are required for new patient.'];
    } else {
        try {
            // Insert the new patient
            $insert_patient_query = "
                INSERT INTO patients (first_name, last_name, contact_number, address)
                VALUES (:first_name, :last_name, :contact_number, :address)
            ";
            $stmt = $conn->prepare($insert_patient_query);
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':address', $address, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'New patient added successfully! Please select them from the list.'];
                // No need to fetch patients again, just redirect
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to add the new patient. Database error.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error adding patient.'];
            // error_log("Add Patient (Modal) Insert Error: " . $e->getMessage());
        }
    }
    // Redirect back to the add appointment page to show message and updated list
    header("Location: add_appointment.php");
    exit;
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
    <h2 class="m-0"><i class="fas fa-calendar-plus me-2 text-primary"></i> Add New Appointment</h2>
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

<!-- Add Appointment Form Card -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i> Appointment Details</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="add_appointment.php" id="addAppointmentForm">
            <input type="hidden" name="appointment_form" value="1">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="patient_id" class="form-label">Patient <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <select name="patient_id" id="patient_id" class="form-select" required>
                            <option value="" disabled selected>-- Select Patient --</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new" class="text-primary fw-bold">-- Add New Patient --</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="doctor_id" class="form-label">Doctor <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-md"></i></span>
                        <select name="doctor_id" id="doctor_id" class="form-select" required>
                            <option value="" disabled selected>-- Select Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="service_id" class="form-label">Service <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-briefcase-medical"></i></span>
                        <select name="service_id" id="service_id" class="form-select" required>
                            <option value="" disabled selected>-- Select Service --</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>">
                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="appointment_date" class="form-label">Appointment Date & Time <span
                            class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        <input type="datetime-local" name="appointment_date" id="appointment_date" class="form-control"
                            required min="<?php echo date('Y-m-d\TH:i'); // Prevent booking in the past ?>">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                    <select name="status" id="status" class="form-select" required>
                        <option value="pending" selected>Pending</option>
                        <option value="completed">Completed</option>
                        <option value="canceled">Canceled</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 text-end">
                <a href="manage_appointments.php" class="btn btn-secondary me-2"><i class="fas fa-times me-1"></i>
                    Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Add Appointment</button>
            </div>
        </form>
    </div> <!-- /card-body -->
</div> <!-- /card -->


<!-- Modal for Adding New Patient -->
<div class="modal fade" id="newPatientModal" tabindex="-1" aria-labelledby="newPatientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="add_appointment.php" id="addPatientForm">
                <input type="hidden" name="new_patient_form" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="newPatientModalLabel"><i class="fas fa-user-plus me-2"></i> Add New
                        Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Contact Number <span
                                class="text-danger">*</span></label>
                        <input type="tel" name="contact_number" id="contact_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                            class="fas fa-times me-1"></i> Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i> Add
                        Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add JavaScript to handle the 'Add New Patient' option -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const patientSelect = document.getElementById('patient_id');
        const newPatientModal = new bootstrap.Modal(document.getElementById('newPatientModal'));
        const addAppointmentForm = document.getElementById('addAppointmentForm');

        if (patientSelect) {
            patientSelect.addEventListener('change', function () {
                if (this.value === 'new') {
                    // Open the modal
                    newPatientModal.show();
                    // Reset the select back to default to avoid submitting 'new'
                    this.value = '';
                }
            });
        }

        // Optional: Prevent main form submission if modal is open (though separate forms handle this)
        // addAppointmentForm.addEventListener('submit', function(event) {
        //     if (document.getElementById('newPatientModal').classList.contains('show')) {
        //         event.preventDefault();
        //         alert('Please close the "Add New Patient" window first.');
        //     }
        // });
    });
</script>

<?php
include '../includes/footer.php'; // Include the shared footer
?>