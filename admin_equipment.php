<?php
// --- 1. ส่วนประมวลผล Logic (ต้องอยู่บนสุดเพื่อป้องกัน Error 500) ---
session_start();
include('db_connect.php');
include_once('functions.php'); 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- ประมวลผลการบันทึกข้อมูล (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_eq'])) {
    $eq_id = (int)$_POST['eq_id'];
    $eq_name = trim($_POST['eq_name']);
    $asset_number = trim($_POST['asset_number']);
    $status_input = $_POST['status_input']; 

    $total_qty = 1; 
    $broken_qty = ($status_input === 'broken') ? 1 : 0;

    // --- ตรวจสอบเลขครุภัณฑ์ซ้ำ ---
    $check_asset = $conn->prepare("SELECT eq_id FROM equipment WHERE asset_number = ? AND eq_id != ? AND is_active = 1");
    $check_asset->bind_param("si", $asset_number, $eq_id);
    $check_asset->execute();
    $res_check = $check_asset->get_result();

    if ($res_check->num_rows > 0) {
        $message = '<div class="alert alert-danger shadow-sm">⚠️ ไม่สามารถบันทึกได้: เลขครุภัณฑ์ "'.htmlspecialchars($asset_number).'" มีอยู่ในระบบแล้ว</div>';
    } else {
        if ($eq_id > 0) {
            // กรณีแก้ไข: ตรวจสอบสถานะการยืม
            $loan_check_res = $conn->query("SELECT COUNT(*) FROM loan_transactions WHERE eq_id = $eq_id AND status = 'ค้างส่ง'");
            $loan_check = $loan_check_res->fetch_row()[0];
            
            if ($loan_check > 0 && $status_input === 'broken') {
                 $message = '<div class="alert alert-warning shadow-sm">⚠️ ไม่สามารถตั้งเป็น "ชำรุด" ได้ เนื่องจากอุปกรณ์ถูกยืมอยู่</div>';
            } else {
                $available_qty = ($loan_check == 0 && $broken_qty == 0) ? 1 : 0;
                $stmt = $conn->prepare("UPDATE equipment SET eq_name=?, asset_number=?, total_qty=?, broken_qty=?, available_qty=? WHERE eq_id=?");
                $stmt->bind_param("ssiiii", $eq_name, $asset_number, $total_qty, $broken_qty, $available_qty, $eq_id);
                
                if ($stmt->execute()) {
                    writeLog($conn, 'UPDATE_EQUIPMENT', 'equipment', $eq_id, "แก้ไข: $eq_name ($asset_number)");
                    header("Location: admin_equipment.php?msg=" . urlencode("อัปเดตข้อมูลสำเร็จ"));
                    exit;
                }
            }
        } else {
            // กรณีเพิ่มอุปกรณ์ใหม่
            $available_qty = ($broken_qty === 0) ? 1 : 0;
            $stmt = $conn->prepare("INSERT INTO equipment (eq_name, asset_number, total_qty, broken_qty, available_qty, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("ssiii", $eq_name, $asset_number, $total_qty, $broken_qty, $available_qty);
            if ($stmt->execute()) {
                writeLog($conn, 'INSERT_EQUIPMENT', 'equipment', $conn->insert_id, "เพิ่มใหม่: $eq_name ($asset_number)");
                header("Location: admin_equipment.php?msg=" . urlencode("เพิ่มครุภัณฑ์เรียบร้อย"));
                exit;
            }
        }
    }
}

// --- การลบอุปกรณ์ ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $info = $conn->query("SELECT eq_name FROM equipment WHERE eq_id = $id")->fetch_assoc();
    if($conn->query("UPDATE equipment SET is_active = 0 WHERE eq_id = $id")) {
        writeLog($conn, 'DELETE_EQUIPMENT', 'equipment', $id, "ยกเลิกใช้งาน: " . $info['eq_name']);
    }
    header("Location: admin_equipment.php"); exit;
}

// --- 2. ส่วนแสดงผล UI ---
include('header.php');

$edit_eq = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res_edit = $conn->query("SELECT * FROM equipment WHERE eq_id = $id");
    if ($res_edit) $edit_eq = $res_edit->fetch_assoc();
}

