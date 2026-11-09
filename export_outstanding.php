<?php
include('db_connect.php');
include('auth_function.php');
checkAuth();

date_default_timezone_set('Asia/Bangkok');

// 1. รับค่าการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 2. ตั้งค่าชื่อไฟล์
$filename = "outstanding_loans_" . date('Ymd_His') . ".xls";

// 3. Header สำหรับ Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // ป้องกันภาษาไทยเพี้ยน

// 4. เงื่อนไข SQL (ล็อกสถานะ 'ค้างส่ง')
$where = "WHERE t.status = 'ค้างส่ง'";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (p.full_name LIKE '%$s%' OR p.id_card LIKE '%$s%' OR e.asset_number LIKE '%$s%' OR p.an_number LIKE '%$s%')";
}

$sql = "SELECT t.*, p.full_name, p.an_number, p.phone, p.affiliation,
               p.address_no, p.moo, p.tumbon, p.amphur, p.province,
               e.eq_name, e.asset_number
        FROM loan_transactions t
        JOIN patients p ON t.patient_id = p.patient_id
        JOIN equipment e ON t.eq_id = e.eq_id
        $where ORDER BY t.due_date ASC";

$result = $conn->query($sql);
?>

<table border="1">
    <thead>
        <tr style="background-color: #e74a3b; color: white; font-weight: bold;">
            <th>AN</th>
            <th>ชื่อ-นามสกุล</th>
            <th>ที่อยู่</th>
            <th>เบอร์โทร</th>
            <th>สถานบริการ</th>
            <th>อุปกรณ์</th>
            <th>เลขครุภัณฑ์ (SN)</th>
            <th>วันที่ยืม</th>
            <th>กำหนดคืน</th>
            <th>จำนวนวันค้างส่ง</th>
            <th>มัดจำ</th>
            <th>เล่ม/เลขที่ใบเสร็จ</th>
            <th>หมายเหตุ</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
                $due_date = new DateTime($row['due_date']);
                $today = new DateTime(date('Y-m-d'));
                $interval = $today->diff($due_date);
                $days_diff = (int)$interval->format("%r%a");
                
                $diff_text = ($days_diff < 0) ? "เกินกำหนด " . abs($days_diff) . " วัน" : "เหลืออีก " . $days_diff . " วัน";
                
                $full_addr = "เลขที่ " . $row['address_no'] . " ม." . $row['moo'] . " ต." . $row['tumbon'] . " อ." . $row['amphur'] . " จ." . $row['province'];
            ?>
            <tr>
                <td style="vnd.ms-excel.numberformat:@"><?php echo $row['an_number']; ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($full_addr); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo $row['phone']; ?></td>
                <td><?php echo htmlspecialchars($row['affiliation']); ?></td>
                <td><?php echo htmlspecialchars($row['eq_name']); ?></td>
                <td style="vnd.ms-excel.numberformat:@"><?php echo $row['asset_number']; ?></td>
                <td><?php echo date('d/m/Y', strtotime($row['loan_date'])); ?></td>
                <td><?php echo date('d/m/Y', strtotime($row['due_date'])); ?></td>
                <td style="<?php echo ($days_diff < 0) ? 'color:red;' : ''; ?>">
                    <?php echo $diff_text; ?>
                </td>
                <td style="vnd.ms-excel.numberformat:#,##0.00"><?php echo $row['deposit_amount']; ?></td>
                <td><?php echo $row['receipt_book_no'] . "/" . $row['receipt_no']; ?></td>
                <td><?php echo htmlspecialchars($row['notes']); ?></td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>