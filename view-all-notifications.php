<?php
session_start();

// Database connection parameters
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

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    echo "No student is logged in. Redirecting to login page...";
    header("Location: Student-login.php");
    exit();
}

// Get student ID from session
$student_id = $_SESSION['student_id'];

// Function to fetch messages
function getMessages($conn, $student_id) {
    $sql = "SELECT m.*, s.first_name as sender_first_name, s.last_name as sender_last_name
            FROM messages m
            LEFT JOIN students_registration s ON m.sent_by = s.student_id
            WHERE m.received_by = ?
            ORDER BY m.sent_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Mark messages as seen
    $sql_update_seen = "UPDATE messages SET seen = 1 WHERE received_by = ?";
    $stmt_update_seen = $conn->prepare($sql_update_seen);
    $stmt_update_seen->bind_param("i", $student_id);
    $stmt_update_seen->execute();
    $stmt_update_seen->close();

    return $result;
}

// Function to get issued and overdue books
function getIssuedAndOverdueBooks($conn, $student_id) {
    $sql = "SELECT ib.*, b.title, b.author, DATEDIFF(CURDATE(), ib.return_date) as days_overdue
            FROM issue_book ib
            JOIN books b ON ib.book_id = b.id
            WHERE ib.student_id = ? AND ib.status = 'issued' AND ib.return_date < CURDATE()
            ORDER BY ib.issue_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}



// Get book requests
function getBookRequests($conn, $student_id) {
    $sql = "SELECT br.*, s.first_name, s.last_name, b.title
            FROM book_requests br
            JOIN students_registration s ON br.student_id = s.student_id
            JOIN books b ON br.book_id = b.id
            WHERE br.student_id = ?
            ORDER BY br.request_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}



// Get unpaid fines for the student
function getUnpaidFines($conn, $student_id) {
    $sql = "SELECT f.*, i.issue_date, i.return_date, b.title AS book_title, s.first_name, s.last_name
            FROM fines f
            JOIN issue_book i ON f.issue_id = i.issue_id
            JOIN books b ON i.book_id = b.id
            JOIN students_registration s ON i.student_id = s.student_id  # Join to get student info
            WHERE i.student_id = ? AND f.status = 'unpaid'
            ORDER BY f.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

$messages = getMessages($conn, $student_id);
$bookRequests = getBookRequests($conn, $student_id);
$unpaidFines = getUnpaidFines($conn, $student_id);
$issuedAndOverdueBooks = getIssuedAndOverdueBooks($conn, $student_id);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .notification-section {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .notification-section h2 {
            margin-bottom: 15px;
            font-size: 1.2em;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;

        }
        .notification-card {
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .notification-header {
            background-color: #e9ecef;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between; /* Space between sender and date */
            align-items: center;
        }
        .notification-body {
            padding: 15px;
        }
        .sender-info {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0; /* Remove default margin */

        }
        .message-text {
            font-size: 1rem;
            color: #212529;
        }

        .view-message-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .view-message-link:hover {
            text-decoration: underline;
        }

        .no-notifications {
            text-align: center;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-top: 10px;
            background-color: #ffffff;
            color: #6c757d;
        }

        .section-header{
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .book-title{
          font-weight: bold;
        }

    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Notifications</h1>

        <div class="notification-section">
            <h2 class = "section-header">
                <i class="material-icons">email</i> Messages
            </h2>
            <?php if ($messages->num_rows > 0): ?>
                <?php while($message = $messages->fetch_assoc()): ?>
                    <div class="card notification-card">
                        <div class="notification-header">
                            <div class = "sender-info">
                                <strong>From:</strong>
                                <?php
                                    if ($message['sent_by'] == 0):
                                        echo "Admin";
                                    else:
                                        echo htmlspecialchars($message['sender_first_name'] . " " . $message['sender_last_name']);
                                    endif;
                                ?>
                            </div>
                            <small>Sent: <?php echo date("F j, Y, g:i a", strtotime($message['sent_at'])); ?></small>
                        </div>
                        <div class="notification-body">
                            <p class="message-text"><?php echo htmlspecialchars($message['message_text']); ?></p>
                            <a href="Student-view-messages.php" class="view-message-link">View Message</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <p>No messages found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="notification-section">
            <h2 class = "section-header">
                <i class="material-icons">local_library</i> Issued and Overdue Books
            </h2>
            <?php if ($issuedAndOverdueBooks->num_rows > 0): ?>
                <?php while ($book = $issuedAndOverdueBooks->fetch_assoc()): ?>
                    <div class="card notification-card">
                        <div class="notification-header">
                            <span class="sender-info">Book: <span class="book-title"><?php echo htmlspecialchars($book['title']); ?></span></span>
                            <small>Issue Date: <?php echo date("F j, Y", strtotime($book['issue_date'])); ?></small>
                        </div>
                        <div class="notification-body">
                            <p>Author: <?php echo htmlspecialchars($book['author']); ?></p>
                            <p>Return Date: <?php echo date("F j, Y", strtotime($book['return_date'])); ?></p>
                            <p>Days Overdue: <?php echo htmlspecialchars($book['days_overdue']); ?></p>
                            <a href="Student-view-penalty.php" class="view-message-link">View Issue Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <p>No issued or overdue books.</p>
                </div>
            <?php endif; ?>
        </div>


        <div class="notification-section">
            <h2 class = "section-header">
                <i class="material-icons">library_add</i> Book Requests
            </h2>
            <?php if ($bookRequests->num_rows > 0): ?>
                <?php while($request = $bookRequests->fetch_assoc()): ?>
                    <div class="card notification-card">
                        <div class="notification-header">
                            <span class = "sender-info">Book Request: <span class = "book-title"><?php echo htmlspecialchars($request['title']); ?></span></span>
                            <small>Requested By: <?php echo htmlspecialchars($request['first_name'] . " " . $request['last_name']); ?></small>
                        </div>
                        <div class="notification-body">
                            <p>Request Date: <?php echo date("F j, Y, g:i a", strtotime($request['request_date'])); ?></p>
                            <p>Status: <?php echo htmlspecialchars(ucfirst($request['status'])); ?></p>
                            <a href="#" class="Student-view-issue-book.php">View My Request</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <p>No book requests.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="notification-section">
            <h2 class = "section-header">
                <i class="material-icons">warning</i> Unpaid Fines
            </h2>
            <?php if ($unpaidFines->num_rows > 0): ?>
                <?php while($fine = $unpaidFines->fetch_assoc()): ?>
                    <div class="card notification-card">
                        <div class="notification-header">
                            <span class = "sender-info">Unpaid Fine: <?php echo htmlspecialchars($fine['fine_amount']); ?></span>
                            <small>Student: <?php echo htmlspecialchars($fine['first_name'] . " " . $fine['last_name']); ?></small>
                        </div>
                        <div class="notification-body">
                            <p>Book: <span class = "book-title"><?php echo htmlspecialchars($fine['book_title']); ?></span></p>
                            <p>Days Overdue: <?php echo htmlspecialchars($fine['days_overdue']); ?></p>
                            <p>Due Date: <?php echo date("F j, Y, g:i a", strtotime($fine['return_date'])); ?></p>
                            <a href="Student-view-fine.php" class="view-message-link">View Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <p>No unpaid fines.</p>
                </div>
            <?php endif; ?>
        </div>



    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
