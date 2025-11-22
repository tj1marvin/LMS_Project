<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['book_id'])) {
    $book_id = $_POST['book_id'];

    $sql = "UPDATE books SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("i", $book_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
       echo "success"; // Or you could return the new view count if needed.
    } else {
        echo "error";
    }

    $stmt->close();
}

$conn->close();
?>