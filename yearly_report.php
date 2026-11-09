<?php
include('db_connect.php');
include('header.php');
checkAuth();

// 1. ตั้งค่า Timezone และจัดการปีงบประมาณ
date_default_timezone_set('Asia/Bangkok'); 
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// กำหนดช่วงวันที่ปีงบประมาณไทย: 1 ต.ค. (ปีก่อนหน้า) - 30 ก.ย. (ปีปัจจุบัน)
$startDate = ($selected_year - 1) . "-10-01";
$endDate = $selected_year . "-09-30";

// 2. ดึงข้อมูลสถานะคลัง (ปัจจุบัน) และยอดยืมสะสม (ตามปีงบประมาณ)
$report_sql = "
    SELECT 
        e.eq_name, 
        COUNT(e.eq_id) as total_inventory,
        SUM(CASE WHEN e.available_qty = 1 AND e.broken_qty = 0 THEN 1 ELSE 0 END) as total_ready,
        SUM(CASE WHEN e.broken_qty = 1 THEN 1 ELSE 0 END) as total_broken,
        SUM(CASE WHEN e.available_qty = 0 AND e.broken_qty = 0 THEN 1 ELSE 0 END) as total_in_use,
        (SELECT COUNT(t.loan_id) 
         FROM loan_transactions t 
         WHERE t.eq_id IN (SELECT eq_id FROM equipment WHERE eq_name = e.eq_name) 
         AND t.loan_date BETWEEN ? AND ?) as yearly_loans
    FROM equipment e
    WHERE e.is_active = 1
    GROUP BY e.eq_name
    ORDER BY total_inventory DESC";

