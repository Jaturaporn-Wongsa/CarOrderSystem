<?php
/**
 * ==============================================================================
 * File: admin/dashboard.php
 * Description: แดชบอร์ดผู้ดูแลระบบ สรุปสถิติโควตา ออเดอร์ และอนุมัติการสั่งจอง
 * Encoding: UTF-8 (Without BOM)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/auth.php';
require_once '../config/database.php';

// ตรวจสอบสิทธิ์การเข้าถึงเฉพาะผู้ดูแลระบบ 
checkAdmin();

// ==============================================================================
// 1. INITIALIZATION & HELPER FUNCTIONS
// ==============================================================================
$pending_orders     = [];
$quota_summary      = [];
$brand_summaries    = [];
$chart_labels       = json_encode([]);
$chart_data         = json_encode([]);
$chart_colors_json  = json_encode([]);
$status_json_labels = json_encode([]);
$status_json_data   = json_encode([]);

$total_quotas   = 0;
$total_approved = 0;
$total_pending  = 0;
$total_sales    = 0;

/**
 * ฟังก์ชันสำหรับลบ Emoji ออกจากข้อความ เพื่อให้แสดงเฉพาะ Bootstrap Icon
 */
function removeEmoji(string $string): string {
    $pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2300}-\x{23FF}]/u';
    return trim(preg_replace($pattern, '', $string));
}

/**
 * กำหนดชุดสีมาตรฐานแยกตามภาคสำหรับ UI และ Chart.js
 */
function getRegionColorConfig(?string $region): array {
    $region = trim($region ?? '');
    switch ($region) {
        case 'กลาง':
        case 'ภาคกลาง':
            return ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#bfdbfe', 'chart' => '#3b82f6'];
        case 'เหนือ':
        case 'ภาคเหนือ':
            return ['bg' => '#f3e8ff', 'color' => '#6d28d9', 'border' => '#ddd6fe', 'chart' => '#8b5cf6'];
        case 'อีสาน':
        case 'ภาคอีสาน':
        case 'ตะวันออกเฉียงเหนือ':
        case 'ภาคตะวันออกเฉียงเหนือ':
            return ['bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fde68a', 'chart' => '#f59e0b'];
        case 'ใต้':
        case 'ภาคใต้':
            return ['bg' => '#d1fae5', 'color' => '#047857', 'border' => '#a7f3d0', 'chart' => '#10b981'];
        case 'ตะวันออก':
        case 'ภาคตะวันออก':
            return ['bg' => '#cffafe', 'color' => '#0e7490', 'border' => '#a5f3fc', 'chart' => '#06b6d4'];
        case 'ตะวันตก':
        case 'ภาคตะวันตก':
            return ['bg' => '#fce7f3', 'color' => '#be185d', 'border' => '#fbcfe8', 'chart' => '#ec4899'];
        default:
            return ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0', 'chart' => '#64748b'];
    }
}

