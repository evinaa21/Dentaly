<?php
$page_title = "Book New Appointment";
include '../includes/header.php';

// DB connection
if (!isset($conn)) {
    include '../config/db.php';
}

// For large patient databases, use AJAX search instead of loading all patients
$doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$services = $conn->query("SELECT id, service_name, price FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'] ?? '';
    $doctor_id = $_POST['doctor_id'] ?? '';
    $service_id = $_POST['service_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (!$patient_id || !$doctor_id || !$service_id || !$appointment_date || !$appointment_time) {
        $error = "All fields except notes are required.";
    } else {
        $datetime = date('Y-m-d H:i:00', strtotime("$appointment_date $appointment_time"));
        try {
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, service_id, appointment_date, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$patient_id, $doctor_id, $service_id, $datetime, $notes]);
            $success = true;
        } catch (PDOException $e) {
            $error = "Failed to book appointment. Please try again.";
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
                        <form method="POST" id="addAppointmentForm" autocomplete="off">
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label for="patient_id" class="form-label fw-semibold">Patient <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select select2-ajax" id="patient_id" name="patient_id" required
                                        style="width:100%;">
                                        <option value="">Search Patient...</option>
                                        <option value="new">+ Add New Patient</option>
                                    </select>
                                    <div class="form-text">Type to search patients by name or contact.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="doctor_id" class="form-label fw-semibold">Doctor <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select select2" id="doctor_id" name="doctor_id" required>
                                        <option value="">Select Doctor...</option>
                                        <?php foreach ($doctors as $d): ?>
                                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
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
                                            <option value="<?= $s['id'] ?>">
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
                                        min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="appointment_time" class="form-label fw-semibold">Time <span
                                            class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="appointment_time" name="appointment_time"
                                        required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="notes" class="form-label fw-semibold">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"
                                    placeholder="Optional notes..."></textarea>
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
                <div class="mb-3">
                    <label for="np_dental_history" class="form-label">Dental History</label>
                    <textarea class="form-control" id="np_dental_history" name="dental_history" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Patient</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom JS for Select2, AJAX Patient Search, Modal -->
<script>
    $(document).ready(function () {
        // Select2 for static dropdowns
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // AJAX Select2 for patients (for large DB)
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
                    // Add "Add New Patient" option at the end
                    results.push({ id: 'new', text: '+ Add New Patient' });
                    return { results: results };
                },
                cache: true
            },
            minimumInputLength: 1
        });

        // Show modal when "Add New Patient" is selected
        $('#patient_id').on('select2:select', function (e) {
            if (e.params.data.id === 'new') {
                $('#newPatientModal').modal('show');
                $('#patient_id').val(null).trigger('change');
            }
        });

        // Handle new patient form submission via AJAX
        $('#newPatientForm').on('submit', function (e) {
            e.preventDefault();
            $('#newPatientError').addClass('d-none').text('');
            var formData = $(this).serialize();
            $.post('../ajax/add_patient.php', formData, function (data) {
                if (data.status === 'success') {
                    // Add new patient to dropdown and select it
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