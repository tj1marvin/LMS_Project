<?php
// Database connection parameters (Ensure these are correct)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// --- Notification Queries ---

// 1. New User Registrations
$new_users_sql = "SELECT student_id, first_name, last_name, created_at 
                    FROM students_registration 
                    ORDER BY created_at DESC";
$new_users_result = $conn->query($new_users_sql);

// 2. Book Requests
$book_requests_sql = "SELECT r.request_id, r.student_id, s.first_name, s.last_name, b.title, r.request_date, r.status
                        FROM book_requests r
                        JOIN students_registration s ON r.student_id = s.student_id
                        JOIN books b ON r.book_id = b.id
                        ORDER BY r.request_date DESC";
$book_requests_result = $conn->query($book_requests_sql);

// 3. Fines (unpaid)
$fines_sql = "SELECT f.fine_id, f.student_id, s.first_name, s.last_name, f.fine_amount, f.created_at, f.status
                FROM fines f
                JOIN students_registration s ON f.student_id = s.student_id
                WHERE f.status = 'unpaid'
                ORDER BY f.created_at DESC";
$fines_result = $conn->query($fines_sql);

// 4.  New Messages
$messages_sql = "SELECT m.message_id, m.sent_by, s.first_name, s.last_name, m.message_text, m.sent_at, m.seen
                 FROM messages m
                 JOIN students_registration s ON m.sent_by = s.student_id
                 WHERE m.received_by = $admin_id
                 ORDER BY m.sent_at DESC";
$messages_result = $conn->query($messages_sql);

// --- End Notification Queries ---

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .notif-icon {
            margin-right: 10px;
            font-size: 1.5rem; /* Increased size for better visibility */
        }
        .notif-primary { color: #007bff; }       /* Blue */
        .notif-success { color: #28a745; }     /* Green */
        .notif-info { color: #17a2b8; }        /* Cyan */
        .notif-warning { color: #dc3545; }      /* Red */
        .notif-content {
            display: flex;
            align-items: center; /* Vertically center icon and text */
        }
        .mark-as-read {
            margin-left: auto; /* Push the button to the right */
        }

    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Notifications</h1>

        <div class="accordion" id="notificationsAccordion">
            <div class="card">
                <div class="card-header" id="headingNewUsers">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapseNewUsers" aria-expanded="true" aria-controls="collapseNewUsers">
                            New User Registrations
                        </button>
                    </h2>
                </div>
                <div id="collapseNewUsers" class="collapse" aria-labelledby="headingNewUsers" data-parent="#notificationsAccordion">
                    <div class="card-body">
                        <?php if ($new_users_result && $new_users_result->num_rows > 0): ?>
                            <?php while ($user_row = $new_users_result->fetch_assoc()): ?>
                                <div class="notification-item">
                                    <div class="notif-content">
                                        <i class="notif-icon notif-primary fa fa-user-plus"></i>
                                        <div>
                                            <span class="block">New user registered: <?php echo htmlspecialchars($user_row['first_name'] . ' ' . $user_row['last_name']); ?></span>
                                            <span class="time">Registered on: <?php echo date('M d, Y h:i A', strtotime($user_row['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <a href="view-student-details.php?student_id=<?php echo $user_row['student_id']; ?>" class="btn btn-sm btn-outline-primary mark-as-read">View Details</a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="notification-item">No new user registrations.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" id="headingBookRequests">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseBookRequests" aria-expanded="false" aria-controls="collapseBookRequests">
                            Book Requests
                        </button>
                    </h2>
                </div>
                <div id="collapseBookRequests" class="collapse" aria-labelledby="headingBookRequests" data-parent="#notificationsAccordion">
                    <div class="card-body">
                        <?php if ($book_requests_result && $book_requests_result->num_rows > 0): ?>
                            <?php while ($request_row = $book_requests_result->fetch_assoc()): ?>
                                <div class="notification-item">
                                    <div class="notif-content">
                                        <i class="notif-icon notif-info fa fa-book"></i>
                                        <div>
                                            <span class="block">Book Request: <?php echo htmlspecialchars($request_row['title']); ?> by <?php echo htmlspecialchars($request_row['first_name'] . ' ' . $request_row['last_name']); ?></span>
                                            <span class="time">Requested on: <?php echo date('M d, Y h:i A', strtotime($request_row['request_date'])); ?></span>
                                            <span class="badge badge-<?php echo ($request_row['status'] == 'pending') ? 'warning' : (($request_row['status'] == 'approved') ? 'success' : 'danger'); ?>">
                                                <?php echo ucfirst($request_row['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a href="request-book.php" class="btn btn-sm btn-outline-primary mark-as-read">View Requests</a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="notification-item">No book requests.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" id="headingFines">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseFines" aria-expanded="false" aria-controls="collapseFines">
                            Unpaid Fines
                        </button>
                    </h2>
                </div>
                <div id="collapseFines" class="collapse" aria-labelledby="headingFines" data-parent="#notificationsAccordion">
                    <div class="card-body">
                        <?php if ($fines_result && $fines_result->num_rows > 0): ?>
                            <?php while ($fine_row = $fines_result->fetch_assoc()): ?>
                                <div class="notification-item">
                                    <div class="notif-content">
                                        <i class="notif-icon notif-warning fa fa-exclamation-triangle"></i>
                                        <div>
                                            <span class="block">Unpaid Fine: $<?php echo htmlspecialchars(number_format($fine_row['fine_amount'], 2)); ?> by <?php echo htmlspecialchars($fine_row['first_name'] . ' ' . $fine_row['last_name']); ?></span>
                                            <span class="time">Issued on: <?php echo date('M d, Y h:i A', strtotime($fine_row['created_at'])); ?></span>
                                            <span class="badge badge-danger"><?php echo ucfirst($fine_row['status']); ?></span>
                                        </div>
                                    </div>
                                    <a href="view-fines.php" class="btn btn-sm btn-outline-primary mark-as-read">View Fines</a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="notification-item">No unpaid fines.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" id="headingMessages">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseMessages" aria-expanded="false" aria-controls="collapseMessages">
                            Messages
                        </button>
                    </h2>
                </div>
                <div id="collapseMessages" class="collapse" aria-labelledby="headingMessages" data-parent="#notificationsAccordion">
                    <div class="card-body">
                        <?php if ($messages_result && $messages_result->num_rows > 0): ?>
                            <?php while ($message_row = $messages_result->fetch_assoc()): ?>
                                <div class="notification-item">
                                    <div class="notif-content">
                                        <i class="notif-icon notif-success fa fa-envelope"></i>
                                        <div>
                                            <span class="block">Message from <?php echo htmlspecialchars($message_row['first_name'] . ' ' . $message_row['last_name']); ?></span>
                                            <span class="time">Sent on: <?php echo date('M d, Y h:i A', strtotime($message_row['sent_at'])); ?></span>
                                             <span class="badge badge-<?php echo ($message_row['seen'] == 0) ? 'warning' : 'success'; ?>">
                                                <?php echo ($message_row['seen'] == 0) ? 'Unseen' : 'Seen'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <a href="view-message.php" class="btn btn-sm btn-outline-primary mark-as-read">View Message</a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="notification-item">No new messages.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.mark-as-read').click(function() {
                // In a real application, you would use AJAX to update the database
                // to mark the notification as read.  This is a simplified example.
                $(this).closest('.notification-item').fadeOut();
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
