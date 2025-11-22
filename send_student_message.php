<?php
// Start the session
session_start();

// Database connection variables
$servername = "localhost"; // Change if necessary
$username = "root"; // Your database username
$password = ""; // Your database password (leave it blank for XAMPP)
$dbname = "LMS"; // Your database name

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    echo "No student is logged in. Redirecting to login page...";
    header("Location: student_login.php"); // Change to your student login page
    exit();
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the form data
    $sender_username = $_SESSION['username']; // Assuming you store the username in the session
    $recipient_username = $_POST['recipient_username'];
    $message_content = $_POST['message_content'];

    // Validate the input
    if (empty($recipient_username) || empty($message_content)) {
        echo "Recipient username and message content cannot be empty.";
        exit();
    }

    // Send the message
    $query = "INSERT INTO messages (sender_username, recipient_username, message_content, created_at, updated_at) 
              VALUES (?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("sss", $sender_username, $recipient_username, $message_content);
        if ($stmt->execute()) {
            // Message sent successfully
            echo "Message sent successfully!";
        } else {
            echo "Error sending message: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
}

// Close the database connection
$conn->close();

// Redirect back to the student message system page
header("Location: test-student_message.php"); // Change to your actual student message system page
exit();
?>
