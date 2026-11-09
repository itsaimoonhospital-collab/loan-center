<?php
include('header.php');
include('db_connect.php');
include_once('functions.php'); // ดึงฟังก์ชัน writeLog มาใช้งาน
checkAuth();

date_default_timezone_set('Asia/Bangkok');

// 1. รับค่าค้นหาด่วน (Quick Search)
$q_search = isset($_GET['q_search']) ? trim($_GET['q_search']) : '';
$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : (isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0);

if (!empty($q_search)) {
    $search_sql = "SELECT t.loan_id FROM loan_transactions t 
                   JOIN patients p ON t.patient_id = p.patient_id 
                   JOIN equipment e ON t.eq_id = e.eq_id
                   WHERE (t.loan_id = ? OR p.full_name LIKE ? OR e.asset_number = ?) 
                   AND t.status = 'ค้างส่ง' LIMIT 1";
    $stmt_s = $conn->prepare($search_sql);
    $search_param = "%$q_search%";
    $stmt_s->bind_param("iss", $q_search, $search_param, $q_search);
    $stmt_s->execute();
    $search_res = $stmt_s->get_result()->fetch_assoc();
    if ($search_res) {
        $loan_id = $search_res['loan_id'];
    } else {
        $message = "❌ ไม่พบรายการค้างส่งที่ตรงกับ: " . htmlspecialchars($q_search);
        $loan_id = 0;
    }
}

$loan_data = null;
if ($loan_id > 0) {
    $sql = "SELECT t.*, p.full_name, p.an_number, e.eq_name, e.asset_number, e.eq_id
            FROM loan_transactions t
            LEFT JOIN patients p ON t.patient_id = p.patient_id
            LEFT JOIN equipment e ON t.eq_id = e.eq_id
            WHERE t.loan_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $loan_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$loan_data) {
        $message = "❌ ไม่พบรายการยืมรหัส #$loan_id ในระบบ";
    } elseif ($loan_data['status'] === 'ส่งคืนแล้ว') {
        $message = "⚠️ รายการนี้คืนไปแล้วเมื่อวันที่ " . date('d/m/Y', strtotime($loan_data['return_date']));
        $loan_data = null; 
    }
}

// 2. ประมวลผลบันทึก (แก้ไขเฉพาะส่วน Log ให้ละเอียดขึ้น)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_return'])) {
    $return_date = $_POST['return_date'];
    $deposit_refunded = isset($_POST['deposit_refunded']) ? (int)$_POST['deposit_refunded'] : 0;
    $donation = isset($_POST['donation']) ? (int)$_POST['donation'] : 0;
    $is_broken = isset($_POST['is_broken']) ? 1 : 0; 
    $return_notes = trim($_POST['return_notes']);
    $eq_id = $_POST['eq_id'];
    
    // ดึง ID และชื่อเจ้าหน้าที่จาก Session
    $staff_id = $_SESSION['user_id']; 
    $staff_name = $_SESSION['name'] ?? 'เจ้าหน้าที่'; 
    $patient_name = $loan_data['full_name'] ?? 'ไม่ระบุชื่อ';
    $equipment_info = ($loan_data['eq_name'] ?? 'อุปกรณ์') . " (" . ($loan_data['asset_number'] ?? '-') . ")";

    $status_text = $is_broken ? "⚠️ ชำรุด" : "✅ ปกติ";
    $formatted_notes = ($loan_data['notes'] ? $loan_data['notes'] . "\n" : "") . "[" . date('d/m/Y H:i') . " โดย $staff_name]: รับคืน ($status_text) - $return_notes";

    $conn->begin_transaction();
    try {
        // อัปเดตข้อมูลการคืน
        $stmt_upd = $conn->prepare("UPDATE loan_transactions SET status = 'ส่งคืนแล้ว', return_date = ?, returned_by = ?, deposit_refunded = ?, donation = ?, notes = ? WHERE loan_id = ?");
        $stmt_upd->bind_param("siiisi", $return_date, $staff_id, $deposit_refunded, $donation, $formatted_notes, $loan_id);
        $stmt_upd->execute();

        // อัปเดตสถานะอุปกรณ์ในคลัง
        if ($is_broken) {
            $stmt_eq = $conn->prepare("UPDATE equipment SET broken_qty = 1, available_qty = 0 WHERE eq_id = ?");
        } else {
            $stmt_eq = $conn->prepare("UPDATE equipment SET broken_qty = 0, available_qty = 1 WHERE eq_id = ?");
        }
        $stmt_eq->bind_param("i", $eq_id);
        $stmt_eq->execute();

        // --- แก้ไขจุดนี้: บันทึก Log การทำงานแบบละเอียดตามต้องการ ---
        $log_content = "เจ้าหน้าที่: $staff_name รับคืนอุปกรณ์จากคุณ: $patient_name | รายการ: $equipment_info | สภาพ: $status_text | รหัสรายการ: #$loan_id";
        writeLog($conn, 'RETURN_EQUIPMENT', 'loan_transactions', $loan_id, $log_content);

        // แจ้งเตือนผ่าน Telegram
        $tg_msg = "<b>✅ รับคืนอุปกรณ์เรียบร้อย</b>\n--------------------------------\n";
        $tg_msg .= "👤 <b>ผู้คืน:</b> " . $patient_name . "\n🛠 <b>อุปกรณ์:</b> " . $equipment_info . "\n📍 <b>สภาพ:</b> " . ($is_broken ? "❌ ชำรุด" : "✅ ปกติ") . "\n💰 <b>มัดจำ:</b> " . ($donation > 0 ? "บริจาคให้ รพ." : "คืนเงินแล้ว") . "\n👮 <b>ผู้รับ:</b> " . $staff_name;
        sendTelegramNotification($tg_msg);

        $conn->commit();
        echo "<script>alert('บันทึกการรับคืนสำเร็จ'); window.location.href='outstanding_list.php';</script>";
        exit;
    } catch (Exception $e) { 
        $conn->rollback();
        die("เกิดข้อผิดพลาด: " . $e->getMessage()); 
    }
}
?>

