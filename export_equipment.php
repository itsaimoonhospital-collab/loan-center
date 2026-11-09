<?php
session_start();
include('db_connect.php');
include_once('functions.php');

// ตรวจสอบสิทธิ์ Admin ก่อนให้ดาวน์โหลด
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

// ตั้งค่า Header เพื่อบอก Browser ว่านี่คือไฟล์ Excel (CSV)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Equipment_Report_'.date('Y-m-d').'.csv');

// ปรับแต่งให้รองรับภาษาไทยใน Excel (BOM)
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// เขียนหัวตาราง
fputcsv($output, array('ลำดับ', 'เลขครุภัณฑ์', 'รายการอุปกรณ์', 'สถานะปัจจุบัน', 'ยอดทั้งหมด', 'ชำรุด', 'คงเหลือพร้อมใช้'));

// ดึงข้อมูลอุปกรณ์ที่ยังใช้งานอยู่
$sql = "SELECT * FROM equipment WHERE is_active = 1 ORDER BY eq_name ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        // วิเคราะห์สถานะ
        $is_broken = ($row['broken_qty'] == 1);
        $is_borrowed = ($row['available_qty'] == 0 && !$is_broken);
        
        $status_text = 'พร้อมใช้งาน';
        if ($is_broken) { $status_text = 'ชำรุด'; }
        elseif ($is_borrowed) { $status_text = 'ถูกยืม'; }

        // เขียนข้อมูลลงไฟล์ CSV
        fputcsv($output, array(
            $i++,
            $row['asset_number'],
            $row['eq_name'],
            $status_text,
            $row['total_qty'],
            $row['broken_qty'],
            $row['available_qty']
        ));
    }
}

// บันทึก Log การส่งออกข้อมูล
if (function_exists('writeLog')) {
    writeLog($conn, 'EXPORT_EXCEL', 'equipment', null, "ส่งออกรายงานครุภัณฑ์เป็นไฟล์ Excel");
}

fclose($output);
exit;
?>