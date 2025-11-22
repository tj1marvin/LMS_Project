<?php

// Start the session
session_start();

// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS"; // replace with your database name

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode([]);
    exit();
}

$student_id = $_SESSION['student_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Get current page from URL, default to 1
$limit = 12; // Number of books per page
$category = isset($_GET['category']) ? $_GET['category'] : 'recommended'; // Default to recommended

// Function to fetch recommended books based on reading history with pagination
function fetch_recommended_books($conn, $student_id, $page, $limit) {
    // Get reading history from the database
    $history_sql = "SELECT b.genre 
                    FROM borrowing_history bh 
                    JOIN books b ON bh.book_id = b.id 
                    WHERE bh.student_id = ?";
    $history_stmt = $conn->prepare($history_sql);
    $history_stmt->bind_param("i", $student_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();

    $student_history = [];
    while ($row = $history_result->fetch_assoc()) {
        $student_history[] = $row['genre'];
    }
    $history_stmt->close();

    // Calculate genre frequencies
    $genre_counts = array_count_values($student_history);
    arsort($genre_counts);
    $top_genres = array_slice(array_keys($genre_counts), 0, 3);

    // Get book recommendations based on top genres with pagination
    $offset = ($page - 1) * $limit;
    $recommended_books = [];
    foreach ($top_genres as $genre) {
        $recommend_sql = "SELECT id, title, author, genre, image_path FROM books WHERE genre = ? LIMIT ?, ?";
        $recommend_stmt = $conn->prepare($recommend_sql);
        $recommend_stmt->bind_param("sii", $genre, $offset, $limit);
        $recommend_stmt->execute();
        $recommend_result = $recommend_stmt->get_result();

        while ($row = $recommend_result->fetch_assoc()) {
            $recommended_books[] = $row;
        }
        $recommend_stmt->close();
    }

    return $recommended_books;
}

// Function to fetch popular books
function fetch_popular_books($conn, $page, $limit) {
    $offset = ($page - 1) * $limit;
    $popular_sql = "SELECT id, title, author, genre, image_path FROM books ORDER BY popularity DESC LIMIT ?, ?";
    $popular_stmt = $conn->prepare($popular_sql);
    $popular_stmt->bind_param("ii", $offset, $limit);
    $popular_stmt->execute();
    $popular_result = $popular_stmt->get_result();

    $popular_books = [];
    while ($row = $popular_result->fetch_assoc()) {
        $popular_books[] = $row;
    }
    $popular_stmt->close();

    return $popular_books;
}

// Function to fetch latest books
function fetch_latest_books($conn, $page, $limit) {
    $offset = ($page - 1) * $limit;
    $latest_sql = "SELECT id, title, author, genre, image_path FROM books ORDER BY created_at DESC LIMIT ?, ?";
    $latest_stmt = $conn->prepare($latest_sql);
    $latest_stmt->bind_param("ii", $offset, $limit);
    $latest_stmt->execute();
    $latest_result = $latest_stmt->get_result();

    $latest_books = [];
    while ($row = $latest_result->fetch_assoc()) {
        $latest_books[] = $row;
    }
    $latest_stmt->close();

    return $latest_books;
}
// Function to fetch most viewed books
function fetch_most_viewed_books($conn, $page, $limit) {
    $offset = ($page - 1) * $limit;
    $most_viewed_sql = "SELECT id, title, author, genre, image_path FROM books ORDER BY views DESC LIMIT ?, ?";
    $most_viewed_stmt = $conn->prepare($most_viewed_sql);
    $most_viewed_stmt->bind_param("ii", $offset, $limit);
    $most_viewed_stmt->execute();
    $most_viewed_result = $most_viewed_stmt->get_result();

    $most_viewed_books = [];
    while ($row = $most_viewed_result->fetch_assoc()) {
        $most_viewed_books[] = $row;
    }
    $most_viewed_stmt->close();

    return $most_viewed_books;
}

// Determine which category to fetch
switch ($category) {
    case 'recommended':
        $books = fetch_recommended_books($conn, $student_id, $page, $limit);
        break;
    case 'popular':
        $books = fetch_popular_books($conn, $page, $limit);
        break;
    case 'latest':
        $books = fetch_latest_books($conn, $page, $limit);
        break;
    case 'most_viewed':
        $books = fetch_most_viewed_books($conn, $page, $limit);
        break;
    default:
        $books = [];
        break;
}
// Return the books as a JSON response
echo json_encode($books);

// Close the database connection
$conn->close();


?>
