<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        // หากไม่ได้ Login ให้เด้งไปหน้า Login
        header("Location: login.php");
        exit();
    }
}
?>