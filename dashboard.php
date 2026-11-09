<?php
// 1. ตั้งค่า Timezone ให้เป็นประเทศไทยเพื่อให้เวลาอัปเดตตรงกับปัจจุบัน
date_default_timezone_set('Asia/Bangkok'); 

include('db_connect.php'); 
include('header.php'); 
checkAuth(); 

// 2. ข้อมูลสถิติด้านคลังอุปกรณ์ (อิงระบบ 1 หมายเลขครุภัณฑ์ : 1 ชิ้น)
$total_inventory = $conn->query("SELECT COUNT(eq_id) AS total FROM equipment WHERE is_active = 1")->fetch_assoc()['total'] ?? '0';
$total_available = $conn->query("SELECT COUNT(eq_id) AS total FROM equipment WHERE is_active = 1 AND broken_qty = 0 AND available_qty = 1")->fetch_row()[0] ?? '0';
$total_broken = $conn->query("SELECT COUNT(eq_id) AS total FROM equipment WHERE is_active = 1 AND broken_qty = 1")->fetch_row()[0] ?? '0';
$in_use = $conn->query("SELECT COUNT(eq_id) AS total FROM equipment WHERE is_active = 1 AND broken_qty = 0 AND available_qty = 0")->fetch_row()[0] ?? '0';

// 3. ข้อมูลกราฟแท่ง: การยืมรายสังกัด (10 รพ.สต. หลัก)
$base_affiliations = ['PCU', 'รพ.สต.โคกยาว', 'รพ.สต.ดงมะไฟ', 'รพ.สต.ดู่ลาด', 'รพ.สต.นาเวียง', 'รพ.สต.หนองแวง', 'รพ.สต.สีสุก', 'รพ.สต.ไผ่', 'รพ.สต.คำครตา', 'รพ.สต.โคกกลาง'];
$loan_stats_sql = "SELECT p.affiliation, COUNT(t.loan_id) AS loan_count FROM loan_transactions t JOIN patients p ON t.patient_id = p.patient_id WHERE p.affiliation IN ('".implode("','", $base_affiliations)."') GROUP BY p.affiliation";
$loan_stats_result = $conn->query($loan_stats_sql);
$chart_data = array_fill_keys($base_affiliations, 0); 
while ($row = $loan_stats_result->fetch_assoc()) { $chart_data[$row['affiliation']] = (int)$row['loan_count']; }
$jsChartLabels = json_encode(array_keys($chart_data));
$jsChartData = json_encode(array_values($chart_data));

// 4. ข้อมูลกราฟวงกลม 1: สัดส่วนประเภทอุปกรณ์ (รวมทั้งหมด)
$eq_stats_sql = "SELECT e.eq_name, COUNT(t.loan_id) as count FROM loan_transactions t JOIN equipment e ON t.eq_id = e.eq_id GROUP BY e.eq_name ORDER BY count DESC";
$eq_stats_res = $conn->query($eq_stats_sql);
$eq_labels = []; $eq_counts = [];
while($row = $eq_stats_res->fetch_assoc()){ $eq_labels[] = $row['eq_name']; $eq_counts[] = (int)$row['count']; }
$jsEqLabels = json_encode($eq_labels);
$jsEqCounts = json_encode($eq_counts);

// 5. [เพิ่มใหม่] ข้อมูลกราฟวงกลม 2: เปรียบเทียบสถานะ ค้างส่ง vs ส่งคืนแล้ว
$status_stats_sql = "SELECT status, COUNT(loan_id) as count FROM loan_transactions GROUP BY status";
$status_stats_res = $conn->query($status_stats_sql);
$status_data = ['ค้างส่ง' => 0, 'ส่งคืนแล้ว' => 0];
while($row = $status_stats_res->fetch_assoc()){ $status_data[$row['status']] = (int)$row['count']; }
$jsStatusLabels = json_encode(array_keys($status_data));
$jsStatusCounts = json_encode(array_values($status_data));
?>

