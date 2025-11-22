<?php
// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS"; // replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission for returning a book
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $issue_id = intval($_POST['issue_id']);

    // Start a transaction
    $conn->begin_transaction();

    try {
        // Update the issue_book table to set the status to 'returned'
        $update_sql = "UPDATE issue_book SET status = 'returned', return_date = NOW() WHERE issue_id = ?";
        $stmt = $conn->prepare($update_sql);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $issue_id);

        if ($stmt->execute()) {
            // Get the book_id from the issue_book table
            $book_sql = "SELECT book_id FROM issue_book WHERE issue_id = ?";
            $book_stmt = $conn->prepare($book_sql);
            if ($book_stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $book_stmt->bind_param("i", $issue_id);
            $book_stmt->execute();
            $book_stmt->bind_result($book_id);
            $book_stmt->fetch();
            $book_stmt->close();

            // Increment the available copies in the books table
            $update_copies_sql = "UPDATE books SET available_copies = available_copies + 1 WHERE id = ?";
            $update_copies_stmt = $conn->prepare($update_copies_sql);
            if ($update_copies_stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $update_copies_stmt->bind_param("i", $book_id);
            $update_copies_stmt->execute();
            $update_copies_stmt->close();

            // Commit the transaction
            $conn->commit();
            echo "<script>alert('Book returned successfully!'); window.location.href='view-returned-book.php';</script>";
        } else {
            throw new Exception("Error returning book: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        echo "Transaction failed: " . $e->getMessage();
    }
}

// Fetch issued books
$issued_books_sql = "SELECT ib.issue_id, b.title, s.first_name, s.last_name, ib.issue_date 
                     FROM issue_book ib 
                     JOIN books b ON ib.book_id = b.id 
                     JOIN students_registration s ON ib.student_id = s.student_id 
                     WHERE ib.status = 'issued'";
$issued_books_result = $conn->query($issued_books_sql);

// Close the connection
$conn->close();
?>
