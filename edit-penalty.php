<?php
session_start();

/**
 * Configuration File Inclusion
 * For database credentials and other global settings.
 */
require_once 'db_connection.php';

/**
 * Database Connection Function
 * Establishes a connection to the MySQL database using credentials from config.php.
 * @return mysqli|false Database connection object or false on failure.
 */
function connect_db()
{
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        return false; // Indicate connection failure
    }
    return $conn;
}

/**
 * Get Total Count Function
 * Executes a SQL query to count total records in a given table.
 * @param mysqli $conn Database connection object.
 * @param string $tableName Name of the table to query.
 * @param string $condition Optional WHERE clause condition.
 * @return int Total count of records, or 0 on error.
 */
function getTotalCount($conn, $tableName, $condition = '')
{
    $sql = "SELECT COUNT(*) as total FROM " . $tableName . " " . $condition;
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['total'];
    }
    return 0;
}

/**
 * Get Dashboard Chart Data Function
 * Fetches data for the dashboard line chart from the database.
 * This function is assumed to query monthly data for books issued, returned and penalties.
 * For actual implementation, dashboard.php backend script is required to provide this data in JSON.
 * @param mysqli $conn Database connection object.
 * @return array Associative array containing chart data (months, totalIssued, totalReturned_books, total_penalty), or empty arrays on error.
 */
function getDashboardChartData($conn)
{
    // --- Database Query Logic to fetch chart data ---

    $months = []; // Array to store month labels (e.g., 'Jan', 'Feb')
    $totalIssued = []; // Array to store count of books issued each month
    $totalReturned_books = []; // Array to store count of books returned each month
    $total_penalty = []; // Array to store total penalty amount each month

    // --- 1. Fetch Monthly Books Issued Data ---
    $sqlIssued = "SELECT
                      DATE_FORMAT(issue_date, '%b') as month,
                      COUNT(*) as count
                  FROM issue_book
                  GROUP BY month
                  ORDER BY MIN(issue_date)";

    $resultIssued = $conn->query($sqlIssued);
    $issuedData = []; // Temporary array to hold fetched issued data
    if ($resultIssued && $resultIssued->num_rows > 0) {
        while ($row = $resultIssued->fetch_assoc()) {
            $issuedData[$row['month']] = $row['count'];
        }
    }

    // --- 2. Fetch Monthly Books Returned Data ---
    $sqlReturned = "SELECT
                      DATE_FORMAT(issue_date, '%b') as month,
                      COUNT(*) as count
                  FROM issue_book
                  WHERE status = 'returned'
                  GROUP BY month
                  ORDER BY MIN(issue_date)";

    $resultReturned = $conn->query($sqlReturned);
    $returnedData = []; // Temporary array to hold fetched returned data
    if ($resultReturned && $resultReturned->num_rows > 0) {
        while ($row = $resultReturned->fetch_assoc()) {
            $returnedData[$row['month']] = $row['count'];
        }
    }

    // --- 3. Fetch Monthly Total Penalty Data ---
    $sqlPenalty = "SELECT
                      DATE_FORMAT(ib.issue_date, '%b') as month,
                      SUM(p.penalty_amount) as total_penalty
                  FROM penalties p
                  JOIN issue_book ib ON p.issue_id = ib.issue_id
                  GROUP BY month
                  ORDER BY MIN(ib.issue_date)";

    $resultPenalty = $conn->query($sqlPenalty);
    $penaltyData = []; // Temporary array to hold fetched penalty data
    if ($resultPenalty && $resultPenalty->num_rows > 0) {
        while ($row = $resultPenalty->fetch_assoc()) {
            $penaltyData[$row['month']] = $row['total_penalty'];
        }
    }

    // --- 4.  Standardize Months and Populate Chart Data Arrays ---
    $allMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    foreach ($allMonths as $month) {
        $months[] = $month; // Add month to the months array for labels
        $totalIssued[] = isset($issuedData[$month]) ? (int)$issuedData[$month] : 0; // Get issued count or 0 if no data for month
        $totalReturned_books[] = isset($returnedData[$month]) ? (int)$returnedData[$month] : 0; // Get returned count or 0 if no data
        $total_penalty[] = isset($penaltyData[$month]) ? (float)$penaltyData[$month] : 0; // Get penalty total or 0 if no data
    }


    return [
        'months' => $months,
        'totalIssued' => $totalIssued,
        'totalReturned_books' => $totalReturned_books,
        'total_penalty' => $total_penalty,
    ];
}


