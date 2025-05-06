<?php
include '../config/db.php'; // Include database connection

header('Content-Type: application/json'); // Set header early

$search = isset($_GET['query']) ? trim($_GET['query']) : '';
$clients = []; // Default to empty array

if (!empty($search)) {
    $searchTerms = explode(' ', $search); // Split the search query into words
    $searchTerms = array_filter($searchTerms); // Remove empty elements

    if (!empty($searchTerms)) {
        // Build the WHERE clause dynamically
        $whereClauses = [];
        $params = [];
        $counter = 0; // Use counter for unique param names

        foreach ($searchTerms as $term) {
            $paramName = ":search" . $counter++;
            // Search in first name, last name, and contact number
            $whereClauses[] = "(first_name LIKE $paramName OR last_name LIKE $paramName OR contact_number LIKE $paramName)";
            $params[$paramName] = '%' . $term . '%';
        }

        $whereClause = implode(' AND ', $whereClauses); // Use AND to match all terms

        $query = "
            SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, contact_number
            FROM patients
            WHERE $whereClause
            ORDER BY last_name, first_name -- Order results
            LIMIT 15; -- Limit results for performance
        ";

        try {
            $stmt = $conn->prepare($query);
            // Bind parameters using the generated names
            foreach ($params as $paramName => $paramValue) {
                $stmt->bindValue($paramName, $paramValue, PDO::PARAM_STR);
            }
            $stmt->execute();
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Log error, but return empty array to client to avoid exposing details
            error_log("Client Search Error: " . $e->getMessage());
            $clients = []; // Ensure empty array on error
        }
    }
}

// Always return JSON, even if empty
echo json_encode($clients);
exit; // Ensure script terminates
?>