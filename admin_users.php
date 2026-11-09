<?php
include('header.php');
include('db_connect.php');
checkAuth();

// ตรวจสอบสิทธิ์ Admin
if (!isAdmin()) {
    echo '<div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
    include('footer.php');
    exit;
}

$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$edit_user = null;

// --- Process CRUD Operations ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $name = $_POST['name'];
    $role = $_POST['role'];
    $position = $_POST['position'];
    $password = $_POST['password'];
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    // --- ส่วนจัดการอัปโหลดลายเซ็น ---
    $signature_name = null;
    if (isset($_FILES['staff_signature']) && $_FILES['staff_signature']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES["staff_signature"]["name"], PATHINFO_EXTENSION));
        $allowed = array("png", "jpg", "jpeg");
        
        if (in_array($ext, $allowed)) {
            $signature_name = "staff_sig_" . date("Ymd_His") . "_" . uniqid() . "." . $ext;
            if (!file_exists("signatures/")) { mkdir("signatures/", 0777, true); }
            move_uploaded_file($_FILES["staff_signature"]["tmp_name"], "signatures/" . $signature_name);
        }
    }

    if ($user_id > 0) {
        // --- กรณีแก้ไขผู้ใช้งาน ---
        $sql = "UPDATE users SET username=?, name=?, role=?, position=?";
        $params = [$username, $name, $role, $position];
        $types = "ssss";

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password=?";
            $params[] = $hashed_password;
            $types .= "s";
        }

        if ($signature_name) {
            $sql .= ", staff_signature=?";
            $params[] = $signature_name;
            $types .= "s";
        }

        $sql .= " WHERE user_id=?";
        $params[] = $user_id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $message = "อัปเดตผู้ใช้งานสำเร็จ!";
        } else {
            $message = "เกิดข้อผิดพลาด: " . $stmt->error;
        }
        
    } else {
        // --- กรณีเพิ่มผู้ใช้งานใหม่ ---
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, name, role, position, password, staff_signature) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $name, $role, $position, $hashed_password, $signature_name);
            if ($stmt->execute()) {
                $message = "เพิ่มผู้ใช้งานสำเร็จ!";
            } else {
                $message = "เกิดข้อผิดพลาด: " . $stmt->error;
            }
        } else {
            $message = "กรุณากรอกรหัสผ่านสำหรับผู้ใช้งานใหม่";
        }
    }
}

// 2. ลบผู้ใช้งาน (คงเดิม)
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id !== $_SESSION['user_id']) { 
        if ($conn->query("DELETE FROM users WHERE user_id = $id")) {
            $message = "ลบผู้ใช้งานสำเร็จ!";
        } else {
            $message = "เกิดข้อผิดพลาด: " . $conn->error;
        }
    } else {
        $message = "ไม่สามารถลบบัญชีผู้ใช้งานของตนเองได้";
    }
    header("Location: admin_users.php?msg=" . urlencode($message));
    exit;
}

// 3. เตรียมข้อมูลแก้ไข
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $result = $conn->query("SELECT user_id, username, name, role, position, staff_signature FROM users WHERE user_id = $id");
    if ($result->num_rows == 1) {
        $edit_user = $result->fetch_assoc();
    }
}

$user_list = $conn->query("SELECT user_id, username, name, role, position, staff_signature FROM users ORDER BY user_id DESC");
?>

<h4 class="mb-4">👨‍👨‍ จัดการสมาชิกผู้ใช้งานระบบ</h4>

<div class="card mb-4 shadow-sm">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><?php echo $edit_user ? 'แก้ไขผู้ใช้งาน: ' . $edit_user['name'] : 'เพิ่มผู้ใช้งานใหม่'; ?></h5>
    </div>
    <div class="card-body">
        <form method="post" action="admin_users.php" enctype="multipart/form-data">
            <input type="hidden" name="user_id" value="<?php echo $edit_user ? $edit_user['user_id'] : ''; ?>">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>ชื่อ-สกุล:</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $edit_user ? $edit_user['name'] : ''; ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>ตำแหน่ง:</label>
                    <input type="text" name="position" class="form-control" value="<?php echo $edit_user ? $edit_user['position'] : ''; ?>" required>
                </div>
                <div class="form-group col-md-3">
                    <label>สิทธิ์:</label>
                    <select name="role" class="form-control" required>
                        <option value="user" <?php echo ($edit_user && $edit_user['role'] == 'user') ? 'selected' : ''; ?>>User (ผู้บันทึก)</option>
                        <option value="admin" <?php echo ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Admin (ผู้ดูแลระบบ)</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>ชื่อผู้ใช้งาน (Login):</label>
                    <input type="text" name="username" class="form-control" value="<?php echo $edit_user ? $edit_user['username'] : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>รหัสผ่าน: <?php echo $edit_user ? '(เว้นว่างหากไม่ต้องการเปลี่ยน)' : ''; ?></label>
                    <input type="password" name="password" class="form-control" <?php echo $edit_user ? '' : 'required'; ?>>
                </div>
                <div class="form-group col-md-6">
                    <label>ลายเซ็นเจ้าหน้าที่ (ไฟล์ภาพ .png/.jpg):</label>
                    <input type="file" name="staff_signature" class="form-control-file" accept="image/*">
                    <?php if ($edit_user && $edit_user['staff_signature']): ?>
                        <div class="mt-2">
                            <small class="text-muted">ลายเซ็นเดิม:</small><br>
                            <img src="signatures/<?php echo $edit_user['staff_signature']; ?>" height="50" style="border: 1px solid #ddd; background: #f9f9f9;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn btn-<?php echo $edit_user ? 'warning' : 'success'; ?> px-4">
                <i class="fas fa-save"></i> <?php echo $edit_user ? 'บันทึกการแก้ไข' : 'เพิ่มผู้ใช้งาน'; ?>
            </button>
            <?php if ($edit_user): ?>
                <a href="admin_users.php" class="btn btn-secondary px-4">ยกเลิก</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<h4>รายการผู้ใช้งานทั้งหมด</h4>
<div class="table-responsive">
    <table class="table table-bordered table-striped bg-white">
        <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>ชื่อ-สกุล</th>
                <th>ตำแหน่ง</th>
                <th>สิทธิ์</th>
                <th>ลายเซ็น</th>
                <th width="150">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $user_list->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['user_id']; ?></td>
                    <td><strong><?php echo $row['name']; ?></strong><br><small class="text-muted"><?php echo $row['username']; ?></small></td>
                    <td><?php echo $row['position']; ?></td>
                    <td><span class="badge badge-<?php echo ($row['role'] == 'admin') ? 'danger' : 'info'; ?>"><?php echo strtoupper($row['role']); ?></span></td>
                    <td class="text-center">
                        <?php if ($row['staff_signature']): ?>
                            <img src="signatures/<?php echo $row['staff_signature']; ?>" height="40" style="background: #eee;">
                        <?php else: ?>
                            <small class="text-muted">ไม่มีข้อมูล</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="admin_users.php?action=edit&id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-warning">แก้ไข</a>
                        <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                            <a href="admin_users.php?action=delete&id=<?php echo $row['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('ยืนยันการลบ?');">ลบ</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php $conn->close(); include('footer.php'); ?>