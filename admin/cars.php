<?php
/**
 * ==============================================================================
 * File: admin/cars.php - จัดการคลังรถมอเตอร์ไซค์และโควตา (Master Data & Quotas)
 * Description: ระบบจัดการโควตารถมอเตอร์ไซค์ พร้อมระบบจำแนกสีประจำภูมิภาค (Region Badge)
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

if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

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

/**
 * สร้าง Badge สำหรับแสดงสีประจำภูมิภาค (Region Color Coding)
 */
if (!function_exists('getRegionBadge')) {
    function getRegionBadge($region) {
        $r = mb_strtolower(trim($region));
        if (empty($r) || $r === '-') {
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle font-normal"><i class="bi bi-geo-alt me-1"></i>ไม่ระบุ</span>';
        }

        if (str_contains($r, 'กลาง')) {
            return '<span class="region-badge badge-central"><i class="bi bi-geo-alt-fill me-1"></i>' . htmlspecialchars($region) . '</span>';
        } elseif (str_contains($r, 'เหนือ')) {
            return '<span class="region-badge badge-north"><i class="bi bi-geo-alt-fill me-1"></i>' . htmlspecialchars($region) . '</span>';
        } elseif (str_contains($r, 'อีสาน') || str_contains($r, 'ตะวันออกเฉียงเหนือ')) {
            return '<span class="region-badge badge-esan"><i class="bi bi-geo-alt-fill me-1"></i>' . htmlspecialchars($region) . '</span>';
        } elseif (str_contains($r, 'ใต้')) {
            return '<span class="region-badge badge-south"><i class="bi bi-geo-alt-fill me-1"></i>' . htmlspecialchars($region) . '</span>';
        } elseif (str_contains($r, 'ตะวันตก')) {
            return '<span class="region-badge badge-west"><i class="bi bi-geo-alt-fill me-1"></i>' . htmlspecialchars($region) . '</span>';
        } elseif (str_contains($r, 'ตะวันออก')) {
            return '<span class="region-badge badge-east"><i class="bi bi-geo-alt-fill me-1"></i>' . htmlspecialchars($region) . '</span>';
        }

        return '<span class="region-badge badge-default"><i class="bi bi-geo-alt-fill me-1"></i>' . htmlspecialchars($region) . '</span>';
    }
}

// ==========================================
// 3. FETCH DATA & PREPARE CONSTANTS
// ==========================================

// 3.1 ดึงรายชื่อพนักงานขาย พร้อมเรียงลำดับภาษาไทย
try {
    $stmt_users = $conn->prepare("SELECT name FROM users WHERE role = 'sales'");
    $stmt_users->execute();
    $sales_list = $stmt_users->fetchAll(PDO::FETCH_COLUMN);

    // เรียงลำดับชื่อพนักงานขายตามพยัญชนะไทย
    if (class_exists('Collator')) {
        $collator = new Collator('th_TH');
        $collator->sort($sales_list);
    } else {
        usort($sales_list, function($a, $b) {
            return strcmp($a, $b);
        });
    }
} catch (PDOException $e) { 
    $sales_list = []; 
}

// 3.2 ดึงข้อมูลรถมอเตอร์ไซค์ทั้งหมด
try {
    $sql_all_cars = "SELECT c.id, c.brand, c.model_name, GROUP_CONCAT(cc.color_name SEPARATOR ', ') AS color_name 
                     FROM cars c 
                     LEFT JOIN car_colors cc ON c.id = cc.car_id 
                     GROUP BY c.id, c.brand, c.model_name";
    $stmt_cars = $conn->query($sql_all_cars);
    $all_cars_data = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_cars_data = [];
}

$brands = array_unique(array_column($all_cars_data, 'brand'));
sort($brands);

// 3.3 ตั้งค่ารายการคงที่
$months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$current_month = $months[date('n') - 1];
$statuses = ['จอง', 'ไม่จอง'];

