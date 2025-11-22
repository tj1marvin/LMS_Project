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
$student_id = $_SESSION['student_id'];


// Pagination variables
$limit = 12; // Number of books per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Initialize search query
$searchQuery = "";
$searchParam = "";

if (isset($_GET['search'])) {
  $searchQuery = " WHERE title LIKE '%" . $_GET['search'] . "%' OR author LIKE '%" . $_GET['search'] . "%'";
  $searchParam = "?search=" . $_GET['search'];
}

// Query to fetch all books with pagination and search
$sql = "SELECT * FROM books " . $searchQuery . " LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Query to count total books for pagination (including search)
$total_books_sql = "SELECT COUNT(*) as total FROM books " . $searchQuery;
$total_books_result = $conn->query($total_books_sql);
$total_books_row = $total_books_result->fetch_assoc();
$total_books = $total_books_row['total'];
$total_pages = ceil($total_books / $limit);

// Query to fetch all books with pagination
$sql = "SELECT * FROM books LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Query to count total books for pagination (including search)
$total_books_sql = "SELECT COUNT(*) as total FROM books " . $searchQuery;
$total_books_result = $conn->query($total_books_sql);
$total_books_row = $total_books_result->fetch_assoc();
$total_books = $total_books_row['total'];
$total_pages = ceil($total_books / $limit);


// Query to check which books are in the wishlist
$wishlist_sql = "SELECT book_id FROM reserved WHERE student_id = $student_id";
$wishlist_result = $conn->query($wishlist_sql);
$wishlist_books = [];
while ($row = $wishlist_result->fetch_assoc()) {
  $wishlist_books[] = $row['book_id'];
}

