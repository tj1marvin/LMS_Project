<?php
// Database connection parameters (same as before)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

// Create connection (same as before)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();

// Check admin login (same as before)
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$student_data = null;
$issued_books = [];
$payment_transactions = [];
$student_penalties = [];
$error_message = null;
$combined_transactions = []; // Initialize combined transactions array (not used directly now, but kept for summary)

if ($student_id > 0) {
    // Fetch student details (same as before)
    $student_sql = "SELECT student_id, first_name, last_name, enrollment_no, username, email, contact, student_img, approved, created_at, updated_at
                     FROM students_registration
                     WHERE student_id = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $student_data = $student_result->fetch_assoc();
    $student_stmt->close();

    if ($student_data) {
        // Fetch ALL issued books for the student (removed status filter)
        $issued_books_sql = "SELECT ib.issue_id, ib.issue_date, b.title AS book_title, b.isbn AS book_isbn, ib.return_date, ib.status AS issue_status
                              FROM issue_book ib
                              JOIN books b ON ib.book_id = b.id
                              WHERE ib.student_id = ?";
        $issued_books_stmt = $conn->prepare($issued_books_sql);
        if ($issued_books_stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $issued_books_stmt->bind_param("i", $student_id);
        $issued_books_stmt->execute();
        $issued_books_result = $issued_books_stmt->get_result();
        $issued_books = $issued_books_result->fetch_all(MYSQLI_ASSOC);
        $issued_books_stmt->close();

        // Fetch payment transactions and directly link to issue_book via issue_id in payments table
        $payments_sql = "SELECT p.payment_id, p.amount, p.payment_date, p.payment_method, p.transaction_id,
                                  b.title AS book_title, b.isbn AS book_isbn, p.payment_date AS transaction_date,
                                  ib.issue_date AS related_issue_date, ib.return_date AS related_return_date
                           FROM payments p
                           LEFT JOIN issue_book ib ON p.issue_id = ib.issue_id
                           LEFT JOIN books b ON ib.book_id = b.id
                           WHERE p.student_id = ?";
        $payments_stmt = $conn->prepare($payments_sql);
        $payments_stmt->bind_param("i", $student_id);
        $payments_stmt->execute();
        $payments_result = $payments_stmt->get_result();
        $payment_transactions = $payments_result->fetch_all(MYSQLI_ASSOC);
        $payments_stmt->close();


        // Fetch penalties for the student (same as before)
        $penalties_sql = "SELECT p.penalty_id, p.penalty_amount, p.status AS penalty_status, p.days_overdue, ib.issue_id, ib.issue_date, ib.return_date, b.title AS book_title
                           FROM penalties p
                           JOIN issue_book ib ON p.issue_id = ib.issue_id
                           JOIN books b ON ib.book_id = b.id
                           WHERE ib.student_id = ?";
        $penalties_stmt = $conn->prepare($penalties_sql);
        $penalties_stmt->bind_param("i", $student_id);
        $penalties_stmt->execute();
        $penalties_result = $penalties_stmt->get_result();
        $student_penalties = $penalties_result->fetch_all(MYSQLI_ASSOC);
        $penalties_stmt->close();

        // --- Calculate Summary Statistics (same as before) ---
        $student_data['total_books_issued'] = count($issued_books);
        $student_data['total_payments_count'] = count($payment_transactions);
        $total_amount_paid = 0;
        foreach ($payment_transactions as $payment) {
            $total_amount_paid += $payment['amount'];
        }
        $student_data['total_amount_paid'] = $total_amount_paid;
        $student_data['total_penalties_count'] = count($student_penalties);
        $total_penalty_amount = 0;
        foreach ($student_penalties as $penalty) {
            $total_penalty_amount += $penalty['penalty_amount'];
        }
        $student_data['total_penalty_amount'] = $total_penalty_amount;

        // --- Calculate Balance to Pay ---
        $balance_to_pay = 0;
        foreach ($student_penalties as $penalty) {
            if ($penalty['penalty_status'] == 'unpaid') {
                $balance_to_pay += $penalty['penalty_amount'];
            }
        }
        $student_data['balance_to_pay'] = $balance_to_pay;


        // --- Combine Penalties and Payments (for summary - not used in list anymore) ---
        foreach ($student_penalties as $penalty) {
            $combined_transactions[] = array(
                'type' => 'penalty',
                'date' => $penalty['issue_date'],
                'sort_date' => $penalty['issue_date'] // For sorting summary if needed
            );
        }
        foreach ($payment_transactions as $payment) {
            $combined_transactions[] = array(
                'type' => 'payment',
                'date' => $payment['payment_date'],
                'sort_date' => $payment['payment_date'] // For sorting summary if needed
            );
        }
        usort($combined_transactions, function ($a, $b) {
            return strtotime($b['sort_date']) - strtotime($a['sort_date']);
        });
    } else {
        $error_message = "Student not found.";
    }
} else {
    $error_message = "Invalid Student ID.";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>LMS - Librarian Admin Dashboard</title>
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
                    <a href="index.php" class="logo">
                        <img
                            src="assets/img/kaiadmin/logo_dark.png"
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
                        <li class="nav-item active">
                            <a data-bs-toggle="collapse" href="#dashboard" class="collapsed" aria-expanded="false">
                                <i class="fas fa-home"></i>
                                <p>Dashboard</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse" id="dashboard">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="index.php">
                                            <span class="sub-item">Overview</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-section">
                            <span class="sidebar-mini-icon">
                                <i class="fa fa-ellipsis-h"></i>
                            </span>
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
                                        <a href="add-book.html">
                                            <span class="sub-item">Add Book</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="view-books.php">
                                            <span class="sub-item">View Books</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a data-bs-toggle="collapse" href="#users">
                                <i class="fas fa-users"></i>
                                <p>Users</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse" id="users">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="add-user.html">
                                            <span class="sub-item">Add Student</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="view-students.php">
                                            <span class="sub-item">View Students</span>
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
                                        <a href="issue-book.php">
                                            <span class="sub-item">Issue Book</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="view-issued-book.php">
                                            <span class="sub-item">View Issue Books</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="book_request.php">
                                            <span class="sub-item">View Request Books</span>
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
                                        <a href="return-book.php">
                                            <span class="sub-item">Return Book</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="view-returned-book.php">
                                            <span class="sub-item">View Returned Books</span>
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
                                        <a href="view-penalty.php">
                                            <span class="sub-item">View Penalty</span>
                                        </a>



                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- End Sidebar here maun-->

        <div class="main-panel">
            <div class="main-header">
                <div class="main-header-logo">
                    <!-- Logo Header -->
                    <div class="logo-header" data-background-color="dark">
                        <a href="index.php" class="logo">
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
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <button type="submit" class="btn btn-search pe-1">
                                        <i class="fa fa-search search-icon"></i>
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    placeholder="Search ..."
                                    class="form-control" />
                            </div>
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
                                            <input
                                                type="text"
                                                placeholder="Search ..."
                                                class="form-control" />
                                        </div>
                                    </form>
                                </ul>
                            </li>
                            <li class="nav-item topbar-icon dropdown hidden-caret">
                                <a
                                    class="nav-link dropdown-toggle"
                                    href="#"
                                    id="messageDropdown"
                                    role="button"
                                    data-bs-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false">
                                    <i class="fa fa-envelope"></i>
                                </a>
                                <ul
                                    class="dropdown-menu messages-notif-box animated fadeIn"
                                    aria-labelledby="messageDropdown">
                                    <li>
                                        <div
                                            class="dropdown-title d-flex justify-content-between align-items-center">
                                            Messages
                                            <a href="#" class="small">Mark all as read</a>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="message-notif-scroll scrollbar-outer">
                                            <div class="notif-center">
                                                <a href="#">
                                                    <div class="notif-img">
                                                        <img
                                                            src="assets/img/jm_denis.jpg"
                                                            alt="Img Profile" />
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="subject">Jimmy Denis</span>
                                                        <span class="block"> How are you ? </span>
                                                        <span class="time">5 minutes ago</span>
                                                    </div>
                                                </a>
                                                <a href="#">
                                                    <div class="notif-img">
                                                        <img
                                                            src="assets/img/chadengle.jpg"
                                                            alt="Img Profile" />
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="subject">Chad</span>
                                                        <span class="block"> Ok, Thanks ! </span>
                                                        <span class="time">12 minutes ago</span>
                                                    </div>
                                                </a>
                                                <a href="#">
                                                    <div class="notif-img">
                                                        <img
                                                            src="assets/img/mlane.jpg"
                                                            alt="Img Profile" />
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="subject">Jhon Doe</span>
                                                        <span class="block">
                                                            Ready for the meeting today...
                                                        </span>
                                                        <span class="time">12 minutes ago</span>
                                                    </div>
                                                </a>
                                                <a href="#">
                                                    <div class="notif-img">
                                                        <img
                                                            src="assets/img/talha.jpg"
                                                            alt="Img Profile" />
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="subject">Talha</span>
                                                        <span class="block"> Hi, Apa Kabar ? </span>
                                                        <span class="time">17 minutes ago</span>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a class="see-all" href="javascript:void(0);">See all messages<i class="fa fa-angle-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item topbar-icon dropdown hidden-caret">
                                <a
                                    class="nav-link dropdown-toggle"
                                    href="#"
                                    id="notifDropdown"
                                    role="button"
                                    data-bs-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false">
                                    <i class="fa fa-bell"></i>
                                    <span class="notification">4</span>
                                </a>
                                <ul
                                    class="dropdown-menu notif-box animated fadeIn"
                                    aria-labelledby="notifDropdown">
                                    <li>
                                        <div class="dropdown-title">
                                            You have 4 new notification
                                        </div>
                                    </li>
                                    <li>
                                        <div class="notif-scroll scrollbar-outer">
                                            <div class="notif-center">
                                                <a href="#">
                                                    <div class="notif-icon notif-primary">
                                                        <i class="fa fa-user-plus"></i>
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="block"> New user registered </span>
                                                        <span class="time">5 minutes ago</span>
                                                    </div>
                                                </a>
                                                <a href="#">
                                                    <div class="notif-icon notif-success">
                                                        <i class="fa fa-comment"></i>
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="block">
                                                            Rahmad commented on Admin
                                                        </span>
                                                        <span class="time">12 minutes ago</span>
                                                    </div>
                                                </a>
                                                <a href="#">
                                                    <div class="notif-img">
                                                        <img
                                                            src="assets/img/profile2.jpg"
                                                            alt="Img Profile" />
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="block">
                                                            Reza send messages to you
                                                        </span>
                                                        <span class="time">12 minutes ago</span>
                                                    </div>
                                                </a>
                                                <a href="#">
                                                    <div class="notif-icon notif-danger">
                                                        <i class="fa fa-heart"></i>
                                                    </div>
                                                    <div class="notif-content">
                                                        <span class="block"> Farrah liked Admin </span>
                                                        <span class="time">17 minutes ago</span>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a class="see-all" href="javascript:void(0);">See all notifications<i class="fa fa-angle-right"></i>
                                        </a>
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
                                                <a class="col-6 col-md-4 p-0" href="#">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-danger rounded-circle">
                                                            <i class="far fa-calendar-alt"></i>
                                                        </div>
                                                        <span class="text">Calendar</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="#">
                                                    <div class="quick-actions-item">
                                                        <div
                                                            class="avatar-item bg-warning rounded-circle">
                                                            <i class="fas fa-map"></i>
                                                        </div>
                                                        <span class="text">Maps</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="#">
                                                    <div class="quick-actions-item">
                                                        <div class="avatar-item bg-info rounded-circle">
                                                            <i class="fas fa-file-excel"></i>
                                                        </div>
                                                        <span class="text">Reports</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="#">
                                                    <div class="quick-actions-item">
                                                        <div
                                                            class="avatar-item bg-success rounded-circle">
                                                            <i class="fas fa-envelope"></i>
                                                        </div>
                                                        <span class="text">Emails</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="#">
                                                    <div class="quick-actions-item">
                                                        <div
                                                            class="avatar-item bg-primary rounded-circle">
                                                            <i class="fas fa-file-invoice-dollar"></i>
                                                        </div>
                                                        <span class="text">Invoice</span>
                                                    </div>
                                                </a>
                                                <a class="col-6 col-md-4 p-0" href="#">
                                                    <div class="quick-actions-item">
                                                        <div
                                                            class="avatar-item bg-secondary rounded-circle">
                                                            <i class="fas fa-credit-card"></i>
                                                        </div>
                                                        <span class="text">Payments</span>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>

                            <li class="nav-item topbar-user dropdown hidden-caret">
                                <a
                                    class="dropdown-toggle profile-pic"
                                    data-bs-toggle="dropdown"
                                    href="#"
                                    aria-expanded="false">
                                    <div class="avatar-sm">
                                        <img
                                            src="assets/img/profile.jpg"
                                            alt="..."
                                            class="avatar-img rounded-circle" />
                                    </div>
                                    <span class="profile-username">
                                        <span class="op-7">Hi,</span>
                                        <span class="fw-bold">Hizrian</span>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-user animated fadeIn">
                                    <div class="dropdown-user-scroll scrollbar-outer">
                                        <li>
                                            <div class="user-box">
                                                <div class="avatar-lg">
                                                    <img
                                                        src="assets/img/profile.jpg"
                                                        alt="image profile"
                                                        class="avatar-img rounded" />
                                                </div>
                                                <div class="u-text">
                                                    <h4>Hizrian</h4>
                                                    <p class="text-muted">hello@example.com</p>
                                                    <a
                                                        href="profile.html"
                                                        class="btn btn-xs btn-secondary btn-sm">View Profile</a>
                                                </div>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">My Profile</a>
                                            <a class="dropdown-item" href="#">My Balance</a>
                                            <a class="dropdown-item" href="#">Inbox</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Account Setting</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Logout</a>
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
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Student Details</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="index.php">
                                    <i class="icon-home"> <i class="fas fa-home"></i></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"> <i class="fas fa-angle-right"></i></i>
                            </li>
                            <li class="nav-item">
                                <a href="view-students.php">Students</a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"> <i class="fas fa-angle-right"></i></i>
                            </li>
                            <li class="nav-item active">
                                <a href="view-student-details.php?student_id=<?php echo htmlspecialchars($student_id); ?>">Student Details</a>
                            </li>
                        </ul>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="card p-3">
                                <div class="d-flex align-items-center">
                                    <span class="stamp stamp-md bg-primary me-3">
                                        <i class="fas fa-book-open"></i>
                                    </span>
                                    <div>
                                        <h5 class="mb-1">
                                            <b><?php echo htmlspecialchars($student_data['total_books_issued']); ?> <small>Issued</small></b>
                                        </h5>
                                        <small class="text-muted">Total Books</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card p-3">
                                <div class="d-flex align-items-center">
                                    <span class="stamp stamp-md bg-info me-3">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </span>
                                    <div>
                                        <h5 class="text-info fw-bold">
                                            <b><?php echo htmlspecialchars($student_data['total_payments_count']); ?> <small>Transactions</small></b>
                                        </h5>
                                        <small class="text-muted">Payment Count</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card p-3">
                                <div class="d-flex align-items-center">
                                    <span class="stamp stamp-md bg-success me-3">
                                        <i class="fas fa-dollar-sign"></i>
                                    </span>
                                    <div>
                                        <h5 class="text-success fw-bold">
                                            <b>$<?php echo htmlspecialchars(number_format($student_data['total_amount_paid'], 2)); ?> <small>USD</small></b>
                                        </h5>
                                        <small class="text-muted">Amount Paid</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card p-3">
                                <div class="d-flex align-items-center">
                                    <span class="stamp stamp-md bg-warning me-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span>
                                    <div>
                                        <h5 class="mb-1">
                                            <b><?php echo htmlspecialchars($student_data['total_penalties_count']); ?> <small>Penalties</small></b>
                                        </h5>
                                        <small class="text-muted">Total Count</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card p-3">
                                <div class="d-flex align-items-center">
                                    <span class="stamp stamp-md bg-danger me-3">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </span>
                                    <div>
                                        <h5 class="mb-1">
                                            <b>$<?php echo htmlspecialchars(number_format($student_data['total_penalty_amount'], 2)); ?> <small>USD</small></b>
                                        </h5>
                                        <small class="text-muted">Penalty Amount</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card p-3">
                                <div class="d-flex align-items-center">
                                    <span class="stamp stamp-md bg-secondary me-3">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </span>
                                    <div>
                                        <h5 class="text-danger fw-bold">
                                            <b>$<?php echo htmlspecialchars(number_format($student_data['balance_to_pay'], 2)); ?> <small>USD</small></b>
                                        </h5>
                                        <small class="text-muted">Balance Due</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Student Information</div>
                                </div>
                                <div class="card-body">
                                    <?php if ($error_message): ?>
                                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                    <?php elseif ($student_data): ?>
                                        <div class="text-center">
                                            <?php if ($student_data['student_img']): ?>
                                                <img src="<?php echo htmlspecialchars($student_data['student_img']); ?>" alt="Student Image" class="student-img-detail" style="width: 400px; height: auto;">
                                            <?php else: ?>
                                                <img src="placeholder-student.png" alt="No Student Image" class="student-img-detail">
                                            <?php endif; ?>
                                        </div>
                                        <dl class="row">
                                            <dt class="col-sm-2">Student ID</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['student_id']); ?></dd>

                                            <dt class="col-sm-2">First Name</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['first_name']); ?></dd>

                                            <dt class="col-sm-2">Last Name</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['last_name']); ?></dd>

                                            <dt class="col-sm-2">Enrollment No</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['enrollment_no']); ?></dd>

                                            <dt class="col-sm-2">Username</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['username']); ?></dd>

                                            <dt class="col-sm-2">Email</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['email']); ?></dd>

                                            <dt class="col-sm-2">Contact</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['contact']); ?></dd>

                                            <dt class="col-sm-2">Status</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars(ucfirst($student_data['approved'])); ?></dd>

                                            <dt class="col-sm-2">Created At</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['created_at']); ?></dd>

                                            <dt class="col-sm-2">Updated At</dt>
                                            <dd class="col-sm-10"><?php echo htmlspecialchars($student_data['updated_at']); ?></dd>
                                        </dl>
                                    <?php else: ?>
                                        <p>Student details not found.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($student_data): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="card-title d-flex justify-content-between align-items-center">
                                            <h4 class="card-title">Issued Books</h4>
                                            <select id="issuedBooksSort" class="form-control form-control-sm" style="width: auto;">
                                                <option value="default">Sort by Date (Newest First)</option>
                                                <option value="title">Sort by Title (A-Z)</option>
                                                <option value="isbn">Sort by ISBN</option>
                                                <option value="returned">Returned Books</option>
                                                <option value="not_returned">Not Returned Books</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="issued-books-list"> <?php if (count($issued_books) > 0): ?>
                                                <ul class="list-group">
                                                    <?php foreach ($issued_books as $book): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center"
                                                            data-book-title="<?php echo htmlspecialchars($book['book_title']); ?>"
                                                            data-book-isbn="<?php echo htmlspecialchars($book['book_isbn']); ?>"
                                                            data-issue-date="<?php echo htmlspecialchars($book['issue_date']); ?>"
                                                            data-issue-status="<?php echo htmlspecialchars($book['issue_status']); ?>">
                                                            <div>
                                                                <?php echo htmlspecialchars($book['book_title']); ?> (ISBN: <?php echo htmlspecialchars($book['book_isbn']); ?>)
                                                                <br> Issued Date: <?php echo htmlspecialchars($book['issue_date']); ?>
                                                                <?php if ($book['issue_status'] == 'returned'): ?>
                                                                    | Return Date: <?php echo htmlspecialchars($book['return_date']); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span class="badge <?php echo ($book['issue_status'] == 'issued') ? 'bg-info' : 'bg-success'; ?> rounded-pill">
                                                                <?php echo ucfirst(htmlspecialchars($book['issue_status'])); ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p>No books currently issued to this student.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="card-title d-flex justify-content-between align-items-center">
                                            <h4 class="card-title">Penalties</h4>
                                            <select id="penaltiesSort" class="form-control form-control-sm" style="width: auto;">
                                                <option value="default">Sort by Date (Newest First)</option>
                                                <option value="amount">Sort by Amount</option>
                                                <option value="status">Sort by Status</option>
                                                <option value="book_title">Sort by Book Title</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="penalties-list">
                                            <?php if (count($student_penalties) > 0): ?>
                                                <ul class="list-group">
                                                    <?php foreach ($student_penalties as $penalty): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-top"
                                                            data-penalty-date="<?php echo htmlspecialchars($penalty['issue_date']); ?>"
                                                            data-penalty-amount="<?php echo htmlspecialchars($penalty['penalty_amount']); ?>"
                                                            data-penalty-status="<?php echo htmlspecialchars($penalty['penalty_status']); ?>"
                                                            data-penalty-book-title="<?php echo htmlspecialchars($penalty['book_title']); ?>">
                                                            <div>
                                                                <i class="fas fa-exclamation-triangle text-danger me-2"></i> <b>Penalty</b> - Book: <?php echo htmlspecialchars($penalty['book_title']); ?>
                                                                <br> Amount: <?php echo htmlspecialchars(number_format($penalty['penalty_amount'], 2)); ?> USD | Days Overdue: <?php echo htmlspecialchars($penalty['days_overdue']); ?>
                                                                <br> Date: <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($penalty['issue_date']))); ?>
                                                                <br> Status: <span class="badge <?php echo ($penalty['penalty_status'] == 'paid') ? 'bg-success' : 'bg-danger'; ?> rounded-pill"><?php echo ucfirst(htmlspecialchars($penalty['penalty_status'])); ?></span>
                                                            </div>
                                                            <div>
                                                                <?php if ($penalty['penalty_status'] == 'unpaid'): ?>
                                                                    <a href="pay-penalty.php?penalty_id=<?php echo htmlspecialchars($penalty['penalty_id']); ?>" class="btn btn-success btn-sm ms-2">Pay Now</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p>No penalties recorded for this student.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="card-title d-flex justify-content-between align-items-center">
                                            <h4 class="card-title">Payment Transactions</h4>
                                            <select id="paymentsSort" class="form-control form-control-sm" style="width: auto;">
                                                <option value="default">Sort by Date (Newest First)</option>
                                                <option value="amount">Sort by Amount</option>
                                                <option value="method">Sort by Method</option>
                                                <option value="book_title">Sort by Book Title</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="payments-list">
                                            <?php if (count($payment_transactions) > 0): ?>
                                                <ul class="list-group">
                                                    <?php foreach ($payment_transactions as $payment): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-top"
                                                            data-payment-date="<?php echo htmlspecialchars($payment['payment_date']); ?>"
                                                            data-payment-amount="<?php echo htmlspecialchars($payment['amount']); ?>"
                                                            data-payment-method="<?php echo htmlspecialchars($payment['payment_method']); ?>"
                                                            data-payment-book-title="<?php echo htmlspecialchars($payment['book_title']); ?>">
                                                            <div>
                                                                <i class="fas fa-check-circle text-success me-2"></i> <b>Payment</b> - Book: <?php echo htmlspecialchars($payment['book_title']); ?>
                                                                <br> Amount: <?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?> USD | Method: <?php echo htmlspecialchars(ucfirst($payment['payment_method'])); ?>
                                                                <br> Date: <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($payment['payment_date']))); ?>
                                                                <?php if ($payment['transaction_id']): ?>
                                                                    | Transaction ID: <?php echo htmlspecialchars($payment['transaction_id']); ?>
                                                                <?php endif; ?>
                                                                <br> Status: <span class="badge bg-success rounded-pill">Paid</span>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p>No payment transactions recorded for this student.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>

                    

                </div>
            </div>

            <style>
                .stamp {
                    height: 2.5rem;
                    width: 2.5rem;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    color: #fff;
                    font-size: 1.2rem;
                    flex-shrink: 0;
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Issued Books Sorting
                    const issuedBooksSortDropdown = document.getElementById('issuedBooksSort');
                    const issuedBookList = document.getElementById('issued-books-list').querySelector('ul');
                    const issuedBookListItems = Array.from(issuedBookList.querySelectorAll('li'));

                    issuedBooksSortDropdown.addEventListener('change', function() {
                        const sortBy = this.value;
                        let sortedItems = [...issuedBookListItems];

                        if (sortBy === 'title') {
                            sortedItems.sort((a, b) => a.dataset.bookTitle.localeCompare(b.dataset.bookTitle));
                        } else if (sortBy === 'isbn') {
                            sortedItems.sort((a, b) => a.dataset.bookIsbn.localeCompare(b.dataset.bookIsbn));
                        } else if (sortBy === 'returned') {
                            sortedItems.sort((a, b) => {
                                const statusA = a.dataset.issueStatus.toLowerCase();
                                const statusB = b.dataset.issueStatus.toLowerCase();
                                if (statusA === 'returned' && statusB !== 'returned') return -1;
                                if (statusA !== 'returned' && statusB === 'returned') return 1;
                                return 0;
                            });
                        } else if (sortBy === 'not_returned') {
                            sortedItems.sort((a, b) => {
                                const statusA = a.dataset.issueStatus.toLowerCase();
                                const statusB = b.dataset.issueStatus.toLowerCase();
                                if (statusA === 'issued' && statusB !== 'issued') return -1;
                                if (statusA !== 'issued' && statusB === 'issued') return 1;
                                return 0;
                            });
                        } else { // default
                            sortedItems.sort((a, b) => new Date(b.dataset.issueDate) - new Date(a.dataset.issueDate));
                        }

                        issuedBookList.innerHTML = '';
                        sortedItems.forEach(item => issuedBookList.appendChild(item));
                    });

                    // Penalties Sorting
                    const penaltiesSortDropdown = document.getElementById('penaltiesSort');
                    const penaltiesList = document.getElementById('penalties-list').querySelector('ul');
                    const penaltyListItems = Array.from(penaltiesList.querySelectorAll('li'));

                    penaltiesSortDropdown.addEventListener('change', function() {
                        const sortBy = this.value;
                        let sortedItems = [...penaltyListItems];

                        if (sortBy === 'amount') {
                            sortedItems.sort((a, b) => parseFloat(a.dataset.penaltyAmount) - parseFloat(b.dataset.penaltyAmount));
                        } else if (sortBy === 'status') {
                            sortedItems.sort((a, b) => a.dataset.penaltyStatus.localeCompare(b.dataset.penaltyStatus));
                        } else if (sortBy === 'book_title') {
                            sortedItems.sort((a, b) => a.dataset.penaltyBookTitle.localeCompare(b.dataset.penaltyBookTitle));
                        } else { // default - Date
                            sortedItems.sort((a, b) => new Date(b.dataset.penaltyDate) - new Date(a.dataset.penaltyDate));
                        }

                        penaltiesList.innerHTML = '';
                        sortedItems.forEach(item => penaltiesList.appendChild(item));
                    });

                    // Payments Sorting
                    const paymentsSortDropdown = document.getElementById('paymentsSort');
                    const paymentsList = document.getElementById('payments-list').querySelector('ul');
                    const paymentListItems = Array.from(paymentsList.querySelectorAll('li'));

                    paymentsSortDropdown.addEventListener('change', function() {
                        const sortBy = this.value;
                        let sortedItems = [...paymentListItems];

                        if (sortBy === 'amount') {
                            sortedItems.sort((a, b) => parseFloat(a.dataset.paymentAmount) - parseFloat(b.dataset.paymentAmount));
                        } else if (sortBy === 'method') {
                            sortedItems.sort((a, b) => a.dataset.paymentMethod.localeCompare(b.dataset.paymentMethod));
                        } else if (sortBy === 'book_title') {
                            sortedItems.sort((a, b) => a.dataset.paymentBookTitle.localeCompare(b.dataset.paymentBookTitle));
                        } else { // default - Date
                            sortedItems.sort((a, b) => new Date(b.dataset.paymentDate) - new Date(a.dataset.paymentDate));
                        }

                        paymentsList.innerHTML = '';
                        sortedItems.forEach(item => paymentsList.appendChild(item));
                    });
                });
            </script>
            <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
            <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


            <?php
            $conn->close();
            ?>


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

    <!-- Kaiadmin DEMO methods, don't include it in your project! -->
    <script src="assets/js/setting-demo.js"></script>
    <script src="assets/js/demo.js"></script>
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