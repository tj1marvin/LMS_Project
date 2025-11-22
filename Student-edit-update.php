<?php

// Start the session
session_start();

// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS"; // replace with your database name

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    echo "No student is logged in. Redirecting to login page...";
    header("Location: Student-login.php");
    exit();
}

// Get the student ID from the session
$student_id = $_SESSION['student_id'];


// Fetch user messages
$message_sql = "SELECT m.message_id, m.message_text, m.sent_at, 
                CASE 
                    WHEN m.sent_by = ? THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students_registration WHERE student_id = m.received_by)
                    ELSE (SELECT CONCAT(first_name, ' ', last_name) FROM students_registration WHERE student_id = m.sent_by)
                END AS sender_name,
                CASE 
                    WHEN m.sent_by = ? THEN (SELECT student_img FROM students_registration WHERE student_id = m.received_by)
                    ELSE (SELECT student_img FROM students_registration WHERE student_id = m.sent_by)
                END AS sender_img
                FROM messages m 
                WHERE m.sent_by = ? OR m.received_by = ?
                ORDER BY m.sent_at DESC";

$message_stmt = $conn->prepare($message_sql);
$message_stmt->bind_param("iiii", $student_id, $student_id, $student_id, $student_id);
$message_stmt->execute();
$message_result = $message_stmt->get_result();

$messages = [];
while ($row = $message_result->fetch_assoc()) {
    $messages[] = $row;
}

$message_stmt->close();






// Fetch notifications for the logged-in student
$student_id = $_SESSION['student_id']; // Assuming student_id is stored in session

$notifications_sql = "
    SELECT 
        m.message_id,
        m.message_text,
        m.sent_at,
        CASE 
            WHEN m.sent_by = ? THEN 'You sent a message to ' 
            ELSE CONCAT((SELECT CONCAT(first_name, ' ', last_name) FROM students_registration WHERE student_id = m.sent_by), ' sent you a message: ')
        END AS notification_text,
        m.sent_by,
        'message' AS type
    FROM messages m
    WHERE m.received_by = ? OR m.sent_by = ?

    UNION ALL

    SELECT 
        b.issue_id AS message_id,
        CONCAT('You have issued the book: ', (SELECT title FROM books WHERE id = b.book_id)) AS message_text,
        b.issue_date AS sent_at,
        'Book Issued' AS notification_text,
        NULL AS sent_by,
        'book' AS type
    FROM issue_book b
    WHERE b.student_id = ?

    UNION ALL

    SELECT 
        p.payment_id AS message_id,
        CONCAT('Payment of ', p.amount, ' was made.') AS message_text,
        p.payment_date AS sent_at,
        'Payment Notification' AS notification_text,
        NULL AS sent_by,
        'payment' AS type
    FROM payments p
    WHERE p.student_id = ?
    
    ORDER BY sent_at DESC
";

$notifications_stmt = $conn->prepare($notifications_sql);
$notifications_stmt->bind_param("iiiii", $student_id, $student_id, $student_id, $student_id, $student_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

$notifications = [];
while ($row = $notifications_result->fetch_assoc()) {
    $notifications[] = $row;
}

$notifications_stmt->close();









// Fetch student information
$student_sql = "SELECT first_name, last_name, email, student_img FROM students_registration WHERE student_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows > 0) {
    $student_info = $student_result->fetch_assoc();
    $student_name = htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']);
    $profile_image = htmlspecialchars($student_info['student_img']);
    $student_email = htmlspecialchars($student_info['email']);
} else {
    // Handle case where student info is not found
    $student_name = "Student";
    $profile_image = "assets/img/default_profile.png"; // Default image
    $student_email = "email@example.com"; // Default email
}

$student_stmt->close();





// Fetch current student data
$sql = "SELECT student_id, first_name, last_name, enrollment_no, username, email, contact, student_img
        FROM students_registration
        WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 1) {
    $student_data = $result->fetch_assoc();
} else {
    $error_message = "Failed to retrieve profile data.";
}
$stmt->close();

