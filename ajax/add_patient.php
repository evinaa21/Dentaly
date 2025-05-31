<?php
include '../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$dental_history = trim($_POST['dental_history'] ?? '');

if (empty($first_name) || empty($last_name) || empty($contact_number)) {
    echo json_encode(['status' => 'error', 'message' => 'First name, last name, and contact number are required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO patients (first_name, last_name, contact_number, address, dental_history) 
        VALUES (:first_name, :last_name, :contact_number, :address, :dental_history)
    ");

    $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
    $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
    $stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);
    $stmt->bindParam(':address', $address, PDO::PARAM_STR);
    $stmt->bindParam(':dental_history', $dental_history, PDO::PARAM_STR);

    if ($stmt->execute()) {
        $patient_id = $conn->lastInsertId();
        echo json_encode([
            'status' => 'success',
            'patient' => [
                'id' => $patient_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'contact_number' => $contact_number
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add patient']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>