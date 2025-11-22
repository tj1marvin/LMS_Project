<?php
// Start the session at the beginning
session_start();

// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS"; // replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Database connection error. Please try again later.");
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if email and password are set
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "Invalid email format.";
            exit();
        }
        // Prepare and bind
        $stmt = $conn->prepare("SELECT student_id, password, approved FROM students_registration WHERE email = ?");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
            die("Database query error. Please try again later.");
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($student_id, $hashed_password, $approved);
        $stmt->fetch();
        if ($stmt->num_rows > 0) {
            // Check if the account is approved
            if ($approved === 'active') {
                // Verify the password
                if (password_verify($password, $hashed_password)) {
                    // Start session and redirect to a protected page
                    session_regenerate_id(true); // Prevent session fixation
                    $_SESSION['student_id'] = $student_id; // Store student_id in session
                    $_SESSION['email'] = $email; // Store email in session
                    header("Location: Student-index.php");
                    exit();
                } else {
                    echo "Invalid password.";
                }
            } else {
                echo "Your account is inactive. Please contact support.";
            }
        } else {
            echo "No user found with that email.";
        }

        $stmt->close();
    } else {
        echo "Email and password must be provided.";
    }
} else {
    echo "Invalid request method.";
}

$conn->close();
?>