// Establish database connection
$conn = connect_db();
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error()); // More user-friendly error message
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id']; // Admin ID from session

// --- Fetch Dashboard Data using functions ---
$totalStudents = getTotalCount($conn, 'students_registration');
$totalBooks = getTotalCount($conn, 'books');
$totalReturnedBooks = getTotalCount($conn, 'issue_book', "WHERE status = 'returned'");
$totalIssuedBooks = getTotalCount($conn, 'issue_book');
$totalCurrentlyBorrowedBooks = getTotalCount($conn, 'issue_book', "WHERE status = 'issued'"); // Added metric
$chartData = getDashboardChartData($conn); // Fetch chart data (PLACEHOLDER - Needs backend `dashboard.php`)

// --- NEW: Fetch Unread Message Count for Admin ---
$unreadMessagesCount = getTotalCount($conn, 'messages', "WHERE received_by = $admin_id AND seen = 0");


function getRecentAdminMessages($conn, $admin_id, $limit = 5)
{
    $sqlMessages = "SELECT
                      m.message_id,
                      m.subject,
                      m.message_content,
                      m.timestamp,
                      s.student_id AS sender_id,
                      s.full_name AS sender_name,
                      s.profile_image AS sender_profile_image
                  FROM messages m
                  LEFT JOIN students_registration s ON m.sent_by_student_id = s.student_id
                  WHERE m.received_by = ?
                  ORDER BY m.timestamp DESC
                  LIMIT ?";

    $stmt = $conn->prepare($sqlMessages);
    if (!$stmt) {
        return []; // Return empty array if prepare fails
    }

    $stmt->bind_param("ii", $admin_id, $limit); // Assuming admin_id and limit are integers
    if (!$stmt->execute()) {
        return []; // Return empty array if execute fails
    }

    $result = $stmt->get_result();
    $messages = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }

    $stmt->close();
    return $messages;
}

// ... (Your existing PHP code: connect_db(), session check, data fetching) ...

// --- NEW: Fetch Unread Message Count for Admin ---
$unreadMessagesCount = getTotalCount($conn, 'messages', "WHERE received_by = $admin_id AND seen = 0");

// --- NEW: Fetch Recent Messages for Admin ---
$recentMessages = getRecentAdminMessages($conn, $admin_id);

function getRecentAdminNotifications($conn, $admin_id, $limit = 4)
{
    $sqlNotifications = "SELECT
                      notification_id,
                      notification_type,
                      notification_content,
                      timestamp
                  FROM admin_notifications
                  WHERE admin_id = ?
                  ORDER BY timestamp DESC
                  LIMIT ?";

    $stmt = $conn->prepare($sqlNotifications);
    if (!$stmt) {
        return []; // Return empty array if prepare fails
    }

    $stmt->bind_param("ii", $admin_id, $limit); // Assuming admin_id and limit are integers
    if (!$stmt->execute()) {
        return []; // Return empty array if execute fails
    }

    $result = $stmt->get_result();
    $notifications = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }

    $stmt->close();
    return $notifications;
}
$unreadMessagesCount = getTotalCount($conn, 'messages', "WHERE received_by = $admin_id AND seen = 0");

// --- NEW: Fetch Recent Messages for Admin (already present) ---
$recentMessages = getRecentAdminMessages($conn, $admin_id);

// --- NEW: Fetch Recent Notifications for Admin ---
$recentNotifications = getRecentAdminNotifications($conn, $admin_id);

