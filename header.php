<?php
// header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่าโซนเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ฟังก์ชันตรวจสอบสิทธิ์
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>ระบบยืมคืนอุปกรณ์ผู้ป่วย | รพ.ทรายมูล</title> 
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="./images/logo2.png">

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <style>
        body { font-family: 'Sarabun', sans-serif; display: flex; flex-direction: column; min-height: 100vh; background-color: #f8f9fc; margin: 0; }
        #wrapper { flex: 1 0 auto; }
        
        #image-banner { 
            height: 190px; 
            background: url('images/banner3.png'); 
            background-size: cover; 
            background-position: center; 
            background-repeat: no-repeat;
            width: 100%;
        }

        .navbar { background-color: #ffffff !important; border-bottom: 2px solid #4e73df; padding-top: 0.8rem; padding-bottom: 0.8rem; }
        .navbar-nav .nav-link { font-weight: 500; color: #5a5c69 !important; padding: 0.5rem 1rem !important; border-radius: 5px; transition: all 0.2s; }
        .navbar-nav .nav-link:hover { color: #4e73df !important; background-color: rgba(78, 115, 223, 0.1); }
        .navbar-nav .nav-item.active .nav-link { color: #4e73df !important; font-weight: 600; }
        .dropdown-menu { border: none; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); border-radius: 8px; }
        .user-badge { background: #eaecf4; padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.9rem; color: #4e73df; font-weight: 600; cursor: pointer; }
        .btn-logout { border-radius: 50px; padding: 0.4rem 1.2rem; font-weight: 600; margin-left: 10px; }
        .main-content { padding-top: 2rem; padding-bottom: 5rem; }
    </style>
</head>
<body>
    <div id="wrapper">
        <header id="image-banner"></header>

        <nav class="navbar navbar-expand-lg navbar-light sticky-top shadow-sm">
            <div class="container-fluid mx-lg-5">
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-th-large mr-1"></i> Dashboard</a></li>
                        <li class="nav-item border-left ml-lg-2 pl-lg-2"><a class="nav-link" href="loan_form.php"><i class="fas fa-plus-circle mr-1 text-success"></i> บันทึกการยืม</a></li>
                        <li class="nav-item"><a class="nav-link" href="outstanding_list.php"><i class="fas fa-exclamation-triangle mr-1 text-danger"></i> ค้างส่ง/คืนอุปกรณ์</a></li>
                        <li class="nav-item"><a class="nav-link" href="history.php"><i class="fas fa-history mr-1"></i> ประวัติรายการ</a></li>
                        <li class="nav-item"><a class="nav-link" href="monthly_report.php"><i class="fas fa-file-invoice mr-1 text-info"></i> สรุปยอดรายเดือน</a></li>
                        <li class="nav-item"><a class="nav-link" href="yearly_report.php"><i class="fas fa-chart-line mr-1 text-primary"></i> สรุปยอดรายปี</a></li>
                        
                        <?php if (isAdmin()): ?>
                            <li class="nav-item dropdown ml-lg-3">
                                <a class="nav-link dropdown-toggle font-weight-bold text-info" href="#" id="adminDropdown" data-toggle="dropdown">
                                    <i class="fas fa-cogs mr-1"></i> จัดการระบบ
                                </a>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="admin_equipment.php"><i class="fas fa-boxes mr-2"></i>จัดการคลังอุปกรณ์</a>
                                    <a class="dropdown-item" href="admin_users.php"><i class="fas fa-users-cog mr-2"></i>จัดการสมาชิก</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="system_logs.php"><i class="fas fa-list-alt mr-2"></i>ดูบันทึกกิจกรรม (Logs)</a>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav ml-auto align-items-center">
                        <li class="nav-item dropdown mb-2 mb-lg-0">
                            <a class="nav-link dropdown-toggle p-0" href="#" id="userDropdown" data-toggle="dropdown">
                                <span class="user-badge shadow-sm">
                                    <i class="fas fa-user-circle mr-1"></i> 
                                    <?php echo htmlspecialchars($_SESSION['name'] ?? 'Guest'); ?> 
                                    <small class="text-muted d-none d-sm-inline">(<?php echo htmlspecialchars($_SESSION['role'] ?? '-'); ?>)</small>
                                </span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <h6 class="dropdown-header">โปรไฟล์ของฉัน</h6>
                                <a class="dropdown-item" href="change_password.php">
                                    <i class="fas fa-key fa-sm fa-fw mr-2 text-warning"></i> เปลี่ยนรหัสผ่าน
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i> ออกจากระบบ
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="container-fluid px-lg-5 main-content">