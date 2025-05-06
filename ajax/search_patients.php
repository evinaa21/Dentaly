<?php
header('Content-Type: application/json');
if (!isset($conn))
    include '../config/db.php';
$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, contact_number FROM patients WHERE first_name LIKE ? OR last_name LIKE ? OR contact_number LIKE ? ORDER BY last_name LIMIT 20");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'id' => $row['id'],
            'text' => "{$row['last_name']}, {$row['first_name']} ({$row['contact_number']})"
        ];
    }
}
echo json_encode(['results' => $results]);