$search = isset($_POST['search']) ? trim($_POST['search']) : (isset($_GET['search']) ? trim($_GET['search']) : '');
$filter_status = isset($_POST['filter_status']) ? $_POST['filter_status'] : (isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all');

// สรุปยอด Summary Cards
$stat_total = $conn->query("SELECT COUNT(*) FROM equipment WHERE is_active = 1")->fetch_row()[0];
$stat_available = $conn->query("SELECT COUNT(*) FROM equipment WHERE is_active = 1 AND broken_qty = 0 AND available_qty = 1")->fetch_row()[0];
$stat_borrowed = $conn->query("SELECT COUNT(*) FROM equipment WHERE is_active = 1 AND broken_qty = 0 AND available_qty = 0")->fetch_row()[0];
$stat_broken = $conn->query("SELECT COUNT(*) FROM equipment WHERE is_active = 1 AND broken_qty = 1")->fetch_row()[0];

$where = "WHERE is_active = 1";
if (!empty($search)) $where .= " AND (eq_name LIKE '%$search%' OR asset_number LIKE '%$search%')";
if ($filter_status == 'available') $where .= " AND broken_qty = 0 AND available_qty = 1";
elseif ($filter_status == 'borrowed') $where .= " AND broken_qty = 0 AND available_qty = 0";
elseif ($filter_status == 'broken') $where .= " AND broken_qty = 1";

$equipment_list = $conn->query("SELECT * FROM equipment $where ORDER BY eq_id DESC");
?>


<style>
    .card-stat { border: none; transition: 0.3s; }
    .card-stat:hover { transform: translateY(-5px); }
    .status-badge { width: 100px; padding: 5px 0; border-radius: 50px; font-weight: bold; font-size: 0.75rem; display: inline-block; text-align: center; }
    .badge-available { background-color: #e3fcef; color: #00875a; }
    .badge-borrowed { background-color: #fff9db; color: #f08c00; }
    .badge-broken { background-color: #ffe3e3; color: #e03131; }
</style>

<div class="container-fluid">
    <h4 class="mb-4 text-gray-800 font-weight-bold"><i class="fas fa-barcode"></i> คลังครุภัณฑ์การแพทย์</h4>
    
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stat border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">อุปกรณ์ทั้งหมด</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stat_total) ?> ชิ้น</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stat border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">พร้อมใช้งาน</div>
                            <div class="h5 mb-0 font-weight-bold text-success"><?= number_format($stat_available) ?> ชิ้น</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stat border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">ถูกยืมไป</div>
                            <div class="h5 mb-0 font-weight-bold text-warning"><?= number_format($stat_borrowed) ?> ชิ้น</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-clock fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-stat border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">ชำรุด/งดใช้</div>
                            <div class="h5 mb-0 font-weight-bold text-danger"><?= number_format($stat_broken) ?> ชิ้น</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-tools fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h6 class="m-0 font-weight-bold text-primary">การจัดการข้อมูล</h6>
        <div>
            <a href="export_equipment.php" class="btn btn-sm btn-success shadow-sm">
                <i class="fas fa-file-excel fa-sm text-white-50 mr-1"></i> ส่งออกรายงาน Excel
            </a>
        </div>
    </div>

    <?= $message ?>
    <?php if(isset($_GET['msg'])) echo '<div class="alert alert-success shadow-sm">'.htmlspecialchars($_GET['msg']).'</div>'; ?>

    <div class="card shadow mb-4 border-0">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-primary">
                <?= $edit_eq ? 'แก้ไขครุภัณฑ์: '.$edit_eq['asset_number'] : 'ลงทะเบียนครุภัณฑ์ใหม่' ?>
            </h6>
        </div>
        <div class="card-body bg-light">
            <form method="post" action="admin_equipment.php">
                <input type="hidden" name="eq_id" value="<?= $edit_eq['eq_id'] ?? '0' ?>">
                <div class="row align-items-end">
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold">ชื่ออุปกรณ์</label>
                        <select name="eq_name" class="form-control" required>
                            <option value="">-- เลือกรายการ --</option>
                            <?php 
                            $items = ["เตียงเฟาเลอร์แบบ 3 ไก", "ที่นอนลมแบบลอน(ใหญ่)", "รถเข็นนั่งคนพิการสำหรับผู้ใหญ่", "เครื่องดูดเสมหะ", "รถเข็นปรับนอนได้สำหรับผู้ใหญ่", "เครื่องผลิตอ๊อกซิเจน ขนาด 10 ลิตร", "รถเข็นช่วยเดิน"];
                            foreach ($items as $item): 
                                $selected = (isset($edit_eq['eq_name']) && $edit_eq['eq_name'] == $item) ? 'selected' : '';
                            ?>
                                <option value="<?= $item ?>" <?= $selected ?>><?= $item ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold">เลขครุภัณฑ์</label>
                        <input type="text" name="asset_number" class="form-control" value="<?= htmlspecialchars($edit_eq['asset_number'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold">สถานะ</label>
                        <select name="status_input" class="form-control">
                            <option value="available" <?= (isset($edit_eq['broken_qty']) && $edit_eq['broken_qty'] == 0) ? 'selected' : '' ?>>✅ ปกติ(พร้อมใช้)</option>
                            <option value="broken" <?= (isset($edit_eq['broken_qty']) && $edit_eq['broken_qty'] == 1) ? 'selected' : '' ?>>❌ ชำรุด</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="btn-group w-100 shadow-sm">
                            <button type="submit" name="save_eq" class="btn btn-primary font-weight-bold">
                                <i class="fas fa-save mr-1"></i> บันทึก
                            </button>
                            <?php if ($edit_eq): ?>
                                <a href="admin_equipment.php" class="btn btn-secondary font-weight-bold">
                                    <i class="fas fa-times mr-1"></i> ยกเลิก
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow border-0">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-gray-800">รายการครุภัณฑ์ทั้งหมด</h6>
            <form method="post" class="form-inline">
                <input type="text" name="search" class="form-control form-control-sm mr-2 shadow-sm" placeholder="ค้นหาเลข/ชื่อ" value="<?= htmlspecialchars($search) ?>">
                <select name="filter_status" class="form-control form-control-sm mr-2 shadow-sm" onchange="this.form.submit()">
                    <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>ทุกสถานะ</option>
                    <option value="available" <?= $filter_status == 'available' ? 'selected' : '' ?>>พร้อมใช้</option>
                    <option value="borrowed" <?= $filter_status == 'borrowed' ? 'selected' : '' ?>>ถูกยืม</option>
                    <option value="broken" <?= $filter_status == 'broken' ? 'selected' : '' ?>>ชำรุด</option>
                </select>
                <button type="submit" class="btn btn-sm btn-primary shadow-sm"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size: 0.9rem;">
                <thead class="text-center text-white bg-primary">
                    <tr>
                        <th width="15%">เลขครุภัณฑ์</th>
                        <th class="text-left">ชื่ออุปกรณ์</th>
                        <th width="18%">สถานะปัจจุบัน</th>
                        <th width="12%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $equipment_list->fetch_assoc()): 
                        $is_broken = ($row['broken_qty'] == 1);
                        $is_borrowed = ($row['available_qty'] == 0 && !$is_broken);
                    ?>
                    <tr class="text-center">
                        <td class="font-weight-bold text-dark"><?= htmlspecialchars($row['asset_number']) ?></td>
                        <td class="text-left"><?= htmlspecialchars($row['eq_name']) ?> <small class="text-muted">(ID: #<?= $row['eq_id'] ?>)</small></td>
                        <td>
                            <?php if ($is_broken): ?><div class="status-badge badge-broken">ชำรุด</div>
                            <?php elseif ($is_borrowed): ?><div class="status-badge badge-borrowed">ถูกยืม</div>
                            <?php else: ?><div class="status-badge badge-available">พร้อมใช้</div><?php endif; ?>
                        </td>
                        <td>
                            <a href="admin_equipment.php?action=edit&id=<?= $row['eq_id'] ?>" class="text-warning mr-3" title="แก้ไข"><i class="fas fa-edit"></i></a>
                            <a href="admin_equipment.php?action=delete&id=<?= $row['eq_id'] ?>" class="text-danger" onclick="return confirm('ยืนยันลบ?')" title="ลบ"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>