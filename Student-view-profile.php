<?php
session_start();

require_once 'phpqrcode/qrlib.php';


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}


$student_id_from_session = $_SESSION['student_id'] ?? 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : $student_id_from_session;


if ($student_id <= 0) {
    if (!isset($_GET['student_id']) && $student_id_from_session <= 0) {
        header("Location: Student-login.php");
        exit();
    }
}


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

// --- Pagination Settings ---
$items_per_page = 10; // Adjust as needed

// Get current page numbers for each section, default to 1
$page_books = isset($_GET['page_books']) ? max(1, intval($_GET['page_books'])) : 1;
$page_penalties = isset($_GET['page_penalties']) ? max(1, intval($_GET['page_penalties'])) : 1;
$page_fines = isset($_GET['page_fines']) ? max(1, intval($_GET['page_fines'])) : 1;
$page_payments = isset($_GET['page_payments']) ? max(1, intval($_GET['page_payments'])) : 1;

// Calculate offsets
$offset_books = ($page_books - 1) * $items_per_page;
$offset_penalties = ($page_penalties - 1) * $items_per_page;
$offset_fines = ($page_fines - 1) * $items_per_page;
$offset_payments = ($page_payments - 1) * $items_per_page;

// --- Initialize Variables ---
$student_data = null;
$issued_books = [];
$payment_transactions = [];
$student_penalties = [];
$student_fines = [];
$error_message = null;
$qr_filename = null; // Initialize QR filename variable

$total_books = 0;
$total_penalties = 0;
$total_fines = 0;
$total_payments = 0;

$total_pages_books = 1;
$total_pages_penalties = 1;
$total_pages_fines = 1;
$total_pages_payments = 1;

