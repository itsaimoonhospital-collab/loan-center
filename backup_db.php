<?php
session_start();
include('db_connect.php');
include_once('functions.php');

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

// ตั้งค่าชื่อไฟล์สำรองข้อมูล
$filename = "backup_hospital_" . date("Y-m-d_H-i-s") . ".sql";

// ส่วนการตั้งค่า Header สำหรับดาวน์โหลดไฟล์
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . $filename);

// ดึงรายชื่อตารางทั้งหมดในฐานข้อมูล
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sql_dump = "-- Database Backup: " . date("Y-m-d H:i:s") . "\n";
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// วนลูปเพื่อสร้างคำสั่ง SQL สำหรับแต่ละตาราง
foreach ($tables as $table) {
    // สร้างคำสั่ง DROP และ CREATE TABLE
    $res = $conn->query("SHOW CREATE TABLE $table");
    $row = $res->fetch_row();
    $sql_dump .= "\n\n" . $row[1] . ";\n\n";

    // ดึงข้อมูลในตารางออกมาสร้างเป็นคำสั่ง INSERT
    $result = $conn->query("SELECT * FROM $table");
    while ($row_data = $result->fetch_assoc()) {
        $sql_dump .= "INSERT INTO $table VALUES(";
        $values = array();
        foreach ($row_data as $value) {
            $values[] = "'" . $conn->real_escape_string($value) . "'";
        }
        $sql_dump .= implode(", ", $values) . ");\n";
    }
}

$sql_dump .= "\n\nSET FOREIGN_KEY_CHECKS=1;";

// บันทึก Log การสำรองข้อมูล
if (function_exists('writeLog')) {
    writeLog($conn, 'BACKUP_DATABASE', null, null, "Admin สำรองข้อมูลฐานข้อมูลทั้งหมดลงไฟล์ $filename");
}

echo $sql_dump;
exit;