$stmt = $conn->prepare($report_sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    /* การแสดงผลบนหน้าจอปกติ */
    .logo-print { width: 50px; margin-bottom: 5px; }
    .signature-section { display: none; } 

    /* การตั้งค่าสำหรับการพิมพ์ให้พอดี 1 หน้า A4 แนวนอน */
    @media print {
        @page { 
            size: A4 landscape; 
            margin: 0.8cm 1cm; /* เว้นขอบให้น้อยลงเพื่อเพิ่มพื้นที่ */
        }

        body { 
            background-color: #fff !important; 
            font-size: 12pt !important; 
            color: #000 !important;
            margin: 0;
            padding: 0;
        }

        /* ซ่อนส่วนประกอบเว็บที่ไม่ต้องการ */
        .navbar, .sidebar, .d-print-none, footer, .btn, .card-header, .navbar-toggler { 
            display: none !important; 
        }

        /* ปรับ Container ให้กว้างเต็มหน้ากระดาษ */
        .container-fluid, .card, .card-body { 
            width: 100% !important; 
            padding: 0 !important; 
            margin: 0 !important; 
            border: none !important;
        }

        /* จัดการตารางให้กระชับและคมชัด */
        .table-print { 
            width: 100% !important; 
            border: 1px solid #000 !important;
            margin-top: 5px;
        }

        .table-bordered th, .table-bordered td { 
            border: 1px solid #000 !important; 
            padding: 3px 4px !important; /* ลด Padding ในเซลล์เพื่อประหยัดพื้นที่ */
            line-height: 1.1 !important;
        }

        .table thead th { 
            background-color: #f2f2f2 !important; 
            color: #000 !important; 
            -webkit-print-color-adjust: exact; 
        }

        /* บังคับส่วนลงนามอยู่ท้ายหน้าและไม่แยกหน้า */
        .signature-section { 
            display: flex !important; 
            margin-top: 30px !important;
            page-break-inside: avoid; /* ป้องกันไม่ให้ลายเซ็นถูกดันไปหน้า 2 */
            width: 100%;
        }
    }
</style>

<div class="container-fluid mt-3">
    <div class="card shadow mb-4 d-print-none">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-calendar-check"></i> รายงานปีงบประมาณ</h6>
            <form method="GET" class="form-inline">
                <label class="mr-2 small">ปีงบประมาณ พ.ศ.</label>
                <select name="year" class="form-control form-control-sm mr-2 shadow-sm">
                    <?php
                    $curr_y = date('Y');
                    for($y=$curr_y; $y>=$curr_y-5; $y--) {
                        echo "<option value='$y' ".($y == $selected_year ? "selected" : "").">".($y+543)."</option>";
                    }
                    ?>
                </select>
                <button type="submit" class="btn btn-success btn-sm shadow-sm mr-2">เรียกดู</button>
                <button onclick="window.print();" class="btn btn-info btn-sm shadow-sm"><i class="fas fa-print"></i> พิมพ์รายงาน</button>
            </form>
        </div>
    </div>

    <div class="print-area">
        <div class="text-center mb-4">
            <img src="images/logo2.png" class="logo-print">
            <h4 class="font-weight-bold mb-1">สรุปสถานะคลังและสถิติการยืมอุปกรณ์รายปีงบประมาณ</h4>
            <p class="h6 mb-0">โรงพยาบาลทรายมูล จังหวัดยโสธร</p>
            <p class="font-weight-bold text-success mt-1">
                ปีงบประมาณ พ.ศ. <?= $selected_year + 543 ?> 
                <small class="text-muted">(1 ต.ค. <?= $selected_year + 542 ?> - 30 ก.ย. <?= $selected_year + 543 ?>)</small>
            </p>
        </div>

        <table class="table table-bordered text-center table-print">
            <thead class="bg-light small">
                <tr>
                    <th rowspan="2" class="align-middle">ประเภทอุปกรณ์</th>
                    <th rowspan="2" class="align-middle">คลังทั้งหมด</th>
                    <th colspan="3">สถานะปัจจุบัน (ณ วันที่พิมพ์)</th>
                    <th rowspan="2" class="align-middle" style="background-color: #f1f8e9 !important;">ยอดยืมสะสมทั้งปี (ครั้ง)</th>
                </tr>
                <tr>
                    <th>พร้อมใช้</th>
                    <th>ยืม</th>
                    <th>ชำรุด</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $t_inv = 0; $t_ready = 0; $t_use = 0; $t_broken = 0; $t_loan = 0;
                while($row = $result->fetch_assoc()): 
                    $t_inv += $row['total_inventory'];
                    $t_ready += $row['total_ready'];
                    $t_use += $row['total_in_use'];
                    $t_broken += $row['total_broken'];
                    $t_loan += $row['yearly_loans'];
                ?>
                <tr>
                    <td class="text-left font-weight-bold"><?= htmlspecialchars($row['eq_name']) ?></td>
                    <td><?= number_format($row['total_inventory']) ?></td>
                    <td class="text-success"><?= number_format($row['total_ready']) ?></td>
                    <td class="text-warning"><?= number_format($row['total_in_use']) ?></td>
                    <td class="text-danger"><?= number_format($row['total_broken']) ?></td>
                    <td class="font-weight-bold text-success bg-light"><?= number_format($row['yearly_loans']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot class="font-weight-bold bg-light">
                <tr>
                    <td>รวมยอดสะสมทั้งปี</td>
                    <td><?= number_format($t_inv) ?></td>
                    <td><?= number_format($t_ready) ?></td>
                    <td><?= number_format($t_use) ?></td>
                    <td><?= number_format($t_broken) ?></td>
                    <td><?= number_format($t_loan) ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="row signature-section">
            <div class="col-6 text-center">
                <p>ลงชื่อ......................................................ผู้รายงาน</p>
                <p>(......................................................)</p>
                <p>ตำแหน่ง......................................................</p>
                <p class="small">วันที่........./........../..........</p>
            </div>
            <div class="col-6 text-center">
                <p>ลงชื่อ......................................................ผู้ตรวจสอบ</p>
                <p>(......................................................)
                <p>ตำแหน่ง......................................................</p>
                </p>
                <p class="small">วันที่........./........../..........</p>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>