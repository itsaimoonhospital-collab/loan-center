<?php
include('header.php');
include('db_connect.php');
checkAuth();

// 1. รับค่าการค้นหา
$search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';

// 2. เงื่อนไขการค้นหา (กรองเฉพาะค้างส่ง)
$where_clause = "t.status = 'ค้างส่ง'"; 
if (!empty($search_query)) {
    $where_clause .= " AND (p.full_name LIKE ? OR p.id_card LIKE ? OR e.asset_number LIKE ? OR p.an_number LIKE ? OR t.receipt_no LIKE ? OR t.receipt_book_no LIKE ?)";
}

// 3. SQL Query ดึงข้อมูล
$outstanding_sql = "
    SELECT 
        t.*, p.full_name, p.an_number, p.phone, p.affiliation,
        p.address_no, p.moo, p.tumbon, p.amphur, p.province,
        e.eq_name, e.asset_number
    FROM 
        loan_transactions t
    JOIN 
        patients p ON t.patient_id = p.patient_id
    JOIN 
        equipment e ON t.eq_id = e.eq_id
    WHERE 
        $where_clause
    ORDER BY 
        t.due_date ASC;
";

$stmt = $conn->prepare($outstanding_sql);
if (!empty($search_query)) {
    $param = "%$search_query%";
    $stmt->bind_param("ssssss", $param, $param, $param, $param, $param, $param);
}
$stmt->execute();
$outstanding_result = $stmt->get_result();
?>

