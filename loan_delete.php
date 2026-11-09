<?php
// loan_delete.php
include('db_connect.php'); // ดึงฟังก์ชัน sendTelegramNotification มาใช้งาน
include_once('functions.php'); // ดึงฟังก์ชัน writeLog มาใช้งาน

date_default_timezone_set('Asia/Bangkok');

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { die("Access Denied."); }

$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : 0;

if ($loan_id > 0) {
    // 1. ดึงข้อมูลรายละเอียดก่อนลบ
    $stmt = $conn->prepare("
        SELECT t.eq_id, p.full_name, e.eq_name, e.asset_number 
        FROM loan_transactions t
        JOIN patients p ON t.patient_id = p.patient_id
        JOIN equipment e ON t.eq_id = e.eq_id
        WHERE t.loan_id = ?
    ");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if ($data) {
        $eq_id = $data['eq_id'];
        $patient_name = $data['full_name'];
        $eq_details = $data['eq_name'] . " (" . $data['asset_number'] . ")";
        $staff_name = $_SESSION['name'] ?? 'ไม่ระบุชื่อ';

        $conn->begin_transaction();
        try {
            // 2. ลบรายการยืม
            $del = $conn->prepare("DELETE FROM loan_transactions WHERE loan_id = ?");
            $del->bind_param("i", $loan_id);
            $del->execute();

            // 3. คืนสต็อกอุปกรณ์ (available_qty = 1)
            $upd = $conn->prepare("UPDATE equipment SET available_qty = 1 WHERE eq_id = ?");
            $upd->bind_param("i", $eq_id);
            $upd->execute();

            // 4. บันทึก Log ลงฐานข้อมูล
            $log_msg = "ลบรายการยืม ID: $loan_id ของคุณ $patient_name อุปกรณ์: $eq_details";
            writeLog($conn, 'DELETE_LOAN', 'loan_transactions', $loan_id, $log_msg);

            // 5. --- เพิ่มส่วนแจ้งเตือนผ่าน Telegram ---
            $tg_message = "⚠️ <b>มีการลบรายการยืมอุปกรณ์</b>\n";
            $tg_message .= "รายการ ID: " . $loan_id . "\n";
            $tg_message .= "ผู้ป่วย: " . $patient_name . "\n";
            $tg_message .= "อุปกรณ์: " . $eq_details . "\n";
            $tg_message .= "ผู้ดำเนินการ: " . $staff_name . "\n";
            $tg_message .= "เวลา: " . date('d/m/Y H:i:s') . " น.";
            
            sendTelegramNotification($tg_message); // เรียกใช้ฟังก์ชันจาก db_connect.php

            $conn->commit();
            echo "<script>alert('ลบข้อมูลและแจ้งเตือนเรียบร้อยแล้ว'); window.location.href='outstanding_list.php';</script>";
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Error: " . $e->getMessage() . "'); window.history.back();</script>";
        }
    }
}
?>