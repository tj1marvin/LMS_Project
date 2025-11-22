<?php
session_start();

// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS";

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the student_id is set in the session
if (!isset($_SESSION['student_id'])) {
    echo "not_logged_in";
    exit();
}

$student_id = $_SESSION['student_id'];

// Check if book_id is set in the POST request
if (isset($_POST['book_id'])) {
    $book_id = intval($_POST['book_id']);

    // Remove the book from the reserved table
    $delete_sql = "DELETE FROM reserved WHERE student_id = ? AND book_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $student_id, $book_id);

    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            // Successfully removed from reserved
            echo "removed"; 
        } else {
            // Book was not found in the reserved list
            echo "not_found"; 
        }
    } else {
        // Error during removal
        echo "error_removing: " . $delete_stmt->error; 
    }

    $delete_stmt->close();
} else {
    // book_id not set
    echo "no_book_id"; 
}

// Close the database connection
$conn->close();
?>
