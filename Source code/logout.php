<?php
session_start(); // Ensure session is started before destroying it
session_unset();
session_destroy();
// Optional: Clear any "remember me" cookies if you implemented them
// if (isset($_COOKIE['remember_me_token'])) {
//     unset($_COOKIE['remember_me_token']);
//     setcookie('remember_me_token', '', time() - 3600, "/"); // Expire the cookie
// }
header("Location: index.php?logout=success"); // Redirect to homepage or login page
exit();
?>