// Handle profile update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = htmlspecialchars($_POST['first_name']);
    $last_name = htmlspecialchars($_POST['last_name']);
    $email = htmlspecialchars($_POST['email']);
    $contact = htmlspecialchars($_POST['contact']);
    $student_img = $_POST['old_student_img']; //hidden field

    // Initialize error message here
    $error_message = "";

    // Handle image upload
    if (isset($_FILES['student_img']) && $_FILES['student_img']['error'] == 0) {
        $target_dir = "uploads/images/"; // Directory to save uploaded images
        $target_file = $target_dir . basename($_FILES["student_img"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["student_img"]["tmp_name"]);
        if ($check === false) {
            $error_message = "File is not an image.";
            $uploadOk = 0;
        }
        // Check file size (limit to 2MB)
        if ($_FILES["student_img"]["size"] > 2000000) {
            $error_message = "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        // Allow certain file formats
        if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
            $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            // Do not upload the file
        } else {
            // If everything is ok, try to upload file
            if (move_uploaded_file($_FILES["student_img"]["tmp_name"], $target_file)) {
                // File uploaded successfully, update the database
                $student_img = $target_file;
            } else {
                $error_message = "Sorry, there was an error uploading your file.";
            }
        }
    }


    // If no errors, execute the update
    if (empty($error_message)) {
        $update_sql = "UPDATE students_registration
                           SET first_name = ?, last_name = ?, email = ?, contact = ?, student_img = ?
                           WHERE student_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssi", $first_name, $last_name, $email, $contact, $student_img, $student_id);
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";

            // Refresh student data after update
            $stmt_refresh = $conn->prepare($sql);
            $stmt_refresh->bind_param("i", $student_id);
            $stmt_refresh->execute();
            $result_refresh = $stmt_refresh->get_result();
            $student_data = $result_refresh->fetch_assoc();
            $stmt_refresh->close();
        } else {
            $error_message = "Error updating profile: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Handle password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate old password
    $check_password_sql = "SELECT password FROM students_registration WHERE student_id = ?";
    $check_password_stmt = $conn->prepare($check_password_sql);
    $check_password_stmt->bind_param("i", $student_id);
    $check_password_stmt->execute();
    $result = $check_password_stmt->get_result();
    $row = $result->fetch_assoc();

    if (!password_verify($old_password, $row['password'])) {
        $error_message = "Incorrect old password.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } elseif ($new_password != $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update the password
        $update_password_sql = "UPDATE students_registration SET password = ? WHERE student_id = ?";
        $update_password_stmt = $conn->prepare($update_password_sql);
        $update_password_stmt->bind_param("si", $hashed_password, $student_id);

        if ($update_password_stmt->execute()) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Error updating password: " . $update_password_stmt->error;
        }
        $update_password_stmt->close();
    }
    $check_password_stmt->close();
}


// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>LMS - Student Library</title>
    <meta
        content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
        name="viewport" />
    <link
        rel="icon"
        href="assets/img/kaiadmin/favicon.ico"
        type="image/x-icon" />

    <!-- Fonts and icons -->
    <script src="assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
        WebFont.load({
            google: {
                families: ["Public Sans:300,400,500,600,700"]
            },
            custom: {
                families: [
                    "Font Awesome 5 Solid",
                    "Font Awesome 5 Regular",
                    "Font Awesome 5 Brands",
                    "simple-line-icons",
                ],
                urls: ["assets/css/fonts.min.css"],
            },
            active: function() {
                sessionStorage.fonts = true;
            },
        });
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/plugins.min.css" />
    <link rel="stylesheet" href="assets/css/kaiadmin.min.css" />


</head>

