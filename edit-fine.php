<?php
session_start();

// Enable PHP error reporting for debugging (VERY IMPORTANT)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection file
require_once 'db_connection.php'; // Adjust path if needed

if (!$conn) {
    die("Database connection failed: " . htmlspecialchars(mysqli_connect_error()));
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

$admin_id = $_SESSION['admin_id'];
$success_message = "";
$error_message = "";
$fine_data = []; // To store fine data for editing
$students = [];     // To store students list for dropdown (though likely disabled in edit form)

// Fetch list of students for dropdown (might be used, even if disabled in edit form for consistency)
$sql_students = "SELECT student_id, first_name, last_name, enrollment_no FROM students_registration WHERE approved = 'active'";
$result_students = $conn->query($sql_students);
if ($result_students && $result_students->num_rows > 0) {
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get fine_id from URL parameter
if (isset($_GET['fine_id']) && is_numeric($_GET['fine_id'])) {
    $fine_id_to_edit = $_GET['fine_id'];

    // Fetch fine details from the database
    $sql_fine = "SELECT
                    f.*,
                    s.first_name,
                    s.last_name,
                    s.enrollment_no,
                    b.title AS book_title  -- Join to get book title
                FROM fines f
                JOIN students_registration s ON f.student_id = s.student_id
                LEFT JOIN issue_book ib ON f.issue_id = ib.issue_id  -- Join to get issue_book data
                LEFT JOIN books b ON ib.book_id = b.id  -- Join to get book title
                WHERE f.fine_id = ?";
    $stmt_fine = $conn->prepare($sql_fine);
    $stmt_fine->bind_param("i", $fine_id_to_edit);
    $stmt_fine->execute();
    $result_fine = $stmt_fine->get_result();

    if ($result_fine && $result_fine->num_rows > 0) {
        $fine_data = $result_fine->fetch_assoc();
    } else {
        $error_message = "Fine not found.";
    }
    $stmt_fine->close();
} else {
    $error_message = "Invalid fine ID.";
}

// Process form submission for updating fine
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_fine'])) {
    $fine_id_update = isset($_POST['fine_id']) ? trim($_POST['fine_id']) : '';
    $fine_type_update = isset($_POST['fine_type']) ? trim($_POST['fine_type']) : '';
    $fine_amount_update = isset($_POST['fine_amount']) ? trim($_POST['fine_amount']) : '';
    $fine_description_update = isset($_POST['fine_description']) ? trim($_POST['fine_description']) : '';
    $fine_status_update = isset($_POST['fine_status']) ? trim($_POST['fine_status']) : '';

    // Validate inputs (basic validation - expand as needed)
    if (empty($fine_id_update) || !is_numeric($fine_id_update) || empty($fine_type_update) || empty($fine_amount_update) || !is_numeric($fine_amount_update) || $fine_amount_update <= 0 || empty($fine_status_update)) {
        $error_message = "Please ensure all fields are filled correctly.";
    } else {
        // Update fine in the fines table
        $update_fine_sql = "UPDATE fines SET fine_type = ?, fine_amount = ?, description = ?, status = ? WHERE fine_id = ?";
        $stmt_update_fine = $conn->prepare($update_fine_sql);

        if (!$stmt_update_fine) {
            $error_message = "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        } else {
            $stmt_update_fine->bind_param("ssdss", $fine_type_update, $fine_amount_update, $fine_description_update, $fine_status_update, $fine_id_update);

            if ($stmt_update_fine->execute()) {
                $success_message = "Fine updated successfully.";
                // Refresh fine data after successful update
                $sql_fine_refresh = "SELECT
                                        f.*,
                                        s.first_name,
                                        s.last_name,
                                        s.enrollment_no,
                                        b.title AS book_title  -- Join to get book title for refresh
                                    FROM fines f
                                    JOIN students_registration s ON f.student_id = s.student_id
                                    LEFT JOIN issue_book ib ON f.issue_id = ib.issue_id  -- Join to get issue_book data
                                    LEFT JOIN books b ON ib.book_id = b.id  -- Join to get book title
                                    WHERE f.fine_id = ?";
                $stmt_fine_refresh = $conn->prepare($sql_fine_refresh);
                $stmt_fine_refresh->bind_param("i", $fine_id_update);
                $stmt_fine_refresh->execute();
                $result_fine_refresh = $stmt_fine_refresh->get_result();
                if ($result_fine_refresh && $result_fine_refresh->num_rows > 0) {
                    $fine_data = $result_fine_refresh->fetch_assoc(); // Refresh $fine_data with updated info
                }
                $stmt_fine_refresh->close();

            } else {
                $error_message = "Error updating fine. Please try again. MySQL Error: " . htmlspecialchars($stmt_update_fine->error);
            }
            $stmt_update_fine->close();
        }
    }
}


if (empty($fine_data) && empty($error_message) && isset($_GET['fine_id'])) {
    $error_message = "Fine not found."; // Handle case where fine ID is valid format but not found in DB after initial fetch attempt
}


?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>LMS - Librarian Admin Dashboard</title>
    <meta
      content="width=device-width, initial-scale=1.0, shrink-to-fit=no"
      name="viewport"
    />
    <link
      rel="icon"
      href="assets/img/kaiadmin/favicon.ico"
      type="image/x-icon"
    />

    <!-- Fonts and icons -->
    <script src="assets/js/plugin/webfont/webfont.min.js"></script>
    <script>
      WebFont.load({
        google: { families: ["Public Sans:300,400,500,600,700"] },
        custom: {
          families: [
            "Font Awesome 5 Solid",
            "Font Awesome 5 Regular",
            "Font Awesome 5 Brands",
            "simple-line-icons",
          ],
          urls: ["assets/css/fonts.min.css"],
        },
        active: function () {
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
                  height="20"
                />
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
            class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom"
          >
            <div class="container-fluid">
              <nav
                class="navbar navbar-header-left navbar-expand-lg navbar-form nav-search p-0 d-none d-lg-flex"
              >
                <div class="input-group">
                  <div class="input-group-prepend">
                    <button type="submit" class="btn btn-search pe-1">
                      <i class="fa fa-search search-icon"></i>
                    </button>
                  </div>
                  <input
                    type="text"
                    placeholder="Search ..."
                    class="form-control"
                  />
                </div>
              </nav>

              <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                <li
                  class="nav-item topbar-icon dropdown hidden-caret d-flex d-lg-none"
                >
                  <a
                    class="nav-link dropdown-toggle"
                    data-bs-toggle="dropdown"
                    href="#"
                    role="button"
                    aria-expanded="false"
                    aria-haspopup="true"
                  >
                    <i class="fa fa-search"></i>
                  </a>
                  <ul class="dropdown-menu dropdown-search animated fadeIn">
                    <form class="navbar-left navbar-form nav-search">
                      <div class="input-group">
                        <input
                          type="text"
                          placeholder="Search ..."
                          class="form-control"
                        />
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
                    aria-expanded="false"
                  >
                    <i class="fa fa-envelope"></i>
                  </a>
                  <ul
                    class="dropdown-menu messages-notif-box animated fadeIn"
                    aria-labelledby="messageDropdown"
                  >
                    <li>
                      <div
                        class="dropdown-title d-flex justify-content-between align-items-center"
                      >
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
                                alt="Img Profile"
                              />
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
                                alt="Img Profile"
                              />
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
                                alt="Img Profile"
                              />
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
                                alt="Img Profile"
                              />
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
                      <a class="see-all" href="javascript:void(0);"
                        >See all messages<i class="fa fa-angle-right"></i>
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
                    aria-expanded="false"
                  >
                    <i class="fa fa-bell"></i>
                    <span class="notification">4</span>
                  </a>
                  <ul
                    class="dropdown-menu notif-box animated fadeIn"
                    aria-labelledby="notifDropdown"
                  >
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
                                alt="Img Profile"
                              />
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
                      <a class="see-all" href="javascript:void(0);"
                        >See all notifications<i class="fa fa-angle-right"></i>
                      </a>
                    </li>
                  </ul>
                </li>
                <li class="nav-item topbar-icon dropdown hidden-caret">
                  <a
                    class="nav-link"
                    data-bs-toggle="dropdown"
                    href="#"
                    aria-expanded="false"
                  >
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
                                class="avatar-item bg-warning rounded-circle"
                              >
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
                                class="avatar-item bg-success rounded-circle"
                              >
                                <i class="fas fa-envelope"></i>
                              </div>
                              <span class="text">Emails</span>
                            </div>
                          </a>
                          <a class="col-6 col-md-4 p-0" href="#">
                            <div class="quick-actions-item">
                              <div
                                class="avatar-item bg-primary rounded-circle"
                              >
                                <i class="fas fa-file-invoice-dollar"></i>
                              </div>
                              <span class="text">Invoice</span>
                            </div>
                          </a>
                          <a class="col-6 col-md-4 p-0" href="#">
                            <div class="quick-actions-item">
                              <div
                                class="avatar-item bg-secondary rounded-circle"
                              >
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
                    aria-expanded="false"
                  >
                    <div class="avatar-sm">
                      <img
                        src="assets/img/profile.jpg"
                        alt="..."
                        class="avatar-img rounded-circle"
                      />
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
                              class="avatar-img rounded"
                            />
                          </div>
                          <div class="u-text">
                            <h4>Hizrian</h4>
                            <p class="text-muted">hello@example.com</p>
                            <a
                              href="profile.html"
                              class="btn btn-xs btn-secondary btn-sm"
                              >View Profile</a
                            >
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
                <h3 class="fw-bold mb-3">Fines Management</h3>
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
                        <a href="view-fines.php">Fines</a>
                    </li>
                    <li class="separator">
                        <i class="icon-arrow-right"></i>
                    </li>
                    <li class="nav-item">
                        <a href="view-fines.php">Edit Fine</a>
                    </li>
                </ul>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Edit Fine Details</div>
                        </div>
                        <div class="card-body">
                            <?php if ($success_message): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                            <?php endif; ?>
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                            <?php endif; ?>

                            <?php if (!empty($fine_data)): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="fine_id" value="<?php echo htmlspecialchars($fine_data['fine_id']); ?>">

                                    <div class="form-group">
                                        <label for="student_id">Student</label>
                                        <input type="text" class="form-control" id="student_name" value="<?php echo htmlspecialchars($fine_data['first_name'] . ' ' . $fine_data['last_name'] . ' (' . $fine_data['enrollment_no'] . ')'); ?>" readonly>
                                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($fine_data['student_id']); ?>">
                                        <small class="form-text text-muted">Student associated with this fine (cannot be changed).</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fine_type">Fine Type</label>
                                        <select class="form-control" id="fine_type" name="fine_type" required>
                                            <option value="">-- Select Fine Type --</option>
                                            <option value="damage" <?php if ($fine_data['fine_type'] == 'damage') echo 'selected'; ?>>Damage</option>
                                            <option value="lost" <?php if ($fine_data['fine_type'] == 'lost') echo 'selected'; ?>>Lost</option>
                                            <option value="other" <?php if ($fine_data['fine_type'] == 'other') echo 'selected'; ?>>Other</option>
                                        </select>
                                        <small class="form-text text-muted">Specify the type of fine.</small>
                                    </div>
                                    <?php if ($fine_data['fine_type'] == 'damage' || $fine_data['fine_type'] == 'lost'): ?>
                                        <div class="form-group">
                                            <label>Book Title</label>
                                            <input type="text" class="form-control"  value="<?php echo htmlspecialchars($fine_data['book_title']); ?>" readonly>
                                            <small class="form-text text-muted">Book associated with this fine.</small>
                                        </div>
                                    <?php endif; ?>

                                    <div class="form-group">
                                        <label for="fine_amount">Fine Amount</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">$</span>
                                            </div>
                                            <input type="number" class="form-control" id="fine_amount" name="fine_amount" required placeholder="Enter Fine Amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars($fine_data['fine_amount']); ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="fine_description">Description (Optional)</label>
                                        <textarea class="form-control" id="fine_description" name="fine_description" rows="3" placeholder="Enter description or reason for the fine"><?php echo htmlspecialchars($fine_data['description']); ?></textarea>
                                        <small class="form-text text-muted">Optional: Add a description for the fine.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fine_status">Fine Status</label>
                                        <select class="form-control" id="fine_status" name="fine_status" required>
                                            <option value="">-- Select Status --</option>
                                            <option value="unpaid" <?php if ($fine_data['status'] == 'unpaid') echo 'selected'; ?>>Unpaid</option>
                                            <option value="paid" <?php if ($fine_data['status'] == 'paid') echo 'selected'; ?>>Paid</option>
                                        </select>
                                        <small class="form-text text-muted">Update the status of the fine.</small>
                                    </div>

                                    <button type="submit" name="update_fine" class="btn btn-primary">Update Fine</button>
                                    <a href="view-fines.php" class="btn btn-secondary ml-2">Cancel</a>
                                </form>
                            <?php else: ?>
                                <?php if(!empty($error_message)): ?>
                                    <p><?php echo htmlspecialchars($error_message); ?></p>
                                <?php else: ?>
                                    <p>Loading fine details...</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
if ($conn) {
    $conn->close();
}
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
