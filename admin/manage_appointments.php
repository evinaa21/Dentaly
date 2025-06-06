<?php
// Include database connection and start session etc.
include '../config/db.php';

// Define page title *before* including header
$page_title = 'Manage Appointments';
include '../includes/header.php'; // Includes session check, db connection (if needed), sidebar, and starts main-content div

// Check if the user is actually an admin - redirect if not (or adjust role check as needed)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$message = ''; // For success/error messages
$message_class = 'alert-info'; // Default

// AUTO-CANCEL PAST APPOINTMENTS
try {
    $auto_cancel_query = "
        UPDATE appointments 
        SET status = 'canceled' 
        WHERE status = 'pending' 
        AND appointment_date < NOW()
    ";
    $auto_cancel_stmt = $conn->prepare($auto_cancel_query);
    $auto_cancel_stmt->execute();

    $affected_rows = $auto_cancel_stmt->rowCount();
    if ($affected_rows > 0) {
        // Check if a message is already set from delete operation
        if (!empty($message)) {
            $message .= " Additionally, $affected_rows past pending appointments were automatically canceled.";
        } else {
            $message = "$affected_rows past pending appointments were automatically canceled.";
        }
        $message_class = 'alert-info'; // Keep as info or change if needed
    }
} catch (PDOException $e) {
    error_log("Auto-cancel error: " . $e->getMessage());
}

// Handle Delete Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    if ($delete_id <= 0) {
        $message = "Invalid appointment ID for deletion.";
        $message_class = 'alert-danger';
    } else {
        try {
            $delete_query = "DELETE FROM appointments WHERE id = :id";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);

            if ($delete_stmt->execute()) {
                if ($delete_stmt->rowCount() > 0) {
                    $message = "Appointment deleted successfully!";
                    $message_class = 'alert-success';
                } else {
                    $message = "Appointment not found or already deleted.";
                    $message_class = 'alert-warning';
                }
            } else {
                $message = "Failed to delete appointment.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            $message = "Error deleting appointment: " . $e->getMessage();
            $message_class = 'alert-danger';
            // error_log("Delete Appointment Error: " . $e->getMessage());
        }
    }
}

// Fetch all appointments with patient, doctor, and service details
try {
    $query = "
        SELECT 
            a.id, a.appointment_date, a.status, a.notes,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            u.name AS doctor_name,
            s.service_name, s.price
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        JOIN services s ON a.service_id = s.id
        ORDER BY a.appointment_date DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching appointments: " . $e->getMessage();
    $message_class = 'alert-danger';
    $appointments = [];
}
?>

<!-- Page Title and Add Button -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">Manage Appointments</h2>
    <a href="add_appointment.php" class="btn btn-primary d-none d-sm-inline-flex">
        <!-- Hide on xs screens if floating button is used -->
        <i class="fas fa-plus me-2"></i>Add New Appointment
    </a>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo htmlspecialchars($message_class); ?> alert-dismissible fade show" role="alert"
        data-auto-dismiss="8000">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Appointments Table Card -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="fas fa-calendar-alt me-2"></i>All Appointments
    </div>
    <div class="card-body p-0">
        <?php if (empty($appointments)): ?>
            <p class="text-center text-muted p-4">No appointments found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date & Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Service</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Price</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($appointment['appointment_date']))); ?>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td class="text-center">
                                    <span class="badge rounded-pill <?php
                                    switch ($appointment['status']) {
                                        case 'completed':
                                            echo 'bg-success';
                                            break;
                                        case 'canceled':
                                            echo 'bg-danger';
                                            break;
                                        default:
                                            echo 'bg-warning text-dark';
                                    }
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                    </span>
                                </td>
                                <td class="text-end">$<?php echo number_format($appointment['price'], 2); ?></td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>"
                                            class="btn btn-info-soft btn-sm" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_appointment.php?id=<?php echo $appointment['id']; ?>"
                                            class="btn btn-warning-soft btn-sm" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline confirm-delete-form">
                                            <input type="hidden" name="delete_id" value="<?php echo $appointment['id']; ?>">
                                            <button type="submit" class="btn btn-danger-soft btn-sm confirm-delete"
                                                data-message="Delete appointment for <?php echo htmlspecialchars($appointment['patient_name']); ?>?"
                                                data-bs-toggle="tooltip" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Initialize Bootstrap tooltips
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Custom confirm for delete forms
    const confirmDeleteForms = document.querySelectorAll('.confirm-delete-form');
    confirmDeleteForms.forEach(form => {
        form.addEventListener('submit', function (event) {
            const button = form.querySelector('.confirm-delete');
            const message = button.dataset.message || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
</script>

<style>
    /* --- Ultra-Premium Table & Card Layout Enhancements --- */
    /* Consider moving these to a global CSS file if used on multiple pages, 
       or a page-specific <style> block in the <head> if substantial.
       For this exercise, keeping them here as per original structure. */
    .card {
        border-radius: 1.25rem;
        box-shadow: 0 8px 32px 0 rgba(13, 110, 253, 0.09), 0 1.5px 6px 0 rgba(0, 0, 0, 0.04);
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(8px);
        border: 1.5px solid #e5e9f2;
        overflow: hidden;
        position: relative;
        margin-bottom: 2.5rem;
    }

    .card-header {
        background: linear-gradient(90deg, #fff 80%, #f0f4ff 100%);
        border-radius: 1rem 1rem 0 0;
        font-weight: 700;
        font-size: 1.15rem;
        letter-spacing: 0.3px;
        border-bottom: 1px solid #e5e5e5;
        padding: 1.1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: relative;
        z-index: 2;
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

    .btn-outline-info,
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

    .btn-outline-info:last-child,
    .btn-outline-warning:last-child,
    .btn-outline-danger:last-child {
        margin-right: 0;
    }

    .btn-outline-info:hover,
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

    .btn-outline-info:hover {
        background: #d1ecf1;
        color: #0c5460 !important;
    }

    .btn-outline-info i,
    .btn-outline-warning i,
    .btn-outline-danger i {
        transition: transform 0.18s;
    }

    .btn-outline-info:hover i,
    .btn-outline-warning:hover i,
    .btn-outline-danger:hover i {
        transform: scale(1.18) rotate(-8deg);
    }

    @media (max-width: 991.98px) {

        .card,
        .card-header,
        .table,
        .table-responsive {
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

        /* Floating Add Button for mobile */
        .floating-add-btn {
            /* display: none; */
            /* Controlled by d-sm-none on the button itself now */
            display: inline-flex !important;
            /* Ensure it shows on small screens */
        }
    }

    .floating-add-btn {
        display: none;
        position: fixed;
        bottom: 2.2rem;
        right: 1.5rem;
        z-index: 1055;
        background: linear-gradient(90deg, #0d6efd 80%, #f0f4ff 100%);
        color: #fff;
        border-radius: 50%;
        width: 56px;
        height: 56px;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 32px 0 rgba(13, 110, 253, 0.18);
        font-size: 2rem;
        border: none;
        transition: box-shadow 0.18s, transform 0.18s, background 0.18s;
    }

    .floating-add-btn:hover,
    .floating-add-btn:focus {
        background: #0d6efd;
        color: #fff;
        box-shadow: 0 12px 32px 0 rgba(13, 110, 253, 0.28);
        transform: scale(1.08);
    }

    .card::after {
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

<!-- Floating Add Button for mobile -->
<a href="add_appointment.php" class="btn floating-add-btn d-sm-none" title="Add New Appointment">
    <i class="fas fa-plus"></i>
</a>

<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>