<style>
    .table-outstanding { font-size: 0.85rem; } 
    .table-outstanding th { background-color: #f8f9fc; color: #4e73df; font-weight: 700; text-align: center; vertical-align: middle !important; }
    .table-outstanding td { vertical-align: middle !important; color: #444; }
    .an-badge { background-color: #e74a3b; color: white; padding: 2px 8px; border-radius: 15px; font-weight: bold; font-size: 0.75rem; }
    .phone-link { color: #4e73df; font-weight: bold; text-decoration: none; font-size: 0.85rem; }
    .img-evidence-thumb { width: 40px; height: 40px; object-fit: cover; cursor: pointer; border-radius: 4px; border: 1px solid #ddd; margin: 1px; transition: transform 0.2s; }
    .img-evidence-thumb:hover { transform: scale(1.1); z-index: 5; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    
    .row-overdue { background-color: rgba(231, 74, 59, 0.1) !important; } 
    .row-near { background-color: rgba(246, 194, 62, 0.1) !important; }    
    .blink-red { animation: blinker 1.2s linear infinite; color: #e74a3b; font-weight: bold; }
    @keyframes blinker { 50% { opacity: 0.2; } }

    .evidence-group { display: flex; flex-wrap: wrap; justify-content: center; min-width: 90px; }
    .addr-text { font-size: 0.75rem; line-height: 1.2; color: #666; margin-top: 3px; display: block; }
</style>

<div class="container-fluid">
    <div class="card shadow mb-4 border-left-danger">
        <div class="card-header py-2 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-calendar-alt"></i> รายการค้างส่ง</h6>
            <form method="POST" class="form-inline" id="searchForm">
                <input type="text" name="search_query" id="search_query" class="form-control form-control-sm mr-2" placeholder="ค้นหา..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit" class="btn btn-danger btn-sm shadow-sm">ค้นหา</button>
                
                <button type="button" onclick="exportOutstandingExcel()" class="btn btn-success btn-sm shadow-sm ml-2">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </button>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-outstanding mb-0 text-center">
                    <thead>
                        <tr>
                            <th width="80">AN</th>
                            <th>ผู้ป่วย / ที่อยู่ / เบอร์โทร</th>
                            <th>สถานบริการ</th>
                            <th>อุปกรณ์</th>
                            <th width="100">วันที่ยืม</th>
                            <th width="120">กำหนดคืน</th>
                            <th width="120">มัดจำ/ใบเสร็จ</th>
                            <th width="100">หลักฐาน</th>
                            <th width="100">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($outstanding_result && $outstanding_result->num_rows > 0): ?>
                        <?php while($row = $outstanding_result->fetch_assoc()): 
                            $due_date = new DateTime($row['due_date']);
                            $today = new DateTime(date('Y-m-d'));
                            $interval = $today->diff($due_date);
                            $days_left = (int)$interval->format("%r%a");

                            $row_class = ($days_left < 0) ? "row-overdue" : (($days_left <= 7) ? "row-near" : "");
                            $status_text = ($days_left < 0) ? "<div class='blink-red small'>⚠️ เกิน ".abs($days_left)." วัน</div>" : (($days_left <= 7) ? "<div class='text-warning small'>⏳ อีก $days_left วัน</div>" : "<div class='text-success small'>✅ ปกติ</div>");

                            // รวมที่อยู่
                            $full_addr = "เลขที่ " . ($row['address_no'] ?: '-') . " ม." . ($row['moo'] ?: '-') . " ต." . ($row['tumbon'] ?: '-') . " อ." . ($row['amphur'] ?: '-') . " จ." . ($row['province'] ?: '-');
                            $images = array_filter([$row['evidence_img'], $row['evidence_img2'], $row['evidence_img3'], $row['evidence_img4']]);
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td><span class="<?= $row['an_number'] ? 'an-badge' : 'text-muted' ?>"><?= $row['an_number'] ?: '-' ?></span></td>
                            <td class="text-left">
                                <div class="font-weight-bold text-dark"><?= htmlspecialchars($row['full_name']) ?></div>
                                <span class="addr-text"><i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($full_addr) ?></span>
                                <?php if($row['phone']): ?>
                                    <a href="tel:<?= $row['phone'] ?>" class="phone-link mt-1 d-inline-block"><i class="fas fa-phone-alt"></i> <?= $row['phone'] ?></a>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-light border"><?= $row['affiliation'] ?></span></td>
                            <td class="text-left">
                                <div class="text-info font-weight-bold small"><?= htmlspecialchars($row['eq_name']) ?></div>
                                <div class="small text-muted" style="font-size: 0.7rem;">SN: <?= $row['asset_number'] ?></div>
                            </td>
                            <td><?= date('d/m/Y', strtotime($row['loan_date'])) ?></td>
                            <td>
                                <div class="font-weight-bold"><?= date('d/m/Y', strtotime($row['due_date'])) ?></div>
                                <?= $status_text ?>
                            </td>
                            <td class="text-left">
                                <div class="font-weight-bold text-dark">฿<?= number_format($row['deposit_amount'], 2) ?></div>
                                <div class="small text-muted mt-1">เล่ม: <?= $row['receipt_book_no'] ?: '-' ?><br>เลขที่: <?= $row['receipt_no'] ?: '-' ?></div>
                            <td>
                                <div class="evidence-group">
                                    <?php foreach($images as $img): 
                                        $imgPath = "uploads/" . $img;
                                        if(!empty($img) && file_exists($imgPath)): ?>
                                        <img src="<?= $imgPath ?>" class="img-evidence-thumb open-modal-trigger" data-img="<?= $imgPath ?>">
                                    <?php endif; endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group-vertical w-100">
                                    <a href="return_process.php?loan_id=<?= $row['loan_id'] ?>" class="btn btn-success btn-sm mb-1 rounded shadow-sm">รับคืน</a>
                                    <div class="d-flex">
                                        <a href="generate_pdf.php?loan_id=<?= $row['loan_id'] ?>" target="_blank" class="btn btn-outline-info btn-sm rounded mr-1 flex-fill"><i class="fas fa-print"></i></a>
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded flex-fill" onclick="confirmDelete(<?= $row['loan_id'] ?>, '<?= htmlspecialchars($row['full_name']) ?>')"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">ไม่พบรายการค้างส่ง</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:transparent; border:none;">
            <div class="modal-body p-0 text-center">
                <img src="" id="displayFullImage" class="img-fluid rounded shadow-lg" style="max-height: 90vh; border: 4px solid #fff;">
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(loanId, patientName) {
    if (confirm('⚠️ ยืนยันการลบรายการยืมของ "' + patientName + '"?')) {
        window.location.href = 'loan_delete.php?loan_id=' + loanId;
    }
}

$(document).ready(function() {
    $(document).on('click', '.open-modal-trigger', function() {
        var imgSrc = $(this).attr('data-img');
        if (imgSrc) {
            $('#displayFullImage').attr('src', imgSrc);
            $('#imagePreviewModal').modal('show');
        }
    });
});

function exportOutstandingExcel() {
    const search = document.getElementById('search_query').value;
    // ส่งค่าไปยังไฟล์ export_outstanding.php
    window.location.href = 'export_outstanding.php?search=' + encodeURIComponent(search);
}
</script>

<?php include('footer.php'); ?>