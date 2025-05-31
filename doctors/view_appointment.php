<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'View Appointment Details';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a doctor
if ($_SESSION['role'] !== 'doctor') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// Get logged-in doctor ID
$doctor_id = $_SESSION['user_id'];
$appointment_id = null;
$appointment = null;
$error_message = '';

// Handle AJAX status update requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    
    $appointment_id_update = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $new_status = $_POST['new_status'] ?? null;
    
    $response = ['success' => false, 'message' => ''];
    
    if (!$appointment_id_update || !$new_status || !in_array($new_status, ['completed', 'canceled'])) {
        $response['message'] = 'Invalid data provided.';
    } else {
        try {
            // First check the appointment date and ownership
            $check_query = "SELECT appointment_date FROM appointments 
                           WHERE id = :appointment_id AND doctor_id = :doctor_id";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':appointment_id', $appointment_id_update, PDO::PARAM_INT);
            $check_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            $check_stmt->execute();
            $appointment_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment_data) {
                $response['message'] = 'Appointment not found or access denied.';
            } else {
                // Check if trying to mark future appointment as completed
                $appointment_date = new DateTime($appointment_data['appointment_date']);
                $current_date = new DateTime();
                
                if ($new_status === 'completed' && $appointment_date > $current_date) {
                    $response['message'] = 'Cannot mark future appointments as completed. Appointment is scheduled for ' . $appointment_date->format('M j, Y \a\t g:i A') . '.';
                } else {
                    // Update the status
                    $update_query = "UPDATE appointments SET status = :status 
                                     WHERE id = :appointment_id AND doctor_id = :doctor_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
                    $update_stmt->bindParam(':appointment_id', $appointment_id_update, PDO::PARAM_INT);
                    $update_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
                    $update_stmt->execute();
                    
                    if ($update_stmt->rowCount() > 0) {
                        $response['success'] = true;
                        $response['message'] = "Appointment status updated to {$new_status}.";
                    } else {
                        $response['message'] = 'Failed to update appointment status.';
                    }
                }
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error occurred.';
        }
    }
    
    echo json_encode($response);
    exit;
}

