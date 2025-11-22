<?php
require 'vendor/autoload.php'; // Include the Stripe PHP library

// Database connection parameters
$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "LMS"; // replace with your database name

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
/*
// Set your secret key. Remember to switch to your live secret key in production!
\Stripe\Stripe::setApiKey('YOUR_SECRET_KEY'); // Replace with your secret key

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve the token and other payment details from the form
    $token = $_POST['stripeToken'];
    $studentId = $_POST['student_id']; // Assuming you pass the student ID from the form
    $amount = $_POST['amount']; // Amount in cents (e.g., $10.00 = 1000 cents)
    $paymentMethod = 'online'; // Payment method
    $transactionId = null; // Initialize transaction ID

    try {
        // Create a charge: this will charge the user's card
        $charge = \Stripe\Charge::create([
            'amount' => $amount,
            'currency' => 'usd',
            'description' => 'Library Fine Payment',
            'source' => $token,
        ]);

        // Get the transaction ID from the charge response
        $transactionId = $charge->id;

        // Save payment details to the database
        $sql = "INSERT INTO payments (student_id, amount, payment_date, payment_method, transaction_id) VALUES (?, ?, NOW(), ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idss", $studentId, $amount, $paymentMethod, $transactionId);

        if ($stmt->execute()) {
            echo "Payment successful! Your transaction ID is: " . htmlspecialchars($transactionId);
        } else {
            echo "Error recording payment: " . $stmt->error;
        }

        $stmt->close();
    } catch (\Stripe\Exception\CardException $e) {
        // Handle card errors
        echo "Payment failed: " . $e->getMessage();
    } catch (Exception $e) {
        // Handle other errors
        echo "An error occurred: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}*/

// Close the database connection
$conn->close();
?>
