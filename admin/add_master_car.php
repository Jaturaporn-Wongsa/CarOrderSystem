<?php
/**
 * ==============================================================================
 * File: admin/add_master_car.php - จัดการคลังรถมอเตอร์ไซค์หลัก (Master Data)
 * Description: บันทึก แก้ไข และจัดการข้อมูลรถมอเตอร์ไซค์หลักของระบบ (ฉบับสมบูรณ์)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// ==========================================
// 1. INCLUDE CONFIG & AUTHENTICATION
// ==========================================
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
checkAdmin();

// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================
if (!function_exists('cleanEmoji')) {
    function cleanEmoji($text) {
        if (empty($text)) return '';
        return trim(preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F1E6}-\x{1F1FF}]/u', '', $text));
    }
}

if (!function_exists('getColorStyle')) {
    function getColorStyle($colorName) {
        $c = mb_strtolower(trim($colorName));
        if (empty($c)) return 'color: #64748b;';

        if (str_contains($c, 'espresso')) return 'color: #4a2e2b;';
        if (str_contains($c, 'matte'))    return 'color: #334155;';
        if (str_contains($c, 'black'))    return 'color: #0f172a;';
        if (str_contains($c, 'white'))    return 'color: #64748b;';
        if (str_contains($c, 'red'))      return 'color: #dc2626;';
        if (str_contains($c, 'blue'))     return 'color: #2563eb;';
        if (str_contains($c, 'yellow') || str_contains($c, 'gold')) return 'color: #d97706;';
        if (str_contains($c, 'green'))    return 'color: #16a34a;';
        if (str_contains($c, 'gray') || str_contains($c, 'grey')) return 'color: #64748b;';
        if (str_contains($c, 'silver'))   return 'color: #94a3b8;';

        return 'color: #475569;';
    }
}

// ==========================================
// 3. PROCESS FORM SUBMISSION (ADD MASTER CAR)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ((isset($_POST['action']) && $_POST['action'] === 'add_car') || isset($_POST['brand']))) {
    $brand      = trim($_POST['brand'] ?? '');
    $model_name = trim($_POST['model_name'] ?? '');
    $color_name = trim($_POST['color_name'] ?? '');
    $status     = 'Available';

    if (!empty($brand) && !empty($model_name)) {
        try {
            $conn->beginTransaction();

            // บันทึกลงตาราง cars
            $sql  = "INSERT INTO cars (brand, model_name, status) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$brand, $model_name, $status]);
            $car_id = $conn->lastInsertId();

            // บันทึกสี (แยกด้วยเครื่องหมาย ,)
            if (!empty($color_name)) {
                $colors     = explode(',', $color_name);
                $sql_color  = "INSERT INTO car_colors (car_id, color_name) VALUES (?, ?)";
                $stmt_color = $conn->prepare($sql_color);
                foreach ($colors as $color) {
                    $c = trim($color);
                    if (!empty($c)) {
                        $stmt_color->execute([$car_id, $c]);
                    }
                }
            }

            $conn->commit();
            $_SESSION['success'] = "บันทึกข้อมูลรถมอเตอร์ไซค์ใหม่เรียบร้อยแล้ว";
            header("Location: add_master_car.php");
            exit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "กรุณากรอกยี่ห้อและรุ่นรถมอเตอร์ไซค์ให้ครบถ้วน";
    }
}

// ==========================================
// 4. FETCH MASTER CARS DATA & CALCULATE STATS
// ==========================================
try {
    $sql = "SELECT cars.*, GROUP_CONCAT(car_colors.color_name SEPARATOR ', ') AS color_name 
            FROM cars 
            LEFT JOIN car_colors ON cars.id = car_colors.car_id 
            GROUP BY cars.id 
            ORDER BY cars.id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cars = [];
}

// คำนวณข้อมูลสถิติ
$total_models       = count($cars);
$unique_brands      = count(array_unique(array_filter(array_column($cars, 'brand'))));
$total_colors_count = 0;
$available_cars     = 0;

foreach ($cars as $c) {
    if (!empty($c['color_name'])) {
        $color_arr          = explode(',', $c['color_name']);
        $total_colors_count += count(array_filter(array_map('trim', $color_arr)));
    }
    if (($c['status'] ?? 'Available') === 'Available') {
        $available_cars++;
    }
}

// ==========================================
// 5. INCLUDE LAYOUT HEADERS
// ==========================================
if (file_exists(__DIR__ . '/../includes/header.php')) {
    include_once __DIR__ . '/../includes/header.php';
}
if (file_exists(__DIR__ . '/../includes/navbar.php')) {
    include_once __DIR__ . '/../includes/navbar.php';
}
?>

<!-- External Styles & Fonts -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --font-kanit: 'Kanit', sans-serif;
        --bg-light: #f8fafc;
        --card-border-radius: 16px;
        --modal-border-radius: 24px;
        --primary-blue: #0d6efd;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 80px;
    }

    body, html, button, input, select, textarea, table {
        font-family: var(--font-kanit) !important;
    }

    body {
        background-color: var(--bg-light);
        color: #334155;
        overflow-x: hidden;
    }

    /* Header & Stat Icons */
    .page-header-icon-svg {
        width: 32px !important;
        height: 32px !important;
        min-width: 32px !important;
        min-height: 32px !important;
        display: inline-block !important;
        flex-shrink: 0 !important;
    }

    .stat-card-icon-svg {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
        min-height: 28px !important;
        display: inline-block !important;
        flex-shrink: 0 !important;
    }

    /* Desktop Main Layout & Sidebar Margins */
    #mainContentArea,
    .main-content-layout {
        margin-left: var(--sidebar-width) !important;
        width: calc(100% - var(--sidebar-width)) !important;
        min-height: 100vh;
        padding: 24px 28px !important;
        box-sizing: border-box !important;
        transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                    width 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative;
        z-index: 1;
    }

    /* Sidebar Collapsed State for PC/Desktop */
    body.sidebar-collapsed #mainContentArea,
    body.sidebar-collapsed .main-content-layout,
    body.collapsed-sidebar #mainContentArea,
    body.collapsed-sidebar .main-content-layout,
    .sidebar-collapsed #mainContentArea,
    .sidebar-collapsed .main-content-layout,
    body.collapsed #mainContentArea,
    body.collapsed .main-content-layout,
    .sidebar.collapsed ~ #mainContentArea,
    #sidebar.collapsed ~ #mainContentArea {
        margin-left: var(--sidebar-collapsed-width) !important;
        width: calc(100% - var(--sidebar-collapsed-width)) !important;
    }

    /* Mobile Responsive Shift (Under 768px only) */
    @media (max-width: 767.98px) {
        #mainContentArea,
        .main-content-layout,
        body.sidebar-collapsed #mainContentArea,
        body.sidebar-collapsed .main-content-layout,
        body.collapsed-sidebar #mainContentArea,
        body.collapsed-sidebar .main-content-layout,
        .sidebar-collapsed #mainContentArea,
        .sidebar-collapsed .main-content-layout,
        body.collapsed #mainContentArea,
        body.collapsed .main-content-layout {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 16px !important;
        }
    }

    .fade-in-up {
        animation: fadeInUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Cards & Stats */
    .stat-card, .main-card {
        border-radius: var(--card-border-radius);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    .stat-card {
        transition: all 0.25s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }

    .icon-shape {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        flex-shrink: 0;
    }

    /* Form Controls */
    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        padding: 0.65rem 0.9rem;
        font-size: 0.92rem;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    /* Modal Backdrop & Custom Styling */
    .modal {
        background-color: transparent !important;
    }
    
    .modal-backdrop { 
        background-color: rgba(15, 23, 42, 0.45) !important; 
    }
    
    .modal-backdrop.show { 
        opacity: 1 !important; 
        backdrop-filter: blur(6px) !important;
        -webkit-backdrop-filter: blur(6px) !important;
    }

    .modal-content-custom {
        border-radius: var(--modal-border-radius) !important;
        padding: 12px;
        border: none !important;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1) !important;
    }

    .modal-label-custom {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.92rem;
        margin-bottom: 0.4rem;
    }

    .modal-input-readonly {
        background-color: #f8fafc !important;
        border: 1px solid #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 600;
    }

    /* Tables & Badges */
    .table-custom {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .table-custom thead th {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 12px 14px;
        white-space: nowrap;
    }

    .table-custom tbody td {
        padding: 12px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 0.88rem;
    }

    .table-custom tbody tr:hover {
        background-color: #f8fafc;
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        font-size: 0.78rem;
        font-weight: 500;
        padding: 0.25rem 0.65rem;
        border-radius: 50rem;
        white-space: nowrap;
    }

    .badge-status-available {
        background-color: #f0fdf4;
        color: #16a34a;
        border: 1px solid #dcfce7;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-action:hover {
        transform: translateY(-1px);
    }
</style>

<div class="position-relative dashboard-container">
    
    <!-- Sidebar -->
    <?php 
    if (file_exists(__DIR__ . '/../includes/sidebar.php')) {
        include_once __DIR__ . '/../includes/sidebar.php'; 
    }
    ?>

    <!-- ส่วนเนื้อหาหลัก -->
    <div class="main-content-layout fade-in-up" id="mainContentArea">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show rounded-3 mb-3">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars(cleanEmoji($_SESSION['success'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show rounded-3 mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars(cleanEmoji($_SESSION['error'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- หัวข้อหน้า -->
        <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <svg class="page-header-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="4.5" width="6.5" height="2.4" rx="1.2" fill="#2563eb"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.5 2.5 C12.2 2.5 13.5 3.8 13.5 5.5 C13.5 7.2 12.2 8.5 10.5 8.5 C8.8 8.5 7.5 7.2 7.5 5.5 C7.5 3.8 8.8 2.5 10.5 2.5 Z M10.5 4 C9.7 4 9 4.7 9 5.5 C9 6.3 9.7 7 10.5 7 C11.3 7 12 6.3 12 5.5 C12 4.7 11.3 4 10.5 4 Z" fill="#2563eb"/>
                    <path d="M10.5 7.5 C7 7.5 4.5 10 4.5 13.5 L4.5 17.5 C4.5 18.5 5.3 19.2 6.2 19.2 L7.2 19.2 C7.4 19.2 7.5 19 7.5 18.8 L7.5 16 C7.5 14.3 8.8 13 10.5 13 C12.2 13 13.5 14.3 13.5 16 L13.5 18.8 C13.5 19 13.6 19.2 13.8 19.2 L14.8 19.2 C15.7 19.2 16.5 18.5 16.5 17.5 L16.5 13.5 C16.5 10 14 7.5 10.5 7.5 Z" fill="#2563eb"/>
                    <rect x="8.3" y="13.5" width="4.4" height="8" rx="2.2" fill="#2563eb"/>
                    <circle cx="18" cy="6" r="4.5" fill="#2563eb"/>
                    <path d="M18 3.8 V8.2 M15.8 6 H20.2" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <h3 class="fw-bold text-dark mb-0">จัดการคลังรถมอเตอร์ไซค์หลัก</h3>
            </div>
            <p class="text-muted m-0 small" style="font-size: 0.88rem; line-height: 1.3;">บันทึกรายการยี่ห้อ รุ่นรถ และตัวเลือกสีรถมอเตอร์ไซค์เข้าสู่คลังข้อมูลหลัก (Master Data)</p>
        </div>

        <!-- Section 1: การ์ดสรุปสถิติ 4 ใบ -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">รุ่นมอเตอร์ไซค์ทั้งหมด</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_models); ?> <span class="fs-6 text-muted fw-normal">รุ่น</span></h3>
                        </div>
                        <div class="icon-shape bg-primary bg-opacity-10 text-primary">
                            <svg class="stat-card-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1" y="4.5" width="6.5" height="2.4" rx="1.2" fill="#2563eb"/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M10.5 2.5 C12.2 2.5 13.5 3.8 13.5 5.5 C13.5 7.2 12.2 8.5 10.5 8.5 C8.8 8.5 7.5 7.2 7.5 5.5 C7.5 3.8 8.8 2.5 10.5 2.5 Z M10.5 4 C9.7 4 9 4.7 9 5.5 C9 6.3 9.7 7 10.5 7 C11.3 7 12 6.3 12 5.5 C12 4.7 11.3 4 10.5 4 Z" fill="#2563eb"/>
                                <path d="M10.5 7.5 C7 7.5 4.5 10 4.5 13.5 L4.5 17.5 C4.5 18.5 5.3 19.2 6.2 19.2 L7.2 19.2 C7.4 19.2 7.5 19 7.5 18.8 L7.5 16 C7.5 14.3 8.8 13 10.5 13 C12.2 13 13.5 14.3 13.5 16 L13.5 18.8 C13.5 19 13.6 19.2 13.8 19.2 L14.8 19.2 C15.7 19.2 16.5 18.5 16.5 17.5 L16.5 13.5 C16.5 10 14 7.5 10.5 7.5 Z" fill="#2563eb"/>
                                <rect x="8.3" y="13.5" width="4.4" height="8" rx="2.2" fill="#2563eb"/>
                                <circle cx="18" cy="6" r="4.5" fill="#2563eb"/>
                                <path d="M18 3.8 V8.2 M15.8 6 H20.2" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-archive-fill text-primary me-1"></i> บันทึกในคลัง Master
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">ยี่ห้อในระบบ</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($unique_brands); ?> <span class="fs-6 text-muted fw-normal">แบรนด์</span></h3>
                        </div>
                        <div class="icon-shape bg-info bg-opacity-10 text-info">
                            <i class="bi bi-tags-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-diagram-3 text-info me-1"></i> จำแนกตามผู้ผลิต
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">ตัวเลือกสีรวม</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_colors_count); ?> <span class="fs-6 text-muted fw-normal">เฉดสี</span></h3>
                        </div>
                        <div class="icon-shape bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-palette-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-paint-bucket text-warning me-1"></i> ตัวเลือกสีพร้อมจำหน่าย
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">สถานะพร้อมใช้งาน</span>
                            <h3 class="fw-bold text-success my-1"><?= number_format($available_cars); ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                        </div>
                        <div class="icon-shape bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i> พร้อมเปิดจัดสรรโควตา
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Split Area Layout -->
        <div class="row g-4">
            
            <!-- ฝั่งซ้าย: ฟอร์มเพิ่มรถใหม่ -->
            <div class="col-xl-5 col-lg-5 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-plus-circle-fill text-primary fs-5"></i>
                        <h6 class="fw-bold m-0 text-dark">เพิ่มรถมอเตอร์ไซค์ใหม่</h6>
                    </div>
                    
                    <form method="POST" action="add_master_car.php">
                        <input type="hidden" name="action" value="add_car">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">ยี่ห้อ (Brand)</label>
                                <input type="text" name="brand" class="form-control" placeholder="เช่น Honda, Yamaha, Lambretta" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">รุ่นรถมอเตอร์ไซค์ (Model)</label>
                                <input type="text" name="model_name" class="form-control" placeholder="เช่น X300 GP, Forza 350" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">สีรถ (คั่นด้วยเครื่องหมายจุลภาค ,)</label>
                                <input type="text" name="color_name" class="form-control" placeholder="เช่น ขาว, ดำ, แดง, เทาแมตต์">
                                <div class="form-text text-muted small mt-1">
                                    <i class="bi bi-info-circle me-1"></i>ระบุได้หลายสีโดยใช้เครื่องหมายจุลภาค (,) คั่นระหว่างสี
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-medium d-inline-flex align-items-center gap-2 w-100 justify-content-center">
                                    <i class="bi bi-save"></i> บันทึกข้อมูลเข้าคลัง
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ฝั่งขวา: ตารางรายการรถมอเตอร์ไซค์ในคลังหลัก -->
            <div class="col-xl-7 col-lg-7 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-list-stars text-primary fs-5"></i>
                            <h6 class="fw-bold m-0 text-dark">รายการรถมอเตอร์ไซค์ในคลังหลัก</h6>
                        </div>
                        <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill fw-medium">รวม <?= count($cars) ?> รายการ</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 8%;">#</th>
                                    <th style="width: 37%;">ยี่ห้อ & รุ่นรถ</th>
                                    <th style="width: 30%;">ตัวเลือกสีรถ</th>
                                    <th class="text-center" style="width: 15%;">สถานะ</th>
                                    <th class="text-center" style="width: 10%;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($cars)): $no = 1; foreach ($cars as $car): ?>
                                <tr>
                                    <td class="text-center text-muted font-monospace"><?= $no++ ?></td>
                                    <td>
                                        <div class="fw-bold text-dark text-nowrap">
                                            <?= htmlspecialchars($car['brand'] ?? '-') ?> <?= htmlspecialchars($car['model_name'] ?? '') ?>
                                        </div>
                                        <div class="mt-1">
                                            <span class="badge bg-light text-muted border border-light-subtle font-monospace" style="font-size: 0.72rem;">ID: #<?= $car['id'] ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($car['color_name'])) {
                                            $colors = explode(',', $car['color_name']);
                                            foreach ($colors as $c) {
                                                $trimmed_c = trim($c);
                                                if (!empty($trimmed_c)) {
                                                    echo '<div class="small d-flex align-items-center gap-1 text-nowrap" style="'.getColorStyle($trimmed_c).' font-weight: 500;">
                                                            <i class="bi bi-palette-fill"></i> '.htmlspecialchars($trimmed_c).'
                                                          </div>';
                                                }
                                            }
                                        } else {
                                            echo '<span class="text-muted small">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-status badge-status-available">
                                            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($car['status'] ?? 'Available') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button type="button" class="btn btn-action btn-outline-primary btn-edit-car" 
                                                title="แก้ไข"
                                                data-id="<?= $car['id'] ?>"
                                                data-brand="<?= htmlspecialchars($car['brand'] ?? '', ENT_QUOTES) ?>"
                                                data-model="<?= htmlspecialchars($car['model_name'] ?? '', ENT_QUOTES) ?>"
                                                data-color="<?= htmlspecialchars($car['color_name'] ?? '', ENT_QUOTES) ?>"
                                                data-status="<?= htmlspecialchars($car['status'] ?? 'Available', ENT_QUOTES) ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <a href="../actions/delete_car.php?id=<?= $car['id'] ?>" 
                                               class="btn btn-action btn-outline-danger" 
                                               title="ลบ"
                                               onclick="return confirm('คุณต้องการลบรถมอเตอร์ไซค์คันนี้ใช่หรือไม่?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                            ยังไม่มีข้อมูลรถมอเตอร์ไซค์ในระบบ
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ==================================================================
     EDIT MODAL
     ================================================================== -->
<div class="modal fade" id="editCarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content modal-content-custom bg-white">
            
            <!-- Header -->
            <div class="modal-header border-0 pb-2 pt-3 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary fs-4"></i>
                    <h5 class="modal-title fw-bold text-dark mb-0 fs-5">แก้ไขข้อมูลรถมอเตอร์ไซค์ในคลัง</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="../actions/update_master_car.php" method="POST">
                <input type="hidden" name="id" id="m_car_id">
                
                <!-- Body -->
                <div class="modal-body px-3 py-2">
                    <!-- รหัสรถ (ID) -->
                    <div class="mb-3">
                        <label class="modal-label-custom text-muted small">รหัสรถ (ID)</label>
                        <input type="text" id="m_display_id" class="form-control modal-input-readonly" readonly>
                    </div>

                    <!-- ยี่ห้อ (Brand) & รุ่นมอเตอร์ไซค์ (Model) -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="modal-label-custom">ยี่ห้อ (Brand)</label>
                            <input type="text" name="brand" id="m_brand" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="modal-label-custom">รุ่นมอเตอร์ไซค์ (Model)</label>
                            <input type="text" name="model_name" id="m_model" class="form-control" required>
                        </div>
                    </div>

                    <!-- สถานะ -->
                    <div class="mb-3">
                        <label class="modal-label-custom">สถานะ</label>
                        <input type="text" name="status" id="m_status" class="form-control" required>
                    </div>

                    <!-- สีรถ -->
                    <div class="mb-2">
                        <label class="modal-label-custom">สีรถ (คั่นด้วยเครื่องหมายจุลภาค ,)</label>
                        <input type="text" name="color_name" id="m_color" class="form-control" placeholder="เช่น Gemma White, Super Black, Latte Green">
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer border-0 pt-3 pb-2 px-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-medium text-dark" data-bs-dismiss="modal" style="background-color: #f1f5f9; border: none; padding: 0.55rem 1.25rem;">
                        ยกเลิก
                    </button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-medium d-inline-flex align-items-center gap-2" style="padding: 0.55rem 1.25rem;">
                        <i class="bi bi-box-arrow-down fs-6"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('editCarModal');
    const editCarModal = modalElement ? new bootstrap.Modal(modalElement) : null;

    // Event Listener สำหรับปุ่มแก้ไขในตาราง
    document.querySelectorAll('.btn-edit-car').forEach(btn => {
        btn.addEventListener('click', function() {
            const id     = this.dataset.id;
            const brand  = this.dataset.brand;
            const model  = this.dataset.model;
            const color  = this.dataset.color;
            const status = this.dataset.status;

            document.getElementById('m_car_id').value     = id;
            document.getElementById('m_display_id').value = `#${id}`;
            document.getElementById('m_brand').value      = brand;
            document.getElementById('m_model').value      = model;
            document.getElementById('m_color').value      = color;
            document.getElementById('m_status').value     = status;

            if (editCarModal) {
                editCarModal.show();
            }
        });
    });
});
</script>

<?php 
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php'; 
}
?>