// 3.4 ดึงข้อมูลโควตารถมอเตอร์ไซค์
try {
    $sql = "SELECT q.id, c.brand, c.model_name, q.color_name, 
                   q.region, q.sales_name, q.month, q.max_limit, q.status as quota_status 
            FROM car_quotas q 
            INNER JOIN cars c ON q.car_id = c.id 
            ORDER BY q.id ASC"; 
    $stmt = $conn->prepare($sql); 
    $stmt->execute();
    $quotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quotas = [];
}

// 3.5 คำนวณสถิติหลัก
$total_quotas_count = count($quotas);
$total_sales_count  = count(array_unique(array_filter(array_column($quotas, 'sales_name'))));
$total_units_quota  = array_sum(array_column($quotas, 'max_limit'));
$reserved_count     = 0;

foreach ($quotas as $q) {
    if (($q['quota_status'] ?? '') === 'จอง') {
        $reserved_count++;
    }
}

// ==========================================
// 4. INCLUDE LAYOUT HEADERS
// ==========================================
if (file_exists(__DIR__ . '/../includes/header.php')) {
    include_once __DIR__ . '/../includes/header.php';
}
if (file_exists(__DIR__ . '/../includes/navbar.php')) {
    include_once __DIR__ . '/../includes/navbar.php';
}
?>

