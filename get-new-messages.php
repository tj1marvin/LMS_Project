<?php
session_start();

// Include database connection and functions (adjust path if necessary)
require_once 'db_connection.php';
require_once 'analytics-dashboard-functions.php'; // Assuming you have relevant functions

$conn = connect_db();
if (!$conn) {
    die(json_encode(['error' => 'Database connection failed.'])); // Return JSON error
}

$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0; // Get admin ID from session, default to 0 if not set
$last_message_id = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0; // Get last message ID from request

// Function to fetch new messages since a given message ID
function getNewMessagesSince($conn, $admin_id, $last_message_id) {
    $sqlNewMessages = "SELECT
                            m.message_id,
                            m.message_text AS message_content,
                            m.sent_at AS timestamp,
                            s.student_id AS sender_id,
                            CONCAT(s.first_name, ' ', s.last_name) AS sender_name,
                            s.student_img AS sender_profile_image
                        FROM messages m
                        LEFT JOIN students_registration s ON m.sent_by = s.student_id
                        WHERE m.received_by = ? AND m.message_id > ?
                        ORDER BY m.sent_at ASC"; // Order by ASC for chronological appending

    $stmt = $conn->prepare($sqlNewMessages);
    if (!$stmt) {
        error_log("getNewMessagesSince Prepare Error: " . $conn->error);
        return [];
    }

    $stmt->bind_param("ii", $admin_id, $last_message_id);
    if (!$stmt->execute()) {
        error_log("getNewMessagesSince Execute Error: " . $stmt->error);
        return [];
    }

    $result = $stmt->get_result();
    $newMessages = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $newMessages[] = $row;
        }
    }

    $stmt->close();
    return $newMessages;
}

$newMessages = getNewMessagesSince($conn, $admin_id, $last_message_id);

header('Content-Type: application/json'); // Set response type to JSON
echo json_encode(['messages' => $newMessages]); // Encode messages array as JSON and output

if ($conn) {
    $conn->close();
}
exit;
?>