// ==============================================================================
// 2. DATABASE QUERIES & DATA PROCESSING
// ==============================================================================
try {
    // 2.1 ดึงรายการคำขอที่รอการตรวจสอบ
    $sql_pending = "SELECT o.*, 
                           c.model_name, 
                           c.brand, 
                           u.name AS sales_name,
                           COALESCE(NULLIF(TRIM(o.region), ''), u.region, 'ไม่ระบุ') AS display_region
                    FROM orders o 
                    LEFT JOIN cars c ON o.car_id = c.id 
                    LEFT JOIN users u ON o.sales_id = u.id 
                    WHERE LOWER(TRIM(o.status)) IN ('pending', 'รอดำเนินการ', 'รอการตรวจสอบ', '') 
                       OR o.status IS NULL
                    ORDER BY o.created_at DESC";
    $pending_orders = $conn->query($sql_pending)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $total_pending  = count($pending_orders);

    // 2.2 ตรวจสอบชื่อตารางและคอลัมน์โควตาที่ใช้งาน
    $quota_table  = "quotas";
    $quota_column = "quota_amount";
    $check_table  = $conn->query("SHOW TABLES LIKE 'car_quotas'");
    if ($check_table->rowCount() > 0) {
        $quota_table  = "car_quotas";
        $quota_column = "max_limit";
    }

    // 2.3 คำนวณยอดโควตารวมและสรุปแยกตามยี่ห้อ
    $sql_quota_details = "SELECT q.*, c.brand, c.model_name FROM {$quota_table} q LEFT JOIN cars c ON q.car_id = c.id";
    $quotas_all = $conn->query($sql_quota_details)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($quotas_all as $q) {
        $max = (int)($q[$quota_column] ?? 0);
        $total_quotas += $max;
        $brand = trim($q['brand'] ?? 'อื่นๆ');
        if (!empty($brand)) {
            if (!isset($brand_summaries[$brand])) {
                $brand_summaries[$brand] = ['total_quota' => 0, 'approved_count' => 0];
            }
            $brand_summaries[$brand]['total_quota'] += $max;
        }
    }

    // 2.4 ดึงข้อมูลโควตารวมแยกรายภาค (สำหรับ Bar Chart)
    $sql_quota = "SELECT region, MAX({$quota_column}) AS total_quota FROM {$quota_table} GROUP BY region";
    $quota_summary = $conn->query($sql_quota)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 2.5 คำนวณจำนวนรถที่อนุมัติแล้วแยกตามยี่ห้อ
    $sql_orders = "SELECT o.*, c.brand, c.model_name FROM orders o LEFT JOIN cars c ON o.car_id = c.id";
    $all_orders = $conn->query($sql_orders)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($all_orders as $o) {
        $st    = mb_strtolower(trim($o['status'] ?? ''), 'UTF-8');
        $brand = trim($o['brand'] ?? 'อื่นๆ');
        $qty   = (int)($o['quantity'] ?? 0);

        if (in_array($st, ['approved', 'อนุมัติแล้ว', 'อนุมัติ'])) {
            $total_approved += $qty;
            if (isset($brand_summaries[$brand])) {
                $brand_summaries[$brand]['approved_count'] += $qty;
            }
        }
    }

    // 2.6 นับจำนวนพนักงานขายทั้งหมด
    $stmt_sales  = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'sales'");
    $total_sales = (int)($stmt_sales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // 2.7 จัดเตรียมข้อมูลสำหรับ Bar Chart
    $chart_labels_arr = array_column($quota_summary, 'region');
    $chart_data_arr   = array_column($quota_summary, 'total_quota');
    $chart_colors_arr = [];

    foreach ($chart_labels_arr as $rg) {
        $color_cfg = getRegionColorConfig($rg);
        $chart_colors_arr[] = $color_cfg['chart'];
    }

    $chart_labels      = json_encode($chart_labels_arr, JSON_UNESCAPED_UNICODE);
    $chart_data        = json_encode($chart_data_arr);
    $chart_colors_json = json_encode($chart_colors_arr);

    // 2.8 จัดเตรียมข้อมูลสำหรับ Doughnut Chart
    $sql_status  = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
    $status_data = $conn->query($sql_status)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $status_counts_map = ['อนุมัติแล้ว' => 0, 'ปฏิเสธ' => 0, 'รอดำเนินการ' => 0];
    foreach ($status_data as $row) {
        $st    = mb_strtolower(trim($row['status'] ?? ''), 'UTF-8');
        $count = (int)$row['count'];

        if (in_array($st, ['approved', 'อนุมัติแล้ว', 'อนุมัติ'])) {
            $status_counts_map['อนุมัติแล้ว'] += $count;
        } elseif (in_array($st, ['rejected', 'ปฏิเสธ', 'ไม่อนุมัติ'])) {
            $status_counts_map['ปฏิเสธ'] += $count;
        } else {
            $status_counts_map['รอดำเนินการ'] += $count;
        }
    }

    $status_json_labels = json_encode(array_keys($status_counts_map), JSON_UNESCAPED_UNICODE);
    $status_json_data   = json_encode(array_values($status_counts_map));

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . htmlspecialchars($e->getMessage()));
}

