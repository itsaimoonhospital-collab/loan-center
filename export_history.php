<?php
include('db_connect.php');
include('auth_function.php'); // เรียกใช้เพื่อตรวจสอบสิทธิ์
checkAuth(); 

// ตั้งค่า Timezone ให้ตรงกับเวลาไทย
date_default_timezone_set('Asia/Bangkok'); 

// 1. รับค่าตัวกรอง
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 2. ตั้งค่าชื่อไฟล์
$filename = "loan_history_" . date('Ymd_His') . ".xls";

// 3. จัดการ Header สำหรับ Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 4. เงื่อนไขการค้นหา
$where = "WHERE 1=1";
if ($filter_status != 'all') {
    $where .= " AND t.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if ($search != '') {
    $s = $conn->real_escape_string($search);
    // ปรับเงื่อนไขการค้นหาให้ครอบคลุม Snapshot
    $where .= " AND (p.full_name LIKE '%$s%' OR p.an_number LIKE '%$s%' OR e.eq_name LIKE '%$s%' OR t.snap_tumbon LIKE '%$s%' OR t.snap_amphur LIKE '%$s%')";
}

// 5. SQL ดึงข้อมูล: ใช้ข้อมูล snap_ จาก loan_transactions แทน patients
$sql = "SELECT t.*, p.full_name, p.an_number, p.id_card,
               e.eq_name, e.asset_number, u.name as staff_name
        FROM loan_transactions t 
        JOIN patients p ON t.patient_id = p.patient_id 
        JOIN equipment e ON t.eq_id = e.eq_id 
        LEFT JOIN users u ON t.created_by = u.user_id 
        $where ORDER BY t.loan_id DESC";

$result = $conn->query($sql);
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-type" content="text/html;charset=utf-8" />
</head>
<body>
    <table border="1">
        <thead>
            <tr style="background-color: #4e73df; color: white; font-weight: bold;">
                <th>AN</th>
                <th>ID</th>
                <th>วันที่ยืม</th>
                <th>กำหนดคืน</th>
                <th>วันที่คืนจริง</th>
                <th>ชื่อผู้ป่วย</th>
                <th>ผู้ยืมแทน</th> <th>ความสัมพันธ์</th>
                <th>ที่อยู่ขณะยืม (Snapshot)</th> <th>เบอร์โทร</th>
                <th>สถานบริการ/รพ.สต.</th>
                <th>ชื่ออุปกรณ์</th>
                <th>เลขครุภัณฑ์ (SN)</th>
                <th>ค่ามัดจำ</th>
                <th>เล่มใบเสร็จ/เลขที่</th>
                <th>สถานะ</th>
                <th>ผู้บันทึก</th>
                <th>หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                    $is_overdue = ($row['status'] == 'ค้างส่ง' && strtotime(date('Y-m-d')) > strtotime($row['due_date']));
                    $status_text = $row['status'];
                    if($is_overdue) $status_text .= " (เกินกำหนด)";

                    // รวมที่อยู่จากข้อมูล Snapshot
                    $full_addr = "เลขที่ " . ($row['snap_address_no'] ?: '-') . 
                                 " ม." . ($row['snap_moo'] ?: '-') . 
                                 " ต." . ($row['snap_tumbon'] ?: '-') . 
                                 " อ." . ($row['snap_amphur'] ?: '-') . 
                                 " จ." . ($row['snap_province'] ?: '-');
                ?>
                <tr>
                    <td style="vnd.ms-excel.numberformat:@"><?php echo $row['an_number']; ?></td>
                    <td><?php echo $row['loan_id']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['loan_date'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['due_date'])); ?></td>
                    <td><?php echo ($row['return_date']) ? date('d/m/Y', strtotime($row['return_date'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['borrower_name'] ?: '-'); ?></td> <td><?php echo htmlspecialchars($row['borrower_relation'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($full_addr); ?></td>
                    <td style="vnd.ms-excel.numberformat:@"><?php echo $row['snap_phone'] ?: $row['phone']; ?></td>
                    <td><?php echo htmlspecialchars($row['affiliation']); ?></td>
                    <td><?php echo htmlspecialchars($row['eq_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['asset_number']); ?></td>
                    <td style="vnd.ms-excel.numberformat:#,##0.00"><?php echo $row['deposit_amount']; ?></td>
                    <td><?php echo "เล่ม " . $row['receipt_book_no'] . " เลขที่ " . $row['receipt_no']; ?></td>
                    <td style="color: <?php echo ($row['status'] == 'ค้างส่ง') ? '#e74a3b' : '#1cc88a'; ?>;">
                        <?php echo $status_text; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['notes']); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>