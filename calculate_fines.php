<?php
// calculate_fines.php

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "LMS";

// Fine Settings - Configure these values as needed
$return_duration_days = 14; // Default return duration for books in days
$fine_per_day = 1.00;      // Fine amount per overdue day in your currency

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 1. Identify Overdue Books ---
$overdue_books_sql = "SELECT issue_id, book_id, student_id, issue_date
                       FROM issue_book
                       WHERE status = 'issued'
                         AND return_date IS NULL
                         AND issue_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)"; // CURDATE() for date only comparison

$stmt_overdue = $conn->prepare($overdue_books_sql);
$stmt_overdue->bind_param("i", $return_duration_days); // Bind return_duration_days as integer
$stmt_overdue->execute();
$overdue_result = $stmt_overdue->get_result();

if ($overdue_result->num_rows > 0) {
    echo "Processing overdue books...\n";

    while ($book_issue = $overdue_result->fetch_assoc()) {
        $issue_id = $book_issue['issue_id'];
        $issue_date = new DateTime($book_issue['issue_date']);
        $current_date = new DateTime();

        // --- 2. Calculate Overdue Days ---
        // Calculate the due date (issue date + return duration)
        $due_date = clone $issue_date; // Clone to avoid modifying the original issue_date
        $due_date->modify("+{$return_duration_days} days");

        if ($current_date > $due_date) { // Check if current date is after the due date
            $overdue_interval = $current_date->diff($due_date);
            $days_overdue = $overdue_interval->days;

            // --- 3. Calculate Fine Amount ---
            $fine_amount = $days_overdue * $fine_per_day;

            // --- 4. Check if an Unpaid Fine Already Exists for this Issue ---
            $check_fine_sql = "SELECT fine_id FROM fines WHERE issue_id = ? AND status = 'unpaid'";
            $stmt_check_fine = $conn->prepare($check_fine_sql);
            $stmt_check_fine->bind_param("i", $issue_id); // Bind issue_id as integer
            $stmt_check_fine->execute();
            $check_fine_result = $stmt_check_fine->get_result();

            if ($check_fine_result->num_rows == 0) {
                // --- 5. Insert New Fine Record into the fines table ---
                $insert_fine_sql = "INSERT INTO fines (issue_id, fine_amount, days_overdue) VALUES (?, ?, ?)";
                $stmt_insert_fine = $conn->prepare($insert_fine_sql);
                $stmt_insert_fine->bind_param("idd", $issue_id, $fine_amount, $days_overdue); // 'i' for issue_id (INT), 'd' for fine_amount (DECIMAL), 'd' for days_overdue (INT, treated as double for decimal)


                if ($stmt_insert_fine->execute()) {
                    echo "Fine of $" . number_format($fine_amount, 2) . " generated for Issue ID: " . $issue_id . " (Overdue by " . $days_overdue . " days).\n";
                } else {
                    echo "Error creating fine for Issue ID: " . $issue_id . " - " . $stmt_insert_fine->error . "\n";
                }
                $stmt_insert_fine->close();
            } else {
                echo "Unpaid fine already exists for Issue ID: " . $issue_id . ". Skipping fine generation.\n";
            }
            $stmt_check_fine->close();
        }
    }
} else {
    echo "No overdue books found at this time.\n";
}

$stmt_overdue->close();
$conn->close();

echo "Automated fine calculation process completed.\n";

?>