<?php
session_start();

/**
 * Configuration File Inclusion and Database Connection Function (as in your code)
 */
require_once 'db_connection.php';
// Establish database connection
$conn = connect_db();
if (!$conn) {
    die("Oops! Database connection failed. Please check server logs for details.");
}

// Check if admin is logged in (as in your code)
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// --- Functions for Recent Messages and Notifications (as in your code) ---
function getRecentAdminMessages($conn, $admin_id, $limit = 5) {
    $sqlMessages = "SELECT
                        m.message_id,
                        m.message_text AS message_content,
                        m.sent_at AS timestamp,
                        s.student_id AS sender_id,
                        CONCAT(s.first_name, ' ', s.last_name) AS sender_name,
                        s.student_img AS sender_profile_image
                    FROM messages m
                    LEFT JOIN students_registration s ON m.sent_by = s.student_id
                    WHERE m.received_by = ?
                    ORDER BY m.sent_at DESC
                    LIMIT ?";

    $stmt = $conn->prepare($sqlMessages);
    if (!$stmt) {
        error_log("getRecentAdminMessages Prepare Error: " . $conn->error);
        return [];
    }

    $stmt->bind_param("ii", $admin_id, $limit);
    if (!$stmt->execute()) {
        error_log("getRecentAdminMessages Execute Error: " . $stmt->error);
        return [];
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

function getRecentAdminNotifications($conn, $admin_id, $limit = 4) {
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
        error_log("getRecentAdminNotifications Prepare Error: " . $conn->error);
        return [];
    }

    $stmt->bind_param("ii", $admin_id, $limit);
    if (!$stmt->execute()) {
        error_log("getRecentAdminNotifications Execute Error: " . $stmt->error);
        return [];
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

function connect_db() {
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        return null;
    }
    return $conn;
}

/**
 * Function to Get Total Book Count
 * @param mysqli $conn Database connection object.
 * @return int Total book count, or 0 on error.
 */
function getTotalBooksCount($conn) {
    $sql = "SELECT COUNT(*) AS total_books FROM books";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['total_books'];
    }
    return 0;
}

/**
 * Function to Get Total Student Count
 * @param mysqli $conn Database connection object.
 * @return int Total student count, or 0 on error.
 */
function getTotalStudentsCount($conn) {
    $sql = "SELECT COUNT(*) AS total_students FROM students_registration";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['total_students'];
    }
    return 0;
}

/**
 * Get Total Count Function
 * Executes a SQL query to count total records in a given table.
 * @param mysqli $conn Database connection object.
 * @param string $tableName Name of the table to query.
 * @param string $condition Optional WHERE clause condition.
 * @return int Total count of records, or 0 on error.
 */
function getTotalCount($conn, $tableName, $condition = '') {
    $sql = "SELECT COUNT(*) as total FROM " . $tableName;
    if ($condition) {
        $sql .= " WHERE " . $condition;
    }
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

/// Function to get total payments received
function getTotalPayments($conn) {
    $sql = "SELECT SUM(amount) AS total_payments FROM payments";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (float)$row['total_payments'];
    }
    return 0;
}

// Function to get payments collected this month
function getMonthlyPayments($conn) {
    $sql = "SELECT SUM(amount) AS monthly_payments FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (float)$row['monthly_payments'];
    }
    return 0;
}

// Function to get payments by method
function getPaymentsByMethod($conn) {
    $sql = "SELECT payment_method, SUM(amount) AS total_amount FROM payments GROUP BY payment_method";
    $result = $conn->query($sql);
    return $result;
}

// --- NEW FUNCTIONS FOR TRANSACTION HISTORY, SEARCH, AND SORTING ---

/**
 * Function to get all transactions with search and sort functionality.
 * @param mysqli $conn Database connection.
 * @param string $searchKeyword Search term.
 * @param string $sortColumn Column to sort by.
 * @param string $sortOrder Sort order (ASC or DESC).
 * @return mysqli_result|false Result set or false on error.
 */
