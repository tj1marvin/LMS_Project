<?php
session_start();

/**
 * Configuration File Inclusion
 * For database credentials and other global settings.
 */
require_once 'db_connection.php';

/**
 * Database Connection Function
 * Establishes a connection to the MySQL database using credentials from db_connection.php.
 * Returns a database connection object or NULL on failure, logs error.
 * @return mysqli|null Database connection object or null on failure.
 */
function connect_db()
{
  global $servername, $username, $password, $dbname;
  $conn = new mysqli($servername, $username, $password, $dbname);
  if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error); // Log detailed error
    return null; // Return null to indicate failure, handle error upstream
  }
  return $conn;
}

/**
 * Get Total Count Function (SECURE - Prepared Statement)
 * Executes a SQL query to count total records in a given table, using prepared statement.
 * @param mysqli $conn Database connection object.
 * @param string $tableName Name of the table to query.
 * @param string $condition Optional WHERE clause condition (should be parameterized for dynamic conditions).
 * @param array $params Parameter array for prepared statement if using dynamic condition.
 * @return int Total count of records, or 0 on error.
 */




function getTotalCount($conn, $tableName, $condition = '', $params = [])
{
  $sql = "SELECT COUNT(*) as total FROM " . $tableName;
  if ($condition) {
    $sql .= " WHERE " . $condition;
  }

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    error_log("getTotalCount SQL Prepare Error: " . $conn->error); // Log prepare error
    return 0; // Handle prepare error
  }

  if ($params) {
    $types = str_repeat('s', count($params)); // Assuming string parameters, adjust if needed
    $stmt->bind_param($types, ...$params);
  }

  if (!$stmt->execute()) {
    error_log("getTotalCount SQL Execute Error: " . $stmt->error); // Log execute error
    return 0; // Handle execute error
  }

  $result = $stmt->get_result();
  if ($result && $row = $result->fetch_assoc()) {
    $stmt->close();
    return $row['total'];
  }

  $stmt->close();
  return 0;
}

/**
 * Get Total Sum Function (SECURE - Prepared Statement)
 * Executes a SQL query to sum values in a given column of a table, using prepared statement.
 * @param mysqli $conn Database connection object.
 * @param string $tableName Name of the table to query.
 * @param string $sumColumn Name of the column to sum.
 * @param string $condition Optional WHERE clause condition (should be parameterized for dynamic conditions).
 * @param array $params Parameter array for prepared statement if using dynamic condition.
 * @return float|int Total sum of values, or 0 on error.
 */
function getTotalSum($conn, $tableName, $sumColumn, $condition = '', $params = [])
{
  $sql = "SELECT SUM(" . $sumColumn . ") as total_sum FROM " . $tableName;
  if ($condition) {
    $sql .= " WHERE " . $condition;
  }

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    error_log("getTotalSum SQL Prepare Error: " . $conn->error);
    return false; // Indicate prepare error
  }

  if ($params) {
    $types = str_repeat('s', count($params)); // Assuming string parameters, adjust if needed
    $stmt->bind_param($types, ...$params);
  }

  if (!$stmt->execute()) {
    error_log("getTotalSum SQL Execute Error: " . $stmt->error);
    return false; // Indicate execute error
  }

  $result = $stmt->get_result();
  if ($result && $row = $result->fetch_assoc()) {
    $stmt->close();
    return $row['total_sum'] ?? 0; // Return sum or 0 if NULL
  }

  $stmt->close();
  return 0; // Return 0 if no result or error
}


/**
 * Get Dashboard Chart Data Function
 * Fetches data for the dashboard line chart from the database.
 * Returns chart data arrays or empty arrays on query failure.
 * @param mysqli $conn Database connection object.
 * @return array Associative array containing chart data, or empty arrays on error.
 */
