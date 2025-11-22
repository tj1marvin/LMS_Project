<?php
session_start();

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

// Check if the admin is logged in and has the 'admin' role
if (!isset($_SESSION['username'])) {

  header("Location: index.php");
  
  exit();
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if required fields are set
    if (isset($_POST['firstName']) && isset($_POST['lastName']) && isset($_POST['enrollmentNo']) && 
        isset($_POST['username']) && isset($_POST['password']) && isset($_POST['email'])) {
        
        // Get form data
        $first_name = $_POST['firstName'];
        $last_name = $_POST['lastName'];
        $enrollment_no = $_POST['enrollmentNo'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
        $contact = isset($_POST['contact']) ? $_POST['contact'] : ''; // Optional field
        $student_img = ''; // Placeholder for image path

        // Handle file upload for student image
        if (isset($_FILES['studentImage']) && $_FILES['studentImage']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/images/"; // Directory to save uploaded images
            $target_file = $target_dir . basename($_FILES["studentImage"]["name"]);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // Check if image file is a valid image
            $check = getimagesize($_FILES["studentImage"]["tmp_name"]);
            if ($check !== false) {
                // Move the uploaded file to the target directory
                if (move_uploaded_file($_FILES["studentImage"]["tmp_name"], $target_file)) {
                    $student_img = $target_file; // Save the file path
                } else {
                    echo "Sorry, there was an error uploading your file.";
                }
            } else {
                echo "File is not an image.";
            }
        }

        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO students_registration (first_name, last_name, enrollment_no, username, email, password, contact, student_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            die("Prepare failed: " . htmlspecialchars($conn->error));
        }

        $stmt->bind_param("ssssssss", $first_name, $last_name, $enrollment_no, $username, $email, $hashed_password, $contact, $student_img);

        if ($stmt->execute()) {
            
            header("Location: view-students.php");
            exit(); 
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "All required fields must be provided.";
    }
}

$conn->close();
?>
