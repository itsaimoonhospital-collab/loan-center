<?php
// 1. เริ่มต้นและจัดการ Header
ob_start();
require_once __DIR__ . '/vendor/autoload.php';
include('db_connect.php');

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// ตรวจสอบสิทธิ์การใช้งาน
if (!isset($_SESSION['user_id'])) {
    die("Access Denied: กรุณาเข้าสู่ระบบ");
}

$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : 0;
if ($loan_id <= 0) { die("ไม่พบรหัสรายการยืม"); }

/**
 * 2. ฟังก์ชันแปลงเงินเป็นตัวอักษรไทย
 */
function ThaiBaht($number) {
    if ($number == 0) return "ศูนย์บาทถ้วน";
    $txtnum = array('ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า');
    $txtunit = array('','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน');
    $number = number_format($number, 2, '.', '');
    $numbers = explode('.', $number);
    $num = $numbers[0];
    $baht = '';
    $length = strlen($num);
    for ($i = 0; $i < $length; $i++) {
        $n = substr($num, $i, 1);
        if ($n != 0) {
            if ($i == ($length - 1) && $n == 1 && $length > 1) { $baht .= 'เอ็ด'; }
            elseif ($i == ($length - 2) && $n == 2) { $baht .= 'ยี่สิบ'; }
            elseif ($i == ($length - 2) && $n == 1) { $baht .= 'สิบ'; }
            else { $baht .= $txtnum[$n] . $txtunit[$length - $i - 1]; }
        }
    }
    $baht .= 'บาทถ้วน';
    return $baht;
}

/**
 * 3. ดึงข้อมูลรายการยืม (Snapshot ข้อมูล ณ วันที่ยืม)
 */
$base_stmt = $conn->prepare("SELECT patient_id, loan_date, evidence_img FROM loan_transactions WHERE loan_id = ?");
$base_stmt->bind_param("i", $loan_id);
$base_stmt->execute();
$base_data = $base_stmt->get_result()->fetch_assoc();

if (!$base_data) { die("ไม่พบข้อมูล"); }

$p_id = $base_data['patient_id'];
$l_date = $base_data['loan_date'];
$e_img = $base_data['evidence_img'];

$sql = "SELECT t.*, p.full_name, p.id_card, p.an_number, p.illness, 
               e.eq_name, e.asset_number, 
               u.name AS staff_name, u.position AS staff_position, u.staff_signature
        FROM loan_transactions t
        JOIN patients p ON t.patient_id = p.patient_id
        JOIN equipment e ON t.eq_id = e.eq_id
        LEFT JOIN users u ON t.created_by = u.user_id 
        WHERE t.patient_id = ? AND t.loan_date = ? AND (t.evidence_img = ? OR (t.evidence_img IS NULL AND ? IS NULL))";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $p_id, $l_date, $e_img, $e_img);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while($row = $result->fetch_assoc()) { $items[] = $row; }
$data = $items[0]; 

$display_borrower = !empty($data['borrower_name']) ? $data['borrower_name'] : $data['full_name'];
$due_date_val = $data['due_date'];
$d = date('j', strtotime($due_date_val));
$m = date('n', strtotime($due_date_val));
$y = date('Y', strtotime($due_date_val)) + 543;
$months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
$thai_month = $months[$m];