function getDashboardChartData($conn)
{
  $months = [];
  $totalIssued = [];
  $totalReturned_books = [];
  $total_penalty = [];
  $totalBookRequests = [];
  $newStudentRegistrations = [];

  // Helper function to execute queries and handle errors
  $executeQuery = function ($sql) use ($conn) {
    $result = $conn->query($sql);
    if (!$result) {
      error_log("Chart Data Query Error: " . $conn->error . " SQL: " . $sql);
      return false; // Indicate query failure
    }
    return $result;
  };

  // 1. Fetch Monthly Books Issued Data
  $sqlIssued = "SELECT DATE_FORMAT(issue_date, '%b') as month, COUNT(*) as count FROM issue_book GROUP BY month ORDER BY MIN(issue_date)";
  $resultIssued = $executeQuery($sqlIssued);
  $issuedData = [];
  if ($resultIssued && $resultIssued->num_rows > 0) {
    while ($row = $resultIssued->fetch_assoc()) {
      $issuedData[$row['month']] = $row['count'];
    }
  }

  // 2. Fetch Monthly Books Returned Data
  $sqlReturned = "SELECT DATE_FORMAT(issue_date, '%b') as month, COUNT(*) as count FROM issue_book WHERE status = 'returned' GROUP BY month ORDER BY MIN(issue_date)";
  $resultReturned = $executeQuery($sqlReturned);
  $returnedData = [];
  if ($resultReturned && $resultReturned->num_rows > 0) {
    while ($row = $resultReturned->fetch_assoc()) {
      $returnedData[$row['month']] = $row['count'];
    }
  }

  // 3. Fetch Monthly Total Penalty Data
  $sqlPenalty = "SELECT DATE_FORMAT(ib.issue_date, '%b') as month, SUM(p.penalty_amount) as total_penalty FROM penalties p JOIN issue_book ib ON p.issue_id = ib.issue_id GROUP BY month ORDER BY MIN(ib.issue_date)";
  $resultPenalty = $executeQuery($sqlPenalty);
  $penaltyData = [];
  if ($resultPenalty && $resultPenalty->num_rows > 0) {
    while ($row = $resultPenalty->fetch_assoc()) {
      $penaltyData[$row['month']] = $row['total_penalty'];
    }
  }
  // 4. Fetch Monthly Book Requests Data
  $sqlRequests = "SELECT DATE_FORMAT(request_date, '%b') as month, COUNT(*) as count FROM book_requests GROUP BY month ORDER BY MIN(request_date)";
  $resultRequests = $executeQuery($sqlRequests);
  $requestsData = [];
  if ($resultRequests && $resultRequests->num_rows > 0) {
    while ($row = $resultRequests->fetch_assoc()) {
      $requestsData[$row['month']] = $row['count'];
    }
  }

  // 5. Fetch Monthly New Student Registrations Data ---
  $sqlRegistrations = "SELECT DATE_FORMAT(created_at, '%b') as month, COUNT(*) as count FROM students_registration GROUP BY month ORDER BY MIN(created_at)";
  $resultRegistrations = $executeQuery($sqlRegistrations);
  $registrationsData = [];
  if ($resultRegistrations && $resultRegistrations->num_rows > 0) {
    while ($row = $resultRegistrations->fetch_assoc()) {
      $registrationsData[$row['month']] = $row['count'];
    }
  }

  $allMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  foreach ($allMonths as $month) {
    $months[] = $month;
    $totalIssued[] = isset($issuedData[$month]) ? (int)$issuedData[$month] : 0;
    $totalReturned_books[] = isset($returnedData[$month]) ? (int)$returnedData[$month] : 0;
    $total_penalty[] = isset($penaltyData[$month]) ? (float)$penaltyData[$month] : 0;
    // --- ADD THESE TWO LINES TO POPULATE THE ARRAYS ---
    $totalBookRequests[] = isset($requestsData[$month]) ? (int)$requestsData[$month] : 0;
    $newStudentRegistrations[] = isset($registrationsData[$month]) ? (int)$registrationsData[$month] : 0;
  }

  return [
    'months' => $months,
    'totalIssued' => $totalIssued,
    'totalReturned_books' => $totalReturned_books,
    'total_penalty' => $total_penalty,
    'totalBookRequests' => $totalBookRequests,
    'newStudentRegistrations' => $newStudentRegistrations,
  ];
}


/**
 * Get Recent Admin Messages Function (SECURE - Prepared Statement)
 * Fetches recent messages for the admin from students.
 * @param mysqli $conn Database connection object.
 * @param int $admin_id Admin ID.
 * @param int $limit Maximum number of messages to retrieve.
 * @return array Array of recent messages, or empty array on error.
 */