$issue_id = isset($_GET['issue_id']) ? intval($_GET['issue_id']) : 0;
$penalty_data = null;
$error_message = null;
$success_message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $penalty_amount = isset($_POST['penalty_amount']) ? filter_var($_POST['penalty_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
    $status = isset($_POST['status']) ? filter_var($_POST['status'], FILTER_SANITIZE_STRING) : 'unpaid'; // Default to unpaid if not set

    if (!is_numeric($penalty_amount)) {
        $error_message = "Invalid penalty amount. Please enter a valid number.";
    } else {
        // Check if penalty already exists for this issue_id
        $check_penalty_sql = "SELECT penalty_id FROM penalties WHERE issue_id = ?";
        $check_stmt = $conn->prepare($check_penalty_sql);
        $check_stmt->bind_param("i", $issue_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update existing penalty
            $update_penalty_sql = "UPDATE penalties SET penalty_amount = ?, status = ? WHERE issue_id = ?";
            $update_stmt = $conn->prepare($update_penalty_sql);
            $update_stmt->bind_param("dsi", $penalty_amount, $status, $issue_id);

            if ($update_stmt->execute()) {
                $success_message = "Penalty updated successfully.";
            } else {
                $error_message = "Error updating penalty: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            // Fetch days_overdue to insert into penalties table
            $days_overdue_sql = "SELECT DATEDIFF(NOW(), return_date) AS days_overdue FROM issue_book WHERE issue_id = ?";
            $days_overdue_stmt = $conn->prepare($days_overdue_sql);
            $days_overdue_stmt->bind_param("i", $issue_id);
            $days_overdue_stmt->execute();
            $days_overdue_result = $days_overdue_stmt->get_result();
            $days_overdue_row = $days_overdue_result->fetch_assoc();
            $days_overdue = $days_overdue_row['days_overdue'];
            $days_overdue_stmt->close();

            // --- Fetch student_id ---
            $student_id_sql = "SELECT student_id FROM issue_book WHERE issue_id = ?";
            $student_id_stmt = $conn->prepare($student_id_sql);
            $student_id_stmt->bind_param("i", $issue_id);
            $student_id_stmt->execute();
            $student_id_result = $student_id_stmt->get_result();
            $student_id_row = $student_id_result->fetch_assoc();
            $student_id_for_penalty = $student_id_row['student_id']; // Assign to new variable
            $student_id_stmt->close();

            // Insert new penalty
            $insert_penalty_sql = "INSERT INTO penalties (issue_id, penalty_amount, days_overdue, status, student_id) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_penalty_sql);
            $insert_stmt->bind_param("idssi", $issue_id, $penalty_amount, $days_overdue, $status, $student_id_for_penalty);

            if ($insert_stmt->execute()) {
                $success_message = "Penalty added successfully.";
            } else {
                $error_message = "Error adding penalty: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $check_stmt->close();

        // After update/insert, re-fetch penalty data to display in the form
        if ($success_message) {
            $penalty_data_sql = "SELECT p.penalty_id, p.penalty_amount, p.status, ib.issue_id, b.title as book_title, s.first_name, s.last_name, DATEDIFF(NOW(), ib.return_date) AS days_overdue
                                FROM penalties p
                                JOIN issue_book ib ON p.issue_id = ib.issue_id
                                JOIN books b ON ib.book_id = b.id
                                JOIN students_registration s ON ib.student_id = s.student_id
                                WHERE p.issue_id = ?";
            $penalty_data_stmt = $conn->prepare($penalty_data_sql);
            $penalty_data_stmt->bind_param("i", $issue_id);
            $penalty_data_stmt->execute();
            $penalty_data_result = $penalty_data_stmt->get_result();
            $penalty_data = $penalty_data_result->fetch_assoc();
            $penalty_data_stmt->close();
        }
    }
} else { // --- CORRECTED ELSE BLOCK (GET REQUEST HANDLING) ---
    if ($issue_id > 0) {
        // Fetch penalty data if available, otherwise fetch issue details for context
        $penalty_data_sql = "SELECT p.penalty_id, p.penalty_amount, p.status, ib.issue_id, b.title as book_title, s.first_name, s.last_name, DATEDIFF(NOW(), ib.return_date) AS days_overdue
                                FROM penalties p
                                RIGHT JOIN issue_book ib ON p.issue_id = ib.issue_id
                                JOIN books b ON ib.book_id = b.id
                                JOIN students_registration s ON ib.student_id = s.student_id
                                WHERE ib.issue_id = ?";
        $penalty_data_stmt = $conn->prepare($penalty_data_sql);
        $penalty_data_stmt->bind_param("i", $issue_id);
        $penalty_data_stmt->execute();
        $penalty_data_result = $penalty_data_stmt->get_result();
        $penalty_data = $penalty_data_result->fetch_assoc();
        $penalty_data_stmt->close();

        if (!$penalty_data) {
            // If no penalty found, fetch issue details to populate form context
            $issue_details_sql = "SELECT ib.issue_id, b.title as book_title, s.first_name, s.last_name, DATEDIFF(NOW(), ib.return_date) AS days_overdue
                                    FROM issue_book ib
                                    JOIN books b ON ib.book_id = b.id
                                    JOIN students_registration s ON ib.student_id = s.student_id
                                    WHERE ib.issue_id = ?";
            $issue_details_stmt = $conn->prepare($issue_details_sql);
            $issue_details_stmt->bind_param("i", $issue_id);
            $issue_details_stmt->execute();
            $issue_details_result = $issue_details_stmt->get_result();
            $penalty_data = $issue_details_result->fetch_assoc();
            $issue_details_stmt->close();
            if (!$penalty_data) {
                $error_message = "Issue ID not found.";
            }
        }
    } else {
        $error_message = "Invalid Issue ID.";
    }
}


// Close the database connection
$conn->close();
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
                                    <?php if ($unreadMessagesCount > 0): ?>
                                        <span class="message-notification-badge"><?php echo htmlspecialchars($unreadMessagesCount); ?></span>
                                    <?php endif; ?>
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
                                                <?php if (empty($recentMessages)): ?>
                                                    <a href="#">
                                                        <div class="notif-content text-center">
                                                            <span class="block">No new messages</span>
                                                        </div>
                                                    </a>
                                                <?php else: ?>
                                                    <?php foreach ($recentMessages as $message): ?>
                                                        <a href="#">
                                                            <div class="notif-img">
                                                                <img
                                                                    src="<?php echo htmlspecialchars($message['sender_profile_image'] ? 'assets/img/' . $message['sender_profile_image'] : 'assets/img/profile.jpg'); ?>"
                                                                    alt="Img Profile"
                                                                    onerror="this.src='assets/img/profile.jpg';" />
                                                            </div>
                                                            <div class="notif-content">
                                                                <span class="subject"><?php echo htmlspecialchars($message['sender_name'] ?: 'Unknown Sender'); ?></span> <span class="block">
                                                                    <?php
                                                                    $short_message = strlen($message['message_content']) > 50 ? substr($message['message_content'], 0, 50) . '...' : $message['message_content'];
                                                                    echo htmlspecialchars($short_message);
                                                                    ?>
                                                                </span>
                                                                <span class="time">
                                                                    <?php
                                                                    // Format timestamp - you might want to use a more user-friendly format
                                                                    echo date('g:i a', strtotime($message['timestamp'])); // Example:  h:i am/pm format
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <a class="see-all" href="view-message.php">See all messages<i class="fa fa-angle-right"></i>
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
                                    <?php
                                    // Count unread notifications (you might want to fetch this count from DB separately for efficiency if you have many notifications)
                                    $unreadNotificationCount = 0;
                                    foreach ($recentNotifications as $notif) {
                                        if (!$notif['seen']) { // Assuming 'seen' column is boolean or 0/1
                                            $unreadNotificationCount++;
                                        }
                                    }
                                    ?>
                                    <?php if ($unreadNotificationCount > 0): ?>
                                        <span class="notification"><?php echo htmlspecialchars($unreadNotificationCount); ?></span>
                                    <?php endif; ?>
                                </a>
                                <ul
                                    class="dropdown-menu notif-box animated fadeIn"
                                    aria-labelledby="notifDropdown">
                                    <li>
                                        <div class="dropdown-title">
                                            You have <?php echo htmlspecialchars(count($recentNotifications)); ?> new notification<?php echo count($recentNotifications) !== 1 ? 's' : ''; ?>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="notif-scroll scrollbar-outer">
                                            <div class="notif-center">
                                                <?php if (empty($recentNotifications)): ?>
                                                    <a href="#">
                                                        <div class="notif-content text-center">
                                                            <span class="block">No new notifications</span>
                                                        </div>
                                                    </a>
                                                <?php else: ?>
                                                    <?php foreach ($recentNotifications as $notification): ?>
                                                        <a href="#">
                                                            <div class="notif-icon notif-primary"> <i class="fa fa-bell"></i> <?php // Example: Default bell icon 
                                                                                                                                ?>
                                                            </div>
                                                            <div class="notif-content">
                                                                <span class="block">
                                                                    <?php echo htmlspecialchars($notification['notification_content']); ?>
                                                                </span>
                                                                <span class="time">
                                                                    <?php
                                                                    echo date('g:i a', strtotime($notification['timestamp']));
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
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
                                            <a class="dropdown-item" href="logout.php">Logout</a>
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
                        <h3 class="fw-bold mb-3">Edit Penalty</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="index.php">
                                    <i class="icon-home"></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"> <i class="fas fa-angle-right"></i></i>
                            </li>
                            <li class="nav-item">
                                <a href="view-penalty.php">Overdue Books & Penalties</a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"> <i class="fas fa-angle-right"></i></i>
                            </li>
                            <li class="nav-item active">
                                <a href="edit-penalty.php">Edit Penalty</a>
                            </li>
                        </ul>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">
                                        <?php if ($penalty_data && isset($penalty_data['penalty_id'])): ?>
                                            Edit Penalty for Issue #<?php echo htmlspecialchars($penalty_data['issue_id']); ?>
                                        <?php else: ?>
                                            Add Penalty for Issue #<?php echo htmlspecialchars($issue_id); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if ($error_message): ?>
                                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                    <?php endif; ?>
                                    <?php if ($success_message): ?>
                                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                                    <?php endif; ?>
                                    <?php if ($penalty_data): ?>
                                        <form method="POST">
                                            <div class="form-group">
                                                <label for="book_title">Book Title</label>
                                                <input type="text" class="form-control" id="book_title" value="<?php echo htmlspecialchars($penalty_data['book_title'] ?? 'N/A'); ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label for="issued_to">Issued To</label>
                                                <input type="text" class="form-control" id="issued_to" value="<?php echo htmlspecialchars($penalty_data['first_name'] ?? 'N/A') . ' ' . htmlspecialchars($penalty_data['last_name'] ?? ''); ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label for="days_overdue">Days Overdue</label>
                                                <input type="text" class="form-control" id="days_overdue" value="<?php echo htmlspecialchars($penalty_data['days_overdue'] ?? 'N/A'); ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label for="penalty_amount">Penalty Amount</label>
                                                <input type="number" class="form-control" id="penalty_amount" name="penalty_amount" value="<?php echo htmlspecialchars($penalty_data['penalty_amount'] ?? ''); ?>" step="0.01" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="status">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="unpaid" <?php echo (isset($penalty_data['status']) && $penalty_data['status'] == 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                                    <option value="paid" <?php echo (isset($penalty_data['status']) && $penalty_data['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Save Penalty</button>
                                            <a href="view-penalty.php" class="btn btn-secondary ml-2">Cancel</a>
                                        </form>
                                    <?php elseif (!$error_message): ?>
                                        <p>No data found for Issue ID: <?php echo htmlspecialchars($issue_id); ?></p>
                                        <a href="view-penalty.php" class="btn btn-secondary">Back to Overdue Books</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
            <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


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