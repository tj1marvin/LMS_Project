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
    $days = 7; // Default borrowing period

    // Check if the book is already issued to the student
    $check_issued_sql = "SELECT * FROM issue_book WHERE student_id = ? AND book_id = ? AND status = 'issued'";
    $check_issued_stmt = $conn->prepare($check_issued_sql);
    $check_issued_stmt->bind_param("ii", $student_id, $book_id);
    $check_issued_stmt->execute();
    $check_issued_stmt->store_result();

    if ($check_issued_stmt->num_rows > 0) {
        echo "already_issued"; // Book is already issued to the student
        $check_issued_stmt->close();
        $conn->close();
        exit();
    }

    // Check if the book exists
    $check_book_sql = "SELECT id FROM books WHERE id = ?";
    $check_stmt = $conn->prepare($check_book_sql);
    $check_stmt->bind_param("i", $book_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        // Calculate the return date
        $return_date = date('Y-m-d H:i:s', strtotime("+$days days"));

        // Insert into issue_book table
        $sql_issue = "INSERT INTO issue_book (book_id, student_id, issue_date, return_date, status) VALUES (?, ?, NOW(), ?, 'issued')";
        $stmt_issue = $conn->prepare($sql_issue);
        $stmt_issue->bind_param("iis", $book_id, $student_id, $return_date);

        if ($stmt_issue->execute()) {
            // Optionally add to wishlist
            $sql_wishlist = "INSERT INTO wishlist (student_id, book_id, added_at) VALUES (?, ?, NOW())";
            $stmt_wishlist = $conn->prepare($sql_wishlist);
            $stmt_wishlist->bind_param("ii", $student_id, $book_id);
            
            if ($stmt_wishlist->execute()) {
                echo "success"; // Successfully issued and added to wishlist
            } else {
                echo "issue_success_but_wishlist_failed"; // Issued but failed to add to wishlist
            }
        } else {
            echo "issue_failed: " . $stmt_issue->error; // Failed to issue the book
        }

        $stmt_issue->close();
        if (isset($stmt_wishlist)) {
            $stmt_wishlist->close();
        }
    } else {
        echo "issue_failed: Book does not exist."; // Book not found
    }

    $check_stmt->close();
} else {
    echo "no_book_id"; // No book ID provided
}

// Close the database connection
$conn->close();
?>
