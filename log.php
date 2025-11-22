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
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if email and password are set
    if (!empty($_POST['email']) && !empty($_POST['password'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Prepare and bind
        $stmt = $conn->prepare("SELECT id, username, password FROM admin_login WHERE email = ?"); 
        if ($stmt === false) {
            die("Prepare failed: " . htmlspecialchars($conn->error));
        }

        $stmt->bind_param("s", $email);

        // Execute the statement and check for errors
        if ($stmt->execute()) {
            $stmt->store_result();
            $stmt->bind_result($admin_id, $username, $hashed_password); 
            $stmt->fetch();

            if ($stmt->num_rows > 0) {
                // Verify the password
                if (password_verify($password, $hashed_password)) {
                    // Start session and redirect to a protected page
                    $_SESSION['admin_id'] = $admin_id; // Store admin_id in session, not username
                    header("Location: index.php");
                    exit();
                } else {
                    echo "<p class='text-danger'>Invalid password.</p>";
                }
            } else {
                echo "<p class='text-danger'>No user found with that email.</p>";
            }
        } else {
            echo "Execution failed: " . htmlspecialchars($stmt->error);
        }

        $stmt->close();
    } else {
        echo "<p class='text-danger'>Email and password must be provided.</p>";
    }
} else {
    echo "<p class='text-danger'>Invalid request method.</p>";
}

$conn->close();
?>