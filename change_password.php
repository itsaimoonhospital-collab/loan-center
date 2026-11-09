<?php
// 1. ตรวจสอบ Session ก่อนสิ่งอื่นใด
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include('db_connect.php');
include_once('functions.php'); // มั่นใจว่ามีฟังก์ชัน writeLog ในนี้

// 2. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน";
    } elseif (strlen($new_password) < 6) {
        $error = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    } else {
        // ดึงข้อมูลรหัสเดิม
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if ($user && password_verify($old_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $upd->bind_param("si", $hashed, $user_id);
            
            if ($upd->execute()) {
                // บันทึก Log
                if (function_exists('writeLog')) {
                    writeLog($conn, 'UPDATE_PASSWORD', 'users', $user_id, "เจ้าหน้าที่: " . $_SESSION['name'] . " เปลี่ยนรหัสผ่านสำเร็จ");
                }
                
                $success = "เปลี่ยนรหัสผ่านสำเร็จ! ระบบกำลังนำท่านกลับหน้าหลัก...";
                // ใช้ JavaScript แทน header refresh เพื่อป้องกันปัญหาหน้าขาวบางกรณี
                echo "<script>
                    setTimeout(function(){
                        window.location.href = 'dashboard.php';
                    }, 2000);
                </script>";
            } else {
                $error = "ไม่สามารถอัปเดตข้อมูลในฐานข้อมูลได้";
            }
        } else {
            $error = "รหัสผ่านเดิมไม่ถูกต้อง";
        }
    }
}

// ค่อย include header หลังจากประมวลผล Logic เสร็จเพื่อเลี่ยงปัญหา Header already sent
include('header.php'); 
?>

<div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="card shadow border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-5">
            <h5 class="text-center font-weight-bold mb-4">เปลี่ยนรหัสผ่านใหม่</h5>

            <?php if ($error): ?>
                <div class="alert alert-danger small"><?= $error ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success small text-center"><?= $success ?></div>
            <?php else: ?>
                <form method="post">
                <div class="text-center mb-4">
                <i class="fas fa-key fa-3x text-primary mb-3"></i>
                <p class="text-muted small">รักษาความปลอดภัยข้อมูลตามมาตรฐาน PDPA</p>
            </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">รหัสผ่านเดิม</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block shadow font-weight-bold">บันทึกข้อมูล</button>
                    </button>
                    <a href="dashboard.php" class="btn btn-link btn-block mt-2 text-muted small">ยกเลิก</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>