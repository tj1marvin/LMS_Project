<?php
session_start();

// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the admin is logged in
if (isset($_SESSION['admin_id'])) { 
    $admin_id = $_SESSION['admin_id']; // Use admin_id instead of username
} else {
    header("Location: login.php");
    exit();
}
// Get the request ID from the URL
$request_id = $_GET['id'];

// Update the status of the request to 'approved' and issue the book
$sql = "UPDATE book_requests SET status = 'approved' WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);

if ($stmt->execute()) {
    // Get the student_id and book_id from the request
    $get_request_sql = "SELECT student_id, book_id FROM book_requests WHERE request_id = ?";
    $get_request_stmt = $conn->prepare($get_request_sql);
    $get_request_stmt->bind_param("i", $request_id);
    $get_request_stmt->execute();
    $get_request_stmt->bind_result($student_id, $book_id);
    $get_request_stmt->fetch();
    $get_request_stmt->close();

    // Issue the book to the student
    $issue_sql = "INSERT INTO issue_book (book_id, student_id, issue_date, return_date, status) VALUES (?, ?, NOW(), ?, 'issued')";
    $issue_stmt = $conn->prepare($issue_sql);
    $return_date = date('Y-m-d H:i:s', strtotime('+7 days')); // Adjust the return date as needed
    $issue_stmt->bind_param("iis", $book_id, $student_id, $return_date);

    if ($issue_stmt->execute()) {
        // Decrease the available copies of the book
        $update_copies_sql = "UPDATE books SET Available_Copies = Available_Copies - 1 WHERE id = ?";
        $update_copies_stmt = $conn->prepare($update_copies_sql);
        $update_copies_stmt->bind_param("i", $book_id);
        $update_copies_stmt->execute();
        $update_copies_stmt->close();

        header("Location: book_request.php");
        exit();
    } else {
        echo "Error issuing book: " . $issue_stmt->error;
    }

    $issue_stmt->close();
} else {
    echo "Error approving request: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>