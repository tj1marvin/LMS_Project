 document.addEventListener("DOMContentLoaded", function() {
    const signUpButton = document.getElementById('signUp');
    const signInButton = document.getElementById('signIn');
    const container = document.getElementById('container');

    signUpButton.addEventListener('click', () => {
        container.classList.add("right-panel-active");
    });

    signInButton.addEventListener('click', () => {
        container.classList.remove("right-panel-active");
    });
}); 


document.addEventListener("DOMContentLoaded", function() {
    const resetForm = document.getElementById('reset-form');

    resetForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const username = document.getElementById('username').value;

        // Call the PHP script to reset the password
        await fetch('reset_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' 
},
            body: `username=${username}`
        });

        alert(`Password reset for ${username}`);
    });
});




$(document).ready(function() {
    $('#reset-password-form').submit(function(event) {
      event.preventDefault();
      var email = $('input[name="email"]').val();
      $.ajax({
        type: 'POST',
        url: '/reset_password',
        data: { email: email },
        success: function(data) {
          if (data.success) {
            alert('Password reset link sent to your email!');
          } else {
            alert('Error resetting password. Please try again.');
          }
        }
      });
    });
  });

  document.addEventListener("DOMContentLoaded", function() {
    const resetForm = document.getElementById('reset-form');

    resetForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const username = document.getElementById('username').value;

        // Call the PHP script to reset the password
        const response = await fetch('reset_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `username=${username}`
        });

        const result = await response.text();
        alert(result);
    });
});