<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" data-background-color="white">
            <div class="sidebar-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="white">
                    <a href="Student-index.php" class="logo">
                        <img
                            src="assets/img/kaiadmin/logo_dark_Student.png"
                            alt="navbar brand"
                            class="navbar-brand"
                            height="20" />
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar">
                            <i class="gg-menu-right"></i>
                        </button>
                        <button class="btn btn-toggle sidenav-toggler">
                            <i class="gg-menu-left"></i>
                        </button>
                    </div>
                    <button class="topbar-toggler more">
                        <i class="gg-more-vertical-alt"></i>
                    </button>
                </div>
                <!-- End Logo Header -->

            </div>
            <div class="sidebar-wrapper scrollbar scrollbar-inner">
                <div class="sidebar-content">
                    <ul class="nav nav-secondary">
                        <li class="nav-item ">
                            <a data-bs-toggle="collapse" href="#dashboard" class="collapsed" aria-expanded="false">
                                <i class="fas fa-home"></i>
                                <p>Discover</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse " id="dashboard">
                                <ul class="nav nav-collapse">
                                    <li class="active">
                                        <a href="Student-index.php">
                                            <span class="sub-item">Recommendation</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-section">
                            <h4 class="text-section">Library Management</h4>
                        </li>
                        <li class="nav-item">
                            <a data-bs-toggle="collapse" href="#books">
                                <i class="fas fa-book"></i>
                                <p>Books</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse" id="books">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="Student-view-books.php">
                                            <span class="sub-item">View Books</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a data-bs-toggle="collapse" href="#borrowing">
                                <i class="fas fa-book-reader"></i>
                                <p>Borrowing</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse" id="borrowing">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="Student-view-issue-book.php">
                                            <span class="sub-item">View Issued Book</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="Student-reserved-books.php">
                                            <span class="sub-item">View Reserved Book</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a data-bs-toggle="collapse" href="#returns">
                                <i class="fas fa-clipboard-check"></i>
                                <p>Returns</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse" id="returns">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="Student-view-returned-books.php">
                                            <span class="sub-item">View Returned
                                                Books</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a data-bs-toggle="collapse" href="#penalties">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Penalties</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse" id="penalties">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="Student-view-penalty.php">
                                            <span class="sub-item">View
                                                Penalties</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a data-bs-toggle="collapse" href="#messages">
                                <i class="fas fa-comments"></i>
                                <p>Messages</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse" id="messages">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="Student-view-messages.php">
                                            <span class="sub-item">View
                                                Messages</span>
                                        </a>
                                    </li>

                                </ul>
                            </div>
                        </li>
                        <li class="nav-item active">
                            <a data-bs-toggle="collapse" href="#profile">
                                <i class="fas fa-user"></i>
                                <p>Profile</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse show" id="profile">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="Student-view-profile.php">
                                            <span class="sub-item">View Profile</span>
                                        </a>
                                    </li>
                                    <li class="active">
                                        <a href="Student-edit-update.php">
                                            <span class="sub-item">Edit Profile</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- End Sidebar  hdsghfgsjjgdsjhfhgfhgfhgsfdhgfjfgjhdsgfjhgfjhgdsjhfjsdhgfjdsgfjhdsgjh here maun-->
        <div class="main-panel">
            <div class="main-header">
                <div class="main-header-logo">
                    <!-- Logo Header -->
                    <div class="logo-header" data-background-color="dark">
                        <a href="Student-index.php" class="logo">
                            <img
                                src="assets/img/kaiadmin/logo_light.svg"
                                alt="navbar brand"
                                class="navbar-brand"
                                height="20" />
                        </a>
                        <div class="nav-toggle">
                            <button class="btn btn-toggle toggle-sidebar">
                                <i class="gg-menu-right"></i>
                            </button>
                            <button class="btn btn-toggle sidenav-toggler">
                                <i class="gg-menu-left"></i>
                            </button>
                        </div>
                        <button class="topbar-toggler more">
                            <i class="gg-more-vertical-alt"></i>
                        </button>
                    </div>
                    <!-- End Logo Header -->
                </div>
                <!-- Navbar Header  search bar-->
                <nav
                    class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
                    <div class="container-fluid">
                        <nav
                            class="navbar navbar-header-left navbar-expand-lg navbar-form nav-search p-0 d-none d-lg-flex">

                        </nav>


                        <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                            <li
                                class="nav-item topbar-icon dropdown hidden-caret d-flex d-lg-none">
                                <a
                                    class="nav-link dropdown-toggle"
                                    data-bs-toggle="dropdown"
                                    href="#"
                                    role="button"
                                    aria-expanded="false"
                                    aria-haspopup="true">
                                    <i class="fa fa-search"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-search animated fadeIn">
                                    <form class="navbar-left navbar-form nav-search">
                                        <div class="input-group">
                                            <input type="text" id="search" placeholder="Search for books..." class="form-control" />
                                        </div>
                                    </form>
                                </ul>
                            </li>
                            <li class="nav-item topbar-icon dropdown hidden-caret">
                                <a class="nav-link dropdown-toggle" href="#" id="messageDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-envelope"></i>
                                </a>
                                <ul class="dropdown-menu messages-notif-box animated fadeIn" aria-labelledby="messageDropdown">
                                    <li>
                                        <div class="dropdown-title d-flex justify-content-between align-items-center">
                                            Messages
                                            <a href="#" class="small">Mark all as read</a>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="message-notif-scroll scrollbar-outer">
                                            <div class="notif-center">
                                                <?php if (empty($messages)): ?>
                                                    <div class="notif-content">
                                                        <span class="block">No messages found.</span>
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($messages as $message): ?>
                                                        <a href="#">
                                                            <div class="notif-img">
                                                                <img src="<?php echo htmlspecialchars($message['sender_img']); ?>" alt="Img Profile" />
                                                            </div>
                                                            <div class="notif-content">
                                                                <span class="subject"><?php echo htmlspecialchars($message['sender_name']); ?></span>
                                                                <span class="block"><?php echo htmlspecialchars($message['message_text']); ?></span>
                                                                <span class="time"><?php echo date('F j, Y, g:i a', strtotime($message['sent_at'])); ?></span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a class="see-all" href="Student-view-messages.php">See all messages<i class="fa fa-angle-right"></i></a>
                                    </li>
                                </ul>
                            </li>

                            <li class="nav-item topbar-icon dropdown hidden-caret">
                                <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-bell"></i>
                                    <span class="notification"><?php echo count($notifications); ?></span>
                                </a>
                                <ul class="dropdown-menu notif-box animated fadeIn" aria-labelledby="notifDropdown">
                                    <li>
                                        <div class="dropdown-title">
                                            You have <?php echo count($notifications); ?> new notifications
                                        </div>
                                    </li>
                                    <li>
                                        <div class="notif-scroll scrollbar-outer">
                                            <div class="notif-center">
                                                <?php if (isset($notifications) && is_array($notifications) && count($notifications) > 0): ?>
                                                    <?php foreach ($notifications as $notification): ?>
                                                        <?php
                                                        $link = '#'; // Default link
                                                        if ($notification['type'] === 'message') {
                                                            $link = 'Student-view-messages.php?message_id=' . $notification['message_id']; // Correct variable
                                                        } elseif ($notification['type'] === 'book') {
                                                            $link = 'Student-view-issue-book.php'; // Or wherever book requests are handled
                                                        } elseif ($notification['type'] === 'payment') {
                                                            $link = 'Student-view-payments.php'; // Or wherever fines/payments are viewed
                                                        }
                                                        ?>
                                                        <a href="<?php echo htmlspecialchars($link); ?>">
                                                            <div class="notif-icon <?php echo ($notification['type'] === 'message') ? 'notif-primary' : (($notification['type'] === 'book') ? 'notif-info' : 'notif-warning'); ?>">
                                                                <i class="fa <?php echo ($notification['type'] === 'message') ? 'fa-envelope' : (($notification['type'] === 'book') ? 'fa-book' : 'fa-exclamation-triangle'); ?>"></i>
                                                            </div>
                                                            <div class="notif-content">
                                                                <span class="block"><?php echo htmlspecialchars($notification['notification_text'] . $notification['message_text']); ?></span>
                                                                <span class="time"><?php echo date('M d, Y h:i A', strtotime($notification['sent_at'])); ?></span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p>No notifications</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a class="see-all" href="view-all-notifications.php">See all notifications<i class="fa fa-angle-right"></i></a>
                                    </li>
                                </ul>
                            </li>



                            <li class="nav-item topbar-icon dropdown hidden-caret">
                                <a
                                    class="nav-link"
                                    data-bs-toggle="dropdown"
                                    href="#"
                                    aria-expanded="false">
                                    <i class="fas fa-layer-group"></i>
                                </a>
                                <div class="dropdown-menu quick-actions animated fadeIn">
                                    <div class="quick-actions-header">
                                        <span class="title mb-1">Quick Actions</span>
                                        <span class="subtitle op-7">Shortcuts</span>
                                    </div>
                                    <div class="quick-actions-scroll scrollbar-outer">
                                        <div class="quick-actions-items">
                                            <div class="row m-0">
                                                <a class="col-6 col-md-4 p-0" href="Student-view-books.php">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-danger rounded-circle">
                                                            <i class="fas fa-book"></i>
                                                        </div>
                                                        <span class="text">View Books</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="Student-view-issue-book.php">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-warning rounded-circle">
                                                            <i class="fas fa-book-reader"></i>
                                                        </div>
                                                        <span class="text">Issued Books</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="Student-reserved-books.php">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-info rounded-circle">
                                                            <i class="fas fa-calendar-check"></i>
                                                        </div>
                                                        <span class="text">Reserved Books</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="Student-view-messages.php">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-primary rounded-circle">
                                                            <i class="fas fa-envelope"></i>
                                                        </div>
                                                        <span class="text">Messages</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="Student-payments.php">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-secondary rounded-circle">
                                                            <i class="fas fa-credit-card"></i>
                                                        </div>
                                                        <span class="text">Payments</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="Student-view-fine.php">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-danger rounded-circle">
                                                            <i class="fas fa-exclamation-circle"></i>
                                                        </div>
                                                        <span class="text">Fines</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="Student-view-penalty.php">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-warning rounded-circle">
                                                            <i class="fas fa-ban"></i>
                                                        </div>
                                                        <span class="text">Penalties</span>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </li>

                            <li class="nav-item topbar-user dropdown hidden-caret">
                                <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                                    <div class="avatar-sm">
                                        <img src="<?php echo $profile_image; ?>" alt="..." class="avatar-img rounded-circle" />
                                    </div>
                                    <span class="profile-username">
                                        <span class="op-7">Hi,</span>
                                        <span class="fw-bold"><?php echo $student_name; ?></span>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-user animated fadeIn">
                                    <div class="dropdown-user-scroll scrollbar-outer">
                                        <li>
                                            <div class="user-box">
                                                <div class="avatar-lg">
                                                    <img src="<?php echo $profile_image; ?>" alt="image profile" class="avatar-img rounded" />
                                                </div>
                                                <div class="u-text">
                                                    <h4><?php echo $student_name; ?></h4>
                                                    <p class="text-muted"><?php echo $student_email; ?></p> <!-- Display the student's email -->
                                                    <a href="Student-profile.php" class="btn btn-xs btn-secondary btn-sm">View Profile</a>
                                                </div>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="Student-view-profile.php">My Profile</a>
                                            <a class="dropdown-item" href="Student-view-messages.php">Inbox</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="Student-forgot.php">Account Setting</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="Student-log-out.php">Logout</a>
                                        </li>
                                    </div>
                                </ul>
                            </li>

                        </ul>
                    </div>
                </nav>
                <!-- End Navbar -->

                <!-- Remember this container -->
            </div>


            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h4 class="card-title">Settings</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($error_message) : ?>
                                    <div class="alert alert-danger">
                                        <strong>Error:</strong> <?php echo $error_message; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($success_message) : ?>
                                    <div class="alert alert-success">
                                        <strong>Success:</strong> <?php echo $success_message; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title">Update Profile</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($student_data): ?>
                                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                                <input type="hidden" name="update_profile" value="1">
                                                <div class="text-center mb-4">
                                                    <?php if ($student_data['student_img']): ?>
                                                        <img src="<?php echo htmlspecialchars($student_data['student_img']); ?>" alt="Your Profile Image" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <img src="placeholder-student.png" alt="No Profile Image" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px;">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="form-group row">
                                                    <label for="first_name" class="col-sm-4 col-form-label">First Name</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($student_data['first_name']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <label for="last_name" class="col-sm-4 col-form-label">Last Name</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($student_data['last_name']); ?>" required>
                                                    </div>
                                                </div>

                                                <div class="form-group row">
                                                    <label for="email" class="col-sm-4 col-form-label">Email</label>
                                                    <div class="col-sm-8">
                                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student_data['email']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <label for="contact" class="col-sm-4 col-form-label">Contact Number</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($student_data['contact']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <label for="student_img" class="col-sm-4 col-form-label">Profile Image</label>
                                                    <div class="col-sm-8">
                                                        <input type="file" class="form-control-file" id="student_img" name="student_img" accept="image/*">
                                                        <input type="hidden" name="old_student_img" value="<?php echo htmlspecialchars($student_data['student_img']); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <div class="col-sm-8 offset-sm-4">
                                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                                    </div>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <p class="text-center text-danger">Failed to load your profile information.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Reset Password</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                            <input type="hidden" name="reset_password" value="1">
                                            <div class="form-group row">
                                                <label for="old_password" class="col-sm-4 col-form-label">Old Password</label>
                                                <div class="col-sm-8">
                                                    <input type="password" class="form-control" id="old_password" name="old_password" required>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="new_password" class="col-sm-4 col-form-label">New Password</label>
                                                <div class="col-sm-8">
                                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <label for="confirm_password" class="col-sm-4 col-form-label">Confirm New Password</label>
                                                <div class="col-sm-8">
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                </div>
                                            </div>
                                            <div class="form-group row">
                                                <div class="col-sm-8 offset-sm-4">
                                                    <button type="submit" class="btn btn-danger">Reset Password</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>



                <!-- Information on the footering  -->
                <footer class="footer">
                    <div class="container-fluid d-flex justify-content-between">
                        <nav class="pull-left">
                            <ul class="nav">
                                <li class="nav-item">
                                    <a class="nav-link" href="###">
                                        LMS Library Management System
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#"> Help </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#"> About </a>
                                </li>
                            </ul>
                        </nav>
                        <div class="copyright">
                            2024, made with <i class="fa fa-heart heart text-danger"></i> by
                            <a href="#">Marvin</a>
                        </div>
                        <div>
                            Distributed by
                            <a target="_blank" href="#">LMS</a>.
                        </div>
                    </div>
                </footer>
            </div>

        </div>

        <!--   Core JS Files   -->
        <script src="assets/js/core/jquery-3.7.1.min.js"></script>
        <script src="assets/js/core/popper.min.js"></script>
        <script src="assets/js/core/bootstrap.min.js"></script>

        <!-- jQuery Scrollbar -->
        <script src="assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js"></script>

        <!-- Chart JS -->
        <script src="assets/js/plugin/chart.js/chart.min.js"></script>

        <!-- jQuery Sparkline -->
        <script src="assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js"></script>

        <!-- Chart Circle -->
        <script src="assets/js/plugin/chart-circle/circles.min.js"></script>

        <!-- Datatables -->
        <script src="assets/js/plugin/datatables/datatables.min.js"></script>

        <!-- Bootstrap Notify -->
        <script src="assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js"></script>

        <!-- jQuery Vector Maps -->
        <script src="assets/js/plugin/jsvectormap/jsvectormap.min.js"></script>
        <script src="assets/js/plugin/jsvectormap/world.js"></script>

        <!-- Sweet Alert -->
        <script src="assets/js/plugin/sweetalert/sweetalert.min.js"></script>

        <!-- Kaiadmin JS -->
        <script src="assets/js/kaiadmin.min.js"></script>


        <script>
            $("#lineChart").sparkline([102, 109, 120, 99, 110, 105, 115], {
                type: "line",
                height: "70",
                width: "100%",
                lineWidth: "2",
                lineColor: "#177dff",
                fillColor: "rgba(23, 125, 255, 0.14)",
            });

            $("#lineChart2").sparkline([99, 125, 122, 105, 110, 124, 115], {
                type: "line",
                height: "70",
                width: "100%",
                lineWidth: "2",
                lineColor: "#f3545d",
                fillColor: "rgba(243, 84, 93, .14)",
            });

            $("#lineChart3").sparkline([105, 103, 123, 100, 95, 105, 115], {
                type: "line",
                height: "70",
                width: "100%",
                lineWidth: "2",
                lineColor: "#ffa534",
                fillColor: "rgba(255, 165, 52, .14)",
            });
        </script>
</body>

</html>