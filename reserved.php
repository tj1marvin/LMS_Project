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

    // Check if the book is already in the wishlist
    $check_wishlist_sql = "SELECT * FROM reserved WHERE student_id = ? AND book_id = ?";
    $check_stmt = $conn->prepare($check_wishlist_sql);
    $check_stmt->bind_param("ii", $student_id, $book_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        // Book is already in the wishlist
        echo "exists"; // Indicate that the book is already in the wishlist
    } else {
        // Book is not in the wishlist, add it
        $insert_sql = "INSERT INTO reserved (student_id, book_id, added_at) VALUES (?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $student_id, $book_id);

        if ($insert_stmt->execute()) {
            echo "added"; // Successfully added to wishlist
        } else {
            echo "error_adding: " . $insert_stmt->error;
        }

        $insert_stmt->close();
    }

    $check_stmt->close();
} else {
    echo "no_book_id";
}

// Close the database connection
$conn->close();
?>
