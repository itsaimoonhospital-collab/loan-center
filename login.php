<?php
session_start();
include('db_connect.php');
include_once('functions.php'); // ดึงฟังก์ชัน writeLog มาใช้งาน

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 1. เพิ่ม position เข้าไปในคำสั่ง SELECT
    $stmt = $conn->prepare("SELECT user_id, password, role, name, position FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $username; 
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            
            // 2. แก้ไขให้ตรงกับชื่อตัวแปรที่ดึงข้อมูลมา
            $_SESSION['position'] = $user['position']; 

            // --- บันทึก Log การเข้าสู่ระบบ ---
            $log_details = "ผู้ใช้งาน: " . $user['name'] . " เข้าสู่ระบบ";
            writeLog($conn, 'LOGIN', 'users', $user['user_id'], $log_details);

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>เข้าสู่ระบบ | ระบบยืมคืนอุปกรณ์</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" href="./images/logo2.png">
    <style>
        body {
            background-color: #f8f9fa; 
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        .login-card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .logo-img {
            max-width: 100px; 
            height: auto;
            margin-bottom: 15px;
        }
        .input-group-text {
            background-color: #fff;
            cursor: pointer;
            border-left: none;
        }
        .password-field {
            border-right: none;
        }
        /* ตกแต่งลิงก์เปลี่ยนรหัสผ่าน */
        .forgot-link {
            font-size: 0.85rem;
            color: #6c757d;
            text-decoration: none;
            transition: 0.3s;
        }
        .forgot-link:hover {
            color: #4e73df;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <img src="images/logo2.png" alt="Logo หน่วยงาน" class="logo-img"> 
                    <h4 class="card-title text-primary">ระบบยืมคืนอุปกรณ์ผู้ป่วย</h4>
                    <p class="text-muted">โรงพยาบาลทรายมูล จังหวัดยโสธร</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" id="formLogin">
                    <div class="form-group">
                        <label for="username">ชื่อผู้ใช้งาน:</label>
                        <input type="text" name="username" id="username" class="form-control" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">รหัสผ่าน:</label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control password-field" required>
                            <div class="input-group-append">
                                <span class="input-group-text" id="togglePassword">
                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block mt-4">
                        <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                    </button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3 text-muted">
            <small>&copy; Copyright <?php echo date('Y'); ?> ระบบยืมคืนอุปกรณ์ผู้ป่วยงานเวชกรรมฟื้นฟู</small><br>
            <small> พัฒนาโดย | นายจิรายุสม์ ทองเฟื่อง นักวิชาการคอมพิวเตอร์</small><br>
            <small>โรงพยาบาลทรายมูล</small>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $("#togglePassword").click(function() {
                const passwordField = $("#password");
                const eyeIcon = $("#eyeIcon");
                const type = passwordField.attr("type") === "password" ? "text" : "password";
                passwordField.attr("type", type);
                eyeIcon.toggleClass("fa-eye fa-eye-slash");
            });
        });
    </script>
</body>
</html>