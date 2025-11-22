<?php
session_start();

// Ensure user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection and functions
require_once 'db_connection.php';
require_once 'analytics-dashboard-functions.php';

// Include TCPDF library
require_once 'tcpdf/tcpdf.php';

// Establish database connection
$conn = connect_db();
if (!$conn) {
    die("Database connection failed.");
}

// Fetch data from the database
$bookData = getBookData($conn);
$studentData = getStudentData($conn);
$issueData = getIssueData($conn);

// Check if data is available
if (empty($bookData) && empty($studentData) && empty($issueData)) {
    // No Data PDF generation
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Library Admin');
    $pdf->SetTitle('Library Data Report - No Data');
    $pdf->SetSubject('No Data Available');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Library Data Report', 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'No data available to generate the report.', 0, 1, 'C');
    $pdf->Output('library_data_report.pdf', 'D');
    exit;
} else {
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Library Admin');
    $pdf->SetTitle('Library Data Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->AddPage();

    // Report Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'Library Data Report', 0, 1, 'C');
    $pdf->Ln(10);

    // Output Book Data
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Books Information', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    foreach ($bookData as $book) {
        $pdf->Cell(0, 10, "Title: " . htmlspecialchars($book['title']) . ", Author: " . htmlspecialchars($book['author']) . ", ISBN: " . htmlspecialchars($book['isbn']), 0, 1);
    }
    $pdf->Ln(5);

    // Output Student Data
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Students Information', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    foreach ($studentData as $student) {
        $pdf->Cell(0, 10, "Name: " . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . ", Enrollment No: " . htmlspecialchars($student['enrollment_no']), 0, 1);
    }
    $pdf->Ln(5);

    // Output Issue Data
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Issued Books Information', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    foreach ($issueData as $issue) {
        $pdf->Cell(0, 10, "Book ID: " . htmlspecialchars($issue['book_id']) . ", Student ID: " . htmlspecialchars($issue['student_id']) . ", Issue Date: " . htmlspecialchars($issue['issue_date']), 0
        , 1);
    }
    $pdf->Ln(5);

    // --- Output PDF ---
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="library_data_report.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    ini_set('zlib.output_compression', '0');
    $pdf->Output('library_data_report.pdf', 'D');
}

// Close database connection
if ($conn) {
    $conn->close();
}
exit;

// Function to fetch book data
function getBookData($conn) {
    $sql = "SELECT title, author, isbn FROM books";
    $result = $conn->query($sql);
    $books = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
    }
    return $books;
}

// Function to fetch student data
function getStudentData($conn) {
    $sql = "SELECT first_name, last_name, enrollment_no FROM students_registration";
    $result = $conn->query($sql);
    $students = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    return $students;
}

// Function to fetch issued book data
function getIssueData($conn) {
    $sql = "SELECT book_id, student_id, issue_date FROM issue_book";
    $result = $conn->query($sql);
    $issues = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $issues[] = $row;
        }
    }
    return $issues;
}
