<?php
session_start(); 
// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS";

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
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

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $bookTitle = $_POST['bookTitle'];
    $bookAuthor = $_POST['bookAuthor'];
    $bookISBN = $_POST['bookISBN'];
    $bookGenre = $_POST['bookGenre'];
    $bookYear = $_POST['bookYear'];
    $bookDescription = $_POST['bookDescription'];
    $availableCopies = (int)$_POST['availableCopies']; // Ensure this is an integer
    // Handle file uploads
    $bookImage = $_FILES['bookImage'];
    $bookFile = $_FILES['bookFile'];

    // Validate image file type
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($bookImage['type'], $allowedImageTypes)) {
        die("Invalid image file type.");
    }

    // Validate file size (e.g., max 2MB)
    if ($bookImage['size'] > 2 * 1024 * 1024) {
        die("Image file size exceeds the limit.");
    }

    // Set file paths
    $imagePath = 'uploads/images/' . basename($bookImage['name']);
    $filePath = 'uploads/files/' . basename($bookFile['name']);

    // Check if the image upload is successful
    if (move_uploaded_file($bookImage['tmp_name'], $imagePath) && move_uploaded_file($bookFile['tmp_name'], $filePath)) {
        // Prepare and bind the SQL statement
        $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, genre, year, description, Available_Copies, image_path, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Check if prepare failed
        if ($stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }

        // Bind parameters (note the correct number of parameters)
        $stmt->bind_param("ssssisiss", $bookTitle, $bookAuthor, $bookISBN, $bookGenre, $bookYear, $bookDescription, $availableCopies, $imagePath, $filePath);

        // Execute the statement
        if ($stmt->execute()) {
            echo "New book added successfully!";
            header("Location: view-books.php");
            exit();
        } else {
            echo "Error: " . $stmt->error;
        }

        // Close the statement
        $stmt->close();
    } else {
        echo "Error uploading files: ";
        if ($bookImage['error'] !== UPLOAD_ERR_OK) {
            echo "Image upload error: " . $bookImage['error'];
        }
        if ($bookFile['error'] !== UPLOAD_ERR_OK) {
            echo "File upload error: " . $bookFile['error'];
        }
    }
}

// Close the database connection
$conn->close();
?>
