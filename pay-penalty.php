<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['student_id'])) {
    header("Location: Student-login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

$penalty_id = isset($_GET['penalty_id']) ? intval($_GET['penalty_id']) : 0;
$error_message = null;
$success_message = null;
$penalty_data = null;
$paypal_button_html = ''; // Variable to hold PayPal Smart Payment Buttons HTML


if ($penalty_id > 0) {
    // Fetch penalty data to display on the page
    $penalty_sql = "SELECT p.penalty_id, p.penalty_amount, p.status, ib.issue_id, b.title as book_title, s.first_name, s.last_name, DATEDIFF(NOW(), ib.return_date) AS days_overdue
                    FROM penalties p
                    LEFT JOIN issue_book ib ON p.issue_id = ib.issue_id
                    LEFT JOIN books b ON ib.book_id = b.id
                    LEFT JOIN students_registration s ON ib.student_id = s.student_id
                    WHERE p.penalty_id = ?";
    $penalty_stmt = $conn->prepare($penalty_sql);
    if ($penalty_stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $penalty_stmt->bind_param("i", $penalty_id);
    $penalty_stmt->execute();
    $penalty_result = $penalty_stmt->get_result();
    if ($penalty_result) { //check if the result is valid
        $penalty_data = $penalty_result->fetch_assoc();
        $penalty_result->free_result(); //free memory associated with the result.
    }
    $penalty_stmt->close();

    if (!$penalty_data) {
        $error_message = "Penalty not found.";
    } else {
        if ($penalty_data['status'] !== 'paid') {
            // Prepare PayPal Smart Payment Buttons only if penalty is not paid
            $paypal_button_html = generatePayPalSmartButtons($penalty_data['penalty_id'], $penalty_data['penalty_amount']);
        }
    }
} else {
    $error_message = "Invalid Penalty ID.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['payment_status']) && $_POST['payment_status'] === 'Completed' && isset($_POST['custom']) && $_POST['custom'] == $penalty_id && isset($_POST['transaction_id'])) {
        // Payment is successful from PayPal Smart Buttons callback
        $transaction_id = $_POST['transaction_id'];
        $payment_method = 'online'; // Payment method is online for PayPal
        $payment_amount = $penalty_data['penalty_amount'];
        $issue_id_for_payment = $penalty_data['issue_id']; // Get issue_id from penalty data
        //check if the penalty is already paid
        if ($penalty_data['status'] !== 'paid') {
            // Record transaction in payments table and get payment_id
            $payment_insert_sql = "INSERT INTO payments (student_id, issue_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?, ?)";
            $payment_insert_stmt = $conn->prepare($payment_insert_sql);
            if ($payment_insert_stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $payment_insert_stmt->bind_param("iisss", $student_id, $issue_id_for_payment, $payment_amount, $payment_method, $transaction_id); // Corrected bind_param type string to "iisss"
            if ($payment_insert_stmt->execute()) {
                $payment_id_inserted = $payment_insert_stmt->insert_id; // Get auto-generated payment_id
                $success_message = "Payment recorded successfully via PayPal.";
                // Update penalty status to 'paid' and link payment_id
                $update_sql = "UPDATE penalties SET status = 'paid', payment_id = ? WHERE penalty_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt === false) {
                    die("Prepare failed: " . $conn->error);
                }
                $update_stmt->bind_param("ii", $payment_id_inserted, $penalty_id); // Bind payment_id and penalty_id
                if ($update_stmt->execute()) {
                    $success_message .= " and Penalty status updated to 'Paid' successfully.";
                    //call the function to return book
                    returnBookAndUpdateCopies($conn, $issue_id_for_payment);
                } else {
                    $error_message = "Error updating penalty status: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $error_message = "Error recording payment: " . $payment_insert_stmt->error;
            }
            $payment_insert_stmt->close();


            // Re-fetch penalty data to update display
            $penalty_data_stmt = $conn->prepare($penalty_sql);
            $penalty_data_stmt->bind_param("i", $penalty_id);
            $penalty_data_stmt->execute();
            $penalty_data_result = $penalty_data_stmt->get_result();
            if ($penalty_data_result) {
                $penalty_data = $penalty_data_result->fetch_assoc();
                $penalty_data_result->free_result();
            }
            $penalty_data_stmt->close();
            $paypal_button_html = ''; // Hide PayPal button after successful payment
        } else {
            $error_message = "Penalty already paid.";
        }
    } elseif (isset($_POST['manual_payment'])) {
        // Manual Payment Submission
        $payment_method = $_POST['payment_method'];
        $transaction_id = isset($_POST['manual_transaction_id']) && !empty($_POST['manual_transaction_id']) ? $_POST['manual_transaction_id'] : null;
        $payment_amount = $penalty_data['penalty_amount'];
        $issue_id_for_payment = $penalty_data['issue_id'];
        //check if the penalty is already paid
        if ($penalty_data['status'] !== 'paid') {
            $payment_insert_sql = "INSERT INTO payments (student_id, issue_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?, ?)";
            $payment_insert_stmt = $conn->prepare($payment_insert_sql);
            if ($payment_insert_stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $payment_insert_stmt->bind_param("iisss", $student_id, $issue_id_for_payment, $payment_amount, $payment_method, $transaction_id);
            if ($payment_insert_stmt->execute()) {
                $payment_id_inserted = $payment_insert_stmt->insert_id;
                $success_message = "Payment recorded successfully via " . ucfirst($payment_method) . ".";

                // Update fine status
                $update_sql = "UPDATE penalties SET status = 'paid', payment_id = ? WHERE penalty_id = ?"; //changed from fines to penalties
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt === false) {
                    die("Prepare failed: " . $conn->error);
                }
                $update_stmt->bind_param("ii", $payment_id_inserted, $penalty_id); //changed from fine_id to penalty_id
                if ($update_stmt->execute()) {
                    $success_message .= " and Penalty status updated to 'Paid' successfully.";
                    //call function to return book
                    returnBookAndUpdateCopies($conn, $issue_id_for_payment);
                } else {
                    $error_message = "Error updating penalty status: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $error_message = "Error recording payment: " . $payment_insert_stmt->error;
            }
            $payment_insert_stmt->close();

            // Re-fetch penalty data
            $penalty_data_stmt = $conn->prepare($penalty_sql);
            $penalty_data_stmt->bind_param("i", $penalty_id);
            $penalty_data_stmt->execute();
            $penalty_data_result = $penalty_data_stmt->get_result();
            if ($penalty_data_result) {
                $penalty_data = $penalty_data_result->fetch_assoc();
                $penalty_data_result->free_result();
            }
            $penalty_data_stmt->close();
            $paypal_button_html = ''; // Hide PayPal button after successful payment and manual payment
        } else {
            $error_message = "Penalty already paid.";
        }
    }
    // Manual override handling is REMOVED
}


function generatePayPalSmartButtons($penalty_id, $amount)
{
    // Sandbox Business Account Email - Replace with your Sandbox Business email
    $business = 'marvin@lms.com'; // Replace with your actual Sandbox Business Email
    $client_id = 'AZeE21qzn91XmWrNE31WIiJKJvZBxnJUgFBZx1QckhbscugZOTkDLzuIzC7ISELKNhlkW8ukGffZTM56'; // Replace with your actual Sandbox Client ID
    $button_html = '
        <div id="paypal-button-container"></div>
        <script src="https://www.paypal.com/sdk/js?client-id=' . $client_id . '&currency=USD&intent=capture"></script>
        <script>
          console.log("Client ID being used: " + "' . $client_id . '");
          paypal.Buttons({
              style: {
                  layout:  \'horizontal\',
                  size:   \'responsive\',
                  shape:  \'rect\',
                  color:  \'blue\'
              },
              createOrder: function(data, actions) {
                  return actions.order.create({
                      purchase_units: [{
                          amount: {
                              value: \'' . $amount . '\'
                          }
                      }]
                  });
              },
              onApprove: function(data, actions) {
                  return actions.order.capture().then(function(details) {
                      // Payment is successful, send details to server to update penalty status
                      var form = document.createElement("form");
                      form.setAttribute("method", "POST");
                      form.setAttribute("action", ""); // Submit to the same page

                      var paymentStatusInput = document.createElement("input");
                      paymentStatusInput.setAttribute("type", "hidden");
                      paymentStatusInput.setAttribute("name", "payment_status");
                      paymentStatusInput.setAttribute("value", "Completed");
                      form.appendChild(paymentStatusInput);

                      var customInput = document.createElement("input");
                      customInput.setAttribute("type", "hidden");
                      customInput.setAttribute("name", "custom");
                      customInput.setAttribute("value", "' . $penalty_id . '");
                      form.appendChild(customInput);

                      var transactionIdInput = document.createElement("input");
                      transactionIdInput.setAttribute("type", "hidden");
                      transactionIdInput.setAttribute("name", "transaction_id");
                      transactionIdInput.setAttribute("value", details.id); // PayPal Transaction ID
                      form.appendChild(transactionIdInput);


                      document.body.appendChild(form);
                      form.submit();

                      alert(\'Transaction completed by \' + details.payer.name.given_name + \'!\');
                  });
              },
              onCancel: function(data) {
                  alert(\'Payment cancelled\');
              },
              onError: function(err) {
                  console.error(\'PayPal error:\', err);
                  alert(\'An error occurred during PayPal payment. Please try again.\');
              }
          }).render(\'#paypal-button-container\');
        </script>
    ';
    return $button_html;
}
function returnBookAndUpdateCopies($conn, $issue_id)
{
    // Start a transaction
    $conn->begin_transaction();
    $book_id = ''; // Initialize book_id variable

    try {
        // Update the issue_book table to set the status to 'returned'
        $update_issue_sql = "UPDATE issue_book SET status = 'returned', return_date = NOW() WHERE issue_id = ?";
        $update_issue_stmt = $conn->prepare($update_issue_sql);
        $update_issue_stmt->bind_param("i", $issue_id);

        if ($update_issue_stmt->execute()) {
            // Get the book_id from the issue_book table
            $book_sql = "SELECT book_id FROM issue_book WHERE issue_id = ?";
            $book_stmt = $conn->prepare($book_sql);
            $book_stmt->bind_param("i", $issue_id);
            $book_stmt->execute();
            $book_stmt->bind_result($book_id);
            $book_stmt->fetch();
            $book_stmt->close();

            // Increment the available copies in the books table
            $update_copies_sql = "UPDATE books SET available_copies = available_copies + 1 WHERE id = ?";
            $update_copies_stmt = $conn->prepare($update_copies_sql);
            $update_copies_stmt->bind_param("i", $book_id);
            $update_copies_stmt->execute();
            $update_copies_stmt->close();

            // Commit the transaction
            $conn->commit();
           // echo "<script>alert('Book returned successfully!'); window.location.href='http://localhost/LMS/books/';</script>";
        } else {
            throw new Exception("Error returning book: " . $update_issue_stmt->error);
        }
        $update_issue_stmt->close();
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        echo "Transaction failed: " . $e->getMessage(); // Keep the echo for error message
    }
}

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
                  <li >
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
                  <li >
                    <a href="Student-view-issue-book.php">
                      <span class="sub-item">View Issued Book</span>
                    </a>
                  </li>
                  <li >
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
            <li class="nav-item active">
              <a data-bs-toggle="collapse" href="#penalties">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Penalties</p>
                <span class="caret"></span>
              </a>
              <div class="collapse show" id="penalties">
                <ul class="nav nav-collapse">
                  <li >
                    <a href="Student-view-penalty.php">
                      <span class="sub-item">View
                        Penalties</span>
                    </a>
                  </li>
                  <li class="active" >
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
                  <li>
                    <a href="send-message.php">
                      <span class="sub-item">Send Message</span>
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
                                            <a class="dropdown-item" href="Student-view-profile.php">My Profile</a>
                                            <a class="dropdown-item" href="#">My Balance</a>
                                            <a class="dropdown-item" href="#">Inbox</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#">Account Setting</a>
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
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Pay Penalty</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="index.php">
                                    <i class="icon-home"> </i></i>
                                </a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"> <i class="fas fa-angle-right"></i></i>
                            </li>
                            <li class="nav-item">
                                <a href="view-penalties.php">Penalties</a>
                            </li>
                            <li class="separator">
                                <i class="icon-arrow-right"> <i class="fas fa-angle-right"></i></i>
                            </li>
                            <li class="nav-item active"><a href="pay-penalty.php?penalty_id=<?php echo htmlspecialchars($penalty_id); ?>">Pay Penalty</a>
                            </li>
                        </ul>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Penalty Details</div>
                                </div>
                                <div class="card-body">
                                    <?php if ($error_message): ?>
                                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                    <?php endif; ?>
                                    <?php if ($success_message): ?>
                                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                                    <?php endif; ?>

                                    <?php if ($penalty_data): ?>
                                        <dl class="row">
                                            <dt class="col-sm-3">Penalty ID</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($penalty_data['penalty_id']); ?></dd>

                                            <dt class="col-sm-3">Book Title</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($penalty_data['book_title']); ?></dd>

                                            <dt class="col-sm-3">Student Name</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($penalty_data['first_name']) . ' ' . htmlspecialchars($penalty_data['last_name']); ?></dd>

                                            <dt class="col-sm-3">Days Overdue</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars($penalty_data['days_overdue']); ?></dd>

                                            <dt class="col-sm-3">Penalty Amount</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars(number_format($penalty_data['penalty_amount'], 2)); ?></dd>

                                            <dt class="col-sm-3">Status</dt>
                                            <dd class="col-sm-9"><?php echo htmlspecialchars(ucfirst($penalty_data['status'])); ?></dd>
                                        </dl>

                                        <?php if ($penalty_data['status'] !== 'paid'): ?>
                                            <div class="mb-3">
                                                <?php echo $paypal_button_html; ?>
                                            </div>

                                            <div class="card mt-4">
                                                <div class="card-header">
                                                    <div class="card-title">Pay Manually</div>
                                                </div>
                                                <div class="card-body">
                                                    <form method="POST">
                                                        <div class="form-group">
                                                            <label for="payment_method">Payment Method</label>
                                                            <select class="form-control" id="payment_method" name="payment_method" required>
                                                                <option value="">Select Payment Method</option>
                                                                <option value="cash">Cash</option>
                                                                <option value="card">Card</option>
                                                                <option value="online">Online Transfer</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="manual_transaction_id">Transaction ID (Optional)</label>
                                                            <input type="text" class="form-control" id="manual_transaction_id" name="manual_transaction_id" placeholder="Enter Transaction ID if applicable">
                                                            <small class="form-text text-muted">For card or online payments, you can add transaction ID for reference.</small>
                                                        </div>
                                                        <input type="hidden" name="manual_payment" value="true">
                                                        <button type="submit" class="btn btn-primary">Record Manual Payment</button>
                                                    </form>
                                                </div>
                                            </div>

                                        <?php else: ?>
                                            <p class="text-success">This penalty has already been paid.</p>
                                        <?php endif; ?>

                                        <a href="Student-view-penalty.php" class="btn btn-secondary mt-3">Back to Penalties</a>

                                    <?php else: ?>
                                        <p>No penalty details found.</p>
                                        <a href="Student-view-penalty.php" class="btn btn-secondary mt-3">Back to Penalties</a>
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