<style>
    .hover-card { transition: all 0.3s ease; border-radius: 12px; border: none; }
    .hover-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .card-summary-title { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold"><i class="fas fa-chart-line"></i> Dashboard สรุปภาพรวม</h1>
        <span class="text-muted small font-weight-bold">อัปเดตล่าสุด: <?= date('d/m/Y H:i') ?> น.</span>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow hover-card py-2 text-white" style="background: linear-gradient(45deg, #4e73df, #224abe);">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="card-summary-title">คลังครุภัณฑ์ทั้งหมด</div>
                            <div class="h3 mb-0 font-weight-bold"><?= number_format($total_inventory) ?> <small>ชิ้น</small></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-warehouse fa-2x opacity-5"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow hover-card py-2 text-white" style="background: linear-gradient(45deg, #1cc88a, #13855c);">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="card-summary-title">ว่าง / พร้อมใช้งาน</div>
                            <div class="h3 mb-0 font-weight-bold"><?= number_format($total_available) ?> <small>ชิ้น</small></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x opacity-5"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow hover-card py-2 text-white" style="background: linear-gradient(45deg, #f6c23e, #dda20a);">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="card-summary-title">ถูกยืมไปใช้งาน</div>
                            <div class="h3 mb-0 font-weight-bold"><?= number_format($in_use) ?> <small>รายการ</small></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-hand-holding-medical fa-2x opacity-5"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow hover-card py-2 text-white" style="background: linear-gradient(45deg, #e74a3b, #be2617);">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="card-summary-title">ชำรุดงดใช้งาน</div>
                            <div class="h3 mb-0 font-weight-bold"><?= number_format($total_broken) ?> <small>ชิ้น</small></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-tools fa-2x opacity-5"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">สถิติการยืมแยกตามหน่วยงาน (รพ.สต.)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 300px;"><canvas id="affiliationChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">สัดส่วนประเภทอุปกรณ์</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 300px;"><canvas id="equipmentPieChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-success">สัดส่วนการคืนอุปกรณ์</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie" style="height: 300px;"><canvas id="statusPieChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4 border-left-danger">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-exclamation-circle"></i> รายชื่อผู้ป่วยที่เกินกำหนดคืน</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover m-0" style="font-size: 0.9rem;">
                            <thead class="bg-light text-center">
                                <tr>
                                    <th width="10%">กำหนดคืน</th>
                                    <th width="20%">ชื่อผู้ป่วย</th>
                                    <th width="10%">สถานบริการ(รพ.สต.)</th>
                                    <th>อุปกรณ์ที่ยืม</th>
                                    <th width="15%">เบอร์โทรศัพท์</th>
                                    <th width="15%">ระยะเวลาค้าง</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $today_str = date('Y-m-d');
                                $overdue_sql = "SELECT t.*, p.full_name, p.phone, p.affiliation, e.eq_name, e.asset_number FROM loan_transactions t JOIN patients p ON t.patient_id = p.patient_id JOIN equipment e ON t.eq_id = e.eq_id WHERE t.status = 'ค้างส่ง' AND t.due_date < '$today_str' ORDER BY t.due_date ASC";
                                $res = $conn->query($overdue_sql);
                                if ($res->num_rows > 0):
                                    while($row = $res->fetch_assoc()):
                                        $diff = floor((strtotime($today_str) - strtotime($row['due_date'])) / (60 * 60 * 24));
                                ?>
                                <tr class="text-center table-warning">
                                    <td class="font-weight-bold text-danger"><?= date('d/m/Y', strtotime($row['due_date'])) ?></td>
                                    <td class="text-left font-weight-bold"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td><span class="badge badge-light border"><?= $row['affiliation'] ?></span></td>
                                    <td class="text-left"><?= htmlspecialchars($row['eq_name']) ?> <small class="text-muted">(SN: <?= $row['asset_number'] ?>)</small></td>
                                    <td><i class="fas fa-phone-alt text-primary small"></i> <?= $row['phone'] ?: '-' ?></td>
                                    <td><span class="badge badge-danger">เกินกำหนด <?= $diff ?> วัน</span></td>
                                </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">ไม่มีรายการที่เกินกำหนดคืนในขณะนี้</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // กราฟแท่ง: การยืมรายหน่วยงาน
    new Chart(document.getElementById("affiliationChart"), {
        type: 'bar',
        data: {
            labels: <?= $jsChartLabels ?>,
            datasets: [{ label: "ครั้ง", backgroundColor: "#4e73df", data: <?= $jsChartData ?> }]
        },
        options: { maintainAspectRatio: false, scales: { yAxes: [{ ticks: { beginAtZero: true, stepSize: 1 } }] }, legend: { display: false } }
    });

    // กราฟวงแหวน 1: สัดส่วนอุปกรณ์
    new Chart(document.getElementById("equipmentPieChart"), {
        type: 'doughnut',
        data: {
            labels: <?= $jsEqLabels ?>,
            datasets: [{
                data: <?= $jsEqCounts ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69']
            }]
        },
        options: { maintainAspectRatio: false, cutoutPercentage: 70, legend: { position: 'bottom', labels: { boxWidth: 10, fontSize: 10 } } }
    });

    // [เพิ่มใหม่] กราฟวงแหวน 2: ค้างส่ง vs คืนแล้ว
    new Chart(document.getElementById("statusPieChart"), {
        type: 'doughnut',
        data: {
            labels: <?= $jsStatusLabels ?>,
            datasets: [{
                data: <?= $jsStatusCounts ?>,
                backgroundColor: ['#e74a3b', '#1cc88a'] // สีแดงสำหรับค้างส่ง, สีเขียวสำหรับคืนแล้ว
            }]
        },
        options: { maintainAspectRatio: false, cutoutPercentage: 70, legend: { position: 'bottom', labels: { boxWidth: 12, fontSize: 11 } } }
    });
});
</script>

<?php $conn->close(); include('footer.php'); ?>