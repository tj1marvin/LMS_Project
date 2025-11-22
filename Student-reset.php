<?php
// student-reset.php

// Include the database connection file
include 'db_connection.php';

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize a variable to hold the success message
$successMessage = "";

// Function to reset the password
function resetPassword($conn, $email)
{
    // Hash the new password
    $newPassword = password_hash("1234", PASSWORD_DEFAULT);

    // Prepare the SQL update statement. Use a prepared statement!
    $sql = "UPDATE students_registration SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind the parameters
        $stmt->bind_param("ss", $newPassword, $email);

        // Execute the statement
        if ($stmt->execute()) {
            $stmt->close(); // Close the statement
            return true; // Password reset successful
        } else {
            $stmt->close(); // Close the statement
            return false; // Password reset failed
        }
    } else {
        return false; // Prepared statement error
    }
}

// Check if the email is provided via POST
if (isset($_POST['email'])) {
    $email = $_POST['email'];

    // Validate the email (important for security)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email address.";
        exit;
    }

    // Reset the password
    if (resetPassword($conn, $email)) {
        // Set success message
        $successMessage = "Password reset successful! You can now log in with your new password.";
    } else {
        echo "Failed to reset password. Please check the email or try again.";
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
        }

        input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button[type="submit"] {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }

        button[type="submit"]:hover {
            background-color: #0056b3;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .back-button {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            text-align: center;
            display: inline-block;
            text-decoration: none;
        }

        .back-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <form method="POST" action="student-reset.php">
        <h2>Reset Password</h2>
        <?php if ($successMessage): ?>
            <div class="success-message"><?php echo $successMessage; ?></div>
            <a href="Student-login.php" class="back-button">Go to Login</a>
            <?php else: ?>
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit">Reset Password</button>
        <?php endif; ?>
    </form>
</body>

</html>
