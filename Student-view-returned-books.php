PHP

<?php
// Start the session at the beginning of the script
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

// Check if the student_id is set in the session
if (!isset($_SESSION['student_id'])) {
  header("Location: Student-login.php");
  exit();
}

// Fetch the student ID from the session
$student_id = $_SESSION['student_id'];

// Pagination parameters
$limit = 10; // Number of results per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Sorting functionality
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'ib.return_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Sanitize sort column to prevent SQL injection
$allowed_sort_columns = ['b.title', 'b.author', 'ib.return_date'];
if (!in_array($sort_column, $allowed_sort_columns)) {
  $sort_column = 'ib.return_date'; // Default sort column
}

// Toggle sort order if the same column is clicked
if (isset($_GET['sort']) && $_GET['sort'] === $sort_column) {
  $sort_order = ($sort_order === 'ASC') ? 'DESC' : 'ASC';
}

// Fetch returned books for the logged-in student with search, sorting, and pagination
$returned_books_sql = "
    SELECT b.title AS book_title, b.author, ib.return_date
    FROM issue_book ib
    JOIN books b ON ib.book_id = b.id
    WHERE ib.student_id = ? AND ib.status = 'returned'
    AND (b.title LIKE ? OR b.author LIKE ?)
    ORDER BY $sort_column $sort_order
    LIMIT ? OFFSET ?";

$returned_books_stmt = $conn->prepare($returned_books_sql);
if ($returned_books_stmt === false) {
  die("Error preparing statement: " . htmlspecialchars($conn->error));
}

$search_param = '%' . $search . '%';
$returned_books_stmt->bind_param("issii", $student_id, $search_param, $search_param, $limit, $offset);
$returned_books_stmt->execute();
$returned_books_result = $returned_books_stmt->get_result();
$returned_books = $returned_books_result->fetch_all(MYSQLI_ASSOC);

// Count total records for pagination
$count_sql = "
    SELECT COUNT(*) as total
    FROM issue_book ib
    JOIN books b ON ib.book_id = b.id
    WHERE ib.student_id = ? AND ib.status = 'returned'
    AND (b.title LIKE ? OR b.author LIKE ?)";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("iss", $student_id, $search_param, $search_param);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_books = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_books / $limit);

// Close the statements and connection
$returned_books_stmt->close();
$count_stmt->close();


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
            <div class="collapse " id="borrowing">
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
          <li class="nav-item active">
            <a data-bs-toggle="collapse" href="#returns">
              <i class="fas fa-clipboard-check"></i>
              <p>Returns</p>
              <span class="caret"></span>
            </a>
            <div class="collapse show" id="returns">
              <ul class="nav nav-collapse">
                <li class="active">
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
                <li>
                    <a href="Student-view-fine.php">
                      <span class="sub-item">View Fines</span>
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
          <li class="nav-item">
            <a data-bs-toggle="collapse" href="#profile">
              <i class="fas fa-user"></i>
              <p>Profile</p>
              <span class="caret"></span>
            </a>
            <div class="collapse" id="profile">
              <ul class="nav nav-collapse">
                <li>
                  <a href="Student-view-profile.php">
                    <span class="sub-item">View Profile</span>
                  </a>
                </li>
                <li>
                  <a href="Student-edit-profile.php">
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
                    <input
                      type="text"
                      placeholder="Search ..."
                      class="form-control" />
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
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
          <div>
            <h3 class="fw-bold mb-3">My Returned Books</h3>
            <h6 class="op-7 mb-2">Overview of Your Returned Books</h6>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <div class="card card-round">
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>
                          <a href="?page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&sort=b.title&order=<?php echo ($sort_column === 'b.title' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                            Book Title <?php if ($sort_column === 'b.title') echo ($sort_order === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'; ?>
                          </a>
                        </th>
                        <th>
                          <a href="?page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&sort=b.author&order=<?php echo ($sort_column === 'b.author' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                            Author <?php if ($sort_column === 'b.author') echo ($sort_order === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'; ?>
                          </a>
                        </th>
                        <th>
                          <a href="?page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&sort=ib.return_date&order=<?php echo ($sort_column === 'ib.return_date' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>">
                            Return Date <?php if ($sort_column === 'ib.return_date') echo ($sort_order === 'ASC') ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>'; ?>
                          </a>
                        </th>
                      </tr>
                      <tr>

                      </tr>
                    </thead>
                    <tbody>
                      <?php if (count($returned_books) > 0): ?>
                        <?php foreach ($returned_books as $book): ?>
                          <tr>
                            <td><?php echo htmlspecialchars($book['book_title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['return_date']); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="3" class="text-center">No returned books found.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <nav aria-label="Page navigation">
                  <ul class="pagination justify-content-center">
                    <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                      <a class="page-link" href="?page=<?php echo $page - 1 . '&search=' . urlencode($search) . '&sort=' . $sort_column . '&order=' . $sort_order; ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                      <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i . '&search=' . urlencode($search) . '&sort=' . $sort_column . '&order=' . $sort_order; ?>"><?php echo $i; ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                      <a class="page-link" href="?page=<?php echo $page + 1 . '&search=' . urlencode($search) . '&sort=' . $sort_column . '&order=' . $sort_order; ?>">Next</a>
                    </li>
                  </ul>
                </nav>
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