include_once '../includes/header.php';
?>

<!-- ==============================================================================
     3. STYLESHEETS & CUSTOM CSS
     ============================================================================== -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --font-kanit: 'Kanit', sans-serif;
        --bg-light: #f8fafc;
        --card-border-radius: 16px;
    }

    body, html, button, input, select, textarea, table {
        font-family: var(--font-kanit) !important;
    }

    body {
        background-color: var(--bg-light);
        overflow-x: hidden;
    }

    /* Layout & Responsiveness */
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
                    width 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
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

    /* Animation */
    .fade-in-up {
        animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Cards & Components */
    .stat-card, .dashboard-card {
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
    }

    .custom-scroll {
        max-height: 560px;
        overflow-y: auto;
    }
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    .region-badge-custom {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.78rem;
        font-weight: 500;
        padding: 2px 10px;
        border-radius: 20px;
        border-width: 1px;
        border-style: solid;
        white-space: nowrap;
    }

    .table-minimal {
        border-collapse: separate;
        border-spacing: 0;
    }
    .table-minimal thead th {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 12px 16px;
    }
    .table-minimal tbody td {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table-minimal tbody tr:hover { 
        background-color: #f8fafc; 
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    /* Modal Backdrop Blur */
    .modal { background-color: transparent !important; }
    .modal-backdrop { background-color: rgba(15, 23, 42, 0.45) !important; }
    .modal-backdrop.show { 
        opacity: 1 !important; 
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
    }

    .modal-approve-content {
        border-radius: 20px !important;
        padding: 8px;
    }

    .modal-approve-header {
        border-bottom: none;
        padding: 1.5rem 1.5rem 0.5rem 1.5rem;
    }

    .info-summary-card {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.25rem;
    }

    .fs-11 { font-size: 0.75rem; }
    .fs-12 { font-size: 0.8rem; }
</style>

<!-- ==============================================================================
     4. MAIN LAYOUT
     ============================================================================== -->
<div class="position-relative dashboard-container">
    
    <!-- Navigation Sidebar -->
    <?php include_once '../includes/sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content-layout fade-in-up" id="mainContentArea">
        
        <!-- Flash Alert Messages (กรองอิโมจิออก ให้เหลือเฉพาะ Bootstrap Icon) -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show rounded-3 mb-3">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars(removeEmoji($_SESSION['success'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show rounded-3 mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars(removeEmoji($_SESSION['error'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Page Title Header -->
        <div class="mb-4">
            <h3 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2 text-primary fs-3"></i>
                <span>Dashboard ผู้ดูแลระบบ</span>
            </h3>
            <p class="text-muted mb-0 small">ระบบบริหารจัดการออเดอร์สั่งจองรถมอเตอร์ไซค์และตรวจสอบโควตารายภาค</p>
        </div>

        <!-- 4 Stat Summary Cards -->
        <div class="row g-3 mb-4">
            <!-- 1. โควตารวม -->
            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">โควตารวมทั้งหมด</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_quotas); ?> <span class="fs-6 text-muted fw-normal">คัน</span></h3>
                        </div>
                        <div class="icon-shape bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-pie-chart-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $b_name => $b_data): ?>
                                <div class="d-flex justify-content-between text-muted mb-1">
                                    <span><i class="bi bi-chevron-right fs-12 me-1"></i><?= htmlspecialchars($b_name) ?></span>
                                    <span class="fw-semibold text-dark"><?= number_format($b_data['total_quota']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">- ไม่มีข้อมูลยี่ห้อ</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. อนุมัติแล้ว -->
            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">ออเดอร์อนุมัติแล้ว</span>
                            <h3 class="fw-bold text-success my-1"><?= number_format($total_approved); ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                        </div>
                        <div class="icon-shape bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $b_name => $b_data): ?>
                                <div class="d-flex justify-content-between text-muted mb-1">
                                    <span><i class="bi bi-chevron-right fs-12 me-1"></i><?= htmlspecialchars($b_name) ?></span>
                                    <span class="fw-semibold text-dark"><?= number_format($b_data['approved_count']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">- ไม่มีข้อมูลยี่ห้อ</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3. รอการตรวจสอบ -->
            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">รอการตรวจสอบ</span>
                            <h3 class="fw-bold text-warning my-1"><?= number_format($total_pending); ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                        </div>
                        <div class="icon-shape bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-hourglass-split fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-info-circle me-1"></i> รายการสั่งซื้อที่รอยืนยัน
                    </div>
                </div>
            </div>

            <!-- 4. พนักงานขาย -->
            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">พนักงานขายทั้งหมด</span>
                            <h3 class="fw-bold text-info my-1"><?= number_format($total_sales); ?> <span class="fs-6 text-muted fw-normal">คน</span></h3>
                        </div>
                        <div class="icon-shape bg-info bg-opacity-10 text-info">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-person-badge me-1"></i> จำนวนผู้ใช้งานฝั่งพนักงาน
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content Grid (Charts & Pending Table) -->
        <div class="row g-4">
            
            <!-- Left Side: Interactive Charts -->
            <div class="col-xl-6 col-12">
                <div class="d-flex flex-column gap-4">
                    <!-- Bar Chart Card -->
                    <div class="dashboard-card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>สถิติโควตาจำแนกตามภาค (คัน)
                            </h6>
                            <span class="badge bg-light text-muted fw-normal"><i class="bi bi-hand-index-thumb me-1"></i>คลิกเพื่อดูรายละเอียด</span>
                        </div>
                        <div style="position: relative; height: 220px;">
                            <canvas id="quotaChart"></canvas>
                        </div>
                    </div>

                    <!-- Doughnut Chart Card -->
                    <div class="dashboard-card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-pie-chart-fill text-success me-2"></i>สัดส่วนสถานะคำขอจองทั้งหมด
                            </h6>
                        </div>
                        <div style="position: relative; height: 220px;" class="d-flex justify-content-center align-items-center">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Pending Orders Table -->
            <div class="col-xl-6 col-12">
                <div class="dashboard-card p-3 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-clock-history text-warning me-2"></i>รายการที่รอการตรวจสอบ
                        </h6>
                        <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-1 rounded-pill fw-medium">
                            <?= $total_pending ?> รายการ
                        </span>
                    </div>

                    <div class="custom-scroll flex-grow-1">
                        <div class="table-responsive">
                            <table class="table table-minimal align-middle mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th style="width: 32%;">คำขอ / ผู้สั่งจอง</th>
                                        <th style="width: 43%;">รายละเอียดมอเตอร์ไซค์ & ภาค</th>
                                        <th style="width: 25%;" class="text-end">จำนวน & จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($pending_orders)): ?>
                                        <?php foreach ($pending_orders as $order): ?>
                                            <?php 
                                                $region_name = trim($order['display_region'] ?? 'ไม่ระบุ');
                                                $style = getRegionColorConfig($region_name);
                                            ?>
                                            <tr>
                                                <!-- ผู้สั่งจอง -->
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1 rounded-2">
                                                            #<?= htmlspecialchars($order['id'] ?? '-'); ?>
                                                        </span>
                                                    </div>
                                                    <div class="small fw-medium text-dark mt-1">
                                                        <i class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($order['sales_name'] ?? 'ไม่ระบุ'); ?>
                                                    </div>
                                                </td>

                                                <!-- รายละเอียดรถ & ภาค -->
                                                <td>
                                                    <div class="fw-bold text-dark mb-1">
                                                        <?= htmlspecialchars($order['brand'] ?? '-') ?> 
                                                        <span class="fw-normal text-secondary small"><?= htmlspecialchars($order['model_name'] ?? '') ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <span class="small text-muted fs-11">
                                                            <i class="bi bi-shop me-1"></i><?= htmlspecialchars($order['shop_name'] ?? '-'); ?>
                                                        </span>
                                                        <span class="region-badge-custom" style="background-color: <?= $style['bg'] ?>; color: <?= $style['color'] ?>; border-color: <?= $style['border'] ?>;">
                                                            <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($region_name) ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <!-- จำนวน & ปุ่มจัดการ -->
                                                <td class="text-end">
                                                    <div class="d-flex align-items-center justify-content-end gap-3">
                                                        <div class="text-end">
                                                            <span class="fw-bold fs-6 text-dark"><?= (int)($order['quantity'] ?? 1); ?></span>
                                                            <span class="small text-muted">คัน</span>
                                                        </div>
                                                        
                                                        <div class="d-flex gap-1">
                                                            <!-- ปุ่มอนุมัติ -->
                                                            <button type="button" 
                                                                class="btn btn-sm btn-success btn-action shadow-sm btn-approve-action" 
                                                                title="อนุมัติ"
                                                                data-id="<?= $order['id'] ?>"
                                                                data-region="<?= htmlspecialchars($region_name, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-brand="<?= htmlspecialchars($order['brand'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-model="<?= htmlspecialchars($order['model_name'] ?? 'ไม่ระบุ', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-qty="<?= (int)($order['quantity'] ?? 1) ?>">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>

                                                            <!-- ปุ่มปฏิเสธ -->
                                                            <form action="../actions/approve_order.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
                                                                <button type="submit" name="action" value="Rejected" class="btn btn-sm btn-outline-warning btn-action" title="ปฏิเสธ" onclick="return confirm('คุณต้องการปฏิเสธรายการนี้ใช่หรือไม่?');">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </form>

                                                            <!-- ปุ่มลบ -->
                                                            <form action="../actions/delete_order.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="ลบ" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้ออกจากระบบ?');">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block opacity-25 mb-2"></i>
                                                ไม่มีรายการที่รอการตรวจสอบในขณะนี้
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
</div>

<!-- ==============================================================================
     5. MODAL DIALOGS (ยืนยันการอนุมัติ)
     ============================================================================== -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <form action="../actions/approve_order.php" method="POST" class="modal-content modal-approve-content border-0 shadow-lg">
            
            <!-- Modal Header -->
            <div class="modal-header modal-approve-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                    <h5 class="modal-title fw-bold text-dark mb-0 fs-5">ยืนยันการอนุมัติออเดอร์</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body px-4 py-3">
                <input type="hidden" name="order_id" id="approve_order_id">
                <input type="hidden" name="action" value="Approved">
                
                <!-- การ์ดสรุปรายละเอียดข้อมูลออเดอร์ -->
                <div class="info-summary-card mb-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <span class="text-muted fs-12 d-block mb-1">ภาค</span>
                            <span id="approve_region" class="fw-bold text-dark fs-6">-</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted fs-12 d-block mb-1">ยี่ห้อ</span>
                            <span id="approve_brand" class="fw-bold text-dark fs-6">-</span>
                        </div>
                        <div class="col-6 pt-2 border-top">
                            <span class="text-muted fs-12 d-block mb-1">รุ่นมอเตอร์ไซค์</span>
                            <span id="approve_model" class="fw-bold text-dark fs-6">-</span>
                        </div>
                        <div class="col-6 pt-2 border-top">
                            <span class="text-muted fs-12 d-block mb-1">จำนวนที่ขอจอง</span>
                            <span id="approve_requested_qty" class="fw-bold text-primary fs-6">-</span>
                        </div>
                    </div>
                </div>

                <!-- ช่องระบุจำนวนที่อนุมัติจริง -->
                <div class="mb-2">
                    <label class="form-label fw-bold text-dark fs-6 mb-2">จำนวนที่อนุมัติให้จริง (คัน)</label>
                    <input type="number" name="quantity" id="approve_quantity" class="form-control form-control-lg rounded-3 fs-6 px-3 py-2" min="1" required style="border-color: #cbd5e1;">
                    <div class="form-text text-muted fs-12 mt-2 d-flex align-items-center gap-1">
                        <i class="bi bi-info-circle"></i> สามารถปรับเปลี่ยนจำนวนมอเตอร์ไซค์ที่อนุมัติได้
                    </div>
                </div>
            </div>

            <!-- Modal Footer Buttons -->
            <div class="modal-footer border-0 px-4 pb-4 pt-1 justify-content-end gap-2">
                <button type="button" class="btn btn-light text-dark fw-medium rounded-3 px-4 py-2" data-bs-dismiss="modal" style="background-color: #f1f5f9; border: none;">ยกเลิก</button>
                <button type="submit" class="btn btn-success fw-medium rounded-3 px-4 py-2 d-inline-flex align-items-center gap-2" style="background-color: #2e7d32; border: none;">
                    <i class="bi bi-check-lg fs-5"></i> อนุมัติออเดอร์
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==============================================================================
     6. JAVASCRIPT & CHART.JS INITIALIZATION
     ============================================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = 'Kanit';

// 1. Modal Trigger Event Listener
document.addEventListener('click', function (e) {
    const targetBtn = e.target.closest('.btn-approve-action');
    if (targetBtn) {
        const id     = targetBtn.dataset.id;
        const region = targetBtn.dataset.region;
        const brand  = targetBtn.dataset.brand;
        const model  = targetBtn.dataset.model;
        const qty    = targetBtn.dataset.qty;

        document.getElementById('approve_order_id').value = id;
        document.getElementById('approve_region').innerText = region;
        document.getElementById('approve_brand').innerText = brand;
        document.getElementById('approve_model').innerText = model;
        document.getElementById('approve_requested_qty').innerText = qty + ' คัน';
        document.getElementById('approve_quantity').value = qty;

        const approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
        approveModal.show();
    }
});

// 2. Render Bar Chart (โควตาจำแนกตามภาค)
new Chart(document.getElementById('quotaChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= $chart_labels; ?>,
        datasets: [{ 
            label: 'โควตา (คัน)', 
            data: <?= $chart_data; ?>, 
            backgroundColor: <?= $chart_colors_json; ?>, 
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: { 
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 1200, easing: 'easeOutQuart' },
        plugins: { 
            legend: { display: false },
            tooltip: { padding: 10, cornerRadius: 8 }
        },
        scales: {
            x: { grid: { display: false } },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0, 0, 0, 0.04)' },
                ticks: { stepSize: 1, precision: 0 }
            }
        },
        onClick: (event, elements) => {
            if (elements.length > 0) {
                const index = elements[0].index;
                const regionLabels = <?= $chart_labels; ?>;
                const selectedRegion = regionLabels[index];
                window.location.href = 'region_details.php?region=' + encodeURIComponent(selectedRegion);
            }
        }
    }
});

// 3. Render Doughnut Chart (สัดส่วนสถานะคำขอ)
new Chart(document.getElementById('statusChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?= $status_json_labels; ?>,
        datasets: [{
            data: <?= $status_json_data; ?>,
            backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: { 
        responsive: true,
        maintainAspectRatio: false,
        animation: { animateRotate: true, duration: 1200 },
        plugins: { 
            legend: { 
                position: 'right',
                onClick: null,
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    padding: 15,
                    font: { family: 'Kanit', size: 13 }
                }
            } 
        },
        cutout: '68%'
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>