<div class="container py-4 text-dark">
    <div class="row mb-4">
        <div class="col-md-6 mx-auto">
            <form method="GET" class="input-group shadow-sm">
                <input type="text" name="q_search" class="form-control border-primary" placeholder="ค้นหาด่วน (ระบุรหัสยืม, ชื่อ หรือเลขครุภัณฑ์)..." value="<?= htmlspecialchars($q_search) ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> ค้นหา</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($message) && $message): ?>
        <div class="alert alert-warning shadow border-left-warning"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($loan_data): ?>
        <div class="card shadow border-left-success">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="m-0 font-weight-bold text-success">แบบฟอร์มรับคืนอุปกรณ์ (#<?= $loan_id ?>)</h5>
                <span class="badge badge-primary">วันที่ยืม: <?= date('d/m/Y', strtotime($loan_data['loan_date'])) ?></span>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="loan_id" value="<?= $loan_id ?>">
                    <input type="hidden" name="eq_id" value="<?= $loan_data['eq_id'] ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="p-3 bg-light rounded border">
                                <h6 class="font-weight-bold text-dark border-bottom pb-2">ข้อมูลการยืม</h6>
                                <p class="mb-1"><strong>👤 ผู้ป่วย:</strong> <?= htmlspecialchars($loan_data['full_name']) ?></p>
                                <p class="mb-1"><strong>🛠 อุปกรณ์:</strong> <?= htmlspecialchars($loan_data['eq_name']) ?></p>
                                <p class="mb-1"><strong>🔢 เลขครุภัณฑ์:</strong> <?= $loan_data['asset_number'] ?></p>
                                <p class="mb-0 text-danger"><strong>💰 เงินมัดจำ:</strong> <?= number_format($row['deposit_amount'] ?? $loan_data['deposit_amount'], 2) ?> บาท</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold text-primary">วันที่รับคืน:</label>
                                <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">หมายเหตุการรับคืน:</label>
                                <textarea name="return_notes" class="form-control" rows="2" placeholder="ระบุสภาพอุปกรณ์..."></textarea>
                            </div>
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="is_broken" name="is_broken" value="1">
                                <label class="custom-control-label text-danger font-weight-bold" for="is_broken">🚫 อุปกรณ์ชำรุด / ต้องส่งซ่อม</label>
                            </div>
                            <div class="bg-light p-3 rounded border shadow-sm">
                                <label class="font-weight-bold text-dark"><i class="fas fa-hand-holding-usd"></i> การจัดการเงินมัดจำ:</label>
                                <div class="custom-control custom-radio mb-1">
                                    <input type="radio" id="m1" name="m_action" class="custom-control-input" checked onclick="setMoney(1,0)">
                                    <label class="custom-control-label" for="m1">💵 คืนเงินมัดจำครบจำนวน</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="m2" name="m_action" class="custom-control-input" onclick="setMoney(0,1)">
                                    <label class="custom-control-label font-weight-bold text-success" for="m2">❤️ ขอบริจาคให้โรงพยาบาล</label>
                                </div>
                                <input type="hidden" name="deposit_refunded" id="d_ref" value="1">
                                <input type="hidden" name="donation" id="d_don" value="0">
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-right">
                        <a href="outstanding_list.php" class="btn btn-secondary px-4">ยกเลิก</a>
                        <button type="submit" name="submit_return" class="btn btn-success btn-lg px-5 shadow ml-2">ยืนยันการรับคืน</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function setMoney(ref, don) {
    document.getElementById('d_ref').value = ref;
    document.getElementById('d_don').value = don;
}
</script>

<?php include('footer.php'); ?>