<?php
// functions.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('writeLog')) {
    function writeLog($conn, $action, $table_name = null, $record_id = null, $details = null) {
        if (!$conn) return;

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $sql = "INSERT INTO system_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ississ", $user_id, $action, $table_name, $record_id, $details, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>