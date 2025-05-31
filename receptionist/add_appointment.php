<?php
// Define page title *before* including header
$page_title = "Book New Appointment";
include '../includes/header.php';

// Check if the user is actually a receptionist
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// DB connection
if (!isset($conn)) {
    include '../config/db.php';
}

// For large patient databases, use AJAX search instead of loading all patients
$doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$services = $conn->query("SELECT id, service_name, price FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$error = '';
$submitted_data = []; // To repopulate form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_data = $_POST; // Store submitted data

    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $appointment_date_str = trim($_POST['appointment_date'] ?? '');
    $appointment_time_str = trim($_POST['appointment_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Basic date validation
    $date_valid = false;
    if (!empty($appointment_date_str)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $appointment_date_str);
        $date_valid = $date_obj && $date_obj->format('Y-m-d') === $appointment_date_str;
    }

    if (!$patient_id || !$doctor_id || !$service_id || !$date_valid || empty($appointment_time_str)) {
        $error = "All fields marked with * are required. Please ensure date and time are valid.";
    } else {
        $datetime_str = $appointment_date_str . ' ' . $appointment_time_str;
        try {
            $appointment_datetime_obj = new DateTime($datetime_str);
            $datetime_sql_format = $appointment_datetime_obj->format('Y-m-d H:i:s');
            $current_datetime_obj = new DateTime();
            $current_datetime_obj->modify('-5 minutes');

            if ($appointment_datetime_obj < $current_datetime_obj) {
                $error = "Cannot book appointments in the past. Please select a future date and time.";
            } else {
                $appointment_hour = (int) $appointment_datetime_obj->format('H');
                if ($appointment_hour < 8 || $appointment_hour >= 18) {
                    $error = "Appointments must be scheduled between 8:00 AM and 5:59 PM.";
                } else {
                    try {
                        // Check if doctor is already booked within 1.5 hours of this time
                        $appointment_start = clone $appointment_datetime_obj;
                        $appointment_end = clone $appointment_datetime_obj;
                        $appointment_start->modify('-90 minutes');
                        $appointment_end->modify('+90 minutes');

                        $start_time_formatted = $appointment_start->format('Y-m-d H:i:s');
                        $end_time_formatted = $appointment_end->format('Y-m-d H:i:s');

                        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM appointments 
                                                      WHERE doctor_id = :doctor_id 
                                                      AND appointment_date BETWEEN :start_time AND :end_time
                                                      AND status != 'canceled'");
                        $check_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
                        $check_stmt->bindParam(':start_time', $start_time_formatted, PDO::PARAM_STR);
                        $check_stmt->bindParam(':end_time', $end_time_formatted, PDO::PARAM_STR);
                        $check_stmt->execute();
                        $exists = $check_stmt->fetchColumn();

                        if ($exists > 0) {
                            $error = "This doctor already has an appointment within 1.5 hours of the selected time. Each appointment requires at least 90 minutes. Please choose a different time or doctor.";
                        } else {
                            // Check if patient already has an appointment within 1.5 hours of this time
                            $check_patient = $conn->prepare("SELECT COUNT(*) FROM appointments 
                                                             WHERE patient_id = :patient_id 
                                                             AND appointment_date BETWEEN :start_time AND :end_time
                                                             AND status != 'canceled'");
                            $check_patient->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
                            $check_patient->bindParam(':start_time', $start_time_formatted, PDO::PARAM_STR);
                            $check_patient->bindParam(':end_time', $end_time_formatted, PDO::PARAM_STR);
                            $check_patient->execute();
                            $patient_exists = $check_patient->fetchColumn();

                            if ($patient_exists > 0) {
                                $error = "This patient already has another appointment within 1.5 hours of the selected time. Please choose a different time.";
                            } else {
                                $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, service_id, appointment_date, notes) VALUES (:patient_id, :doctor_id, :service_id, :appointment_date, :notes)");
                                $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
                                $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
                                $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
                                $stmt->bindParam(':appointment_date', $datetime_sql_format, PDO::PARAM_STR);
                                $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
                                $stmt->execute();
                                $success = true;
                                $submitted_data = []; // Clear submitted data on success
                            }
                        }
                    } catch (PDOException $e) {
                        $error = "Failed to book appointment: " . $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Invalid date or time format provided.";
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 glass-card">
                <div class="card-header bg-gradient-primary text-white text-center py-4">
                    <h2 class="mb-0 fw-bold"><i class="fas fa-calendar-plus me-2"></i>Book New Appointment</h2>
                </div>
                <div class="card-body p-5">
                    <?php if ($success): ?>
                        <div class="alert alert-success text-center fs-5">
                            <i class="fas fa-check-circle me-2"></i>Appointment booked successfully!
                        </div>
                        <div class="text-center">
                            <a href="manage_appointments.php" class="btn btn-outline-primary mt-3 px-4 py-2 fs-5">
                                <i class="fas fa-arrow-left me-1"></i> Back to Appointments
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger text-center fs-5"><i
                                    class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" id="addAppointmentForm" autocomplete="off"
                            action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label for="patient_id" class="form-label fw-semibold">Patient <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select select2-ajax" id="patient_id" name="patient_id" required
                                        style="width:100%;">
                                        <?php if (!empty($submitted_data['patient_id']) && !empty($submitted_data['patient_id_text'])): ?>
                                            <option value="<?php echo htmlspecialchars($submitted_data['patient_id']); ?>"
                                                selected><?php echo htmlspecialchars($submitted_data['patient_id_text']); ?>
                                            </option>
                                        <?php else: ?>
                                            <option value="">Search Patient...</option>
                                        <?php endif; ?>
                                        <option value="new">+ Add New Patient</option>
                                    </select>
                                    <input type="hidden" name="patient_id_text" id="patient_id_text"
                                        value="<?php echo htmlspecialchars($submitted_data['patient_id_text'] ?? ''); ?>">
                                    <div class="form-text">Type to search patients by name or contact.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="doctor_id" class="form-label fw-semibold">Doctor <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select select2" id="doctor_id" name="doctor_id" required>
                                        <option value="">Select Doctor...</option>
                                        <?php foreach ($doctors as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?php echo (isset($submitted_data['doctor_id']) && $submitted_data['doctor_id'] == $d['id']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($d['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label for="service_id" class="form-label fw-semibold">Service <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select select2" id="service_id" name="service_id" required>
                                        <option value="">Select Service...</option>
                                        <?php foreach ($services as $s): ?>
                                            <option value="<?= $s['id'] ?>" <?php echo (isset($submitted_data['service_id']) && $submitted_data['service_id'] == $s['id']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($s['service_name']) ?>
                                                (<?= number_format($s['price'], 2) ?>$)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="appointment_date" class="form-label fw-semibold">Date <span
                                            class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date"
                                        min="<?= date('Y-m-d') ?>" required
                                        value="<?php echo htmlspecialchars($submitted_data['appointment_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="appointment_time" class="form-label fw-semibold">Time <span
                                            class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="appointment_time" name="appointment_time"
                                        required
                                        value="<?php echo htmlspecialchars($submitted_data['appointment_time'] ?? ''); ?>"
                                        step="900">
                                    <small class="form-text text-muted">Clinic hours: 8 AM - 6 PM.</small>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="notes" class="form-label fw-semibold">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"
                                    placeholder="Optional notes..."><?php echo htmlspecialchars($submitted_data['notes'] ?? ''); ?></textarea>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-gradient-success btn-lg shadow-lg fs-5">
                                    <i class="fas fa-calendar-check me-2"></i>Book Appointment
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Patient Modal -->
<div class="modal fade" id="newPatientModal" tabindex="-1" aria-labelledby="newPatientModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content glass-card" id="newPatientForm" autocomplete="off">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="newPatientModalLabel"><i class="fas fa-user-plus me-2"></i>Add New Patient
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="newPatientError" class="alert alert-danger d-none"></div>
                <div class="mb-3">
                    <label for="np_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="np_first_name" name="first_name" required>
                </div>
                <div class="mb-3">
                    <label for="np_last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="np_last_name" name="last_name" required>
                </div>
                <div class="mb-3">
                    <label for="np_contact_number" class="form-label">Contact Number <span
                            class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="np_contact_number" name="contact_number" required>
                </div>
                <div class="mb-3">
                    <label for="np_address" class="form-label">Address</label>
                    <input type="text" class="form-control" id="np_address" name="address">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Patient</button>
            </div>
        </form>
    </div>
</div>

<!-- Load jQuery before using it -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
    rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        $('#patient_id').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search Patient...',
            ajax: {
                url: '../ajax/search_patients.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    let results = data.results || [];
                    results.push({ id: 'new', text: '+ Add New Patient' });
                    return { results: results };
                },
                cache: true
            },
            minimumInputLength: 1
        });

        $('#patient_id').on('select2:select', function (e) {
            var data = e.params.data;
            $('#patient_id_text').val(data.text);
            if (data.id === 'new') {
                $('#newPatientModal').modal('show');
                $('#patient_id').val(null).trigger('change');
            }
        });

        $('#newPatientForm').on('submit', function (e) {
            e.preventDefault();
            $('#newPatientError').addClass('d-none').text('');
            var formData = $(this).serialize();
            $.post('../ajax/add_patient.php', formData, function (data) {
                if (data.status === 'success') {
                    var newOption = new Option(
                        data.patient.last_name + ', ' + data.patient.first_name + ' (' + data.patient.contact_number + ')',
                        data.patient.id,
                        true, true
                    );
                    $('#patient_id').append(newOption).trigger('change');
                    $('#newPatientModal').modal('hide');
                    $('#newPatientForm')[0].reset();
                } else {
                    $('#newPatientError').removeClass('d-none').text(data.message || 'Failed to add patient.');
                }
            }, 'json').fail(function () {
                $('#newPatientError').removeClass('d-none').text('Server error. Please try again.');
            });
        });
    });
</script>

<style>
    body {
        background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
        min-height: 100vh;
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(8px);
        border-radius: 1.5rem;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
    }

    .bg-gradient-primary {
        background: linear-gradient(90deg, #2575b8 0%, #6a11cb 100%) !important;
    }

    .btn-gradient-success {
        background: linear-gradient(90deg, #43cea2 0%, #185a9d 100%);
        color: #fff;
        border: none;
        transition: background 0.3s;
    }

    .btn-gradient-success:hover,
    .btn-gradient-success:focus {
        background: linear-gradient(90deg, #185a9d 0%, #43cea2 100%);
        color: #fff;
    }

    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 0.75rem;
        min-height: 2.7rem;
        font-size: 1.1rem;
    }

    .modal-content {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 4px 24px 0 rgba(106, 17, 203, 0.10);
    }

    .form-label {
        font-weight: 600;
        color: #185a9d;
    }

    input,
    textarea,
    select {
        font-size: 1.08rem !important;
    }
</style>

<?php include '../includes/footer.php'; ?>