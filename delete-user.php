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

session_start(); 

// Check if the admin is logged in
if (isset($_SESSION['username'])) {
  $student_id = $_SESSION['username'];
} else {
  echo "No student is logged in. Redirecting to login page...";
  header("Location: login.php");
  exit();
}
// Get the user ID from the URL
if (isset($_GET['id'])) {
    $userId = intval($_GET['id']);

    // Delete the user from the database
    $sql = "DELETE FROM students_registration WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        // Redirect back to the view users page
        header("Location: view-students.php");
        exit();
    } else {
        echo "Error deleting record: " . $conn->error;
    }

    $stmt->close();
}

$conn->close();
?>
