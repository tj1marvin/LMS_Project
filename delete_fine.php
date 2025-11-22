<?php
// Include the database connection file
require_once 'db_connection.php';

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if fine_id is set and is a number
    if (isset($_POST['fine_id']) && is_numeric($_POST['fine_id'])) {
        $fine_id = $_POST['fine_id'];

        // Prepare the delete statement
        $delete_sql = "DELETE FROM fines WHERE fine_id = ?";
        $stmt = $conn->prepare($delete_sql);

        if ($stmt) {
            // Bind the parameter
            $stmt->bind_param("i", $fine_id);

            // Execute the statement
            if ($stmt->execute()) {
                // If deletion is successful, return JSON success message
                echo json_encode(array('status' => 'success', 'message' => 'Fine deleted successfully!'));
                exit();
            } else {
                // If there's an error in execution, return JSON error message
                echo json_encode(array('status' => 'error', 'message' => 'Error deleting fine: ' . $stmt->error));
                exit();
            }

            // Close the statement
            $stmt->close();
        } else {
            // If preparing the statement fails, return JSON error
            echo json_encode(array('status' => 'error', 'message' => 'Error preparing statement: ' . $conn->error));
            exit();
        }
    } else {
        // If fine_id is not set or not valid, return JSON error
        echo json_encode(array('status' => 'error', 'message' => 'Invalid fine ID.'));
        exit();
    }
} else {
    // If the request method is not POST, return JSON error
    echo json_encode(array('status' => 'error', 'message' => 'Invalid request method.'));
    exit();
}

// Close the database connection
$conn->close();
?>
