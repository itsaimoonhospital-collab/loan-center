<?php
// db_connect.php
$servername = "localhost";
$username = "root"; 
$password = "root"; 
$dbname = "patient_loan_db"; 


// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// --- ตั้งค่าสำหรับการแจ้งเตือน Telegram ---
// 1. Token ได้จาก @BotFather
// 2. Chat ID ได้จาก @userinfobot หรือ @GetChatID_Bot
// 8087787849:AAFkRD_3FqM5w5M6ah1HAfbQcKDvtb3F5K8
//-5013380025
define('TELEGRAM_BOT_TOKEN', '8087787849:AAFkRD_3FqM5w5M6ah1HAfbQcKDvtb3F5K8');
define('TELEGRAM_CHAT_ID', '-5013380025');

/**
 * ฟังก์ชันสำหรับส่งแจ้งเตือนผ่าน Telegram
 */
function sendTelegramNotification($message) {
    if (empty(TELEGRAM_BOT_TOKEN) || empty(TELEGRAM_CHAT_ID)) {
        return false;
    }

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'html' // รองรับการจัดรูปแบบตัวหนา ขีดเส้นใต้ด้วย HTML
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ช่วยให้ส่งได้แม้ SSL Server มีปัญหา
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return "cURL Error: " . $error;
    }
    return $result;
}
?>