// Handle form submission for borrowing a book
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_id'])) {
  $book_id = intval($_POST['book_id']);
  $days = 7; // Default borrowing period

  // Check if the book exists and get available copies
  $check_book_sql = "SELECT id, available_copies FROM books WHERE id = ?";
  $check_stmt = $conn->prepare($check_book_sql);
  $check_stmt->bind_param("i", $book_id);
  $check_stmt->execute();
  $check_stmt->store_result();
  $check_stmt->bind_result($book_id, $available_copies);
  $check_stmt->fetch();

  if ($check_stmt->num_rows > 0) {
    if ($available_copies > 0) {
      // Calculate the return date
      $return_date = date('Y-m-d H:i:s', strtotime("+$days days"));

      // Insert into issue_book table
      $sql_issue = "INSERT INTO issue_book (book_id, student_id, issue_date, return_date, status) VALUES (?, ?, NOW(), ?, 'issued')";
      $stmt_issue = $conn->prepare($sql_issue);
      $stmt_issue->bind_param("iis", $book_id, $student_id, $return_date);

      // Execute the issue statement
      if ($stmt_issue->execute()) {
        // Decrement available copies
        $update_copies_sql = "UPDATE books SET available_copies = available_copies - 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_copies_sql);
        $update_stmt->bind_param("i", $book_id);

        if ($update_stmt->execute()) {
          // Add to wishlist
          $sql_wishlist = "INSERT INTO reserved (student_id, book_id, added_at) VALUES (?, ?, NOW())";
          $stmt_wishlist = $conn->prepare($sql_wishlist);
          $stmt_wishlist->bind_param("ii", $student_id, $book_id);

          if ($stmt_wishlist->execute()) {
            echo "success"; // Successfully issued and added to wishlist
          } else {
            echo "issue_success_but_reserved_failed"; // Issue succeeded but wishlist failed
          }
        } else {
          echo "issue_failed: Could not update available copies."; // Failed to update available copies
        }
      } else {
        echo "issue_failed: " . $stmt_issue->error; // Error issuing book
      }

      $stmt_issue->close();
    } else {
      echo "issue_failed: No available copies."; // No available copies to issue
    }
  } else {
    echo "issue_failed: Book does not exist."; // Book does not exist
  }

  $check_stmt->close();
  if (isset($update_stmt)) {
    $update_stmt->close();
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



$conn->close();
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
            <li class="nav-item">
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
            <li class="nav-item active">
              <a data-bs-toggle="collapse" href="#books">
                <i class="fas fa-book"></i>
                <p>Books</p>
                <span class="caret"></span>
              </a>
              <div class="collapse show" id="books">
                <ul class="nav nav-collapse">
                  <li class="active">
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
              <div class="input-group">
                <input type="text" id="search" placeholder="Search for books..." class="form-control" />
                <div class="input-group-append">
                  <button type="button" class="btn btn-search pe-1" id="search-btn">
                    <i class="fa fa-search search-icon"></i>

                  </button>
                </div>
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
          <h3 class="fw-bold mb-4">Available Books</h3>


          <div id="book-list" class="row">
            <?php
            // Check if there are available books
            if ($result->num_rows > 0) {
              // Output data of each row
              while ($row = $result->fetch_assoc()) {
                $book_id = $row['id'];
                $title = $row['title'];
                $author = $row['author'];
                $year = $row['year'];
                $image_path = $row['image_path'] ? $row['image_path'] : 'https://via.placeholder.com/300x250?text=No+Image';
                $available_copies = $row['available_copies']; // Get available copies

                // Check if the book is in the wishlist
                $in_wishlist = in_array($book_id, $wishlist_books); // Assuming $wishlist_books is an array of book IDs in the wishlist
            ?>
                <div class="col-md-3 mb-4">
                  <div class="card">
                    <img src="<?php echo $image_path; ?>" class="card-img-top" alt="<?php echo $title; ?>">
                    <div class="card-body">
                      <h5 class="card-title"><?php echo $title; ?></h5>
                      <h6 class="card-subtitle mb-2 text-muted"><?php echo $author; ?></h6>
                      <p class="card-text">Publication Year: <?php echo $year; ?></p>
                      <p class="card-text">
                        <?php if ($available_copies > 0): ?>
                          <span class="badge badge-success">Available (<?php echo $available_copies; ?> copies)</span>
                        <?php else: ?>
                          <span class="badge badge-danger">Not Available</span>
                        <?php endif; ?>
                      </p>
                      <a href="view-book.php?id=<?php echo $book_id; ?>" class="btn btn-primary btn-sm btn-info">View</a>
                      <?php if ($available_copies > 0): ?>
                        <button class="btn btn-warning  btn-sm borrow-btn" data-book-id="<?php echo $book_id; ?>">
                          Borrow
                        </button>
                      <?php else: ?>
                        <button class="btn btn-sm btn-warning" disabled>
                          Not Available
                        </button>
                      <?php endif; ?>

                      <button class="btn <?php echo $in_wishlist ? 'btn-danger' : 'btn-primary'; ?> btn-sm reserved-btn" data-book-id="<?php echo $book_id; ?>">
                        <?php echo $in_wishlist ? 'Book Reserved' : 'Add to Reserve'; ?>
                      </button>

                    </div>
                  </div>
                </div>
            <?php
              }
            } else {
              echo "<p>No available books at the moment.</p>";
            }
            ?>
          </div>
          <!-- Pagination -->
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
              <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1 . $searchParam; ?>" tabindex="-1">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                  <a class="page-link" href="?page=<?php echo $i . $searchParam; ?>"><?php echo $i; ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1 . $searchParam; ?>">Next</a>
              </li>
            </ul>
          </nav>

        </div>
      </div>


      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script>
        $(document).ready(function() {
          $('#search').on('keyup', function() {
            var searchTerm = $(this).val();
            if (searchTerm.length > 0) { // Only search if there's input
              $.ajax({
                url: 'search_books.php', // The PHP file that handles the search
                type: 'GET',
                data: {
                  term: searchTerm
                },
                dataType: 'json', // Expecting JSON response
                success: function(data) {
                  $('#book-list').empty(); // Clear the current book list
                  if (data.length > 0) {
                    $.each(data, function(index, book) {
                      $('#book-list').append(`
                                    <div class="col-md-3 mb-4 book-item" data-title="${book.title}">
                                        <div class="card">
                                            <img src="${book.image_path}" class="card-img-top" alt="${book.title}">
                                            <div class="card-body">
                                                <h5 class="card-title">${book.title}</h5>
                                                <h6 class="card-subtitle mb-2 text-muted">${book.author}</h6>
                                                <p class="card-text">Publisher: ${book.publisher}</p>
                                                <p class="card-text">Genre: ${book.genre}</p>
                                                <p class="card-text">Publication Year: ${book.year}</p>
                                                <p class="card-text">
                                    <?php if ($available_copies > 0): ?>
                                        <span class="badge badge-success">Available (<?php echo $available_copies; ?> copies)</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Not Available</span>
                                    <?php endif; ?>
                                </p>
                                <a href="view-book.php?id=<?php echo $book_id; ?>" class="btn btn-primary btn-sm btn-info">View</a>
                                
                                            </div>
                                        </div>
                                    </div>
                                `);
                    });
                  } else {
                    $('#book-list').append('<p>No books found matching your search.</p>');
                  }
                },
                error: function() {
                  $('#book-list').append('<p>An error occurred while searching.</p>');
                }
              });
            } else {
              window.location.href = window.location.pathname + window.location.search;
            }
          });
        });



        $(document).ready(function() {
          $('.reserved-btn').click(function() {
            var bookId = $(this).data('book-id');
            var button = $(this);

            $.ajax({
              url: 'reserved.php', // Create this PHP file to handle the request
              type: 'POST',
              data: {
                book_id: bookId
              },
              success: function(response) {
                if (response === 'added') {
                  button.text('Reserved');
                  alert('Book added to your Reserve!');
                } else if (response === 'exists') {
                  alert('This book is already in your Reserve.');
                } else if (response === 'removed') {
                  button.text('Add to Wishlist');
                  alert('Book removed from your Reserve.');
                } else if (response === 'not_logged_in') {
                  alert('You need to be logged in to manage your Reserve.');
                } else if (response.startsWith('error_')) {
                  alert('An error occurred: ' + response);
                } else if (response === 'no_book_id') {
                  alert('No book ID provided.');
                }
              },
              error: function() {
                alert('An error occurred while processing your request. Please try again.');
              }
            });
          });
        });


        $(document).ready(function() {
          $('.borrow-btn').click(function() {
            var bookId = $(this).data('book-id');
            var button = $(this);

            $.ajax({
              url: 'borrow.php',
              type: 'POST',
              data: {
                book_id: bookId
              },
              success: function(response) {
                if (response === 'success') {
                  alert('Book issued successfully and added to your wishlist!');
                  window.location.href = 'Student-view-issue-book.php'; // Redirect to the issued books page
                } else if (response === 'already_issued') {
                  alert('This book is already issued to you.'); // Alert for already issued book
                } else if (response.startsWith('issue_failed')) {
                  alert('Error issuing book: ' + response.split(':')[1]); // Show error message
                } else if (response === 'not_logged_in') {
                  alert('You need to log in to borrow books.');
                  window.location.href = 'Student-login.php'; // Redirect to login page
                } else {
                  alert('Book issued successfully, but failed to add to wishlist.');
                }
              },
              error: function(xhr, status, error) {
                alert('An error occurred: ' + error); // Handle AJAX errors
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