function getRecentAdminMessages($conn, $admin_id, $limit = 5)
{
  $sqlMessages = "SELECT
                        m.message_id,
                        m.message_text AS message_content, /* Corrected alias to match usage in HTML */
                        m.sent_at AS timestamp, /* Corrected alias to match usage in HTML */
                        s.student_id AS sender_id,
                        CONCAT(s.first_name, ' ', s.last_name) AS sender_name, /* Corrected to fetch full_name from DB */
                        s.student_img AS sender_profile_image /* Corrected column name to match DB schema */
                    FROM messages m
                    LEFT JOIN students_registration s ON m.sent_by = s.student_id /* Corrected JOIN column to match DB schema */
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


/**
 * Get Recent Admin Notifications Function (SECURE - Prepared Statement)
 * Fetches recent notifications for the admin.
 * @param mysqli $conn Database connection object.
 * @param int $admin_id Admin ID.
 * @param int $limit Maximum number of notifications to retrieve.
 * @return array Array of recent notifications, or empty array on error.
 */
function getRecentAdminNotifications($conn, $admin_id, $limit = 4)
{
  $sqlNotifications = "SELECT
                        notification_id,
                        notification_type,
                        notification_content,
                        timestamp
                    FROM admin_notifications /* Assuming table name is admin_notifications */
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

// Establish database connection
$conn = connect_db();
if (!$conn) {
  die("Oops! Database connection failed. Please check server logs for details."); // User-friendly error
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit();
}

$admin_id = $_SESSION['admin_id'];

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

// --- Fetch Dashboard Data ---
$totalStudents = getTotalCount($conn, 'students_registration');
$totalBooks = getTotalCount($conn, 'books');
$totalReturnedBooks = getTotalCount($conn, 'issue_book', "status = ?", ['returned']); // Prepared statement for condition
$totalIssuedBooks = getTotalCount($conn, 'issue_book');
$totalCurrentlyBorrowedBooks = getTotalCount($conn, 'issue_book', "status = ?", ['issued']); // Prepared statement for condition
$chartData = getDashboardChartData($conn);

// --- Fetch Unread Message Count ---
$unreadMessagesCount = getTotalCount($conn, 'messages', "received_by = ? AND seen = 0", [$admin_id]); // Prepared statement


// --- Fetch Recent Messages ---
$recentMessages = getRecentAdminMessages($conn, $admin_id);

// --- Fetch Recent Notifications ---
$recentNotifications = getRecentAdminNotifications($conn, $admin_id);

// --- Fetch Pending Book Requests Count ---
$pendingBookRequestsCount = getTotalCount($conn, 'book_requests', "status = 'pending'");



// Database connection will be closed automatically when script ends in this setup.
// In more complex applications with persistent connections, explicit closing is recommended within functions.

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
              <div class="collapse show" id="dashboard">
                <ul class="nav nav-collapse">
                  <li class="active">
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
                  <li>
                    <a href="admin-fine.php">
                      <span class="sub-item">Fines</span>
                    </a>
                  </li>
                  <li>
                    <a href="view-fines.php">
                      <span class="sub-item">View Fines</span>
                    </a>
                  </li>


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
        <div class="logo-header" data-background-color="white">
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
            <div>


              <div>
                <div>

                </div>
                <h3 class="fw-bold mb-3">Library System Report</h3>
                <h6 class="op-7 mb-2">Overview of key library statistics and operations.</h6>
              </div>

              </>
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

    <div class="container">
      <div class="page-inner">


        <div class="row">
          <div class="col-sm-6 col-md-3">
            <div class="card card-stats card-round">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-icon">
                    <div class="icon-big text-center icon-primary bubble-shadow-small">
                      <i class="fas fa-graduation-cap"></i>
                    </div>
                  </div>
                  <div class="col col-stats ms-3 ms-sm-0">
                    <div class="numbers">
                      <p class="card-category">Registered Students</p>
                      <h4 class="card-title"><?php echo htmlspecialchars($totalStudents); ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-md-3">
            <div class="card card-stats card-round">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-icon">
                    <div class="icon-big text-center icon-info bubble-shadow-small">
                      <i class="fas fa-book-open"></i>
                    </div>
                  </div>
                  <div class="col col-stats ms-3 ms-sm-0">
                    <div class="numbers">
                      <p class="card-category">Total Book Count</p>
                      <h4 class="card-title"><?php echo htmlspecialchars($totalBooks); ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-md-3">
            <div class="card card-stats card-round">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-icon">
                    <div class="icon-big text-center icon-success bubble-shadow-small">
                      <i class="fas fa-book-reader"></i>
                    </div>
                  </div>
                  <div class="col col-stats ms-3 ms-sm-0">
                    <div class="numbers">
                      <p class="card-category">Books Issued Total</p>
                      <h4 class="card-title"><?php echo htmlspecialchars($totalIssuedBooks); ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-md-3">
            <div class="card card-stats card-round">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-icon">
                    <div class="icon-big text-center icon-secondary bubble-shadow-small">
                      <i class="fas fa-check-double"></i>
                    </div>
                  </div>
                  <div class="col col-stats ms-3 ms-sm-0">
                    <div class="numbers">
                      <p class="card-category">Books Returned Total</p>
                      <h4 class="card-title"><?php echo htmlspecialchars($totalReturnedBooks); ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card card-stats card-round">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-icon">
                    <div class="icon-big text-center icon-warning bubble-shadow-small">
                      <i class="fas fa-undo"></i>
                    </div>
                  </div>
                  <div class="col col-stats ms-3 ms-sm-0">
                    <div class="numbers">
                      <p class="card-category">Books Currently Borrowed</p>
                      <h4 class="card-title"><?php echo htmlspecialchars($totalCurrentlyBorrowedBooks); ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card card-stats card-round">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-icon">
                    <div class="icon-big text-center icon-danger bubble-shadow-small">
                      <i class="fas fa-exclamation-triangle"></i>
                    </div>
                  </div>
                  <div class="col col-stats ms-3 ms-sm-0">
                    <div class="numbers">
                      <p class="card-category">Total Penalties</p>
                      <h4 class="card-title">$<?php echo htmlspecialchars(number_format(array_sum($chartData['total_penalty']), 2)); ?></h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <div class="card card-stats card-round">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-icon">
                    <div class="icon-big text-center icon-dark bubble-shadow-small">
                      <i class="fas fa-book-open"></i>
                    </div>
                  </div>
                  <div class="col col-stats ms-3 ms-sm-0">
                    <div class="numbers">
                      <p class="card-category">Pending Book Requests</p>
                      <h4 class="card-title"><?php echo htmlspecialchars($pendingBookRequestsCount); ?></h4>
                      </h4>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>


        <div class="row">
          <div class="col-md-8">
            <div class="card card-round">
              <div class="card-header">
                <div class="card-head-row">
                  <div class="card-title">Library Operations Trend (Monthly)</div>
                  <div class="card-tools">
                    <a href="export_chart_data.php" class="btn btn-label-success btn-round btn-sm me-2">
                      <span class="btn-label">
                        <i class="fa fa-file-excel"></i>
                      </span>
                      Export Data
                    </a>
                    <a href="#" class="btn btn-label-info btn-round btn-sm" onclick="printChartArea()">
                      <span class="btn-label">
                        <i class="fa fa-print"></i>
                      </span>
                      Print Chart
                    </a>
                  </div>
                </div>
              </div>
              <div class="card-body" id="print-chart-area">
                <div class="chart-container">
                  <canvas id="lineChart" style="height:300px"></canvas>
                </div>
              </div>
            </div>

          </div>

          <div class="col-md-4">
            <div class="card card-primary">
              <div class="card-header">
                <div class="card-head-row">
                  <div class="card-title">Library Reporting & Analysis</div>
                </div>
              </div>
              <div class="card-body">
                <div class="row row-cols-2 g-3">
                  <div class="col">
                    <a href="analytics-dashboard.php" class="btn btn-light btn-block shadow-sm rounded p-3 text-start"> <i class="fas fa-chart-bar fa-2x mb-2 d-block text-primary"></i> <span class="d-block fw-bold">Analytics Dashboard</span> </a>
                  </div>
                  <div class="col">
                    <a href="book-usage-report.php" class="btn btn-light btn-block shadow-sm rounded p-3 text-start">
                      <i class="fas fa-book-reader fa-2x mb-2 d-block text-info"></i>
                      <span class="d-block fw-bold">Book Usage Report</span>
                    </a>
                  </div>
                  <div class="col">
                    <a href="student-activity-report.php" class="btn btn-light btn-block shadow-sm rounded p-3 text-start">
                      <i class="fas fa-user-graduate fa-2x mb-2 d-block text-success"></i>
                      <span class="d-block fw-bold">Student Activity</span>
                    </a>
                  </div>
                  <div class="col">
                    <a href="financial-report.php" class="btn btn-light btn-block shadow-sm rounded p-3 text-start">
                      <i class="fas fa-money-bill-wave fa-2x mb-2 d-block text-warning"></i>
                      <span class="d-block fw-bold">Financial Overview</span>
                    </a>
                  </div>
                  <div class="col">
                    <a href="export_chart_data.php" class="btn btn-light btn-block shadow-sm rounded p-3 text-start"> <i class="fas fa-file-excel fa-2x mb-2 d-block text-danger"></i> <span class="d-block fw-bold">Export Data</span> </a>
                  </div>
                  <div class="col">
                    <a href="system-logs.php" class="btn btn-light btn-block shadow-sm rounded p-3 text-start">
                      <i class="fas fa-clipboard-list fa-2x mb-2 d-block text-primary"></i>
                      <span class="d-block fw-bold">System Logs</span>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      const ctx = document.getElementById('lineChart').getContext('2d');

      // Data from PHP
      let months = <?php echo json_encode($chartData['months']); ?>;
      let totalIssued = <?php echo json_encode($chartData['totalIssued']); ?>;
      let totalReturned = <?php echo json_encode($chartData['totalReturned_books']); ?>;
      let penalty = <?php echo json_encode($chartData['total_penalty']); ?>;
      let bookRequests = <?php echo json_encode($chartData['totalBookRequests']); ?>; // NEW: Get book requests data
      let newRegistrations = <?php echo json_encode($chartData['newStudentRegistrations']); ?>; // NEW: Get student registrations data


      const lineChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: months,
          datasets: [{
              label: 'Books Issued',
              data: totalIssued,
              borderColor: 'rgba(75, 192, 192, 1)',
              backgroundColor: 'rgba(75, 192, 192, 0.2)',
              borderWidth: 2,
              fill: true
            },
            {
              label: 'Books Returned',
              data: totalReturned,
              borderColor: 'rgba(0, 180, 0, 1)',
              backgroundColor: 'rgba(0, 180, 0, 0.2)',
              borderWidth: 2,
              fill: true
            },
            {
              label: 'Penalty Amount',
              data: penalty,
              borderColor: 'rgba(220, 53, 69, 1)',
              backgroundColor: 'rgba(220, 53, 69, 0.2)',
              borderWidth: 2,
              fill: true,
              yAxisID: 'y1'
            },
            {
              label: 'Book Requests', // NEW Dataset for Book Requests
              data: bookRequests,
              borderColor: 'rgba(255, 193, 7, 1)', // Bootstrap warning color (amber/gold)
              backgroundColor: 'rgba(255, 193, 7, 0.2)',
              borderWidth: 2,
              fill: true
            },
            {
              label: 'New Registrations', // NEW Dataset for New Student Registrations
              data: newRegistrations,
              borderColor: 'rgba(0, 123, 255, 1)', // Bootstrap primary color (blue)
              backgroundColor: 'rgba(0, 123, 255, 0.2)',
              borderWidth: 2,
              fill: true
            }
          ]
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true,
              title: {
                display: true,
                text: 'Number of Books / Students / Requests'
              }
            },
            y1: {
              type: 'linear',
              position: 'right',
              beginAtZero: true,
              title: {
                display: true,
                text: 'Penalty Amount'
              },
              grid: {
                drawOnChartArea: false,
              },
              ticks: {
                callback: function(value, index, values) {
                  return '$' + value;
                }
              }
            }
          },
          plugins: {
            tooltip: {
              mode: 'index',
              intersect: false
            }
          }
        }
      });


      function printChartArea() {
        window.print();
      }
    </script>

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