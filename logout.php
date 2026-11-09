<?php
session_start();
include('db_connect.php');

// 1. บันทึก Log ก่อนล้าง Session (ถ้ามีข้อมูล user_id อยู่)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $name = $_SESSION['name'] ?? 'Unknown User';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // เขียนคำสั่ง SQL บันทึกลงตาราง system_logs โดยตรง
    $log_sql = "INSERT INTO system_logs (user_id, action, details, ip_address) 
                VALUES (?, 'LOGOUT', ?, ?)";
    $stmt = $conn->prepare($log_sql);
    if ($stmt) {
        $details = "ผู้ใช้งาน: $name ออกจากระบบ";
        $stmt->bind_param("iss", $user_id, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// 2. ล้างข้อมูลและทำลาย Session
session_unset(); 
session_destroy(); 

// 3. ส่งกลับหน้า Login
header("Location: login.php");
exit;
?>