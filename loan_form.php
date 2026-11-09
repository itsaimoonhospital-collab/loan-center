<?php
include('header.php'); 
include('db_connect.php');
include_once('functions.php'); // เรียกใช้ฟังก์ชันบันทึก Log
checkAuth();

// --- [ส่วนที่ 1: ประมวลผลการบันทึกข้อมูล (PHP)] ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_card'])) {
    
    $id_card = trim($_POST['id_card']); 
    $an_number = trim($_POST['an_number']); 
    $loan_date = !empty($_POST['loan_date']) ? $_POST['loan_date'] : date('Y-m-d');
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+30 days')); 
    $full_name = $_POST['full_name'];
    $birth_date = $_POST['birth_date'];
    $age = (int)$_POST['age'];
    $phone = $_POST['phone'];       
    $borrower_name = !empty($_POST['borrower_name']) ? $_POST['borrower_name'] : $_POST['full_name'];
    $borrower_relation = !empty($_POST['borrower_relation']) ? $_POST['borrower_relation'] : 'ตนเอง';
    $affiliation = $_POST['affiliation'];
    $illness = $_POST['illness'];
    $address_no = $_POST['address_no'];
    $moo = $_POST['moo'];
    $tumbon = $_POST['tumbon'];
    $amphur = $_POST['amphur'];    
    $province = $_POST['province']; 
    
    $eq_ids = isset($_POST['eq_ids']) ? $_POST['eq_ids'] : [];
    $deposit_amounts = isset($_POST['deposit_amounts']) ? $_POST['deposit_amounts'] : [];
    $receipt_book_no = $_POST['receipt_book_no']; // เล่มใบเสร็จ
    $receipt_no = $_POST['receipt_no'];           // เลขที่ใบเสร็จ
    
    $created_by = $_SESSION['user_id']; 
    $staff_name = $_SESSION['name'] ?? 'เจ้าหน้าที่';

    // 1. จัดการรูปภาพ (4 ไฟล์)
    $uploaded_images = [NULL, NULL, NULL, NULL];
    if (isset($_FILES['evidence_images'])) {
        foreach ($_FILES['evidence_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['evidence_images']['error'][$key] == 0 && $key < 4) {
                $ext = strtolower(pathinfo($_FILES["evidence_images"]["name"][$key], PATHINFO_EXTENSION));
                $new_name = "ev_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
                move_uploaded_file($tmp_name, "uploads/" . $new_name);
                $uploaded_images[$key] = $new_name;
            }
        }
    }

    // 2. จัดการลายเซ็น
    $signature_img = NULL;
    if (!empty($_POST['signature_data']) && strlen($_POST['signature_data']) > 100) {
        $sig_data = str_replace(['data:image/png;base64,', ' '], ['', '+'], $_POST['signature_data']);
        $signature_img = "sig_" . date("Ymd_His") . "_" . uniqid() . ".png";
        file_put_contents("signatures/" . $signature_img, base64_decode($sig_data));
    }

    $conn->begin_transaction();
    try {
        // [A] บันทึก/อัปเดตข้อมูลผู้ป่วย
        $check = $conn->prepare("SELECT patient_id FROM patients WHERE id_card = ?");
        $check->bind_param("s", $id_card);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $patient_id = $res->fetch_assoc()['patient_id'];
            $upd = $conn->prepare("UPDATE patients SET an_number=?, full_name=?, birth_date=?, age=?, address_no=?, moo=?, tumbon=?, amphur=?, province=?, phone=?, affiliation=?, illness=? WHERE patient_id=?");
            $upd->bind_param("sssissssssssi", $an_number, $full_name, $birth_date, $age, $address_no, $moo, $tumbon, $amphur, $province, $phone, $affiliation, $illness, $patient_id);
            $upd->execute();
        } else {
            $ins = $conn->prepare("INSERT INTO patients (id_card, an_number, birth_date, full_name, age, address_no, moo, tumbon, amphur, province, phone, affiliation, illness) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssssissssssss", $id_card, $an_number, $birth_date, $full_name, $age, $address_no, $moo, $tumbon, $amphur, $province, $phone, $affiliation, $illness);
            $ins->execute();
            $patient_id = $conn->insert_id;
        }

        // [B] บันทึกการยืม
        $sql_loan = "INSERT INTO loan_transactions (
            patient_id, borrower_name, borrower_relation, eq_id, 
            snap_address_no, snap_moo, snap_tumbon, snap_amphur, snap_province, snap_phone,
            loan_date, due_date, receipt_book_no, receipt_no, deposit_amount, 
            evidence_img, evidence_img2, evidence_img3, evidence_img4, signature_img, created_by, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ค้างส่ง')";
        
        $stmt_loan = $conn->prepare($sql_loan);

        foreach ($eq_ids as $index => $eq_id) {
            if (empty($eq_id)) continue;
            $dep = (float)$deposit_amounts[$index];
            $stmt_loan->bind_param("ississssssssssdsssssi", 
                $patient_id, $borrower_name, $borrower_relation, $eq_id, 
                $address_no, $moo, $tumbon, $amphur, $province, $phone,
                $loan_date, $due_date, $receipt_book_no, $receipt_no, $dep, 
                $uploaded_images[0], $uploaded_images[1], $uploaded_images[2], $uploaded_images[3], $signature_img, $created_by
            );
            $stmt_loan->execute();
            $new_loan_id = $conn->insert_id;

            // Log ข้อมูล
            $eq_res = $conn->query("SELECT eq_name, asset_number FROM equipment WHERE eq_id = " . (int)$eq_id);
            $eq_info = $eq_res->fetch_assoc();
            writeLog($conn, 'INSERT_LOAN', 'loan_transactions', $new_loan_id, "ยืมอุปกรณ์: ".$eq_info['eq_name']);
            writeLog($conn, 'PDPA_CONSENT', 'loan_transactions', $new_loan_id, "ยินยอม PDPA โดยคุณ: ".$borrower_name);

            // --- 2. ส่งแจ้งเตือน Telegram (เพิ่มข้อมูลละเอียดและ PDPA) ---
            if (function_exists('sendTelegramNotification')) {
                $masked_phone = substr($phone, 0, 3) . "-XXX-" . substr($phone, -3);
                
                // ตรวจสอบชื่อผู้มารับจริง
                $receiver_name = !empty($borrower_name) ? $borrower_name : $full_name;
                $receiver_rel = !empty($borrower_relation) ? $borrower_relation : "ตนเอง";

                $tg_msg = "<b>📢 บันทึกรายการยืมใหม่</b>\n";
                $tg_msg .= "--------------------------------\n";
                $tg_msg .= "👤 <b>ผู้ป่วย:</b> $full_name\n";
                $tg_msg .= "🆔 <b>AN:</b> " . ($an_number ?: '-') . "\n";
                $tg_msg .= "🤝 <b>ผู้มารับ:</b> $receiver_name ($receiver_rel)\n";
                $tg_msg .= "🛠 <b>รายการ:</b> " . $eq_info['eq_name'] . "\n";
                $tg_msg .= "🔢 <b>เลขครุภัณฑ์:</b> " . $eq_info['asset_number'] . "\n";
                $tg_msg .= "💰 <b>เงินมัดจำ:</b> " . number_format($dep, 2) . " บาท\n";
                $tg_msg .= "📅 <b>วันที่ยืม:</b> " . date('d/m/Y', strtotime($loan_date)) . "\n";
                $tg_msg .= "🚩 <b>ครบกำหนด:</b> " . date('d/m/Y', strtotime($due_date)) . "\n";
                $tg_msg .= "📄 <b>ใบเสร็จ:</b> $receipt_book_no/$receipt_no\n";
                $tg_msg .= "📞 <b>ติดต่อ:</b> $masked_phone\n";
                $tg_msg .= "👮 <b>เจ้าหน้าที่:</b> $staff_name\n";
                $tg_msg .= "--------------------------------\n";
                $tg_msg .= "✅ <b>PDPA:</b> ยินยอมให้จัดเก็บข้อมูลแล้ว";
                
                sendTelegramNotification($tg_msg);
            }
            $conn->query("UPDATE equipment SET available_qty = available_qty - 1 WHERE eq_id = " . (int)$eq_id);
        }

        $conn->commit();
        echo "<script>alert('บันทึกสำเร็จ'); window.location.href='outstanding_list.php';</script>";
        exit;
    } catch (Exception $e) { $conn->rollback(); echo "<script>alert('Error: ".$e->getMessage()."');</script>"; }
}

