<?php
include('header.php');
include('db_connect.php');
checkAuth();

date_default_timezone_set('Asia/Bangkok');

// 1. รับค่าตัวกรองและค้นหา
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$status_filter = isset($_POST['status_filter']) ? $_POST['status_filter'] : 'all';

// 2. สร้างเงื่อนไข SQL
$where = "WHERE 1=1"; 
if (!empty($search)) {
    $where .= " AND (p.full_name LIKE ? OR p.id_card LIKE ? OR t.receipt_no LIKE ? OR t.snap_tumbon LIKE ? OR t.snap_phone LIKE ? OR p.affiliation LIKE ?)";
}
if ($status_filter != 'all') {
    $where .= " AND t.status = ?";
}

// 3. SQL Query ดึงข้อมูล (JOIN u2 เพื่อดึงชื่อผู้รับคืน)
$sql = "SELECT t.*, p.full_name, p.an_number, p.affiliation,
               t.snap_address_no, t.snap_moo, t.snap_tumbon, t.snap_amphur, t.snap_province, t.snap_phone,
               e.eq_name, e.asset_number,
               u1.name as staff_name,
               u2.name as return_staff_name 
        FROM loan_transactions t 
        JOIN patients p ON t.patient_id = p.patient_id 
        JOIN equipment e ON t.eq_id = e.eq_id 
        LEFT JOIN users u1 ON t.created_by = u1.user_id 
        LEFT JOIN users u2 ON t.returned_by = u2.user_id 
        $where ORDER BY t.loan_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($search) && $status_filter != 'all') {
    $s = "%$search%";
    $stmt->bind_param("sssssss", $s, $s, $s, $s, $s, $s, $status_filter);
} elseif (!empty($search)) {
    $s = "%$search%";
    $stmt->bind_param("ssssss", $s, $s, $s, $s, $s, $s);
} elseif ($status_filter != 'all') {
    $stmt->bind_param("s", $status_filter);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    .table-history { font-size: 0.8rem; } 
    .table-history th { background-color: #f8f9fc; color: #4e73df; padding: 10px; font-weight: 700; text-align: center; border-bottom: 2px solid #e3e6f0; }
    .table-history td { vertical-align: middle !important; padding: 8px !important; color: #444; }
    .text-patient-name { font-size: 0.85rem; font-weight: 700; color: #1a1a1a; margin-bottom: 2px; }
    .address-info { font-size: 0.72rem; color: #6c757d; line-height: 1.3; }
    .affiliation-badge { font-size: 0.7rem; color: #4e73df; background: #eaecf4; padding: 3px 10px; border-radius: 15px; font-weight: 600; border: 1px solid #d1d3e2; }
    .staff-badge { font-size: 0.7rem; color: #5a5c69; background: #f8f9fc; padding: 2px 8px; border-radius: 10px; border: 1px solid #ddd; display: block; margin-bottom: 2px; }
    .return-staff-badge { font-size: 0.7rem; color: #1cc88a; background: #e3fcef; padding: 2px 8px; border-radius: 10px; border: 1px solid #c3e6cb; display: block; }
    .img-evidence-thumb { width: 32px; height: 32px; object-fit: cover; cursor: pointer; border-radius: 4px; border: 1px solid #ddd; transition: 0.2s; margin: 1px; }
    .img-evidence-thumb:hover { transform: scale(1.2); z-index: 5; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    .badge-status { font-size: 0.7rem; padding: 4px 8px; border-radius: 12px; }
    .blink { animation: blinker 1.2s linear infinite; font-weight: bold; }
    @keyframes blinker { 50% { opacity: 0.3; } }
</style>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-2 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history mr-1"></i> ประวัติการยืม-คืนอุปกรณ์</h6>
            <div class="d-flex d-print-none">
                <form method="POST" class="form-inline mr-2">
                    <input type="text" name="search" class="form-control form-control-sm mr-2 shadow-sm" placeholder="ค้นชื่อ, ตำบล, เบอร์โทร..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status_filter" class="form-control form-control-sm mr-2 shadow-sm">
                        <option value="all">ทุกสถานะ</option>
                        <option value="ค้างส่ง" <?= $status_filter == 'ค้างส่ง' ? 'selected' : '' ?>>ค้างส่ง</option>
                        <option value="ส่งคืนแล้ว" <?= $status_filter == 'ส่งคืนแล้ว' ? 'selected' : '' ?>>ส่งคืนแล้ว</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm">ค้นหา</button>
                    <button type="button" onclick="exportData()" class="btn btn-success btn-sm shadow-sm ml-2">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </button>
                </form>
            </div>
        </div>

        <div class="card-body p-1">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-history mb-0 text-center">
                    <thead>
                        <tr>
                            <th>ID</th> <th>AN</th> <th>ชื่อ-ที่อยู่ขณะยืม</th>
                            <th>สถานบริการ</th> <th>อุปกรณ์</th> 
                            <th>วันที่ยืม</th> 
                            <th class="text-danger">กำหนดคืน</th> <th>วันที่คืนจริง</th> <th width="110">มัดจำ</th>
                            <th width="85">หลักฐาน</th> <th>สถานะ</th>
                            <th>ผู้ให้ยืม / ผู้รับคืน</th> <th class="d-print-none">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $is_overdue = ($row['status'] == 'ค้างส่ง' && strtotime(date('Y-m-d')) > strtotime($row['due_date']));
                            
                            // แก้ไข: เพิ่ม evidence_img4 เข้าไปใน Array หลักฐาน
                            $img_list = array_filter([$row['evidence_img'], $row['evidence_img2'], $row['evidence_img3'], $row['evidence_img4']]);
                            
                            $addr = "เลขที่ " . ($row['snap_address_no'] ?: '-') . " ม." . ($row['snap_moo'] ?: '-') . " ต." . ($row['snap_tumbon'] ?: '-') . " อ." . ($row['snap_amphur'] ?: '-') . " จ." . ($row['snap_province'] ?: '-');
                        ?>
                        <tr class="<?= ($row['status'] == 'ส่งคืนแล้ว') ? 'bg-light text-muted' : '' ?>">
                            <td class="small">#<?= $row['loan_id'] ?></td>
                            <td class="font-weight-bold small text-primary"><?= $row['an_number'] ?: '-' ?></td>
                            <td class="text-left">
                                <div class="text-patient-name"><?= htmlspecialchars($row['full_name']) ?></div>
                                <div class="address-info"><i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($addr) ?></div>
                                <small class="font-weight-bold text-primary"><?= $row['snap_phone'] ?: '-' ?></small>
                            </td>
                            <td><span class="affiliation-badge"><?= htmlspecialchars($row['affiliation'] ?: 'ไม่ระบุ') ?></span></td>
                            <td class="text-left">
                                <div class="text-info font-weight-bold small"><?= htmlspecialchars($row['eq_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.65rem;">S/N: <?= $row['asset_number'] ?></div>
                            </td>
                            <td class="small"><?= date('d/m/Y', strtotime($row['loan_date'])) ?></td>
                            <td class="small">
                                <span class="<?= $is_overdue ? 'text-danger blink' : '' ?>">
                                    <?= date('d/m/Y', strtotime($row['due_date'])) ?>
                                </span>
                            </td>
                            <td class="small">
                                <?php if($row['status'] == 'ส่งคืนแล้ว'): ?>
                                    <span class="text-success font-weight-bold"><?= date('d/m/Y', strtotime($row['return_date'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-weight-bold">
                                <?= number_format($row['deposit_amount'], 2) ?>
                                <div class="small text-muted font-weight-normal" style="font-size: 0.65rem;">เล่ม:<?= $row['receipt_book_no'] ?> เลข:<?= $row['receipt_no'] ?></div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-center" style="width: 70px; margin: 0 auto;">
                                    <?php foreach($img_list as $img): ?>
                                        <img src="uploads/<?= $img ?>" class="img-evidence-thumb open-modal-trigger" data-img="uploads/<?= $img ?>">
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-status <?= ($row['status'] == 'ค้างส่ง') ? 'badge-danger' : 'badge-success' ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="staff-badge"><i class="fas fa-user-edit fa-xs"></i> <?= htmlspecialchars($row['staff_name'] ?: 'N/A') ?></span>
                                <?php if($row['status'] == 'ส่งคืนแล้ว'): ?>
                                <span class="return-staff-badge"><i class="fas fa-user-check fa-xs"></i> <?= htmlspecialchars($row['return_staff_name'] ?: 'N/A') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="d-print-none">
                                <div class="btn-group btn-group-sm">
                                    <?php if($row['status'] == 'ค้างส่ง'): ?>
                                        <a href="return_process.php?loan_id=<?= $row['loan_id'] ?>" class="btn btn-success"><i class="fas fa-undo"></i></a>
                                    <?php endif; ?>
                                    <a href="generate_pdf.php?loan_id=<?= $row['loan_id'] ?>" target="_blank" class="btn btn-info"><i class="fas fa-print"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="13" class="text-center py-5">ไม่พบข้อมูลประวัติในระบบ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

    <script>
    function exportData() {
        // 1. ดึงค่าจากตัวกรองที่มีอยู่ในหน้าจอ
        // ตรวจสอบชื่อ name ให้ตรงกับ input/select ในหน้าหลักของคุณ
        const searchVal = document.querySelector('input[name="search"]').value;
        const statusVal = document.querySelector('select[name="status_filter"]').value;

        // 2. สร้าง URL พร้อมพารามิเตอร์
        const baseUrl = 'export_history.php';
        const params = new URLSearchParams({
            search: searchVal,
            status: statusVal
        });

        // 3. เปิดหน้าต่างใหม่เพื่อดาวน์โหลดไฟล์
        window.location.href = baseUrl + '?' + params.toString();
    }
    </script>

<?php include('footer.php'); ?>