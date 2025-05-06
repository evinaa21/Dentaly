<?php
include '../config/db.php';

$search = isset($_GET['query']) ? trim($_GET['query']) : '';
$searchTerms = explode(' ', $search); // Split the search query into words

// Build the WHERE clause dynamically
$whereClauses = [];
$params = [];
foreach ($searchTerms as $index => $term) {
    $paramName = ":search$index";
    $whereClauses[] = "(first_name LIKE $paramName OR last_name LIKE $paramName)";
    $params[$paramName] = '%' . $term . '%';
}

$whereClause = implode(' AND ', $whereClauses);

$query = "
    SELECT id, CONCAT(first_name, ' ', last_name) AS full_name, contact_number 
    FROM patients 
    WHERE $whereClause
    LIMIT 10;
";

$stmt = $conn->prepare($query);
foreach ($params as $paramName => $paramValue) {
    $stmt->bindValue($paramName, $paramValue, PDO::PARAM_STR);
}
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($clients);