$affiliations = ['PCU', 'รพ.สต.โคกยาว', 'รพ.สต.ดงมะไฟ', 'รพ.สต.ดู่ลาด', 'รพ.สต.นาเวียง', 'รพ.สต.หนองแวง', 'รพ.สต.สีสุก', 'รพ.สต.ไผ่', 'รพ.สต.คำครตา', 'รพ.สต.โคกกลาง'];
$equipments = $conn->query("SELECT eq_id, eq_name, asset_number FROM equipment WHERE available_qty > 0 AND is_active = 1")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    #sig-canvas { border: 2px dashed #333; background: #fff; width: 100%; height: 200px; cursor: crosshair; }
    .eq-row { background: #f8f9fc; border: 1px solid #e3e6f0; padding: 15px; margin-bottom: 10px; border-radius: 8px; }
    .section-title { font-weight: bold; color: #4e73df; border-left: 5px solid #4e73df; padding-left: 10px; margin-bottom: 15px; }
    .preview-img { width: 100px; height: 100px; object-fit: cover; display: none; margin: 5px; border: 1px solid #ddd; }
</style>

<div class="container-fluid pb-5 text-dark">
    <form method="post" enctype="multipart/form-data" id="loanForm">
        <h4 class="mb-4 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> บันทึกการยืมกายอุปกรณ์</h4>

        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="section-title">1. ข้อมูลผู้ป่วยและผู้มารับอุปกรณ์</div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>วันที่ยืม</label><input type="date" name="loan_date" id="loan_date" class="form-control" value="<?=date('Y-m-d')?>" required></div>
                    <div class="form-group col-md-3"><label class="text-danger font-weight-bold">กำหนดคืน (30 วัน)</label><input type="date" name="due_date" id="due_date" class="form-control" readonly></div>
                    <div class="form-group col-md-3"><label>เลขบัตรประชาชนผู้ป่วย</label><input type="text" name="id_card" id="id_card" class="form-control" maxlength="13" required></div>
                    <div class="form-group col-md-3"><label>AN</label><input type="text" name="an_number" id="an_number" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>ชื่อ-นามสกุล ผู้ป่วย</label><input type="text" name="full_name" id="full_name" class="form-control" required></div>
                    <div class="form-group col-md-3"><label>วันเกิด</label><input type="date" name="birth_date" id="birth_date" class="form-control" required></div>
                    <div class="form-group col-md-2"><label>อายุ (ปี)</label><input type="text" name="age" id="age" class="form-control" readonly></div>
                    <div class="form-group col-md-3"><label>เบอร์โทรศัพท์</label><input type="text" name="phone" id="phone" class="form-control" required></div>
                </div>

                <div class="form-row bg-light p-2 rounded border mb-3">
                    <div class="form-group col-md-6">
                        <label class="text-primary font-weight-bold">ชื่อผู้รับอุปกรณ์ (กรณีญาติมาแทน)</label>
                        <input type="text" name="borrower_name" id="borrower_name" class="form-control" placeholder="ถ้ามารับเองไม่ต้องกรอก">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="text-primary font-weight-bold">เกี่ยวข้องเป็น</label>
                        <input type="text" name="borrower_relation" id="borrower_relation" class="form-control" placeholder="เช่น บุตร, สามี, ภรรยา">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-2"><label>บ้านเลขที่</label><input type="text" name="address_no" id="address_no" class="form-control"></div>
                    <div class="form-group col-md-1"><label>หมู่</label><input type="text" name="moo" id="moo" class="form-control"></div>
                    <div class="form-group col-md-3"><label>ตำบล</label><input type="text" name="tumbon" id="tumbon" class="form-control"></div>
                    <div class="form-group col-md-3"><label>อำเภอ</label><input type="text" name="amphur" id="amphur" class="form-control" value="ทรายมูล"></div>
                    <div class="form-group col-md-3"><label>จังหวัด</label><input type="text" name="province" id="province" class="form-control" value="ยโสธร"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>สังกัด</label>
                        <select name="affiliation" id="affiliation" class="form-control">
                            <?php foreach($affiliations as $a): ?><option value="<?=$a?>"><?=$a?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-8"><label>โรค/ความพิการ</label><input type="text" name="illness" id="illness" class="form-control" required></div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="section-title">2. รายการอุปกรณ์และใบเสร็จ</div>
                    <button type="button" class="btn btn-success btn-sm" id="add-item-btn">+ เพิ่มอุปกรณ์</button>
                </div>
                <div id="equipment-container">
                    <div class="form-row eq-row align-items-end">
                        <div class="col-md-7"><label class="small font-weight-bold">เลือกอุปกรณ์</label>
                            <select name="eq_ids[]" class="form-control" required>
                                <option value="">-- เลือกอุปกรณ์ --</option>
                                <?php foreach($equipments as $e): ?><option value="<?=$e['eq_id']?>"><?=$e['eq_name']?> (<?=$e['asset_number']?>)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="small font-weight-bold">มัดจำ (บาท)</label><input type="number" name="deposit_amounts[]" class="form-control deposit-input" value="0.00" step="0.01"></div>
                        <div class="col-md-2"><button type="button" class="btn btn-danger btn-block remove-item" style="display:none;">ลบ</button></div>
                    </div>
                </div>
                <div class="text-right mt-3 font-weight-bold text-primary h5">มัดจำรวม: <span id="total-deposit-display">0.00</span> บาท</div>
                    <div class="form-row mb-3">
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold">เล่มที่ใบเสร็จ</label>
                        <input type="text" name="receipt_book_no" class="form-control" placeholder="ระบุเล่มที่ใบเสร็จ">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="font-weight-bold">เลขที่ใบเสร็จ</label>
                        <input type="text" name="receipt_no" class="form-control" placeholder="ระบุเลขที่ใบเสร็จ">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="section-title">3. หลักฐานและความยินยอม (PDPA)</div>
                <div class="alert alert-info small shadow-sm border-left-info mb-3">
                    <strong><i class="fas fa-user-shield"></i> ประกาศความเป็นส่วนตัว (Privacy Notice):</strong><br>
                    โรงพยาบาลทรายมูล จะจัดเก็บและใช้ข้อมูลส่วนบุคคลของท่าน (รวมถึงภาพถ่ายและลายเซ็น) 
                    เพื่อวัตถุประสงค์ในการบริหารจัดการยืม-คืนกายอุปกรณ์ทางการแพทย์ และการติดตามอุปกรณ์เท่านั้น 
                    ท่านมีสิทธิ์ในการขอเข้าถึงหรือลบข้อมูลตามที่กฎหมายกำหนด <br>
                    <a href="#" data-toggle="modal" data-target="#pdpaPdfModal" class="font-weight-bold text-primary text-decoration-underline"><i class="fas fa-file-pdf"></i> คลิกเพื่ออ่านนโยบายฉบับเต็ม</a>
                </div>

                <div class="custom-control custom-checkbox mb-3 text-center">
                    <input type="checkbox" class="custom-control-input" id="pdpa_consent" name="pdpa_consent" required>
                    <label class="custom-control-label font-weight-bold text-primary" for="pdpa_consent">ข้าพเจ้ายินยอมให้จัดเก็บและใช้ข้อมูลส่วนบุคคลตามวัตถุประสงค์</label>
                </div>

                <div class="form-row mb-4 text-center">
                    <?php for($i=0; $i<4; $i++): ?>
                    <div class="col-md-3 mb-3">
                        <label class="small">รูปหลักฐาน <?=($i+1)?></label>
                        <input type="file" name="evidence_images[]" class="form-control-file img-input" data-target="prev-<?=$i?>">
                        <img id="prev-<?=$i?>" class="preview-img mx-auto mt-2 rounded border shadow-sm">
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="text-center">
                    <label class="font-weight-bold text-dark">✍️ ลายเซ็นผู้รับ (สีดำ)</label><br>
                    <canvas id="sig-canvas"></canvas><br>
                    <button type="button" id="sig-clearBtn" class="btn btn-sm btn-outline-danger mt-2">ล้างลายเซ็น</button>
                    <input type="hidden" name="signature_data" id="signature_data">
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg shadow font-weight-bold">บันทึกข้อมูลการยืม</button>
    </form>
</div>

<div class="modal fade" id="pdpaPdfModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header bg-primary text-white"><h5 class="modal-title font-weight-bold">นโยบาย PDPA โรงพยาบาลทรายมูล</h5><button type="button" class="close text-white" data-dismiss="modal">&times;</button></div>
        <div class="modal-body p-0"><iframe src="./docs/pdpa_policy.pdf" width="100%" height="700px" style="border:none;"></iframe></div>
    </div></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const $ = jQuery;

    function calcDate() {
        let loanDateVal = $('#loan_date').val();
        if(loanDateVal){
            let d = new Date(loanDateVal);
            d.setDate(d.getDate() + 30);
            $('#due_date').val(d.toISOString().split('T')[0]);
        }
    }
    $('#loan_date').on('change', calcDate); calcDate();

    $('#birth_date').on('change', function() {
        if (this.value) {
            let birth = new Date(this.value); let today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            let m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            $('#age').val(age >= 0 ? age : 0);
        }
    });

    $('#id_card').on('keyup', function() {
        if (this.value.length === 13) {
            $.getJSON('get_patient_data.php', {id_card: this.value}, function(data) {
                if (data) {
                    $('#full_name').val(data.full_name); $('#an_number').val(data.an_number);
                    $('#birth_date').val(data.birth_date).trigger('change'); $('#phone').val(data.phone);
                    $('#address_no').val(data.address_no); $('#moo').val(data.moo);
                    $('#tumbon').val(data.tumbon); $('#amphur').val(data.amphur); $('#province').val(data.province);
                    $('#affiliation').val(data.affiliation); $('#illness').val(data.illness);
                }
            });
        }
    });

    $('#add-item-btn').click(() => { 
        let template = '<div class="form-row eq-row align-items-end mt-2"><div class="col-md-7"><select name="eq_ids[]" class="form-control" required><option value="">-- เลือกอุปกรณ์ --</option><?php foreach($equipments as $e): ?><option value="<?=$e['eq_id']?>"><?=$e['eq_name']?> (<?=$e['asset_number']?>)</option><?php endforeach; ?></select></div><div class="col-md-3"><input type="number" name="deposit_amounts[]" class="form-control deposit-input" value="0.00" step="0.01"></div><div class="col-md-2"><button type="button" class="btn btn-danger btn-block remove-item">ลบ</button></div></div>';
        $('#equipment-container').append(template); 
        calcTotal(); 
    });
    $(document).on('click', '.remove-item', function() { $(this).closest('.eq-row').remove(); calcTotal(); });
    $(document).on('input', '.deposit-input', calcTotal);
    function calcTotal() {
        let sum = 0; $('.deposit-input').each(function() { sum += parseFloat($(this).val()) || 0; });
        $('#total-deposit-display').text(sum.toLocaleString(undefined, {minimumFractionDigits:2}));
    }

    const canvas = document.getElementById("sig-canvas");
    const ctx = canvas.getContext("2d"); canvas.width = canvas.offsetWidth;
    ctx.lineWidth = 3; ctx.lineCap = "round"; ctx.strokeStyle = "#000000";
    let drawing = false;
    const getPos = e => {
        let rect = canvas.getBoundingClientRect();
        let x = (e.clientX || (e.touches && e.touches[0].clientX)) - rect.left;
        let y = (e.clientY || (e.touches && e.touches[0].clientY)) - rect.top;
        return { x, y };
    };
    const start = e => { drawing = true; ctx.beginPath(); let p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); };
    const draw = e => { if(drawing){ let p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); } };
    canvas.addEventListener("mousedown", start); canvas.addEventListener("touchstart", start);
    canvas.addEventListener("mousemove", draw); canvas.addEventListener("touchmove", draw);
    window.addEventListener("mouseup", () => drawing = false); window.addEventListener("touchend", () => drawing = false);
    $('#sig-clearBtn').click(() => ctx.clearRect(0, 0, canvas.width, canvas.height));

    $(document).on('change', '.img-input', function() {
        let reader = new FileReader();
        reader.onload = e => $('#' + $(this).data('target')).attr('src', e.target.result).show();
        reader.readAsDataURL(this.files[0]);
    });

    $('#loanForm').submit(() => { $('#signature_data').val(canvas.toDataURL()); return true; });
});
</script>
<?php include('footer.php'); ?>