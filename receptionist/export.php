<?php
// filepath: c:\xampp\htdocs\Dentaly\receptionist\export.php
require_once '../config/db.php';

if (isset($_GET['export'])) {
    // Perform session check and role validation before any output
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Allow admin OR receptionist to export
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'receptionist'])) {
        http_response_code(403);
        exit('Access Denied. You do not have permission to export data.');
    }

    $type = $_GET['export'];

    // Handle PDF exports
    if (strpos($type, '_pdf') !== false) {
        // PDF Export Logic
        $data_type = str_replace('_pdf', '', $type);

        // DON'T set PDF headers - we're outputting HTML that will be printed as PDF
        // header('Content-Type: application/pdf'); // REMOVE THIS LINE
        // header('Content-Disposition: attachment; filename="' . $filename . '"'); // REMOVE THIS LINE

        switch ($data_type) {
            case 'patients':
                $title = 'Patients Report';
                $query = "SELECT p.id, p.first_name, p.last_name, u.email, p.contact_number, p.address, p.dental_history, p.created_at 
                         FROM patients p 
                         LEFT JOIN users u ON p.user_id = u.id
                         ORDER BY p.created_at DESC";
                $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Contact', 'Address', 'Dental History', 'Created Date'];
                break;

            case 'appointments':
                $title = 'Appointments Report';
                $query = "SELECT a.id, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                         u.name as doctor_name, s.service_name, a.appointment_date, a.status, s.price
                         FROM appointments a
                         JOIN patients p ON a.patient_id = p.id
                         JOIN users u ON a.doctor_id = u.id
                         JOIN services s ON a.service_id = s.id
                         ORDER BY a.appointment_date DESC";
                $headers = ['ID', 'Patient', 'Doctor', 'Service', 'Date', 'Status', 'Price'];
                break;

            case 'users':
                $title = 'Users Report';
                $query = "SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC";
                $headers = ['ID', 'Name', 'Email', 'Role', 'Created Date'];
                break;

            default:
                exit('Invalid export type specified.');
        }

        // Fetch data
        $stmt = $conn->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate PDF HTML
        echo generatePDFHTML($title, $headers, $data);
        exit;
    }

    // CSV Export Logic (existing code)
    $filename = $type . '_export_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    function sanitize_for_csv($data)
    {
        if (is_null($data)) {
            return '';
        }
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
                        $patient['email'],
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
            fclose($output);
            exit('Invalid export type specified.');
    }

    fclose($output);
    exit;
}

