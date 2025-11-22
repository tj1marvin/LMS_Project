<?php

/**
 * analytics-dashboard-functions.php
 *
 * Contains functions for fetching data for the analytics dashboard.
 */

/**
 * Database Connection File Inclusion
 * Includes database credentials and connection settings.
 */
require_once 'db_connection.php';

/**
 * Database Connection Function
 * Establishes a connection to the MySQL database using credentials from db_connection.php.
 * Returns a database connection object or NULL on failure, logs error.
 * @return mysqli|null Database connection object or null on failure.
 */
function connect_db()
{
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error); // Log detailed error
        return null; // Return null to indicate failure, handle error upstream
    }
    return $conn;
}

/**
 * Function to Get Total Book Count (SECURE - Prepared Statement)
 * @param mysqli $conn Database connection object.
 * @return int Total book count, or 0 on error.
 */
function getTotalBooksCount($conn) {
    $sql = "SELECT COUNT(*) AS total_books FROM books";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['total_books'];
    }
    return 0;
}

/**
 * Function to Get Total Student Count (SECURE - Prepared Statement)
 * @param mysqli $conn Database connection object.
 * @return int Total student count, or 0 on error.
 */
function getTotalStudentsCount($conn) {
    $sql = "SELECT COUNT(*) AS total_students FROM students_registration";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return (int)$row['total_students'];
    }
    return 0;
}

/**
 * Function to Get Book Issue Trend Data (Last 6 Months - SECURE - Prepared Statement)
 * @param mysqli $conn Database connection object.
 * @param int $months Number of months to retrieve data for (default 6).
 * @return array Array with months as keys and issue counts as values.
 */
function getBookIssueTrendData($conn, $months = 6) {
    $data = [];
    for ($i = 0; $i < $months; $i++) {
        $month = date("Y-m", strtotime("-$i months"));
        $startDate = date("Y-m-01 00:00:00", strtotime($month));
        $endDate = date("Y-m-t 23:59:59", strtotime($month)); // 't' gets last day of month

        $sql = "SELECT COUNT(*) AS issue_count FROM issued_books WHERE issue_date >= ? AND issue_date <= ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("getBookIssueTrendData Prepare Error: " . $conn->error);
            continue; // Skip to next month on error, log error
        }

        $stmt->bind_param("ss", $startDate, $endDate);
        if (!$stmt->execute()) {
            error_log("getBookIssueTrendData Execute Error: " . $stmt->error);
            $stmt->close();
            continue; // Skip to next month on error, log error
        }

        $result = $stmt->get_result();
        $issueCount = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $issueCount = (int)$row['issue_count'];
        }
        $stmt->close();

        $data[date("M", strtotime($month))] = $issueCount; // Month abbreviation as label
    }
    return array_reverse($data); // Reverse to chronological order (Jan-Feb-Mar...)
}


/**
 * Function to Get Total Count Function (SECURE - Prepared Statement)
 * Executes a SQL query to count total records in a given table, using prepared statement.
 * @param mysqli $conn Database connection object.
 * @param string $tableName Name of the table to query.
 * @param string $condition Optional WHERE clause condition (should be parameterized for dynamic conditions).
 * @param array $params Parameter array for prepared statement if using dynamic condition.
 * @return int Total count of records, or 0 on error.
 */
function getTotalCount($conn, $tableName, $condition = '', $params = [])
{
    $sql = "SELECT COUNT(*) as total FROM " . $tableName;
    if ($condition) {
        $sql .= " WHERE " . $condition;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("getTotalCount SQL Prepare Error: " . $conn->error); // Log prepare error
        return 0; // Handle prepare error
    }

    if ($params) {
        $types = str_repeat('s', count($params)); // Assuming string parameters, adjust if needed
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log("getTotalCount SQL Execute Error: " . $stmt->error); // Log execute error
        return 0; // Handle execute error
    }

    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['total'];
    }

    $stmt->close();
    return 0;
}