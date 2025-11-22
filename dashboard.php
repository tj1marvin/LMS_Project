<?php
// Include database connection and functions - Assuming these are in 'db_connection.php' and 'index.php' respectively
require_once 'db_connection.php'; // Ensure this path is correct
require_once 'index.php';       // Ensure this path is correct and contains connect_db(), getTotalCount(), etc.

// Establish database connection using the function from index.php
$conn = connect_db();
if (!$conn) {
    // Log error and return JSON error response
    error_log("Data Fetch Script: Database connection failed.");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection error']);
    exit; // Stop further execution
}

$data = []; // Initialize data array to hold results

// --- Fetch Data using functions ---

// 1. Total Users (Students)
$total_users = getTotalCount($conn, 'students_registration');
if ($total_users === false) { // Check for explicit false return indicating error from getTotalCount
    error_log("Data Fetch Script: Failed to fetch total users.");
    $data['error_users'] = 'Failed to fetch total users'; // Optional: Add specific error to data
    $total_users = 0; // Set to default value for display
}
$data['total_users'] = $total_users;


// 2. Total Books
$total_books = getTotalCount($conn, 'books');
if ($total_books === false) {
    error_log("Data Fetch Script: Failed to fetch total books.");
    $data['error_books'] = 'Failed to fetch total books';
    $total_books = 0;
}
$data['total_books'] = $total_books;

// 3. Total Issued Books
$total_issued_books = getTotalCount($conn, 'issue_book');
if ($total_issued_books === false) {
    error_log("Data Fetch Script: Failed to fetch total issued books.");
    $data['error_issued_books'] = 'Failed to fetch total issued books';
    $total_issued_books = 0;
}
$data['total_issued_books'] = $total_issued_books;

// 4. Total Returned Books
$total_returned_books = getTotalCount($conn, 'issue_book', "status = ?", ['returned']); // Use prepared statement
if ($total_returned_books === false) {
    error_log("Data Fetch Script: Failed to fetch total returned books.");
    $data['error_returned_books'] = 'Failed to fetch total returned books';
    $total_returned_books = 0;
}
$data['total_returned_books'] = $total_returned_books;

// 5. Total Penalty Amount -  Need to modify getTotalCount slightly to handle SUM, or create a new function
function getTotalSum($conn, $tableName, $sumColumn, $condition = '', $params = []) {
    $sql = "SELECT SUM(" . $sumColumn . ") as total_sum FROM " . $tableName;
    if ($condition) {
        $sql .= " WHERE " . $condition;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("getTotalSum SQL Prepare Error: " . $conn->error);
        return false; // Indicate prepare error
    }

    if ($params) {
        $types = str_repeat('s', count($params)); // Assuming string parameters, adjust if needed
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log("getTotalSum SQL Execute Error: " . $stmt->error);
        return false; // Indicate execute error
    }

    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['total_sum'] ?? 0; // Return sum or 0 if NULL
    }

    $stmt->close();
    return 0; // Return 0 if no result or error
}


$total_penalty = getTotalSum($conn, 'penalties', 'penalty_amount'); // Assuming 'penalty_amount' is the correct column
if ($total_penalty === false) {
    error_log("Data Fetch Script: Failed to fetch total penalty.");
    $data['error_penalty'] = 'Failed to fetch total penalty';
    $total_penalty = 0;
}
$data['total_penalty'] = $total_penalty;


// 6. Monthly Issued Books Data
$sql = "SELECT MONTH(issue_date) as month, COUNT(*) as total_issued FROM issue_book GROUP BY MONTH(issue_date) ORDER BY MIN(issue_date)"; // Order by month
$result = $conn->query($sql);
$months = [];
$totalIssued = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $months[] = date('F', mktime(0, 0, 0, $row['month'], 1)); // Convert month number to month name (Full month name)
        $totalIssued[] = (int)$row['total_issued']; // Ensure integer for chart data
    }
} else {
    error_log("Data Fetch Script: Failed to fetch monthly issued books data. Error: " . $conn->error);
    $months = []; // Ensure empty arrays are returned in case of error
    $totalIssued = [];
    $data['error_monthly_issued'] = 'Failed to fetch monthly issued books data';
}

$data['months'] = $months;
$data['totalIssued'] = $totalIssued;



// Close the database connection
$conn->close();

// Output data as JSON
header('Content-Type: application/json');
echo json_encode($data);
?>