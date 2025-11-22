<?php
// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS"; // replace with your database name

session_start();

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Check if a search query is provided (either from URL or AJAX)
$search = isset($_GET['term']) ? $_GET['term'] : '';

if ($search) {
    // Prepare the SQL statement
    $sql = "SELECT id, title, author, genre, image_path FROM books 
            WHERE title LIKE ? 
            OR author LIKE ? 
            OR isbn LIKE ? 
            OR genre LIKE ?";
    
    // Prepare the statement
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['error' => 'SQL prepare failed: ' . $conn->error]);
        exit();
    }

    // Bind parameters
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if there are results
    if ($result->num_rows > 0) {
        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = $row; // Collect all book data
        }
        echo json_encode($books); // Return as JSON
    } else {
        echo json_encode([]); // Return an empty array if no results
    }

    // Close the statement
    $stmt->close();
} else {
    // Handle the case where no search query is provided
    echo json_encode([]); 
}

// Close the database connection
$conn->close();
?>
