<?php
// Create admin/export.php
require_once '../config/db.php';
// No header output if we are directly outputting CSV
// session_start(); // Ensure session is started if checking $_SESSION['role']

if (isset($_GET['export'])) {
    // Perform session check and role validation before any output
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        // If not admin and trying to export, deny access.
        // You might want to redirect or show an error page instead of just exiting.
        http_response_code(403);
        exit('Access Denied. You do not have permission to export data.');
    }

    $type = $_GET['export'];
    $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // Add BOM for UTF-8 to support special characters in Excel
    fwrite($output, "\xEF\xBB\xBF");


    // Function to sanitize data for CSV
    function sanitize_for_csv($data)
    {
        if (is_null($data)) {
            return '';
        }
        // Prevent CSV injection by ensuring data starting with =, +, -, @ are quoted
        // or by stripping/escaping them. A simple approach is to quote all fields.
        // However, fputcsv handles quoting. The main concern is the content itself.
        // For simplicity, we'll rely on fputcsv's default behavior.
        // More advanced sanitization might strip formulas or escape specific characters.
        return $data;
    }

    switch ($type) {
        case 'patients':
            fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Contact', 'Address', 'Dental History', 'Created Date']);
            $patients_query = $conn->query("SELECT p.id, p.first_name, p.last_name, u.email, p.contact_number, p.address, p.dental_history, p.created_at 
                                           FROM patients p 
                                           LEFT JOIN users u ON p.user_id = u.id
                                           ORDER BY p.created_at DESC");
            if ($patients_query) {
                while ($patient = $patients_query->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, array_map('sanitize_for_csv', [
                        $patient['id'],
                        $patient['first_name'],
                        $patient['last_name'],
                        $patient['email'], // Added email
                        $patient['contact_number'],
                        $patient['address'],
                        $patient['dental_history'],
                        $patient['created_at']
                    ]));
                }
            }
            break;

        case 'appointments':
            fputcsv($output, ['ID', 'Patient', 'Doctor', 'Service', 'Date', 'Status', 'Price']);
            $appointments_query = $conn->query("
                SELECT a.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       u.name as doctor_name,
                       s.service_name,
                       s.price
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users u ON a.doctor_id = u.id
                JOIN services s ON a.service_id = s.id
                ORDER BY a.appointment_date DESC
            ");
            if ($appointments_query) {
                while ($appt = $appointments_query->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, array_map('sanitize_for_csv', [
                        $appt['id'],
                        $appt['patient_name'],
                        $appt['doctor_name'],
                        $appt['service_name'],
                        $appt['appointment_date'],
                        $appt['status'],
                        $appt['price']
                    ]));
                }
            }
            break;

        case 'users':
            fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Created Date']);
            $users_query = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
            if ($users_query) {
                while ($user = $users_query->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, array_map('sanitize_for_csv', [
                        $user['id'],
                        $user['name'],
                        $user['email'],
                        $user['role'],
                        $user['created_at']
                    ]));
                }
            }
            break;

        default:
            // Handle unknown export type - perhaps close $output and show an error.
            // For now, just close and exit.
            fclose($output);
            exit('Invalid export type specified.');
    }

    fclose($output);
    exit;
}

// If not exporting, then show the HTML page
$page_title = "Export Data"; // Define page title for header
include '../includes/header.php';

// Ensure user is admin to view this page
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
</div>

<!-- Export Options -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="fas fa-download me-2"></i> Export Data
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card border">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h5>Patient Data</h5>
                        <p class="text-muted">Export all patient information</p>
                        <a href="export.php?export=patients" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                        <h5>Appointment Data</h5>
                        <p class="text-muted">Export all appointment records</p>
                        <a href="export.php?export=appointments" class="btn btn-success">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border">
                    <div class="card-body text-center">
                        <i class="fas fa-user-shield fa-3x text-warning mb-3"></i>
                        <h5>User Data</h5>
                        <p class="text-muted">Export all user accounts</p>
                        <a href="export.php?export=users" class="btn btn-warning text-dark">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>