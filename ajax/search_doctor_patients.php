<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request.', 'data' => []];

// 1. Check Login & Role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    $response['message'] = 'Authentication required or invalid role.';
    echo json_encode($response);
    exit;
}
$doctor_id = $_SESSION['user_id'];

// 2. Get Search Query
$search = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($search) < 1) { // Require at least 1 character? Adjust if needed.
    $response['status'] = 'success'; // Return success but empty data if query is too short
    $response['message'] = 'Search term too short.';
    echo json_encode($response);
    exit;
}

// 3. Perform Search Query
$patients = [];
try {
    $searchTerm = '%' . $search . '%';
    // Query finds distinct patients who have had an appointment with this doctor
    // and match the search term
    $query = "
        SELECT DISTINCT p.id, p.first_name, p.last_name, p.contact_number
        FROM patients p
        JOIN appointments a ON p.id = a.patient_id
        WHERE a.doctor_id = :doctor_id
        AND (p.first_name LIKE :search OR p.last_name LIKE :search OR p.contact_number LIKE :search)
        ORDER BY p.last_name, p.first_name
        LIMIT 15; -- Limit results for performance
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['status'] = 'success';
    $response['message'] = count($patients) . ' patient(s) found.';
    $response['data'] = $patients;

} catch (PDOException $e) {
    // error_log("Search Doctor Patients Error: " . $e->getMessage());
    $response['message'] = 'Database error during search.';
}

echo json_encode($response);
exit;
?>