// 4. ตั้งค่า mPDF และสร้างเนื้อหา
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 'format' => 'A4',
        'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 15, 'margin_bottom' => 10,
        'fontDir' => array_merge((new Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'], [__DIR__ . '/fonts']),
        'fontdata' => (new Mpdf\Config\FontVariables())->getDefaults()['fontdata'] + [
            'sarabun' => ['R' => 'THSarabunNew.ttf', 'B' => 'THSarabunNew Bold.ttf']
        ],
        'default_font' => 'sarabun'
    ]);

    $logo = (file_exists('./images/logo2.png')) ? './images/logo2.png' : ''; 
    $borrower_sig = (!empty($data['signature_img']) && file_exists('signatures/'.$data['signature_img'])) ? 'signatures/'.$data['signature_img'] : '';
    $staff_sig_img = (!empty($data['staff_signature']) && file_exists('signatures/' . $data['staff_signature'])) ? 'signatures/' . $data['staff_signature'] : '';

    $html = '
    <style>
        body { font-family: "sarabun"; font-size: 15pt; line-height: 1.3; color: #000; }
        .text-center { text-align: center; }
        .header-title { font-size: 19pt; font-weight: bold; margin: 0; }
        .section-title { background-color: #f2f2f2; padding: 2px 10px; margin: 10px 0 5px 0; border-left: 5px solid #333; font-weight: bold; }
        .table-style { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .table-style th, .table-style td { border: 1px solid #000; padding: 5px; text-align: center; }
        .pdpa-box { 
            margin-top: 20px; 
            padding: 8px; 
            border: 1px solid #ccc; 
            background-color: #fafafa; 
            font-size: 13pt; 
            line-height: 1.2; 
        }
    </style>

    <div class="text-center">
        ' . ($logo ? '<img src="'.$logo.'" style="width: 100px; margin-bottom: 5px;"><br>' : '') . '
        <div class="header-title">ใบยืมวัสดุ/ครุภัณฑ์ทางการแพทย์</div>
        <div style="font-size: 16pt;">โรงพยาบาลทรายมูล จังหวัดยโสธร</div>
    </div>

    <table width="100%" style="margin-top: 10px; border-top: 1px solid #000; padding-top: 10px;">
        <tr>
            <td width="60%"><b>วันที่ยืม:</b> ' . date("d/m/Y", strtotime($data['loan_date'])) . '</td>
            <td width="40%" align="right"><b>เลขที่อ้างอิง:</b> ' . str_pad($data['loan_id'], 5, "0", STR_PAD_LEFT) . '</td>
        </tr>
    </table>

    <div class="section-title">1. ข้อมูลผู้ป่วยและผู้ยืม</div>
        <div style="padding-left: 10px;">
            <table width="100%" cellpadding="2">
                <tr>
                    <td colspan="2"><b>ชื่อผู้ป่วย:</b> ' . htmlspecialchars($data['full_name']) . '</td>
                    <td><b>หมายเลข AN:</b> ' . ($data['an_number'] ?: '-') . '</td>
                </tr>
                <tr>
                    <td colspan="2"><b>เลขบัตรประชาชน (ผู้ป่วย):</b> ' . $data['id_card'] . '</td>
                    <td><b>โรค/อาการ:</b> ' . htmlspecialchars($data['illness'] ?: '-') . '</td>
                </tr>
                <tr>
                    <td colspan="3">
                        <b>ผู้รับผิดชอบการยืม:</b> ' . (!empty($data['borrower_name']) ? htmlspecialchars($data['borrower_name']) . ' (ยืมแทน)' : htmlspecialchars($data['full_name'])) . ' 
                        ' . (!empty($data['borrower_relation']) ? ' [ความสัมพันธ์: ' . htmlspecialchars($data['borrower_relation']) . ']' : '') . '
                    </td>
                </tr>
                <tr>
                    <td colspan="3"><b>ที่อยู่ปัจจุบัน:</b> ' . $data['snap_address_no'] . ' ม.' . $data['snap_moo'] . ' ต.' . $data['snap_tumbon'] . ' อ.' . $data['snap_amphur'] . ' จ.' . $data['snap_province'] . ' (โทร: ' . ($data['snap_phone'] ?: '-') . ')</td>
                </tr>
            </table>
    </div>

    <div class="section-title">2. รายละเอียดอุปกรณ์และหลักฐานการเงิน</div>
    <div style="margin-bottom: 5px; padding-left: 10px;">
        <b>ข้อมูลใบเสร็จ:</b> <b>เล่มที่:</b> ' . ($data['receipt_book_no'] ?: '-') . ' <b>เลขที่:</b> ' . ($data['receipt_no'] ?: '-') . '
    </div>
    <table class="table-style">
        <thead>
            <tr style="background-color: #f9f9f9;">
                <th width="10%">ลำดับ</th>
                <th width="45%">รายการอุปกรณ์</th>
                <th width="30%">เลขครุภัณฑ์</th>
                <th width="15%">มัดจำ (บาท)</th>
            </tr>
        </thead>
        <tbody>';
            $total_deposit = 0;
            foreach($items as $idx => $item) {
                $total_deposit += $item['deposit_amount'];
                $html .= '<tr>
                    <td>'.($idx+1).'</td>
                    <td align="left">'.htmlspecialchars($item['eq_name']).'</td>
                    <td>'.htmlspecialchars($item['asset_number']).'</td>
                    <td align="right">'.number_format($item['deposit_amount'], 2).'</td>
                </tr>';
            }
$html .= '  <tr style="font-weight:bold; background-color:#fafafa;">
                <td colspan="3" align="right">รวมเงินมัดจำทั้งสิ้น</td>
                <td align="right">'.number_format($total_deposit, 2).'</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 15px; text-indent: 45px; text-align: justify;">
        ข้าพเจ้าสัญญาว่าจะดูแลรักษาอุปกรณ์และนำส่งคืน ณ โรงพยาบาลทรายมูล ภายในวันที่ 
        <b>' . $d . ' ' . $thai_month . ' ' . $y . '</b> 
        หากเกิดความเสียหาย ข้าพเจ้ายินดีรับผิดชอบค่าซ่อมแซมหรือชดใช้ตามราคาทรัพย์สิน 
        พร้อมวางเงินมัดจำไว้จำนวน <b>' . number_format($total_deposit, 2) . ' บาท (' . ThaiBaht($total_deposit) . ')</b>
    </div>

    <div class="pdpa-box">
        <strong>ประกาศความเป็นส่วนตัว (Privacy Notice):</strong><br>
        การลงนามในเอกสารนี้ ถือว่าท่านยินยอมให้โรงพยาบาลทรายมูล จัดเก็บและใช้ข้อมูลส่วนบุคคลของท่าน 
        (รวมถึงข้อมูลสุขภาพ ภาพถ่าย และลายมือชื่อ) เพื่อวัตถุประสงค์ในการบริหารจัดการยืม-คืนอุปกรณ์ทางการแพทย์ 
        และการติดตามอุปกรณ์ตามนโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA) ของโรงพยาบาลเท่านั้น
    </div>

    <table width="100%" style="margin-top: 25px;">
        <tr>
            <td width="50%" align="center">
                <div style="min-height: 80px;">
                    ' . ($borrower_sig ? '<img src="'.$borrower_sig.'" style="max-height: 70px; margin-bottom: -10px;">' : '<br><br><br>') . '
                </div>
                <p>ลงชื่อ............................................................ผู้ยืม</p>
                <p>( ' . htmlspecialchars($display_borrower) . ' )</p>
            </td>
            <td width="50%" align="center">
                <div style="min-height: 80px;">
                    ' . ($staff_sig_img ? '<img src="'.$staff_sig_img.'" style="max-height: 70px; margin-bottom: -10px;">' : '<br><br><br>') . '
                </div>
                <p>ลงชื่อ............................................................ผู้ส่งมอบ</p>
                <p>( ' . htmlspecialchars($data['staff_name']) . ' )</p>
                <p style="font-size: 14pt;">ตำแหน่ง ' . htmlspecialchars($data['staff_position'] ?: 'เจ้าหน้าที่') . '</p>
            </td>
        </tr>
    </table>

    <div style="margin-top: 30px; border-top: 1px dashed #000; padding-top: 10px;">
        <b>หมายเหตุ:</b> ให้นำส่งสำเนาแบบฟอร์มนี้มาพร้อมกับอุปกรณ์ในวันที่ส่งคืนและติดต่อในวันเวลาทำการราชการเท่านั้น
    </div>';

    if (ob_get_contents()) ob_end_clean();
    $mpdf->WriteHTML($html);
    $mpdf->Output('Loan_Form_' . $data['loan_id'] . '.pdf', 'I');

} catch (\Mpdf\MpdfException $e) { echo "Error: " . $e->getMessage(); }
?>