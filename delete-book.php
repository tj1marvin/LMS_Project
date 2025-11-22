<?php
session_start(); // Start the session
// Include your database connection code
include 'db_connection.php'; // Adjust the path as necessary
// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
  }
  
  $admin_id = $_SESSION['admin_id'];


// Check if the book ID is provided
if (isset($_GET['bookId'])) {
    $bookId = intval($_GET['bookId']); // Get the book ID from the URL and convert it to an integer

    // Prepare the SQL statement to delete the book
    $sql = "DELETE FROM books WHERE id = ?"; // Replace 'books' with your actual table name

    // Prepare and execute the statement
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $bookId); // Bind the book ID parameter
        if ($stmt->execute()) {
            // If the deletion is successful
            $_SESSION['delete_success'] = "Book deleted successfully!";
        } else {
            // If there was an error during deletion
            $_SESSION['delete_error'] = "Failed to delete the book.";
        }
        $stmt->close();
    } else {
        $_SESSION['delete_error'] = "Failed to prepare the SQL statement.";
    }
} else {
    $_SESSION['delete_error'] = "No book ID provided.";
}

// Redirect back to view-books.php
header("Location: view-books.php");
exit();
?>