// --- Fetch Data ---
if ($student_id > 0) {
    // Fetch student details (no pagination needed)
    $student_sql = "SELECT student_id, first_name, last_name, enrollment_no, username, email, contact, student_img, approved, created_at, updated_at
                     FROM students_registration
                     WHERE student_id = ?";
    $student_stmt = $conn->prepare($student_sql);
    if ($student_stmt) {
        $student_stmt->bind_param("i", $student_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        $student_data = $student_result->fetch_assoc();
        $student_stmt->close();

        if (!$student_data) {
            $error_message = "Student with ID " . htmlspecialchars($student_id) . " not found.";
        }
    } else {
        error_log("Prepare failed for student_sql: " . $conn->error);
        $error_message = "Error retrieving student data.";
    }

    // Proceed only if student data was found
    if ($student_data) {

        // --- Issued Books ---
        // (Count and Fetch Issued Books logic remains the same as before)
        $count_books_sql = "SELECT COUNT(*) FROM issue_book WHERE student_id = ?";
        $count_books_stmt = $conn->prepare($count_books_sql);
        if ($count_books_stmt) {
            $count_books_stmt->bind_param("i", $student_id);
            $count_books_stmt->execute();
            $count_books_stmt->bind_result($total_books);
            $count_books_stmt->fetch();
            $count_books_stmt->close();
            $total_pages_books = ceil($total_books / $items_per_page);
            $page_books = max(1, min($page_books, $total_pages_books));
            $offset_books = ($page_books - 1) * $items_per_page;
        } else {
            error_log("Prepare failed for count_books_sql: " . $conn->error);
            $error_message .= "<br>Error counting issued books.";
        }
        $issued_books_sql = "SELECT ib.issue_id, ib.issue_date, b.title AS book_title, b.isbn AS book_isbn, ib.return_date, ib.status AS issue_status FROM issue_book ib JOIN books b ON ib.book_id = b.id WHERE ib.student_id = ? ORDER BY ib.issue_date DESC LIMIT ? OFFSET ?";
        $issued_books_stmt = $conn->prepare($issued_books_sql);
        if ($issued_books_stmt) {
            $issued_books_stmt->bind_param("iii", $student_id, $items_per_page, $offset_books);
            $issued_books_stmt->execute();
            $issued_books_result = $issued_books_stmt->get_result();
            $issued_books = $issued_books_result->fetch_all(MYSQLI_ASSOC);
            $issued_books_stmt->close();
        } else {
            error_log("Prepare failed for issued_books_sql: " . $conn->error);
            $error_message .= "<br>Error retrieving issued books history.";
        }

        // --- Payments ---
        // (Count and Fetch Payments logic remains the same as before)
        $count_payments_sql = "SELECT COUNT(*) FROM payments WHERE student_id = ?";
        $count_payments_stmt = $conn->prepare($count_payments_sql);
        if ($count_payments_stmt) {
            $count_payments_stmt->bind_param("i", $student_id);
            $count_payments_stmt->execute();
            $count_payments_stmt->bind_result($total_payments);
            $count_payments_stmt->fetch();
            $count_payments_stmt->close();
            $total_pages_payments = ceil($total_payments / $items_per_page);
            $page_payments = max(1, min($page_payments, $total_pages_payments));
            $offset_payments = ($page_payments - 1) * $items_per_page;
        } else {
            error_log("Prepare failed for count_payments_sql: " . $conn->error);
            $error_message .= "<br>Error counting payments.";
        }
        $payments_sql = "SELECT p.payment_id, p.amount, p.payment_date, p.payment_method, p.transaction_id, b.title AS book_title, b.isbn AS book_isbn, ib.issue_date AS related_issue_date, ib.return_date AS related_return_date FROM payments p LEFT JOIN issue_book ib ON p.issue_id = ib.issue_id LEFT JOIN books b ON ib.book_id = b.id WHERE p.student_id = ? ORDER BY p.payment_date DESC LIMIT ? OFFSET ?";
        $payments_stmt = $conn->prepare($payments_sql);
        if ($payments_stmt) {
            $payments_stmt->bind_param("iii", $student_id, $items_per_page, $offset_payments);
            $payments_stmt->execute();
            $payments_result = $payments_stmt->get_result();
            $payment_transactions = $payments_result->fetch_all(MYSQLI_ASSOC);
            $payments_stmt->close();
        } else {
            error_log("Prepare failed for payments_sql: " . $conn->error);
            $error_message .= "<br>Error retrieving payment history.";
        }

        // --- Penalties ---
        // (Count and Fetch Penalties logic remains the same as before)
        $count_penalties_sql = "SELECT COUNT(*) FROM penalties p JOIN issue_book ib ON p.issue_id = ib.issue_id WHERE ib.student_id = ?";
        $count_penalties_stmt = $conn->prepare($count_penalties_sql);
        if ($count_penalties_stmt) {
            $count_penalties_stmt->bind_param("i", $student_id);
            $count_penalties_stmt->execute();
            $count_penalties_stmt->bind_result($total_penalties);
            $count_penalties_stmt->fetch();
            $count_penalties_stmt->close();
            $total_pages_penalties = ceil($total_penalties / $items_per_page);
            $page_penalties = max(1, min($page_penalties, $total_pages_penalties));
            $offset_penalties = ($page_penalties - 1) * $items_per_page;
        } else {
            error_log("Prepare failed for count_penalties_sql: " . $conn->error);
            $error_message .= "<br>Error counting penalties.";
        }
        $penalties_sql = "SELECT p.penalty_id, p.penalty_amount, p.status AS penalty_status, p.days_overdue, ib.issue_id, ib.issue_date, ib.return_date, b.title AS book_title, p.created_at AS penalty_created_at FROM penalties p JOIN issue_book ib ON p.issue_id = ib.issue_id JOIN books b ON ib.book_id = b.id WHERE ib.student_id = ? ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $penalties_stmt = $conn->prepare($penalties_sql);
        if ($penalties_stmt) {
            $penalties_stmt->bind_param("iii", $student_id, $items_per_page, $offset_penalties);
            $penalties_stmt->execute();
            $penalties_result = $penalties_stmt->get_result();
            $student_penalties = $penalties_result->fetch_all(MYSQLI_ASSOC);
            $penalties_stmt->close();
        } else {
            error_log("Prepare failed for penalties_sql: " . $conn->error);
            $error_message .= "<br>Error preparing statement for penalties: " . htmlspecialchars($conn->error);
        }

        // --- Fines ---
        // (Count and Fetch Fines logic remains the same as before)
        $count_fines_sql = "SELECT COUNT(*) FROM fines f JOIN issue_book ib ON f.issue_id = ib.issue_id WHERE ib.student_id = ?";
        $count_fines_stmt = $conn->prepare($count_fines_sql);
        if ($count_fines_stmt) {
            $count_fines_stmt->bind_param("i", $student_id);
            $count_fines_stmt->execute();
            $count_fines_stmt->bind_result($total_fines);
            $count_fines_stmt->fetch();
            $count_fines_stmt->close();
            $total_pages_fines = ceil($total_fines / $items_per_page);
            $page_fines = max(1, min($page_fines, $total_pages_fines));
            $offset_fines = ($page_fines - 1) * $items_per_page;
        } else {
            error_log("Prepare failed for count_fines_sql: " . $conn->error);
            $error_message .= "<br>Error counting fines.";
        }
        $fines_sql = "SELECT f.fine_id, f.fine_amount, f.status AS fine_status, f.days_overdue, f.payment_id, ib.issue_id, ib.issue_date, ib.return_date, b.title AS book_title, f.created_at AS fine_created_at FROM fines f JOIN issue_book ib ON f.issue_id = ib.issue_id JOIN books b ON ib.book_id = b.id WHERE ib.student_id = ? ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
        $fines_stmt = $conn->prepare($fines_sql);
        if ($fines_stmt) {
            $fines_stmt->bind_param("iii", $student_id, $items_per_page, $offset_fines);
            $fines_stmt->execute();
            $fines_result = $fines_stmt->get_result();
            $student_fines = $fines_result->fetch_all(MYSQLI_ASSOC);
            $fines_stmt->close();
        } else {
            error_log("Prepare failed for fines_sql: " . $conn->error);
            $error_message .= "<br>Error retrieving fines history.";
        }

        // --- Calculate Summary Statistics ---
        $student_data['total_books_issued'] = $total_books;
        $student_data['total_payments_count'] = $total_payments;
        $student_data['total_penalties_count'] = $total_penalties;
        $student_data['total_fines_count'] = $total_fines;

        // Get total amount paid
        $all_payments_sql = "SELECT amount FROM payments WHERE student_id = ?"; // Select the 'amount' from the 'payments' table for the given student.
        $all_payments_stmt = $conn->prepare($all_payments_sql); // Prepare the SQL statement for execution.
        $total_amount_paid = 0; // Initialize the variable to store the total amount paid.

        if ($all_payments_stmt) {
            $all_payments_stmt->bind_param("i", $student_id); // Bind the student ID parameter to the prepared statement.
            $all_payments_stmt->execute(); // Execute the prepared statement.
            $all_payments_result = $all_payments_stmt->get_result(); // Get the result set from the executed statement.
            $all_payments = $all_payments_result->fetch_all(MYSQLI_ASSOC); // Fetch all rows from the result set as an associative array.
            $total_amount_paid = array_sum(array_column($all_payments, 'amount')); // Calculate the sum of the 'amount' column from the fetched payments.  array_column gets the amount column.
            $all_payments_stmt->close(); // Close the prepared statement to free up resources.
        }
        $student_data['total_amount_paid'] = $total_amount_paid; // Store the total amount paid in the student data array.

        // Get total penalty amount and unpaid penalty amount
        $all_penalties_sql = "SELECT penalty_amount, status FROM penalties p JOIN issue_book ib ON p.issue_id = ib.issue_id WHERE ib.student_id = ?"; // Select penalty amounts and their statuses, joining 'penalties' and 'issue_book' tables.
        $all_penalties_stmt = $conn->prepare($all_penalties_sql); // Prepare the SQL statement.
        $total_penalty_amount = 0; // Initialize total penalty amount.
        $unpaid_penalty_amount = 0; // Initialize unpaid penalty amount.

        if ($all_penalties_stmt) {
            $all_penalties_stmt->bind_param("i", $student_id); // Bind the student ID.
            $all_penalties_stmt->execute(); // Execute the query.
            $all_penalties_result = $all_penalties_stmt->get_result(); // Get the result set.
            $all_penalties = $all_penalties_result->fetch_all(MYSQLI_ASSOC); // Fetch all penalties as an associative array.
            $total_penalty_amount = array_sum(array_column($all_penalties, 'penalty_amount')); // Sum all penalty amounts.

            foreach ($all_penalties as $p) {  // Iterate through each penalty record.
                if ($p['status'] == 'unpaid') // Check if the penalty is unpaid.
                    $unpaid_penalty_amount += $p['penalty_amount']; // Add the penalty amount to the unpaid total.
            }
            $all_penalties_stmt->close(); // Close the statement.
        }
        $student_data['total_penalty_amount'] = $total_penalty_amount; // Store the total penalty amount.
        $student_data['unpaid_penalty_amount'] = $unpaid_penalty_amount; // Store the unpaid penalty amount.


        // Get total fine amount and unpaid fine amount.  Fines are treated similarly to penalties.
        $all_fines_sql = "SELECT fine_amount, status FROM fines f JOIN issue_book ib ON f.issue_id = ib.issue_id WHERE ib.student_id = ?";
        $all_fines_stmt = $conn->prepare($all_fines_sql);
        $total_fine_amount = 0;
        $unpaid_fine_amount = 0;
        if ($all_fines_stmt) {
            $all_fines_stmt->bind_param("i", $student_id);
            $all_fines_stmt->execute();
            $all_fines_result = $all_fines_stmt->get_result();
            $all_fines = $all_fines_result->fetch_all(MYSQLI_ASSOC);
            $total_fine_amount = array_sum(array_column($all_fines, 'fine_amount'));
            foreach ($all_fines as $f) {
                if ($f['status'] == 'unpaid')
                    $unpaid_fine_amount += $f['fine_amount'];
            }
            $all_fines_stmt->close();
        }
        $student_data['total_fine_amount'] = $total_fine_amount;
        $student_data['unpaid_fine_amount'] = $unpaid_fine_amount;
        // Calculate the total balance due (unpaid fines + unpaid penalties)
        $student_data['balance_to_pay'] = $unpaid_penalty_amount + $unpaid_fine_amount;

        // --- Generate QR Code --- << MOVED LOGIC HERE AND MODIFIED >>
        $qr_code_dir = 'qrcodes/'; // Define the directory
        // Use more descriptive data for the QR code
        $qr_data = "Student Name: " . $student_data['first_name'] . " " . $student_data['last_name'] . "\n" .
            "Enrollment No: " . $student_data['enrollment_no'] . "\n" .
            "Student ID: " . $student_data['student_id'] . "\n" .
            "Balance Due: " . format_currency($student_data['balance_to_pay']) . "\n" . // Include balance
            "Generated On: " . date("Y-m-d H:i:s");

        $qr_filename = $qr_code_dir . 'user_' . $student_data['student_id'] . '.png';

        // Check if directory exists, if not try to create it
        if (!file_exists($qr_code_dir)) {
            if (!mkdir($qr_code_dir, 0775, true)) { // Create recursively with permissions
                error_log("Failed to create QR code directory: " . $qr_code_dir);
                $error_message .= "<br>Could not create directory for QR code generation.";
                $qr_filename = null; // Prevent trying to display non-existent QR code
            }
        }

        // Generate QR code only if directory exists or was created
        if ($qr_filename && is_writable($qr_code_dir)) { // Check if directory is writable
            try {
                QRcode::png($qr_data, $qr_filename, QR_ECLEVEL_L, 4, 4); // Level, Size, Margin
            } catch (Exception $e) {
                error_log("QR Code generation failed: " . $e->getMessage());
                $error_message .= "<br>Failed to generate QR code.";
                $qr_filename = null;
            }
        } elseif ($qr_filename) {
            error_log("QR code directory is not writable: " . $qr_code_dir);
            $error_message .= "<br>QR code directory permissions issue.";
            $qr_filename = null;
        }
        // Removed the redundant database query for QR code data

    } // End if($student_data)
} else {
    $error_message = "Invalid Student ID provided.";
}

// Close connection AFTER all database operations are complete
$conn->close();

// --- Helper Functions ---
// (format_datetime, format_currency, generate_pagination functions remain the same as before)
function format_datetime($datetime_string)
{
    if (empty($datetime_string) || $datetime_string === '0000-00-00 00:00:00') return 'N/A';
    try {
        $date = new DateTime($datetime_string);
        return $date->format('M d, Y, g:i A');
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}
function format_currency($amount)
{
    return '$' . number_format($amount ?? 0, 2);
}
function generate_pagination($base_url, $page_param, $current_page, $total_pages)
{
    if ($total_pages <= 1) {
        return '';
    }
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center">';
    $prev_disabled = ($current_page <= 1) ? ' disabled' : '';
    $prev_link = $base_url . '&' . $page_param . '=' . ($current_page - 1);
    $html .= '<li class="page-item' . $prev_disabled . '"><a class="page-link" href="' . ($current_page > 1 ? $prev_link : '#') . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    if ($start_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&' . $page_param . '=1' . '">1</a></li>';
        if ($start_page > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i == $current_page) ? ' active' : '';
        $link = $base_url . '&' . $page_param . '=' . $i;
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $link . '">' . $i . '</a></li>';
    }
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&' . $page_param . '=' . $total_pages . '">' . $total_pages . '</a></li>';
    }
    $next_disabled = ($current_page >= $total_pages) ? ' disabled' : '';
    $next_link = $base_url . '&' . $page_param . '=' . ($current_page + 1);
    $html .= '<li class="page-item' . $next_disabled . '"><a class="page-link" href="' . ($current_page < $total_pages ? $next_link : '#') . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// Base URL for pagination links (preserves student_id)
$base_pagination_url = basename($_SERVER['PHP_SELF']) . '?student_id=' . urlencode($student_id);


?>
<!DOCTYPE html>
<html lang="en">

<head>
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
                                    <li>
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
                        <li class="nav-item ">
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
                        <li class="nav-item ">
                            <a data-bs-toggle="collapse" href="#penalties">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Penalties</p>
                                <span class="caret"></span>
                            </a>
                            <div class="collapse " id="penalties">
                                <ul class="nav nav-collapse">
                                    <li>
                                        <a href="Student-view-penalty.php">
                                            <span class="sub-item">View
                                                Penalties</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="Student-view-fine.php">
                                            <span class="sub-item">View
                                                Fines</span>
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
                                    <li class="active">
                                        <a href="Student-view-profile.php">
                                            <span class="sub-item">View Profile</span>
                                        </a>
                                    </li>
                                    <li>
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
        <!-- End Sidebar  here maun-->
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
                <div class="page-inner">
                    <div class="page-header mb-4">
                        <h3 class="fw-bold">Student Details </h3>

                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="Student-index.php"><i class="fas fa-home"></i> Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <?php echo isset($student_data['first_name']) ? htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']) : 'Details'; ?>
                                </li>
                            </ol>
                        </nav>
                    </div>
                    <?php if ($error_message) : ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error:</strong> <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($student_data) : ?>
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="card p-2 p-sm-3 summary-card h-100">
                                    <div class="d-flex align-items-center">
                                        <span class="stamp bg-primary me-2 me-sm-3"><i class="fas fa-book-open"></i></span>
                                        <div>
                                            <h5 class="mb-1"><b><?php echo htmlspecialchars($student_data['total_books_issued']); ?></b> <small>Issued</small></h5>
                                            <small class="text-muted d-none d-sm-block">Total Books</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="card p-2 p-sm-3 summary-card h-100">
                                    <div class="d-flex align-items-center">
                                        <span class="stamp bg-info me-2 me-sm-3"><i class="fas fa-file-invoice-dollar"></i></span>
                                        <div>
                                            <h5 class="mb-1"><b><?php echo htmlspecialchars($student_data['total_payments_count']); ?></b> <small>Payments</small></h5>
                                            <small class="text-muted d-none d-sm-block">Transactions</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="card p-2 p-sm-3 summary-card h-100">
                                    <div class="d-flex align-items-center">
                                        <span class="stamp bg-success me-2 me-sm-3"><i class="fas fa-dollar-sign"></i></span>
                                        <div>
                                            <h5 class="mb-1"><b><?php echo format_currency($student_data['total_amount_paid']); ?></b></h5>
                                            <small class="text-muted d-none d-sm-block">Amount Paid</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="card p-2 p-sm-3 summary-card h-100">
                                    <div class="d-flex align-items-center">
                                        <span class="stamp bg-warning me-2 me-sm-3"><i class="fas fa-exclamation-triangle"></i></span>
                                        <div>
                                            <h5 class="mb-1"><b><?php echo htmlspecialchars($student_data['total_penalties_count']); ?></b> <small>Penalties</small></h5>
                                            <small class="text-muted d-none d-sm-block">Incidents</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="card p-2 p-sm-3 summary-card h-100">
                                    <div class="d-flex align-items-center">
                                        <span class="stamp bg-purple me-2 me-sm-3" style="background-color:#6f42c1;"><i class="fas fa-gavel"></i></span>
                                        <div>
                                            <h5 class="mb-1"><b><?php echo htmlspecialchars($student_data['total_fines_count']); ?></b> <small>Fines</small></h5>
                                            <small class="text-muted d-none d-sm-block">Incidents</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="card p-2 p-sm-3 summary-card h-100">
                                    <div class="d-flex align-items-center">
                                        <span class="stamp bg-secondary me-2 me-sm-3"><i class="fas fa-balance-scale"></i></span>
                                        <div>
                                            <h5 class="mb-1 text-danger"><b><?php echo format_currency($student_data['balance_to_pay']); ?></b></h5>
                                            <small class="text-muted d-none d-sm-block">Balance Due</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Student Information</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-9 order-md-1">
                                        <dl class="row">
                                            <dt class="col-sm-3">Full Name</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?></dd>
                                            <dt class="col-sm-3">Enrollment No</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($student_data['enrollment_no']); ?></dd>
                                            <dt class="col-sm-3">Username</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($student_data['username']); ?></dd>
                                            <dt class="col-sm-3">Email</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($student_data['email']); ?></dd>
                                            <dt class="col-sm-3">Contact</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($student_data['contact'] ?? 'N/A'); ?></dd>
                                            <dt class="col-sm-3">Account Status</dt>
                                            <dd class="col-sm-9"><span class="badge <?php echo ($student_data['approved'] == 'active') ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo htmlspecialchars(ucfirst($student_data['approved'])); ?></span></dd>
                                            <dt class="col-sm-3">Registered On</dt>
                                            <dd class="col-sm-9"><?php echo format_datetime($student_data['created_at']); ?></dd>
                                            <dt class="col-sm-3">Last Updated</dt>
                                            <dd class="col-sm-9"><?php echo format_datetime($student_data['updated_at']); ?></dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-3 order-md-2 text-center">
                                        <img src="<?php echo (!empty($student_data['student_img']) ? htmlspecialchars($student_data['student_img']) : 'assets/img/default-student.png'); ?>" alt="Student Image" class="img-thumbnail rounded-circle student-img-detail mb-3">

                                        <?php if ($qr_filename && file_exists($qr_filename)) : ?>
                                            <div class="qr-code-container">
                                                <h6 class="mt-2">Student Details</h6>
                                                <img src="<?php echo htmlspecialchars($qr_filename); ?>?t=<?php echo time(); // Add timestamp to prevent caching 
                                                                                                            ?>" alt="Student QR Code" style="max-width: 200px; height: auto;">
                                            </div>
                                        <?php elseif (!$error_message) : // Show only if QR failed but no other major error  
                                        ?>
                                            <div class="text-muted small mt-2">QR Code could not be generated.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title">Issued Books History (Page <?php echo $page_books; ?> of <?php echo $total_pages_books; ?>)</h4>
                                    <select id="issuedBooksSort" class="form-select form-select-sm" style="width: auto;" aria-label="Sort Issued Books">
                                        <option value="default">Sort by Issue Date (Newest)</option>
                                        <option value="title_asc">Title (A-Z)</option>
                                        <option value="title_desc">Title (Z-A)</option>
                                        <option value="status">Status</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="issued-books-list">
                                    <?php if (!empty($issued_books)) : ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($issued_books as $book) : ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap" data-book-title="<?php echo htmlspecialchars(strtolower($book['book_title'])); ?>" data-issue-date="<?php echo htmlspecialchars($book['issue_date']); ?>" data-issue-status="<?php echo htmlspecialchars($book['issue_status']); ?>">
                                                    <div class="me-3">
                                                        <i class="fas fa-book text-primary me-2"></i><strong><?php echo htmlspecialchars($book['book_title']); ?></strong> <small>(ISBN: <?php echo htmlspecialchars($book['book_isbn']); ?>)</small><br>
                                                        <small>Issued: <?php echo format_datetime($book['issue_date']); ?><?php if ($book['issue_status'] == 'returned') : ?> | Returned: <?php echo format_datetime($book['return_date']); ?><?php endif; ?></small>
                                                    </div>
                                                    <span class="badge <?php echo ($book['issue_status'] == 'issued') ? 'bg-info text-dark' : 'bg-success'; ?> rounded-pill mt-1 mt-sm-0"><?php echo ucfirst(htmlspecialchars($book['issue_status'])); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <p class="text-center text-muted p-3">No book issuance history found.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($total_pages_books > 1) : ?>
                                <div class="card-footer">
                                    <?php echo generate_pagination($base_pagination_url, 'page_books', $page_books, $total_pages_books); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="card-title">Penalties (Page <?php echo $page_penalties; ?> of <?php echo $total_pages_penalties; ?>)</h4>
                                            <select id="penaltiesSort" class="form-select form-select-sm" style="width: auto;" aria-label="Sort Penalties">
                                                <option value="default">Sort by Date (Newest)</option>
                                                <option value="amount_desc">Amount (High-Low)</option>
                                                <option value="amount_asc">Amount (Low-High)</option>
                                                <option value="status">Status</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div id="penalties-list">
                                            <?php if (!empty($student_penalties)) : ?>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach ($student_penalties as $penalty) : ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-start flex-wrap" data-penalty-date="<?php echo htmlspecialchars($penalty['penalty_created_at']); ?>" data-penalty-amount="<?php echo htmlspecialchars($penalty['penalty_amount']); ?>" data-penalty-status="<?php echo htmlspecialchars($penalty['penalty_status']); ?>">
                                                            <div class="me-3">
                                                                <i class="fas fa-exclamation-triangle text-warning me-2"></i><strong>Penalty</strong> - <small>Book: <?php echo htmlspecialchars($penalty['book_title']); ?></small><br>
                                                                <small>Amount: <strong><?php echo format_currency($penalty['penalty_amount']); ?></strong> | Days Overdue: <?php echo htmlspecialchars($penalty['days_overdue']); ?> | Added: <?php echo format_datetime($penalty['penalty_created_at']); ?></small><br>
                                                                Status: <span class="badge <?php echo ($penalty['penalty_status'] == 'paid') ? 'bg-success' : 'bg-danger'; ?> rounded-pill"><?php echo ucfirst(htmlspecialchars($penalty['penalty_status'])); ?></span>
                                                            </div>
                                                            <div class="mt-1 mt-sm-0">
                                                                <?php if ($penalty['penalty_status'] == 'unpaid') : ?>
                                                                    <a href="pay-penalty.php?penalty_id=<?php echo htmlspecialchars($penalty['penalty_id']); ?>&student_id=<?php echo $student_id; ?>" class="btn btn-success btn-sm">Pay Penalty</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <p class="text-center text-muted p-3">No penalties found.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($total_pages_penalties > 1) : ?>
                                        <div class="card-footer">
                                            <?php echo generate_pagination($base_pagination_url, 'page_penalties', $page_penalties, $total_pages_penalties); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="card-title">Fines (Page <?php echo $page_fines; ?> of <?php echo $total_pages_fines; ?>)</h4>
                                            <select id="finesSort" class="form-select form-select-sm" style="width: auto;" aria-label="Sort Fines">
                                                <option value="default">Sort by Date (Newest)</option>
                                                <option value="amount_desc">Amount (High-Low)</option>
                                                <option value="amount_asc">Amount (Low-High)</option>
                                                <option value="status">Status</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div id="fines-list">
                                            <?php if (!empty($student_fines)) : ?>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach ($student_fines as $fine) : ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-start flex-wrap" data-fine-date="<?php echo htmlspecialchars($fine['fine_created_at']); ?>" data-fine-amount="<?php echo htmlspecialchars($fine['fine_amount']); ?>" data-fine-status="<?php echo htmlspecialchars($fine['fine_status']); ?>">
                                                            <div class="me-3">
                                                                <i class="fas fa-gavel text-purple me-2"></i><strong>Fine</strong> - <small>Book: <?php echo htmlspecialchars($fine['book_title']); ?></small><br>
                                                                <small>Amount: <strong><?php echo format_currency($fine['fine_amount']); ?></strong> | Days Overdue: <?php echo htmlspecialchars($fine['days_overdue']); ?> | Added: <?php echo format_datetime($fine['fine_created_at']); ?></small><br>
                                                                Status: <span class="badge <?php echo ($fine['fine_status'] == 'paid') ? 'bg-success' : 'bg-danger'; ?> rounded-pill"><?php echo ucfirst(htmlspecialchars($fine['fine_status'])); ?></span>
                                                            </div>
                                                            <div class="mt-1 mt-sm-0">
                                                                <?php if ($fine['fine_status'] == 'unpaid') : ?>
                                                                    <a href="pay-fine.php?fine_id=<?php echo htmlspecialchars($fine['fine_id']); ?>&student_id=<?php echo $student_id; ?>" class="btn btn-success btn-sm">Pay Fine</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <p class="text-center text-muted p-3">No fines found.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($total_pages_fines > 1) : ?>
                                        <div class="card-footer">
                                            <?php echo generate_pagination($base_pagination_url, 'page_fines', $page_fines, $total_pages_fines); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>


                            <div class="card mb-4">
                                <div class="card-header">
                                    <h4 class="card-title">Payment Transactions (Page <?php echo $page_payments; ?> of <?php echo $total_pages_payments; ?>)</h4>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (!empty($payment_transactions)) : ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-borderless">
                                                <thead>
                                                    <tr>
                                                        <th>Transaction ID</th>
                                                        <th>Amount</th>
                                                        <th>Payment Method</th>
                                                        <th>Date</th>
                                                        <th>Book Details</th>
                                                        <th>Issue/Return Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($payment_transactions as $payment) : ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                                            <td><?php echo format_currency($payment['amount']); ?></td>
                                                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                                            <td><?php echo format_datetime($payment['payment_date']); ?></td>
                                                            <td>
                                                                <?php if ($payment['book_title']) : ?>
                                                                    <?php echo htmlspecialchars($payment['book_title']); ?> (ISBN: <?php echo htmlspecialchars($payment['book_isbn']); ?>)
                                                                <?php else : ?>
                                                                    N/A
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($payment['related_issue_date']) : ?>
                                                                    Issue: <?php echo format_datetime($payment['related_issue_date']); ?><br>
                                                                    Return: <?php echo format_datetime($payment['related_return_date']); ?>
                                                                <?php else : ?>
                                                                    N/A
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else : ?>
                                        <p class="text-center text-muted p-3">No payment transactions found.</p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($total_pages_payments > 1) : ?>
                                    <div class="card-footer">
                                        <?php echo generate_pagination($base_pagination_url, 'page_payments', $page_payments, $total_pages_payments); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php else : ?>
                            <div class="alert alert-danger">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        // --- Issued Books Sorting ---
                        const issuedBooksList = document.getElementById('issued-books-list');
                        const issuedBooksSort = document.getElementById('issuedBooksSort');

                        if (issuedBooksList && issuedBooksSort) {
                            issuedBooksSort.addEventListener('change', () => {
                                const books = Array.from(issuedBooksList.querySelectorAll('li'));
                                const selectedSort = issuedBooksSort.value;

                                books.sort((a, b) => {
                                    const titleA = a.getAttribute('data-book-title');
                                    const titleB = b.getAttribute('data-book-title');
                                    const dateA = new Date(a.getAttribute('data-issue-date'));
                                    const dateB = new Date(b.getAttribute('data-issue-date'));
                                    const statusA = a.getAttribute('data-issue-status');
                                    const statusB = b.getAttribute('data-issue-status');

                                    if (selectedSort === 'title_asc') {
                                        return titleA.localeCompare(titleB);
                                    } else if (selectedSort === 'title_desc') {
                                        return titleB.localeCompare(titleA);
                                    } else if (selectedSort === 'status') {
                                        return statusA.localeCompare(statusB);
                                    } else { // Default: Newest First
                                        return dateB - dateA;
                                    }
                                });

                                // Clear and re-append sorted items
                                issuedBooksList.innerHTML = '';
                                books.forEach(book => issuedBooksList.appendChild(book));
                            });
                        }


                        // --- Penalties Sorting ---
                        const penaltiesList = document.getElementById('penalties-list');
                        const penaltiesSort = document.getElementById('penaltiesSort');

                        if (penaltiesList && penaltiesSort) {
                            penaltiesSort.addEventListener('change', () => {
                                const penalties = Array.from(penaltiesList.querySelectorAll('li'));
                                const selectedSort = penaltiesSort.value;

                                penalties.sort((a, b) => {
                                    const dateA = new Date(a.getAttribute('data-penalty-date'));
                                    const dateB = new Date(b.getAttribute('data-penalty-date'));
                                    const amountA = parseFloat(a.getAttribute('data-penalty-amount'));
                                    const amountB = parseFloat(b.getAttribute('data-penalty-amount'));
                                    const statusA = a.getAttribute('data-penalty-status');
                                    const statusB = b.getAttribute('data-penalty-status');


                                    if (selectedSort === 'amount_asc') {
                                        return amountA - amountB;
                                    } else if (selectedSort === 'amount_desc') {
                                        return amountB - amountA;
                                    } else if (selectedSort === 'status') {
                                        return statusA.localeCompare(statusB);
                                    } else { // Default: Newest First
                                        return dateB - dateA;
                                    }
                                });

                                // Clear and re-append sorted items
                                penaltiesList.innerHTML = '';
                                penalties.forEach(penalty => penaltiesList.appendChild(penalty));
                            });
                        }

                        // --- Fines Sorting ---
                        const finesList = document.getElementById('fines-list');
                        const finesSort = document.getElementById('finesSort');

                        if (finesList && finesSort) {
                            finesSort.addEventListener('change', () => {
                                const fines = Array.from(finesList.querySelectorAll('li'));
                                const selectedSort = finesSort.value;

                                fines.sort((a, b) => {
                                    const dateA = new Date(a.getAttribute('data-fine-date'));
                                    const dateB = new Date(b.getAttribute('data-fine-date'));
                                    const amountA = parseFloat(a.getAttribute('data-fine-amount'));
                                    const amountB = parseFloat(b.getAttribute('data-fine-amount'));
                                    const statusA = a.getAttribute('data-fine-status');
                                    const statusB = b.getAttribute('data-fine-status');

                                    if (selectedSort === 'amount_asc') {
                                        return amountA - amountB;
                                    } else if (selectedSort === 'amount_desc') {
                                        return amountB - amountA;
                                    } else if (selectedSort === 'status') {
                                        return statusA.localeCompare(statusB);
                                    } else { // Default: Newest First
                                        return dateB - dateA;
                                    }
                                });

                                // Clear and re-append sorted items
                                finesList.innerHTML = '';
                                fines.forEach(fine => finesList.appendChild(fine));
                            });
                        }
                    });
                </script>
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