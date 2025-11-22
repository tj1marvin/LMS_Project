<?php
// database_connection.php (Include your database connection code)
require_once 'database_connection.php';

// SQL query to find overdue books without penalties
$overdue_books_sql = "SELECT
    ib.issue_id,
    DATEDIFF(NOW(), ib.return_date) AS days_overdue
FROM
    issue_book ib
WHERE
    ib.status = 'issued'
    AND ib.return_date < NOW()
    AND NOT EXISTS (
        SELECT 1
        FROM penalties p
        WHERE p.issue_id = ib.issue_id
    )";

$overdue_books_result = $conn->query($overdue_books_sql);

if ($overdue_books_result) { // Check if the query executed successfully
    if ($overdue_books_result->num_rows > 0) {
        echo "Found overdue books without penalties. Calculating and adding penalties...\n"; // Logging message

        // SQL query to insert penalties
        $insert_penalty_sql = "INSERT INTO penalties (issue_id, penalty_amount, days_overdue)
        SELECT
            ib.issue_id,
            DATEDIFF(NOW(), ib.return_date) * 1.00 AS penalty_amount, -- $1.00 per day penalty
            DATEDIFF(NOW(), ib.return_date) AS days_overdue
        FROM
            issue_book ib
        WHERE
            ib.status = 'issued'
            AND ib.return_date < NOW()
            AND NOT EXISTS (
                SELECT 1
                FROM penalties p
                WHERE p.issue_id = ib.issue_id
            )";

        if ($conn->query($insert_penalty_sql) === TRUE) {
            echo "Penalties calculated and added successfully.\n"; // Success message
        } else {
            echo "Error adding penalties: " . $conn->error . "\n"; // Error message during penalty insertion
        }
    } else {
        echo "No new overdue books found to apply penalties.\n"; // No overdue books message
    }
} else {
    echo "Error fetching overdue books: " . $conn->error . "\n"; // Error message during overdue books query
}

$conn->close();
?>