<?php
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

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if required fields are set
    if (isset($_POST['first_name'], $_POST['last_name'], $_POST['enrollment_no'], $_POST['username'], $_POST['email'], $_POST['password'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $enrollment_no = $_POST['enrollment_no'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
        $contact = isset($_POST['contact']) ? $_POST['contact'] : null; // Optional field
        $student_img = null; // Initialize variable for image path

        // Handle file upload
        if (isset($_FILES['student_img']) && $_FILES['student_img']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/images/"; // Directory to save uploaded images
            $target_file = $target_dir . basename($_FILES["student_img"]["name"]);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // Check if the file is an actual image
            $check = getimagesize($_FILES["student_img"]["tmp_name"]);
            if ($check === false) {
                die("File is not an image.");
            }

            // Check file size (limit to 2MB)
            if ($_FILES["student_img"]["size"] > 2000000) {
                die("Sorry, your file is too large.");
            }

            // Allow certain file formats
            if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
                die("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
            }

            // Try to upload the file
            if (move_uploaded_file($_FILES["student_img"]["tmp_name"], $target_file)) {
                $student_img = $target_file; // Store the file path
            } else {
                die("Sorry, there was an error uploading your file.");
            }
        }

        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO students_registration (first_name, last_name, enrollment_no, username, email, password, contact, student_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            die("Prepare failed: " . htmlspecialchars($conn->error));
        }

        $stmt->bind_param("ssssssss", $first_name, $last_name, $enrollment_no, $username, $email, $hashed_password, $contact, $student_img);

        if ($stmt->execute()) {
            echo "Registration successful!";
            header("Location: Student-login.php"); // Redirect to login page after successful registration
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
