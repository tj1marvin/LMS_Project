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

// Get the user ID and status from the URL
if (isset($_GET['id'])) {
    $userId = intval($_GET['id']);

    // Fetch the current status of the user
    $sql = "SELECT approved FROM students_registration WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentStatus = $row['approved'];

        // Toggle the status
        $newStatus = ($currentStatus == 'active') ? 'inactive' : 'active';

        // Update the user's status in the database
        $updateSql = "UPDATE students_registration SET approved = ? WHERE student_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $newStatus, $userId);

        if ($updateStmt->execute()) {
            // Redirect back to the view users page
            header("Location: view-students.php");
            exit();
        } else {
            echo "Error updating record: " . $conn->error;
        }

        $updateStmt->close();
    } else {
        echo "User not found.";
    }

    $stmt->close();
} else {
    echo "Invalid request.";
}

$conn->close();
?>
