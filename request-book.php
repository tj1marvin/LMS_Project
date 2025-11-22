<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['student_id'])) {
    header("Location: Student-login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_id'])) {
    $book_id = intval($_POST['book_id']);
    echo "book_id received: " . $book_id . "<br>"; // Debugging
    // Validate book_id (check if it exists in the books table)
    $check_book_sql = "SELECT id FROM books WHERE id = ?";
    $check_book_stmt = $conn->prepare($check_book_sql);
    if ($check_book_stmt === false) {
        die("Error preparing book check statement: " . $conn->error);
    }
    $check_book_stmt->bind_param("i", $book_id);
    $check_book_stmt->execute();
    $check_book_stmt->store_result();
    if ($check_book_stmt->num_rows == 0) {
        die("Invalid book ID.");
    }
    $check_book_stmt->close();
    $conn->begin_transaction();
    // Check for existing request (most recent request)
    $check_request_sql = "SELECT request_id, status FROM book_requests WHERE student_id = ? AND book_id = ? ORDER BY request_date DESC LIMIT 1";
    $check_request_stmt = $conn->prepare($check_request_sql);
    if ($check_request_stmt === false) {
        $conn->rollback();
        die("Error preparing request check statement: " . $conn->error . ". SQL: " . $check_request_sql);
    }
    $check_request_stmt->bind_param("ii", $student_id, $book_id);
    $check_request_stmt->execute();
    $check_request_result = $check_request_stmt->get_result();
    if ($check_request_result->num_rows > 0) {
        $row = $check_request_result->fetch_assoc();
        $request_status = $row['status'];
        if ($request_status === 'pending') {
            $conn->rollback();
            echo "exists"; // Return "exists" to the AJAX call
            exit();
        }
    }
    $check_request_stmt->close();
    // Insert book request
    $insert_sql = "INSERT INTO book_requests (student_id, book_id, request_date, status) VALUES (?, ?, NOW(), 'pending')";
    $insert_stmt = $conn->prepare($insert_sql);
    if ($insert_stmt === false) {
        $conn->rollback();
        die("Error preparing insert statement: " . $conn->error . ". SQL: " . $insert_sql);
    }

    $insert_stmt->bind_param("ii", $student_id, $book_id);
    if ($insert_stmt->execute()) {
        $conn->commit();
        echo "success"; // Return "success" to the AJAX call
        exit();
    } else {
        $conn->rollback();
        error_log("Error sending book request: " . $insert_stmt->error);
        echo "error"; // Return "error" to the AJAX call
        exit();
    }

    $insert_stmt->close();

} else {
    echo "invalid_request"; // Return "invalid_request" to the AJAX call
    exit();
}

$conn->close();
?>