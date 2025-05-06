<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}
if (!isset($conn)) {
    include '../config/db.php';
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$dental_history = trim($_POST['dental_history'] ?? '');

if (!$first_name || !$last_name || !$contact_number) {
    echo json_encode(['status' => 'error', 'message' => 'First name, last name, and contact number are required.']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, contact_number, address, dental_history) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$first_name, $last_name, $contact_number, $address, $dental_history]);
    $id = $conn->lastInsertId();
    echo json_encode([
        'status' => 'success',
        'patient' => [
            'id' => $id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'contact_number' => $contact_number
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}