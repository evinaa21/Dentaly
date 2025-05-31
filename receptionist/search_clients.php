<?php
include '../config/db.php'; // Include database connection

header('Content-Type: application/json'); // Set header early

$search = isset($_GET['query']) ? trim($_GET['query']) : '';
$clients = []; // Default to empty array

if (strlen($search) >= 2) {
    try {
        $search_param = '%' . $search . '%';
        $query = "
            SELECT 
                id, 
                CONCAT(first_name, ' ', last_name) as full_name, 
                contact_number 
            FROM patients 
            WHERE first_name LIKE :search 
               OR last_name LIKE :search 
               OR contact_number LIKE :search
            ORDER BY first_name, last_name 
            LIMIT 50
        ";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Return empty array on error
        $clients = [];
    }
}

// Always return JSON, even if empty
echo json_encode($clients);
exit; // Ensure script terminates
?>