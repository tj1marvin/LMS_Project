<?php
session_start();

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Check if the admin is logged in
if (isset($_SESSION['admin_id'])) {
  $admin_id = $_SESSION['admin_id'];
} else {
  echo "No admin is logged in. Redirecting to login page...";
  header("Location: login.php");
  exit();
}

// Initialize search and sort variables
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sort_column = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'id'; // Default sort column
$sort_order = isset($_GET['order']) ? mysqli_real_escape_string($conn, $_GET['order']) : 'asc'; // Default sort order

// Validate sort column to prevent SQL injection (whitelist approach)
$allowed_columns = ['id', 'title', 'author', 'isbn', 'genre', 'available_copies', 'year'];
if (!in_array($sort_column, $allowed_columns)) {
  $sort_column = 'id'; // Default back to id if invalid
}

// Validate sort order (asc or desc)
$sort_order = strtolower($sort_order) == 'desc' ? 'desc' : 'asc'; // Default to asc if invalid


// Base SQL query
$sql = "SELECT id, title, author, isbn, genre, available_copies, image_path, file_path, year FROM books WHERE 1=1";

// Search condition
if (!empty($search_term)) {
  $sql .= " AND (title LIKE '%{$search_term}%' OR author LIKE '%{$search_term}%' OR isbn LIKE '%{$search_term}%' OR genre LIKE '%{$search_term}%')";
}

// Sorting
$sql .= " ORDER BY {$sort_column} {$sort_order}";

$result = $conn->query($sql);

// Check if the query was successful
if ($result === false) {
  die("Error executing query: " . $conn->error);
}


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
// --- Fetch Unread Message Count ---
$unreadMessagesCount = getTotalCount($conn, 'messages', "received_by = ? AND seen = 0", [$admin_id]); // Prepared statement


// --- Fetch Recent Messages ---
$recentMessages = getRecentAdminMessages($conn, $admin_id);

// --- Fetch Recent Notifications ---
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
            <li class="nav-item">
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
            <li class="nav-item active">
              <a data-bs-toggle="collapse" href="#books">
                <i class="fas fa-book"></i>
                <p>Books</p>
                <span class="caret"></span>
              </a>
              <div class="collapse show" id="books">
                <ul class="nav nav-collapse">
                  <li>
                    <a href="add-book.html">
                      <span class="sub-item">Add Book</span>
                    </a>
                  </li>
                  <li class="active">
                    <a href="view-books.php">
                      <span class="sub-item e">View Books</span>
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
    <!-- End Sidebar  here maun-->

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
          <div class="page-header">
            <h3 class="fw-bold mb-3">View Books</h3>
            <ul class="breadcrumbs mb-3">
              <li class="nav-home">
                <a href="index.php">
                  <i class="icon-home"></i>
                </a>
              </li>
              <li class="separator">
                <i class="icon-arrow-right"></i>
              </li>
              <li class="nav-item">
                <a href="add-book.html">Add Books</a>
              </li>
              <li class="separator">
                <i class="icon-arrow-right"></i>
              </li>
              <li class="nav-item">
                <a href="view-books.php">View Books</a>
              </li>
            </ul>
          </div>
          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-header">
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="card-title">List of Books</div>
                    <div class="search-box">
                      <form method="GET" action="">
                        <div class="input-group">
                          <input type="text" class="form-control" placeholder="Search Books" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                          <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">
                              <i class="fas fa-search"></i>
                            </button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-striped">
                      <thead>
                        <tr>
                          <th>Image</th>
                          <th>
                            <a href="?sort=title&order=<?php echo ($sort_column == 'title' && $sort_order == 'asc') ? 'desc' : 'asc'; ?>">
                              Title <?php if ($sort_column == 'title') echo ($sort_order == 'asc' ? '&#9650;' : '&#9660;'); ?>
                            </a>
                          </th>
                          <th>
                            <a href="?sort=author&order=<?php echo ($sort_column == 'author' && $sort_order == 'asc') ? 'desc' : 'asc'; ?>">
                              Author <?php if ($sort_column == 'author') echo ($sort_order == 'asc' ? '&#9650;' : '&#9660;'); ?>
                            </a>
                          </th>
                          <th>
                            <a href="?sort=isbn&order=<?php echo ($sort_column == 'isbn' && $sort_order == 'asc') ? 'desc' : 'asc'; ?>">
                              ISBN <?php if ($sort_column == 'isbn') echo ($sort_order == 'asc' ? '&#9650;' : '&#9660;'); ?>
                            </a>
                          </th>
                          <th>
                            <a href="?sort=genre&order=<?php echo ($sort_column == 'genre' && $sort_order == 'asc') ? 'desc' : 'asc'; ?>">
                              Genre <?php if ($sort_column == 'genre') echo ($sort_order == 'asc' ? '&#9650;' : '&#9660;'); ?>
                            </a>
                          </th>
                          <th>
                            <a href="?sort=available_copies&order=<?php echo ($sort_column == 'available_copies' && $sort_order == 'asc') ? 'desc' : 'asc'; ?>">
                              Copy Availables <?php if ($sort_column == 'available_copies') echo ($sort_order == 'asc' ? '&#9650;' : '&#9660;'); ?>
                            </a>
                          </th>
                          <th>File</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        // Check if there are results
                        if ($result->num_rows > 0) {
                          // Output data for each row
                          while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><img src='" . htmlspecialchars($row['image_path']) . "' alt='" . htmlspecialchars($row['title']) . "' style='width: 50px; height: auto;'></td>";
                            echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['author']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['isbn']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['genre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['available_copies']) . "</td>";
                            echo "<td><a href='" . htmlspecialchars($row['file_path']) . "' class='btn btn-link'><i class='fas fa-eye'></i> View</a></td>";
                            echo "<td>
                                                <a href='edit-book.php?bookId=" . htmlspecialchars($row['id']) . "&title=" . urlencode($row['title']) . "&author=" . urlencode($row['author']) . "&isbn=" . urlencode($row['isbn']) . "&genre=" . urlencode($row['genre']) . "&year=" . urlencode($row['year']) . "&file_path=" . urlencode($row['file_path']) . "' class='btn btn-warning btn-sm'>Edit</a>
                                                <button class='btn btn-danger btn-sm delete-book' data-id='" . htmlspecialchars($row['id']) . "'>Delete</button>
                                            </td>";
                            echo "</tr>";
                          }
                        } else {
                          echo "<tr><td colspan='8'>No books found.</td></tr>";
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
      </div>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js"></script>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css">
      <script>
        document.querySelectorAll('.delete-book').forEach(button => {
          button.addEventListener('click', function() {
            const bookId = this.getAttribute('data-id');
            swal({
              title: "Are you sure?",
              text: "You won't be able to revert this!",
              type: "warning",
              buttons: {
                confirm: {
                  text: "Yes, delete it!",
                  className: "btn btn-success",
                },
                cancel: {
                  visible: true,
                  className: "btn btn-danger",
                },
              },
            }).then((willDelete) => {
              if (willDelete) {
                // Redirect to the delete book page
                window.location.href = 'delete-book.php?bookId=' + bookId;
              } else {
                swal.close();
              }
            });
          });
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