function getAllTransactions($conn, $searchKeyword = '', $sortColumn = 'payment_date', $sortOrder = 'DESC') {
    $sql = "SELECT
                p.payment_id,
                p.amount,
                p.payment_date,
                p.payment_method,
                s.student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name
            FROM payments p
            JOIN students_registration s ON p.student_id = s.student_id
            WHERE 1=1 "; // 1=1 is always true, allows for easy appending of WHERE clauses

    if (!empty($searchKeyword)) {
        $searchKeyword = $conn->real_escape_string($searchKeyword);
        $sql .= " AND (
                    p.payment_id LIKE '%{$searchKeyword}%' OR
                    s.first_name LIKE '%{$searchKeyword}%' OR
                    s.last_name LIKE '%{$searchKeyword}%' OR
                    CONCAT(s.first_name, ' ', s.last_name) LIKE '%{$searchKeyword}%' OR
                    p.payment_method LIKE '%{$searchKeyword}%'
                 )";
    }

    $sortableColumns = ['payment_id', 'student_name', 'amount', 'payment_date', 'payment_method'];
    if (in_array($sortColumn, $sortableColumns)) {
        $sortOrder = strtoupper($sortOrder) == 'ASC' ? 'ASC' : 'DESC'; // Ensure valid sort order
        $sql .= " ORDER BY {$sortColumn} {$sortOrder}";
    } else {
        $sql .= " ORDER BY payment_date DESC"; // Default sorting
    }

    $result = $conn->query($sql);
    return $result;
}


// --- Handle Search and Sort Parameters ---
$searchKeyword = isset($_GET['search']) ? $_GET['search'] : '';
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'payment_date'; // Default sort by payment_date
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';     // Default descending order

// Fetch financial summary data
$totalPayments = getTotalPayments($conn);
$monthlyPayments = getMonthlyPayments($conn);
$paymentsByMethod = getPaymentsByMethod($conn); // Already fetched above, no need to refetch unless layout changed

// Fetch all transactions with search and sort parameters
$allTransactionsResult = getAllTransactions($conn, $searchKeyword, $sortColumn, $sortOrder);


// --- Fetch Analytics Data (for sidebar/navigation) ---
$totalBooks = getTotalBooksCount($conn);
$totalStudents = getTotalStudentsCount($conn);
$unreadMessagesCount = getTotalCount($conn, 'messages', "received_by = ? AND seen = 0", [$admin_id]);
$recentMessages = getRecentAdminMessages($conn, $admin_id);
$recentNotifications = getRecentAdminNotifications($conn, $admin_id);


// --- Notification Queries ---

// 1. New User Registrations (last 5)
$new_users_sql = "SELECT student_id, first_name, last_name, created_at 
                    FROM students_registration 
                    ORDER BY created_at DESC 
                    LIMIT 5";
$new_users_result = $conn->query($new_users_sql);

// 2. Book Requests (last 5)
$book_requests_sql = "SELECT r.request_id, r.student_id, s.first_name, s.last_name, b.title, r.request_date
                        FROM book_requests r
                        JOIN students_registration s ON r.student_id = s.student_id
                        JOIN books b ON r.book_id = b.id
                        ORDER BY r.request_date DESC
                        LIMIT 5";
$book_requests_result = $conn->query($book_requests_sql);


// 3. Fines (last 5, unpaid)
$fines_sql = "SELECT f.fine_id, f.student_id, s.first_name, s.last_name, f.fine_amount, f.created_at
                FROM fines f
                JOIN students_registration s ON f.student_id = s.student_id
                WHERE f.status = 'unpaid'
                ORDER BY f.created_at DESC
                LIMIT 5";
$fines_result = $conn->query($fines_sql);

// 4.  New Messages (Last 5) - Requires a Messages table.
$messages_sql = "SELECT m.message_id, m.sent_by, s.first_name, s.last_name, m.message_text, m.sent_at
                 FROM messages m
                 JOIN students_registration s ON m.sent_by = s.student_id
                 WHERE m.received_by = $admin_id AND m.seen = 0
                 ORDER BY m.sent_at DESC
                 LIMIT 5";
$messages_result = $conn->query($messages_sql);

// --- End Notification Queries ---

// Fetch admin information
$admin_sql = "SELECT username, email FROM admin_login WHERE id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

if ($admin_result->num_rows > 0) {
  $admin_info = $admin_result->fetch_assoc();
  $admin_username = htmlspecialchars($admin_info['username']);
  $admin_email = htmlspecialchars($admin_info['email']);
} else {
  // Handle case where admin info is not found
  $admin_username = "Admin";
  $admin_email = "admin@example.com"; // Default email
}