// Function to generate PDF HTML
function generatePDFHTML($title, $headers, $data)
{
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                line-height: 1.4;
                color: #333;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px; 
                border-bottom: 2px solid #2c5aa0;
                padding-bottom: 20px;
            }
            .clinic-name { 
                font-size: 28px; 
                font-weight: bold; 
                color: #2c5aa0; 
                margin-bottom: 10px;
            }
            .report-title { 
                font-size: 20px; 
                margin-top: 10px; 
                color: #444;
            }
            .export-date { 
                font-size: 14px; 
                color: #666; 
                margin-top: 10px; 
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            th { 
                background: linear-gradient(135deg, #2c5aa0 0%, #3d6bb3 100%);
                color: white;
                border: 1px solid #2c5aa0; 
                padding: 12px 8px; 
                text-align: left; 
                font-size: 13px; 
                font-weight: bold;
            }
            td { 
                border: 1px solid #ddd; 
                padding: 10px 8px; 
                text-align: left; 
                font-size: 12px; 
                vertical-align: top;
            }
            tr:nth-child(even) { 
                background-color: #f8f9fa; 
            }
            tr:hover {
                background-color: #e3f2fd;
            }
            .footer { 
                margin-top: 40px; 
                text-align: center; 
                font-size: 11px; 
                color: #666; 
                border-top: 1px solid #ddd;
                padding-top: 20px;
            }
            .stats {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                text-align: center;
            }
            .print-btn {
                background: #28a745;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                margin: 10px;
            }
            .print-btn:hover {
                background: #218838;
            }
            @media print {
                body { margin: 0; font-size: 11px; }
                .no-print { display: none; }
                .header { border-bottom: 2px solid #000; }
                .clinic-name { color: #000; }
                th { background: #f0f0f0 !important; color: #000 !important; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="clinic-name">🦷 Dentaly Clinic</div>
            <div class="report-title">' . htmlspecialchars($title) . '</div>
            <div class="export-date">Generated on: ' . date('F j, Y \a\t g:i A') . '</div>
        </div>
        
        <div class="stats">
            <strong>Total Records: ' . count($data) . '</strong>
        </div>
        
        <table>
            <thead>
                <tr>';

    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }

    $html .= '
                </tr>
            </thead>
            <tbody>';

    if (empty($data)) {
        $html .= '<tr><td colspan="' . count($headers) . '" style="text-align: center; color: #666; padding: 20px;">No data available</td></tr>';
    } else {
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                // Format dates nicely
                if (strpos($cell, '-') && (strtotime($cell) !== false)) {
                    $formatted_date = date('M j, Y', strtotime($cell));
                    $html .= '<td>' . htmlspecialchars($formatted_date) . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($cell ?? '') . '</td>';
                }
            }
            $html .= '</tr>';
        }
    }

    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p><strong>Report Summary:</strong> This report contains ' . count($data) . ' records as of ' . date('F j, Y') . '</p>
            <p>© ' . date('Y') . ' Dentaly Clinic - All Rights Reserved | Generated by: ' . ($_SESSION['name'] ?? 'System') . '</p>
        </div>
        
        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()" class="print-btn">🖨️ Print This Report</button>
            <button onclick="window.close()" class="print-btn" style="background: #6c757d;">❌ Close</button>
        </div>
        
        <script>
            // Auto-trigger print dialog when page loads
            window.onload = function() {
                // Small delay to ensure page is fully rendered
                setTimeout(function() {
                    window.print();
                }, 500);
            }
            
            // Close window after printing (optional)
            window.onafterprint = function() {
                // Uncomment if you want to auto-close after printing
                // window.close();
            }
        </script>
    </body>
    </html>';

    return $html;
}

// If not exporting, then show the HTML page
$page_title = "Export Data";
include '../includes/header.php';

// Ensure user is admin or receptionist to view this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'receptionist'])) {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-download me-2"></i><?php echo htmlspecialchars($page_title); ?></h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
</div>

<!-- Export Options -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="fas fa-download me-2"></i> Export Data
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card border h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h5>Patient Data</h5>
                        <p class="text-muted">Export all patient information.</p>
                        <div class="mt-auto">
                            <a href="export.php?export=patients" class="btn btn-primary mb-2 w-100">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </a>
                            <a href="export.php?export=patients_pdf" class="btn btn-danger w-100" target="_blank">
                                <i class="fas fa-file-pdf me-1"></i> View & Print PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                        <h5>Appointment Data</h5>
                        <p class="text-muted">Export all appointment records.</p>
                        <div class="mt-auto">
                            <a href="export.php?export=appointments" class="btn btn-success mb-2 w-100">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </a>
                            <a href="export.php?export=appointments_pdf" class="btn btn-danger w-100" target="_blank">
                                <i class="fas fa-file-pdf me-1"></i> View & Print PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border h-100">
                    <div class="card-body text-center d-flex flex-column">
                        <i class="fas fa-user-shield fa-3x text-warning mb-3"></i>
                        <h5>User Data</h5>
                        <p class="text-muted">Export all user accounts.</p>
                        <div class="mt-auto">
                            <a href="export.php?export=users" class="btn btn-warning text-dark mb-2 w-100">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </a>
                            <a href="export.php?export=users_pdf" class="btn btn-danger w-100" target="_blank">
                                <i class="fas fa-file-pdf me-1"></i> View & Print PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle me-2"></i>
            <strong>How to use:</strong><br>
            • <strong>CSV Export:</strong> Downloads immediately - can be opened in Excel<br>
            • <strong>PDF Export:</strong> Opens in new tab with print dialog - save as PDF or print directly
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>