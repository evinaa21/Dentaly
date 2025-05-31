<?php
include '../config/db.php';
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$results = [];

if (strlen(trim($query)) >= 1) {
    try {
        $search = '%' . $query . '%';
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, contact_number 
            FROM patients 
            WHERE first_name LIKE :search 
               OR last_name LIKE :search 
               OR contact_number LIKE :search 
            ORDER BY first_name, last_name 
            LIMIT 20
        ");
        $stmt->bindParam(':search', $search, PDO::PARAM_STR);
        $stmt->execute();

        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($patients as $patient) {
            $results[] = [
                'id' => $patient['id'],
                'text' => $patient['last_name'] . ', ' . $patient['first_name'] . ' (' . $patient['contact_number'] . ')'
            ];
        }
    } catch (PDOException $e) {
        // Return empty results on error
        $results = [];
    }
}

echo json_encode(['results' => $results]);
?>