$admin_stmt->close();

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
        <!-- End Sidebar  hdsghfgsjjgdsjhfhgfhgfhgsfdhgfjfgjhdsgfjhgfjhgdsjhfjsdhgfjdsgfjhdsgjh here maun-->

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
                            <h1 class="mb-4">Financial Report</h1>
                            
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
                <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown"
                  aria-haspopup="true" aria-expanded="false">
                  <i class="fa fa-bell"></i>
                  <?php
                  // Calculate total unread notifications
                  $total_notifications = 0;
                  if ($new_users_result) $total_notifications += $new_users_result->num_rows;
                  if ($book_requests_result) $total_notifications += $book_requests_result->num_rows;
                  if ($fines_result) $total_notifications += $fines_result->num_rows;
                  if ($messages_result) $total_notifications += $messages_result->num_rows;

                  if ($total_notifications > 0) {
                    echo '<span class="notification">' . $total_notifications . '</span>';
                  }
                  ?>
                </a>
                <ul class="dropdown-menu notif-box animated fadeIn" aria-labelledby="notifDropdown">
                  <li>
                    <div class="dropdown-title">
                      You have <?php echo $total_notifications; ?> new notification<?php echo ($total_notifications != 1) ? 's' : ''; ?>
                    </div>
                  </li>
                  <li>
                    <div class="notif-scroll scrollbar-outer">
                      <div class="notif-center">
                        <?php if ($new_users_result && $new_users_result->num_rows > 0): ?>
                          <?php while ($user_row = $new_users_result->fetch_assoc()): ?>
                            <a href="view-student-details.php?student_id=<?php echo $user_row['student_id']; ?>">
                              <div class="notif-icon notif-primary">
                                <i class="fa fa-user-plus"></i>
                              </div>
                              <div class="notif-content">
                                <span class="block">New user registered: <?php echo htmlspecialchars($user_row['first_name'] . ' ' . $user_row['last_name']); ?></span>
                                <span class="time"><?php echo date('M d, Y h:i A', strtotime($user_row['created_at'])); ?></span>
                              </div>
                            </a>
                          <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($book_requests_result && $book_requests_result->num_rows > 0): ?>
                          <?php while ($request_row = $book_requests_result->fetch_assoc()): ?>
                            <a href="book_request.php">
                              <div class="notif-icon notif-info">
                                <i class="fa fa-book"></i>
                              </div>
                              <div class="notif-content">
                                <span class="block">Book Request: <?php echo htmlspecialchars($request_row['title']); ?> by <?php echo htmlspecialchars($request_row['first_name'] . ' ' . $request_row['last_name']); ?></span>
                                <span class="time"><?php echo date('M d, Y h:i A', strtotime($request_row['request_date'])); ?></span>
                              </div>
                            </a>
                          <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($fines_result && $fines_result->num_rows > 0): ?>
                          <?php while ($fine_row = $fines_result->fetch_assoc()): ?>
                            <a href="view-fines.php">
                              <div class="notif-icon notif-warning">
                                <i class="fa fa-exclamation-triangle"></i>
                              </div>
                              <div class="notif-content">
                                <span class="block">Unpaid Fine: $<?php echo htmlspecialchars(number_format($fine_row['fine_amount'], 2)); ?> by <?php echo htmlspecialchars($fine_row['first_name'] . ' ' . $fine_row['last_name']); ?></span>
                                <span class="time"><?php echo date('M d, Y h:i A', strtotime($fine_row['created_at'])); ?></span>
                              </div>
                            </a>
                          <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($messages_result && $messages_result->num_rows > 0): ?>
                          <?php while ($message_row = $messages_result->fetch_assoc()): ?>
                            <a href="view-message.php">
                              <div class="notif-icon notif-success">
                                <i class="fa fa-envelope"></i>
                              </div>
                              <div class="notif-content">
                                <span class="block">Message from <?php echo htmlspecialchars($message_row['first_name'] . ' ' . $message_row['last_name']); ?></span>
                                <span class="time"><?php echo date('M d, Y h:i A', strtotime($message_row['sent_at'])); ?></span>
                              </div>
                            </a>
                          <?php endwhile; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </li>
                  <li>
                    <a class="see-all" href="view-notifications.php">See all notifications <i class="fa fa-angle-right"></i></a>
                  </li>
                </ul>
              </li>
              <li class="nav-item topbar-icon dropdown hidden-caret">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                  <i class="fas fa-layer-group"></i> <span style="margin-left: 5px;"></span> </a>
                <div class="dropdown-menu quick-actions animated fadeIn">
                  <div class="quick-actions-header">
                    <span class="title mb-1">Library Quick Actions</span>
                    <span class="subtitle op-7">Shortcuts to Library Operations</span>
                  </div>
                  <div class="quick-actions-scroll scrollbar-outer">
                    <div class="quick-actions-items">
                      <div class="row m-0">
                        <div class="col-6 col-md-4 p-0">
                          <a href="view-message.php" class="btn btn-light btn-block shadow-sm rounded p-3 dropdown-item">
                            <i class="fas fa-envelope fa-lg mb-1 d-block text-primary"></i>
                            <span class="d-block fw-bold">View Messages</span>
                          </a>
                        </div>
                        <div class="col-6 col-md-4 p-0">
                          <a href="view-books.php" class="btn btn-light btn-block shadow-sm rounded p-3 dropdown-item">
                            <i class="fas fa-book-open fa-lg mb-1 d-block text-info"></i>
                            <span class="d-block fw-bold">View Books</span>
                          </a>
                        </div>
                        <div class="col-6 col-md-4 p-0">
                          <a href="add-book.html" class="btn btn-light btn-block shadow-sm rounded p-3 dropdown-item">
                            <i class="fas fa-plus-circle fa-lg mb-1 d-block text-success"></i>
                            <span class="d-block fw-bold">Add New Book</span>
                          </a>
                        </div>
                        <div class="col-6 col-md-4 p-0">
                          <a href="issue-book.php" class="btn btn-light btn-block shadow-sm rounded p-3 dropdown-item">
                            <i class="fas fa-book-reader fa-lg mb-1 d-block text-warning"></i>
                            <span class="d-block fw-bold">Issue Book</span>
                          </a>
                        </div>
                        <div class="col-6 col-md-4 p-0">
                          <a href="return-book.php" class="btn btn-light btn-block shadow-sm rounded p-3 dropdown-item">
                            <i class="fas fa-undo fa-lg mb-1 d-block text-danger"></i>
                            <span class="d-block fw-bold">Return Book</span>
                          </a>
                        </div>
                        <div class="col-6 col-md-4 p-0">
                          <a href="view-students.php" class="btn btn-light btn-block shadow-sm rounded p-3 dropdown-item">
                            <i class="fas fa-users fa-lg mb-1 d-block text-primary"></i>
                            <span class="d-block fw-bold">View Students</span>
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </li>

              <li class="nav-item topbar-user dropdown hidden-caret">
                <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                  <div class="avatar-sm">
                    <img src="assets/img/profile.jpg" alt="..." class="avatar-img rounded-circle" />
                  </div>
                  <span class="profile-username">
                    <span class="op-7">Hi,</span>
                    <span class="fw-bold"><?php echo $admin_username; ?></span>
                  </span>
                </a>
                <ul class="dropdown-menu dropdown-user animated fadeIn">
                  <div class="dropdown-user-scroll scrollbar-outer">
                    <li>
                      <div class="user-box">
                        <div class="avatar-lg">
                          <img src="assets/img/profile.jpg" alt="image profile" class="avatar-img rounded" />
                        </div>
                        <div class="u-text">
                          <h4><?php echo $admin_username; ?></h4>
                          <p class="text-muted"><?php echo $admin_email; ?></p>
                          <a href="admin-profile-account.php" class="btn btn-xs btn-secondary btn-sm">View Profile</a>
                        </div>
                      </div>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="admin-profile.php">My Profile</a>
                      <a class="dropdown-item" href="view-message.php">Inbox</a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="admin-profile.php">Account Setting</a>
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

            <div class="content">
                <div class="container-fluid">
                    <div class="report-container">
                        <h1 class="mb-4">Financial Report</h1>
                        <p class="mb-3">This report provides a financial overview of the library, including payment summaries and transaction history.</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card financial-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Total Payments Received</h5>
                                        <div class="card-text">
                                            <h2><?php echo htmlspecialchars(number_format($totalPayments, 2)); ?> USD</h2>
                                            <p class="text-muted"><small>Since the beginning</small></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card financial-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Payments Collected This Month</h5>
                                        <div class="card-text">
                                            <h2><?php echo htmlspecialchars(number_format($monthlyPayments, 2)); ?> USD</h2>
                                            <p class="text-muted"><small>For the current month</small></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card financial-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Payments by Method</h5>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Payment Method</th>
                                                    <th>Total Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($paymentsByMethod && $paymentsByMethod->num_rows > 0) {
                                                    while ($row = $paymentsByMethod->fetch_assoc()) {
                                                        echo "<tr>
                                                                <td>" . htmlspecialchars($row['payment_method']) . "</td>
                                                                <td>" . htmlspecialchars(number_format($row['total_amount'], 2)) . " USD</td>
                                                              </tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='2'>No payments recorded.</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="transaction-table-container">
                            <div class="card financial-card">
                                <div class="card-body">
                                    <h5 class="card-title">Transaction History</h5>

                                    <div class="search-bar">
                                        <form method="GET" action="">
                                            <div class="input-group">
                                                <input type="text" class="form-control" placeholder="Search transactions..." name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                                                <div class="input-group-append">
                                                    <button class="btn btn-primary" type="submit">Search</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th><a href="?sort=payment_id&order=<?php echo ($sortColumn == 'payment_id' && $sortOrder == 'DESC') ? 'ASC' : 'DESC'; ?>&search=<?php echo htmlspecialchars($searchKeyword); ?>" class="sort-link">Payment ID <?php if ($sortColumn == 'payment_id') echo ($sortOrder == 'ASC' ? '<i class="fas fa-sort-up sort-icon"></i>' : '<i class="fas fa-sort-down sort-icon"></i>'); ?></a></th>
                                                    <th><a href="?sort=student_name&order=<?php echo ($sortColumn == 'student_name' && $sortOrder == 'DESC') ? 'ASC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($searchKeyword); ?>" class="sort-link">Student Name <?php if ($sortColumn == 'student_name') echo ($sortOrder == 'ASC' ? '<i class="fas fa-sort-up sort-icon"></i>' : '<i class="fas fa-sort-down sort-icon"></i>'); ?></a></th>
                                                    <th><a href="?sort=amount&order=<?php echo ($sortColumn == 'amount' && $sortOrder == 'DESC') ? 'ASC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($searchKeyword); ?>" class="sort-link">Amount (USD) <?php if ($sortColumn == 'amount') echo ($sortOrder == 'ASC' ? '<i class="fas fa-sort-up sort-icon"></i>' : '<i class="fas fa-sort-down sort-icon"></i>'); ?></a></th>
                                                    <th><a href="?sort=payment_date&order=<?php echo ($sortColumn == 'payment_date' && $sortOrder == 'DESC') ? 'ASC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($searchKeyword); ?>" class="sort-link">Payment Date <?php if ($sortColumn == 'payment_date') echo ($sortOrder == 'ASC' ? '<i class="fas fa-sort-up sort-icon"></i>' : '<i class="fas fa-sort-down sort-icon"></i>'); ?></a></th>
                                                    <th><a href="?sort=payment_method&order=<?php echo ($sortColumn == 'payment_method' && $sortOrder == 'DESC') ? 'ASC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($searchKeyword); ?>" class="sort-link">Payment Method <?php if ($sortColumn == 'payment_method') echo ($sortOrder == 'ASC' ? '<i class="fas fa-sort-up sort-icon"></i>' : '<i class="fas fa-sort-down sort-icon"></i>'); ?></a></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($allTransactionsResult && $allTransactionsResult->num_rows > 0) {
                                                    while ($row = $allTransactionsResult->fetch_assoc()) {
                                                        echo "<tr>
                                                                <td>" . htmlspecialchars($row['payment_id']) . "</td>
                                                                <td>" . htmlspecialchars($row['student_name']) . "</td>
                                                                <td>" . htmlspecialchars(number_format($row['amount'], 2)) . "</td>
                                                                <td>" . htmlspecialchars($row['payment_date']) . "</td>
                                                                <td>" . htmlspecialchars($row['payment_method']) . "</td>
                                                              </tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='5'>No transactions recorded matching your criteria.</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
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