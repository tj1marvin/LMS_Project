<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <!-- Corrected from UFT-8 to UTF-8 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Corrected 'iniitial-scale' to 'initial-scale' -->
    <link rel="stylesheet" href="assets/css/styleS.css"> <!-- Ensure the path is correct -->
    <script src="assets/js/mainS.js" defer></script> <!-- Added 'defer' to ensure the script loads after the HTML -->
    <title>Sign In/Up LMS - Student Library</title> <!-- Added a title for the page -->
</head>
<body>
    <h2>Sign in/up LMS Student Form</h2>
    <div class="container" id="container">
        <div class="form-container sign-up-container">
		<form action="Student-register.php" method="POST" enctype="multipart/form-data">
    <h1>Create Account</h1>
    
    <input type="text" name="first_name" placeholder="First Name" required />
    <input type="text" name="last_name" placeholder="Last Name" required />
    <input type="text" name="enrollment_no" placeholder="Enrollment No" required />
    <input type="text" name="username" placeholder="Username" required />
    <input type="email" name="email" placeholder="Email" required />
    <input type="password" name="password" placeholder="Password" required />
    <input type="text" name="contact" placeholder="Contact Number" />
    
    <!-- File input for student image -->
    <input type="file" name="student_img" accept="image/*" id="studentImgInput" />
    
    <!-- Image preview element -->
    <img id="imagePreview" src="" alt="Image Preview" style="display: none; margin-top: 10px; max-width: 100%; border-radius: 5px;" />
    
    <button type="submit">Sign Up</button>
</form>
        </div>
        <div class="form-container sign-in-container">
            <form action="Student-log.php" method="POST">
                <h1>Sign in</h1>
             <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Password" required />
                <a href="Student-reset.php">Forgot your password?</a>
                <button type="submit">Sign In</button>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>Welcome Back!</h1>
                    <p>To keep connected with us please login with your personal info</p>
                    <button class="ghost" id="signIn">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>Hello, Friend!</h1>
                    <p>Enter your personal details and start your journey with us</p>
                    <button class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
