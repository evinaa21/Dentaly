<?php
// Include database connection and start session etc.
include '../config/db.php';

// Define page title *before* including header
$page_title = 'Manage Appointments';
include '../includes/header.php'; // Includes session check, db connection (if needed), sidebar, and starts main-content div

// Check if the user is actually an admin - redirect if not (or adjust role check as needed)
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$message = ''; // For success/error messages

// Handle Delete Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    try {
        // Delete the appointment
        $delete_query = "DELETE FROM appointments WHERE id = :id";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
        if ($delete_stmt->execute()) {
            $message = "Appointment (ID: $delete_id) deleted successfully!";
            // Use a success class for styling
            $message_class = 'alert-success';
        } else {
            $message = "Failed to delete the appointment.";
            $message_class = 'alert-danger';
        }
    } catch (PDOException $e) {
        $message = "Error deleting appointment: " . $e->getMessage();
        $message_class = 'alert-danger';
    }
}

// Fetch all appointments with patient, doctor, and service details
try {
    $query = "
        SELECT
            a.id AS appointment_id,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            d.name AS doctor_name,
            s.service_name,
            a.appointment_date,
            a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users d ON a.doctor_id = d.id
        JOIN services s ON a.service_id = s.id
        ORDER BY a.appointment_date DESC -- Show most recent first, or ASC for oldest first
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Display error within the main content area
    $message = "Error fetching appointments: " . $e->getMessage();
    $message_class = 'alert-danger';
    $appointments = []; // Ensure appointments array is empty on error
    // Consider logging the error instead of dying in production
    // error_log("Query Failed: " . $e->getMessage());
}
?>

<!-- Page Title and Add Button -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">Manage Appointments</h2>
    <a href="add_appointment.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i> Add New Appointment
    </a>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo $message_class ?? 'alert-info'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Appointments Table Card -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="fas fa-calendar-check me-2"></i> All Appointments
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light"> <!-- Use table-light for better contrast with dark sidebar -->
                    <tr>
                        <th class="text-center">ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No appointments found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td class="text-center"><?php echo htmlspecialchars($appointment['appointment_id']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($appointment['appointment_date']))); ?>
                                </td>
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
                                            break; // Pending
                                    }
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <!-- Use a dropdown for actions on smaller screens if needed, or keep inline -->
                                    <a href="view_appointment.php?id=<?php echo htmlspecialchars($appointment['appointment_id']); ?>"
                                        class="btn btn-sm btn-outline-info me-1" data-bs-toggle="tooltip" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_appointment.php?id=<?php echo htmlspecialchars($appointment['appointment_id']); ?>"
                                        class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="tooltip" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this appointment?');">
                                        <input type="hidden" name="delete_id"
                                            value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip"
                                            title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- /card-body -->
</div> <!-- /card -->

<!-- Initialize Bootstrap Tooltips -->
<script>
    // Ensure tooltips are initialized after content is loaded
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>

<style>
    /* --- Ultra-Premium Table & Card Layout Enhancements --- */
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
            display: flex !important;
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

<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>