<?php
include('header.php');
include('db_connect.php');

// ตรวจสอบสิทธิ์ (เฉพาะ Admin เท่านั้น)
if (!isAdmin()) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div></div>";
    exit;
}

// 1. รับค่าตัวกรอง
$user_filter = isset($_POST['user_filter']) ? $_POST['user_filter'] : 'all';
$action_filter = isset($_POST['action_filter']) ? $_POST['action_filter'] : 'all';

// 2. สร้างเงื่อนไข SQL
$where = "WHERE 1=1";
if ($user_filter != 'all') {
    $where .= " AND l.user_id = " . (int)$user_filter;
}
if ($action_filter != 'all') {
    $where .= " AND l.action = '" . $conn->real_escape_string($action_filter) . "'";
}

// 3. Query ข้อมูล Logs
$sql = "SELECT l.*, u.name as staff_name 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.user_id 
        $where 
        ORDER BY l.log_id DESC LIMIT 500"; // ดึงล่าสุด 500 รายการ

$result = $conn->query($sql);

// ดึงรายชื่อผู้ใช้งานมาทำ Dropdown Filter
$user_list = $conn->query("SELECT user_id, name FROM users ORDER BY name ASC");
// ดึงประเภทกิจกรรมมาทำ Dropdown Filter
$action_list = $conn->query("SELECT DISTINCT action FROM system_logs ORDER BY action ASC");
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-alt mr-1"></i> บันทึกกิจกรรมในระบบ (System Logs)</h6>
            
            <form method="POST" class="form-inline">
                <select name="user_filter" class="form-control form-control-sm mr-2">
                    <option value="all">ทุกคน</option>
                    <?php while($u = $user_list->fetch_assoc()): ?>
                        <option value="<?=$u['user_id']?>" <?=($user_filter == $u['user_id'])?'selected':''?>><?=$u['name']?></option>
                    <?php endwhile; ?>
                </select>
                
                <select name="action_filter" class="form-control form-control-sm mr-2">
                    <option value="all">ทุกกิจกรรม</option>
                    <?php while($a = $action_list->fetch_assoc()): ?>
                        <option value="<?=$a['action']?>" <?=($action_filter == $a['action'])?'selected':''?>><?=$a['action']?></option>
                    <?php endwhile; ?>
                </select>
                
                <button type="submit" class="btn btn-primary btn-sm">กรองข้อมูล</button>
            </form>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" style="font-size: 0.85rem;">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th width="50">ID</th>
                            <th width="160">วัน-เวลา</th>
                            <th width="150">เจ้าหน้าที่</th>
                            <th width="150">กิจกรรม</th>
                            <th>รายละเอียด</th>
                            <th width="130">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                // ใส่สีให้กิจกรรมต่างๆ
                                $badge_class = 'badge-secondary';
                                if(strpos($row['action'], 'INSERT') !== false) $badge_class = 'badge-success';
                                if(strpos($row['action'], 'DELETE') !== false) $badge_class = 'badge-danger';
                                if(strpos($row['action'], 'LOGIN') !== false) $badge_class = 'badge-info';
                                if(strpos($row['action'], 'RETURN') !== false) $badge_class = 'badge-primary';
                            ?>
                                <tr>
                                    <td class="text-muted">#<?=$row['log_id']?></td>
                                    <td><?=date('d/m/Y H:i:s', strtotime($row['created_at']))?></td>
                                    <td><strong><?=htmlspecialchars($row['staff_name'] ?? 'System')?></strong></td>
                                    <td><span class="badge <?=$badge_class?>"><?=$row['action']?></span></td>
                                    <td class="text-dark"><?=htmlspecialchars($row['details'])?></td>
                                    <td class="small text-muted"><?=$row['ip_address']?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลบันทึกกิจกรรม</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>