<!-- ==================================================================
     STYLES & FONTS
     ================================================================== -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --font-kanit: 'Kanit', sans-serif;
        --bg-light: #f8fafc;
        --card-border-radius: 16px;
        --primary-blue: #2563eb;
        --primary-hover: #1d4ed8;
    }

    body, html, button, input, select, textarea, table, div, span, h1, h2, h3, h4, h5, h6 {
        font-family: var(--font-kanit) !important;
    }

    body {
        background-color: var(--bg-light);
        color: #0f172a;
        overflow-x: hidden;
    }

    .page-header-icon-svg {
        width: 32px !important;
        height: 32px !important;
        min-width: 32px !important;
        min-height: 32px !important;
        display: inline-block !important;
        flex-shrink: 0 !important;
        fill: var(--primary-blue);
    }

    .stat-card-icon-svg {
        width: 28px !important;
        height: 28px !important;
        min-width: 28px !important;
        min-height: 28px !important;
        display: inline-block !important;
        flex-shrink: 0 !important;
        fill: var(--primary-blue);
    }

    #mainContentArea,
    .main-content-layout,
    body:not(.sidebar-collapsed):not(.collapsed) #mainContentArea,
    body:not(.sidebar-collapsed):not(.collapsed) .main-content-layout {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        min-height: 100vh;
        padding: 24px 28px !important;
        box-sizing: border-box !important;
        transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                    width 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                    filter 0.25s ease-in-out !important;
        position: relative;
        z-index: 1;
    }

    body.sidebar-collapsed #mainContentArea,
    body.sidebar-collapsed .main-content-layout,
    body.collapsed #mainContentArea,
    body.collapsed .main-content-layout {
        margin-left: 80px !important;
        width: calc(100% - 80px) !important;
    }

    @media (max-width: 991.98px) {
        body #mainContentArea,
        body .main-content-layout,
        body.sidebar-collapsed #mainContentArea,
        body.sidebar-collapsed .main-content-layout {
            margin-left: 80px !important;        
            width: calc(100% - 80px) !important; 
            padding: 16px !important;
        }
    }

    .fade-in-up {
        animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

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

    .region-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.28rem 0.65rem;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 500;
        line-height: 1;
        white-space: nowrap;
    }

    .badge-central { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .badge-north   { background-color: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
    .badge-esan    { background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
    .badge-south   { background-color: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; }
    .badge-west    { background-color: #fce7f3; color: #be185d; border: 1px solid #fbcfe8; }
    .badge-east    { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-default { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .form-control, 
    .form-select,
    .form-control:disabled, 
    .form-select:disabled,
    .form-control[readonly], 
    .form-select[readonly] {
        border-radius: 10px;
        border: 1px solid #cbd5e1 !important;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        background-color: #ffffff !important;
        color: #0f172a !important;
        opacity: 1 !important;
        -webkit-text-fill-color: #0f172a !important;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-blue) !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
    }

    .form-select option {
        color: #0f172a !important;
    }

    .form-control::placeholder {
        color: #64748b !important;
        opacity: 1;
    }

    .form-label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #1e293b !important;
        margin-bottom: 0.35rem;
    }

    .table-custom {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .table-custom thead th {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
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
        line-height: 1.2;
    }

    .badge-status-warning { background-color: #fffbebf1; color: #d97706; border: 1px solid #fef3c7; }
    .badge-status-available { background-color: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }

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

    .modal-backdrop {
        background-color: rgba(15, 23, 42, 0.45) !important;
    }

    .modal-backdrop.show {
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
    }

    body.modal-open .dashboard-container,
    body.modal-open #mainContentArea,
    body.modal-open .main-content-layout {
        filter: blur(5px);
        -webkit-filter: blur(5px);
        pointer-events: none;
    }

    .modal-content {
        border-radius: 20px !important;
        border: 1px solid #e2e8f0 !important;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    }

    .modal-section-box {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
    }

    .modal-section-title {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }
</style>

<!-- ==================================================================
     MAIN HTML LAYOUT
     ================================================================== -->
<div class="position-relative dashboard-container">
    
    <?php 
    if (file_exists(__DIR__ . '/../includes/sidebar.php')) {
        include_once __DIR__ . '/../includes/sidebar.php'; 
    }
    ?>

    <div class="main-content-layout fade-in-up" id="mainContentArea">
        
        <!-- Notification Alerts -->
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

        <!-- Page Header -->
        <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <svg class="page-header-icon-svg" viewBox="0 0 24 24">
                    <rect x="1.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <rect x="15.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <path fill-rule="evenodd" d="M12 2 C13.93 2 15.5 3.57 15.5 5.5 C15.5 7.43 13.93 9 12 9 C10.07 9 8.5 7.43 8.5 5.5 C8.5 3.57 10.07 2 12 2 Z M12 3.8 C11.06 3.8 10.3 4.56 10.3 5.5 C10.3 6.44 11.06 7.2 12 7.2 C12.94 7.2 13.7 6.44 13.7 5.5 C13.7 4.56 12.94 3.8 12 3.8 Z"/>
                    <path d="M12 7.5 C8 7.5 5 10.5 5 14.2 L5 18 C5 19.1 5.9 20 7 20 L8.2 20 C8.4 20 8.5 19.8 8.5 19.6 L8.5 16.5 C8.5 14.6 10.1 13 12 13 C13.9 13 15.5 14.6 15.5 16.5 L15.5 19.6 C15.5 19.8 15.6 20 15.8 20 L17 20 C18.1 20 19 19.1 19 18 L19 14.2 C19 10.5 16 7.5 12 7.5 Z"/>
                    <rect x="9.5" y="13.5" width="5" height="8.5" rx="2.5"/>
                </svg>
                <h3 class="fw-bold text-dark mb-0">จัดการคลังรถมอเตอร์ไซค์และโควตา</h3>
            </div>
            <p class="text-muted m-0 small" style="font-size: 0.88rem; line-height: 1.3;">จัดสรรโควตารถมอเตอร์ไซค์ กำหนดจำนวนตามภูมิภาค และบริหารสิทธิ์การสั่งซื้อของพนักงานขาย</p>
        </div>

        <!-- Section 1: Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">รายการโควตาทั้งหมด</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_quotas_count); ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                        </div>
                        <div class="icon-shape bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-journal-bookmark-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-pie-chart-fill text-primary me-1"></i> รายการโควตาประจำเดือน
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">เซลล์ที่ได้รับโควตา</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_sales_count); ?> <span class="fs-6 text-muted fw-normal">คน</span></h3>
                        </div>
                        <div class="icon-shape bg-info bg-opacity-10 text-info">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-person-check-fill text-info me-1"></i> กระจายโควตารายบุคคล
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">จำนวนจัดสรรรวม</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_units_quota); ?> <span class="fs-6 text-muted fw-normal">คัน</span></h3>
                        </div>
                        <div class="icon-shape bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-box-seam-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-layers-fill text-warning me-1"></i> โควตามอเตอร์ไซค์รวม
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">สถานะสิทธิ์การจอง</span>
                            <h3 class="fw-bold text-success my-1"><?= number_format($reserved_count); ?> <span class="fs-6 text-muted fw-normal">จองแล้ว</span></h3>
                        </div>
                        <div class="icon-shape bg-success bg-opacity-10 text-success">
                            <i class="bi bi-bookmark-check-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-check-circle-fill text-success me-1"></i> ติดสถานะจองสิทธิ์
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Split View (40% Form / 60% Table) -->
        <div class="row g-4">
            
            <!-- Form Card (40%) -->
            <div class="col-xl-5 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-plus-circle-fill text-primary fs-5"></i>
                        <h6 class="fw-bold m-0 text-dark">เพิ่มข้อมูลรถและกำหนดโควตา</h6>
                    </div>
                    
                    <form action="../actions/add_car.php" method="POST" id="add_quota_form">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">ยี่ห้อ (Brand) <span class="text-danger">*</span></label>
                                <select name="brand" id="brand_select" class="form-select" required>
                                    <option value="" selected disabled>เลือกยี่ห้อรถมอเตอร์ไซค์ก่อน</option>
                                    <?php foreach($brands as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">รุ่นรถมอเตอร์ไซค์ (Model)</label>
                                <select name="model_name" id="model_select" class="form-select" required disabled>
                                    <option value="" selected disabled>กรุณาเลือกยี่ห้อก่อน</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">สีรถ (Color)</label>
                                <select name="color_name" id="color_select" class="form-select" required disabled>
                                    <option value="" selected disabled>กรุณาเลือกรุ่นรถก่อน</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">พนักงานขาย (Sales) <span class="text-danger">*</span></label>
                                <select name="sales_name" id="sales_name_input" class="form-select" required disabled>
                                    <option value="" disabled selected>เลือกพนักงานขาย</option>
                                    <?php foreach($sales_list as $name): ?>
                                        <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">ภูมิภาค (Region)</label>
                                <input type="text" name="region" id="region_input" class="form-control" readonly placeholder="ระบบระบุอัตโนมัติ">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">โควตา (จำนวนคัน)</label>
                                <input type="number" name="max_limit" id="max_limit_input" class="form-control" value="1" min="1" required disabled>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">เดือนประจำโควตา</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($current_month) ?>" readonly>
                                <input type="hidden" name="month" value="<?= htmlspecialchars($current_month) ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">สถานะโควตา</label>
                                <select name="status" id="status_select" class="form-select" required disabled>
                                    <option value="" disabled selected>กรุณาเลือกสถานะ</option>
                                    <?php foreach($statuses as $s): ?>
                                        <option value="<?= $s ?>"><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 d-flex justify-content-end mt-4">
                                <button type="submit" id="submit_btn" class="btn btn-primary px-4 py-2 rounded-3 fw-medium d-inline-flex align-items-center gap-2 w-100 justify-content-center" disabled style="background-color: #2563eb;">
                                    <i class="bi bi-save"></i> บันทึกข้อมูลโควตา
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card (60%) -->
            <div class="col-xl-7 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-list-stars text-primary fs-5"></i>
                            <h6 class="fw-bold m-0 text-dark">รายการโควตารถมอเตอร์ไซค์</h6>
                        </div>
                        <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill fw-medium">รวม <?= count($quotas) ?> รายการ</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 5%;">#</th>
                                    <th style="width: 30%;">ข้อมูลรถมอเตอร์ไซค์</th>
                                    <th style="width: 30%;">เซลล์ & ภาค</th>
                                    <th class="text-center" style="width: 12%;">เดือน/โควตา</th>
                                    <th class="text-center" style="width: 13%;">สถานะ</th>
                                    <th class="text-center" style="width: 10%;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($quotas)): $index = 1; foreach ($quotas as $q): ?>
                                <tr>
                                    <td class="text-center text-muted font-monospace"><?= $index++ ?></td>
                                    <td>
                                        <div class="fw-bold text-dark text-nowrap">
                                            <?= htmlspecialchars($q['brand'] ?? '-') ?> <?= htmlspecialchars($q['model_name'] ?? '') ?>
                                        </div>
                                        <div class="small d-flex align-items-center gap-1 mt-1" style="<?= getColorStyle($q['color_name'] ?? '') ?> font-weight: 500;">
                                            <i class="bi bi-palette-fill"></i> สี: <?= htmlspecialchars($q['color_name'] ?? 'ไม่มีข้อมูล') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark text-nowrap mb-1">
                                            <i class="bi bi-person-fill text-secondary me-1"></i><?= htmlspecialchars($q['sales_name'] ?? '-') ?>
                                        </div>
                                        <div>
                                            <?= getRegionBadge($q['region'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="small text-muted mb-1"><?= htmlspecialchars($q['month'] ?? '-') ?></div>
                                        <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1 rounded-pill" style="font-size: 0.8rem;">
                                            <?= htmlspecialchars($q['max_limit'] ?? '0') ?> คัน
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $status = $q['quota_status'] ?? '';
                                            if ($status === 'จอง') {
                                                echo '<span class="badge-status badge-status-warning"><i class="bi bi-clock-history"></i> จอง</span>';
                                            } elseif ($status === 'ไม่จอง') {
                                                echo '<span class="badge-status badge-status-available"><i class="bi bi-check-circle-fill"></i> ไม่จอง</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">'.htmlspecialchars($status).'</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button type="button" class="btn btn-action btn-outline-primary" 
                                                title="แก้ไข"
                                                onclick="openEditModal(
                                                    '<?= $q['id'] ?>', 
                                                    '<?= htmlspecialchars(addslashes($q['brand'] ?? ''), ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars(addslashes($q['model_name'] ?? ''), ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars(addslashes($q['color_name'] ?? ''), ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars(addslashes($q['sales_name'] ?? ''), ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars(addslashes($q['region'] ?? ''), ENT_QUOTES) ?>', 
                                                    '<?= $q['max_limit'] ?>', 
                                                    '<?= htmlspecialchars(addslashes($q['month'] ?? ''), ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars(addslashes($q['quota_status'] ?? ''), ENT_QUOTES) ?>'
                                                )">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <a href="../actions/delete_car.php?id=<?= $q['id'] ?>" 
                                               class="btn btn-action btn-outline-danger" 
                                               title="ลบ"
                                               onclick="return confirm('คุณต้องการลบรายการโควตานี้ใช่หรือไม่?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                            ยังไม่มีข้อมูลโควตารถมอเตอร์ไซค์ที่บันทึกไว้
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
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 640px;">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-pencil-square fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">แก้ไขข้อมูลโควตารถมอเตอร์ไซค์</h5>
                        <p class="text-muted small mb-0" style="font-size: 0.8rem;">ปรับปรุงรายละเอียดพนักงานขาย จำนวนโควตา และสถานะการจอง</p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto align-self-start" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="../actions/update_car.php" method="POST">
                <input type="hidden" name="id" id="m_id">
                
                <div class="modal-body p-4">
                    
                    <!-- Motorbike Info (Read-only) -->
                    <div class="modal-section-box mb-3">
                        <div class="modal-section-title mb-2 d-flex align-items-center gap-1">
                            <i class="bi bi-shield-lock-fill text-secondary"></i> ข้อมูลรถมอเตอร์ไซค์
                        </div>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label text-muted small fw-medium mb-1">ยี่ห้อ</label>
                                <input type="text" class="form-control form-control-sm" id="m_brand" readonly>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label text-muted small fw-medium mb-1">รุ่นรถมอเตอร์ไซค์</label>
                                <input type="text" class="form-control form-control-sm" id="m_model" readonly>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label text-muted small fw-medium mb-1">สีรถ</label>
                                <input type="text" class="form-control form-control-sm" id="m_color" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Sales & Region Selection -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-7">
                            <label class="form-label text-dark small fw-semibold mb-1">ชื่อพนักงานขาย <span class="text-danger">*</span></label>
                            <select name="sales_name" class="form-select" id="m_sales" required>
                                <option value="" disabled>เลือกพนักงานขาย</option>
                                <?php foreach($sales_list as $name): ?>
                                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label text-muted small fw-semibold mb-1">ภูมิภาค</label>
                            <input type="text" name="region" class="form-control" id="m_region" readonly placeholder="ระบบระบุอัตโนมัติ">
                        </div>
                    </div>

                    <!-- Limit, Month & Status -->
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label text-dark small fw-semibold mb-1">จำนวนโควตา (คัน) <span class="text-danger">*</span></label>
                            <input type="number" name="max_limit" class="form-control fw-bold text-primary" id="m_limit" min="1" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label text-muted small fw-semibold mb-1">เดือน</label>
                            <input type="text" class="form-control" id="m_month_display" readonly>
                            <input type="hidden" name="month" id="m_month">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label text-dark small fw-semibold mb-1">สถานะ <span class="text-danger">*</span></label>
                            <select name="status" class="form-select fw-medium" id="m_status" required>
                                <?php foreach($statuses as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3 px-4 fw-medium text-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 fw-medium d-inline-flex align-items-center gap-2 shadow-sm" style="background-color: #2563eb;">
                        <i class="bi bi-box-arrow-down"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================================================================
     JAVASCRIPT LOGIC
     ================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const carsData = <?= json_encode($all_cars_data, JSON_UNESCAPED_UNICODE); ?>;

    const brandSel    = document.getElementById('brand_select');
    const modelSel    = document.getElementById('model_select');
    const colorSel    = document.getElementById('color_select');
    const salesSel    = document.getElementById('sales_name_input');
    const limitInput  = document.getElementById('max_limit_input');
    const statusSel   = document.getElementById('status_select');
    const submitBtn   = document.getElementById('submit_btn');
    const regionInput = document.getElementById('region_input');

    const mSales  = document.getElementById('m_sales');
    const mRegion = document.getElementById('m_region');

    function resetAndDisableForm() {
        modelSel.disabled = true;
        modelSel.innerHTML = '<option value="" selected disabled>กรุณาเลือกยี่ห้อก่อน</option>';

        colorSel.disabled = true;
        colorSel.innerHTML = '<option value="" selected disabled>กรุณาเลือกรุ่นรถก่อน</option>';

        salesSel.disabled = true;
        salesSel.value = '';

        limitInput.disabled = true;
        statusSel.disabled = true;
        statusSel.value = '';

        submitBtn.disabled = true;
        regionInput.value = '';
    }

    if (brandSel) {
        brandSel.addEventListener('change', () => {
            const selectedBrand = brandSel.value;

            if (!selectedBrand) {
                resetAndDisableForm();
                return;
            }

            modelSel.disabled = false;
            salesSel.disabled = false;
            limitInput.disabled = false;
            statusSel.disabled = false;
            submitBtn.disabled = false;

            modelSel.innerHTML = '<option value="" selected disabled>เลือกรุ่นรถมอเตอร์ไซค์</option>';
            colorSel.innerHTML = '<option value="" selected disabled>กรุณาเลือกรุ่นรถก่อน</option>';
            colorSel.disabled = true;

            const filteredModels = carsData.filter(car => car.brand === selectedBrand);
            filteredModels.forEach(car => {
                modelSel.innerHTML += `<option value="${car.model_name}">${car.model_name}</option>`;
            });
        });
    }

    if (modelSel) {
        modelSel.addEventListener('change', () => {
            const selectedBrand = brandSel.value;
            const selectedModel = modelSel.value;

            colorSel.innerHTML = '<option value="" selected disabled>กรุณาเลือกสีรถ</option>';

            const foundCar = carsData.find(car => car.brand === selectedBrand && car.model_name === selectedModel);
            if (foundCar && foundCar.color_name) {
                colorSel.disabled = false;
                const colors = foundCar.color_name.split(',');
                colors.forEach(color => {
                    const trimmedColor = color.trim();
                    if (trimmedColor) {
                        colorSel.innerHTML += `<option value="${trimmedColor}">${trimmedColor}</option>`;
                    }
                });
            } else {
                colorSel.disabled = true;
                colorSel.innerHTML = '<option value="" selected disabled>ไม่พบข้อมูลตัวเลือกสี</option>';
            }
        });
    }

    if (salesSel) {
        salesSel.addEventListener('change', function() {
            if (!this.value) return;
            fetch('../actions/get_car_data.php?action=getRegion&name=' + encodeURIComponent(this.value))
                .then(res => res.json())
                .then(data => { 
                    if (data && data.region) {
                        regionInput.value = data.region; 
                    }
                })
                .catch(err => console.error('Error fetching region:', err));
        });
    }

    if (mSales) {
        mSales.addEventListener('change', function() {
            if (!this.value) return;
            fetch('../actions/get_car_data.php?action=getRegion&name=' + encodeURIComponent(this.value))
                .then(res => res.json())
                .then(data => { 
                    if (data && data.region) {
                        mRegion.value = data.region; 
                    } else {
                        mRegion.value = '';
                    }
                })
                .catch(err => console.error('Error fetching region for modal:', err));
        });
    }
});

/**
 * ฟังก์ชันเรียงลำดับ Option ของ Select Element ตามอักษรภาษาไทย
 */
function sortSelectOptions(selectElement) {
    if (!selectElement || selectElement.options.length <= 1) return;

    const firstOption = selectElement.options[0];
    const options = Array.from(selectElement.options).slice(1);

    options.sort((a, b) => a.text.localeCompare(b.text, 'th'));

    selectElement.innerHTML = '';
    selectElement.appendChild(firstOption);
    options.forEach(opt => selectElement.appendChild(opt));
}

/**
 * เปิด Modal แก้ไขข้อมูล และเลือกชื่อพนักงานขายให้อัตโนมัติ พร้อมจัดเรียงลำดับให้ถูกต้อง
 */
function openEditModal(id, brand, model, color, sales, region, limit, month, status) {
    document.getElementById('m_id').value            = id;
    document.getElementById('m_brand').value         = brand;
    document.getElementById('m_model').value         = model;
    document.getElementById('m_color').value         = color;
    document.getElementById('m_region').value        = region;
    document.getElementById('m_limit').value         = limit;
    document.getElementById('m_month_display').value = month;
    document.getElementById('m_month').value         = month;
    document.getElementById('m_status').value        = status;

    const salesSelect = document.getElementById('m_sales');
    const targetSales = sales ? sales.trim() : '';

    if (salesSelect) {
        // ลบ Option ชั่วคราวที่เคยเพิ่มไว้ออกก่อน
        for (let i = salesSelect.options.length - 1; i >= 0; i--) {
            if (salesSelect.options[i].dataset.isTemp === 'true') {
                salesSelect.remove(i);
            }
        }

        let isMatched = false;
        for (let i = 0; i < salesSelect.options.length; i++) {
            if (salesSelect.options[i].value.trim() === targetSales) {
                isMatched = true;
                break;
            }
        }

        // หากไม่พบชื่อพนักงานในตัวเลือกเดิม ให้สร้าง Option ชั่วคราวขึ้นมา
        if (!isMatched && targetSales !== '') {
            const newOption = new Option(targetSales, targetSales);
            newOption.dataset.isTemp = 'true';
            salesSelect.add(newOption);
        }

        // จัดเรียง Option ทั้งหมดตามลำดับภาษาไทย
        sortSelectOptions(salesSelect);

        // เลือกพนักงานขายที่ตรงกับข้อมูล
        for (let i = 0; i < salesSelect.options.length; i++) {
            if (salesSelect.options[i].value.trim() === targetSales) {
                salesSelect.selectedIndex = i;
                break;
            }
        }
    }
    
    const modalElement = document.getElementById('editModal');
    if (modalElement) {
        const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
        modalInstance.show();
    }
}
</script>

<?php 
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php'; 
}
?>