// 1. Get Appointment ID from URL and Validate
if (!isset($_GET['id']) || !($appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT))) {
    $error_message = "Invalid or missing appointment ID.";
} else {
    // 2. Fetch Appointment Data (Ensure it belongs to the logged-in doctor)
    try {
        $query = "
            SELECT
                a.id AS appointment_id,
                a.appointment_date,
                a.status,
                a.notes AS appointment_notes,
                p.id AS patient_id,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact,
                p.address AS patient_address,
                p.dental_history,
                p.created_at AS patient_created,
                s.id AS service_id,
                s.service_name,
                s.description AS service_description,
                s.price AS service_price
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN services s ON a.service_id = s.id
            WHERE a.id = :appointment_id AND a.doctor_id = :doctor_id
            LIMIT 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            $error_message = "Appointment not found or you do not have permission to view it.";
        }

    } catch (PDOException $e) {
        $error_message = "Database error fetching appointment details: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!-- Page Title & Back Button -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">
        <i class="fas fa-calendar-check me-2 text-primary"></i>
        <?php echo $appointment ? 'Appointment Details' : 'View Appointment'; ?>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
</div>

<!-- Display Error Message if any -->
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php else: ?>
    <!-- Alert container for AJAX messages -->
    <div id="alertContainer"></div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Left Column: Appointment & Service Details -->
        <div class="col-lg-6 mb-4">
            <!-- Appointment Details Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock me-2 text-info"></i> Appointment Info</h5>
                    <?php
                    // Determine badge class based on status
                    $status_class = 'bg-secondary'; // Default
                    if ($appointment['status'] == 'pending')
                        $status_class = 'bg-warning text-dark';
                    elseif ($appointment['status'] == 'completed')
                        $status_class = 'bg-success';
                    elseif ($appointment['status'] == 'canceled')
                        $status_class = 'bg-danger';
                    ?>
                    <span class="badge <?php echo $status_class; ?> fs-6" id="statusBadge">
                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Date & Time:</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars(date('D, d M Y - h:i A', strtotime($appointment['appointment_date']))); ?>
                            <?php
                            // Check if appointment is in the future
                            $appointment_date = new DateTime($appointment['appointment_date']);
                            $current_date = new DateTime();
                            $is_future = $appointment_date > $current_date;
                            ?>
                            <?php if ($is_future): ?>
                                <span class="badge bg-info ms-1" title="Future appointment">Future</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Status:</dt>
                        <dd class="col-sm-8" id="statusText"><?php echo ucfirst(htmlspecialchars($appointment['status'])); ?></dd>

                        <dt class="col-sm-4">Appointment ID:</dt>
                        <dd class="col-sm-8">#<?php echo htmlspecialchars($appointment['appointment_id']); ?></dd>

                        <?php if (!empty($appointment['appointment_notes'])): ?>
                            <dt class="col-sm-4 mt-2">Notes:</dt>
                            <dd class="col-sm-8 mt-2 fst-italic">
                                <?php echo nl2br(htmlspecialchars($appointment['appointment_notes'])); ?>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Service Details Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-briefcase-medical me-2 text-secondary"></i> Service Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Service:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['service_name']); ?></dd>

                        <?php if (!empty($appointment['service_description'])): ?>
                            <dt class="col-sm-4">Description:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['service_description']); ?></dd>
                        <?php endif; ?>

                        <?php if (isset($appointment['service_price'])): ?>
                            <dt class="col-sm-4">Price:</dt>
                            <dd class="col-sm-8">
                                $<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Right Column: Patient Details & Actions -->
        <div class="col-lg-6 mb-4">
            <!-- Patient Details Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-user-injured me-2 text-success"></i> Patient Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name:</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                        </dd>

                        <dt class="col-sm-4">Contact:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['patient_contact'] ?: 'N/A'); ?></dd>

                        <?php if (!empty($appointment['patient_address'])): ?>
                            <dt class="col-sm-4">Address:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['patient_address']); ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($appointment['dental_history'])): ?>
                            <dt class="col-sm-4">Dental History:</dt>
                            <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($appointment['dental_history'])); ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($appointment['patient_created'])): ?>
                            <dt class="col-sm-4">Patient Since:</dt>
                            <dd class="col-sm-8">
                                <?php echo htmlspecialchars(date('d M Y', strtotime($appointment['patient_created']))); ?>
                            </dd>
                        <?php endif; ?>

                        <dt class="col-sm-4">Patient ID:</dt>
                        <dd class="col-sm-8">#<?php echo htmlspecialchars($appointment['patient_id']); ?></dd>
                    </dl>
                    <div class="mt-3 border-top pt-3 text-center">
                        <a href="patient_details.php?patient_id=<?php echo $appointment['patient_id']; ?>"
                            class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-notes-medical me-1"></i> View Full Patient Record
                        </a>
                    </div>
                </div>
            </div>

            <!-- Actions Card - For changing appointment status -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2 text-warning"></i> Actions</h5>
                </div>
                <div class="card-body text-center" id="actionsContainer">
                    <?php if ($appointment['status'] == 'pending'): ?>
                        <?php
                        // Check if appointment is in the future for button state
                        $appointment_date = new DateTime($appointment['appointment_date']);
                        $current_date = new DateTime();
                        $is_future = $appointment_date > $current_date;
                        ?>
                        
                        <!-- Mark as Completed Button -->
                        <button class="btn btn-success me-2" id="completeBtn"
                            onclick="updateAppointmentStatus(<?php echo $appointment_id; ?>, 'completed')"
                            <?php echo $is_future ? 'disabled' : ''; ?>
                            title="<?php echo $is_future ? 'Cannot mark future appointments as completed' : 'Mark as Completed'; ?>">
                            <i class="fas fa-check me-1"></i> 
                            Mark as Completed<?php echo $is_future ? ' (Future)' : ''; ?>
                        </button>
                        
                        <!-- Cancel Appointment Button -->
                        <button class="btn btn-danger me-2" id="cancelBtn"
                            onclick="updateAppointmentStatus(<?php echo $appointment_id; ?>, 'canceled')">
                            <i class="fas fa-times me-1"></i> Cancel Appointment
                        </button>
                        
                        <?php if ($is_future): ?>
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                <small>This appointment is scheduled for the future and cannot be marked as completed yet.</small>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($appointment['status'] == 'completed'): ?>
                        <p class="text-success mb-0"><i class="fas fa-check-circle me-1"></i> This appointment is completed.</p>
                    <?php elseif ($appointment['status'] == 'canceled'): ?>
                        <p class="text-danger mb-0"><i class="fas fa-times-circle me-1"></i> This appointment was canceled.</p>
                    <?php endif; ?>

                    <!-- Add Notes Button -->
                    <button class="btn btn-outline-info mt-3" data-bs-toggle="modal" data-bs-target="#notesModal">
                        <i class="fas fa-sticky-note me-1"></i> Add/Edit Notes
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notesModalLabel"><i class="fas fa-sticky-note me-2"></i> Appointment Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="notesForm">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                        <div class="mb-3">
                            <label for="appointmentNotes" class="form-label">Notes:</label>
                            <textarea class="form-control" id="appointmentNotes" name="notes"
                                rows="6"><?php echo htmlspecialchars($appointment['appointment_notes'] ?? ''); ?></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveNotes()">Save Notes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for AJAX Actions -->
    <script>
        function updateAppointmentStatus(id, newStatus) {
            const statusText = newStatus === 'completed' ? 'completed' : 'canceled';
            if (!confirm(`Are you sure you want to mark this appointment as ${statusText}?`)) {
                return;
            }

            // Show loading state
            const completeBtn = document.getElementById('completeBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            if (completeBtn) completeBtn.disabled = true;
            if (cancelBtn) cancelBtn.disabled = true;

            fetch('view_appointment.php?id=<?php echo $appointment_id; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax_update=1&appointment_id=${id}&new_status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    
                    // Update UI elements
                    const statusBadge = document.getElementById('statusBadge');
                    const statusText = document.getElementById('statusText');
                    const actionsContainer = document.getElementById('actionsContainer');
                    
                    if (statusBadge && statusText && actionsContainer) {
                        // Update status display
                        statusText.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        
                        // Update badge
                        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        statusBadge.className = 'badge fs-6 ' + (newStatus === 'completed' ? 'bg-success' : 'bg-danger');
                        
                        // Update actions section
                        if (newStatus === 'completed') {
                            actionsContainer.innerHTML = `
                                <p class="text-success mb-0"><i class="fas fa-check-circle me-1"></i> This appointment is completed.</p>
                                <button class="btn btn-outline-info mt-3" data-bs-toggle="modal" data-bs-target="#notesModal">
                                    <i class="fas fa-sticky-note me-1"></i> Add/Edit Notes
                                </button>
                            `;
                        } else if (newStatus === 'canceled') {
                            actionsContainer.innerHTML = `
                                <p class="text-danger mb-0"><i class="fas fa-times-circle me-1"></i> This appointment was canceled.</p>
                                <button class="btn btn-outline-info mt-3" data-bs-toggle="modal" data-bs-target="#notesModal">
                                    <i class="fas fa-sticky-note me-1"></i> Add/Edit Notes
                                </button>
                            `;
                        }
                    }
                } else {
                    showAlert('danger', data.message);
                    // Re-enable buttons on error
                    if (completeBtn) completeBtn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while communicating with the server.');
                // Re-enable buttons on error
                if (completeBtn) completeBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
            });
        }

        function saveNotes() {
            const form = document.getElementById('notesForm');
            const formData = new FormData(form);

            fetch('../ajax/save_appointment_notes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    var notesModal = bootstrap.Modal.getInstance(document.getElementById('notesModal'));
                    notesModal.hide();
                    showAlert('success', 'Notes saved successfully!');
                    // Optionally reload to show updated notes
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error saving notes: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while saving notes.');
            });
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            alertContainer.appendChild(alertDiv);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>