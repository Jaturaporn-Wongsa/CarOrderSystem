<?php
/**
 * ==============================================================================
 * File: admin/region_details.php
 * Description: รายงานสรุปข้อมูลพนักงานขายและรายการสั่งจองประจำภาค (Symmetrical Margin Fix)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once '../config/auth.php';
checkAdmin();
require_once '../config/database.php';
require_once '../config/config.php';

// ------------------------------------------------------------------------------
// 1. จัดการและตรวจสอบตัวแปรภาค
// ------------------------------------------------------------------------------
$raw_region = trim($_GET['region'] ?? '');
if (empty($raw_region)) {
    echo "
    <div class='container mt-5'>
        <div class='alert alert-danger border-0 shadow-sm p-4 rounded-3'>
            <i class='bi bi-exclamation-triangle-fill me-2'></i>
            ไม่พบข้อมูลภาคที่ระบุ กรุณาเลือกใหม่อีกครั้ง 
            <a href='dashboard.php' class='alert-link ms-2'>กลับสู่แดชบอร์ด</a>
        </div>
    </div>";
    exit;
}

$region_clean   = str_replace('ภาค', '', $raw_region);
$region_display = 'ภาค' . $region_clean;

// ------------------------------------------------------------------------------
// 2. ดึงข้อมูลและคำนวณสถิติประจำภาค
// ------------------------------------------------------------------------------
$sales_staff    = [];
$orders         = [];
$total_orders   = 0;
$total_approved = 0;
$total_pending  = 0;

try {
    // 2.1 ดึงรายชื่อพนักงานขายประจำภาค
    $stmt_sales = $conn->prepare("
        SELECT id, name 
        FROM users 
        WHERE role = 'sales' AND region LIKE :region 
        ORDER BY name ASC
    ");
    $stmt_sales->execute([':region' => '%' . $region_clean . '%']);
    $sales_staff = $stmt_sales->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 2.2 ดึงรายการสั่งจองประจำภาค
    $stmt_orders = $conn->prepare("
        SELECT o.*, c.model_name, c.brand, u.name AS sales_name
        FROM orders o
        JOIN users u ON o.sales_id = u.id
        JOIN cars c ON o.car_id = c.id
        WHERE u.region LIKE :region
        ORDER BY o.created_at ASC
    ");
    $stmt_orders->execute([':region' => '%' . $region_clean . '%']);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 2.3 คำนวณยอดสถิติรวม
    $total_orders = count($orders);
    foreach ($orders as $o) {
        $st  = trim($o['status'] ?? '');
        $qty = intval($o['approved_quantity'] ?? ($o['approved'] ?? ($o['quantity'] ?? 0)));
        
        if (in_array($st, ['Approved', 'อนุมัติ', 'อนุมัติแล้ว'])) {
            $total_approved += $qty;
        } elseif (!in_array($st, ['Rejected', 'ปฏิเสธ'])) {
            $total_pending++;
        }
    }

} catch (PDOException $e) {
    die("
    <div class='container mt-5'>
        <div class='alert alert-danger border-0 shadow-sm p-4 rounded-3'>
            เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . htmlspecialchars($e->getMessage()) . "
        </div>
    </div>");
}

// นำเข้า Header และ Sidebar
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- ============================================================================ -->
<!-- 3. STYLESHEET                                                                -->
<!-- ============================================================================ -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');

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

    /* -------------------------------------------------------------
       PERFECT MATCH WITH DASHBOARD (แก้ปัญหาระยะขอบขวาแคบ 100%)
       ------------------------------------------------------------- */
    #mainContentArea,
    .main-content,
    body:not(.sidebar-collapsed):not(.collapsed) #mainContentArea,
    body:not(.sidebar-collapsed):not(.collapsed) .main-content {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        min-height: 100vh;
        /* กำหนด Padding ซ้าย-ขวา ให้เท่ากับหน้าแดชบอร์ดแบบเป๊ะๆ */
        padding: 32px 32px !important;
        box-sizing: border-box !important;
        transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                    width 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative;
        z-index: 1;
        overflow-x: hidden;
    }

    body.sidebar-collapsed #mainContentArea,
    body.sidebar-collapsed .main-content,
    body.collapsed #mainContentArea,
    body.collapsed .main-content {
        margin-left: 80px !important;
        width: calc(100% - 80px) !important;
    }

    /* ดักไม่ให้ Bootstrap Row กินพื้นที่ขอบขวาออกไป */
    #mainContentArea .row {
        margin-left: -12px !important;
        margin-right: -12px !important;
    }

    #mainContentArea .row > [class*="col-"] {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    @media (max-width: 991.98px) {
        body #mainContentArea,
        body .main-content,
        body.sidebar-collapsed #mainContentArea,
        body.sidebar-collapsed .main-content {
            margin-left: 80px !important;        
            width: calc(100% - 80px) !important; 
            padding: 20px !important;
        }
    }

    /* -------------------------------------------------------------
       DASHBOARD CARD STYLING
       ------------------------------------------------------------- */
    .card-dash {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: var(--card-border-radius);
        padding: 1.25rem 1.25rem 0.9rem 1.25rem;
        height: 100%;
        min-height: 155px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card-dash:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .card-dash .title-text {
        font-size: 0.92rem;
        font-weight: 600;
        color: #475569;
        line-height: 1.3;
    }

    .card-dash .sub-title {
        font-size: 0.85rem;
        font-weight: 700;
        display: block;
        margin-top: 2px;
    }

    .card-dash .big-num {
        font-size: 1.85rem;
        font-weight: 800;
        line-height: 1;
    }

    .card-dash .unit-text {
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
        margin-left: 0.35rem;
    }

    .card-dash .icon-box {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* Pastel Colors */
    .bg-light-blue { background-color: #eef2ff; color: #3b82f6; }
    .bg-light-green { background-color: #e6f4ea; color: #16a34a; }
    .bg-light-yellow { background-color: #fef7e0; color: #d97706; }
    .bg-light-cyan { background-color: #e0f7fa; color: #0284c7; }

    .card-dash .card-divider {
        border-top: 1px solid #f1f5f9;
        margin-top: 0.85rem;
        padding-top: 0.6rem;
        font-size: 0.82rem;
        color: #64748b;
    }

    .dashboard-card {
        border-radius: var(--card-border-radius);
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    .table-custom {
        width: 100% !important;
        margin-bottom: 0;
        border-collapse: collapse;
    }

    .table-custom thead th {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 10px 12px;
        white-space: nowrap;
    }

    .table-custom tbody td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .avatar-circle {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: #e0e7ff;
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .fs-12 { font-size: 0.78rem; }
    .fs-13 { font-size: 0.83rem; }
    .fs-14 { font-size: 0.88rem; }
</style>

<!-- ============================================================================ -->
<!-- 4. MAIN CONTENT AREA                                                         -->
<!-- ============================================================================ -->
<main id="mainContentArea" class="main-content">
    
    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-bar-graph-fill text-primary fs-3"></i>
                <span>รายงานสรุปข้อมูล: <?= htmlspecialchars($region_display) ?></span>
            </h3>
            <p class="text-muted small mb-0">ระบบบริหารจัดการและตรวจสอบสถิติข้อมูลประจำ<?= htmlspecialchars($region_display) ?></p>
        </div>
        
        <a href="dashboard.php" class="btn btn-light border text-secondary rounded-3 px-3 py-2 fw-medium shadow-sm d-inline-flex align-items-center gap-2 fs-14">
            <i class="bi bi-arrow-left fs-5"></i> กลับหน้าแดชบอร์ด
        </a>
    </div>

    <!-- Section 1: สถิติสรุป 4 การ์ด -->
    <div class="row g-3 mb-4">
        
        <!-- การ์ดที่ 1: ออเดอร์ทั้งหมด -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card-dash">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="title-text">ออเดอร์ทั้งหมดใน</div>
                            <span class="sub-title text-primary"><?= htmlspecialchars($region_display); ?></span>
                        </div>
                        <div class="icon-box bg-light-blue">
                            <i class="bi bi-file-earmark-text-fill fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="big-num text-dark"><?= number_format($total_orders); ?></span>
                        <span class="unit-text">รายการ</span>
                    </div>
                </div>

                <div class="card-divider d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-box-seam me-1 text-primary"></i> ยอดการสั่งจองรวม:</span>
                    <span class="fw-bold text-dark"><?= number_format($total_orders); ?> รายการ</span>
                </div>
            </div>
        </div>

        <!-- การ์ดที่ 2: จำนวนอนุมัติแล้วรวม -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card-dash">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="title-text">จำนวนอนุมัติแล้ว</div>
                            <span class="sub-title text-success">รวมทุกรุ่นประจำ<?= htmlspecialchars($region_display); ?></span>
                        </div>
                        <div class="icon-box bg-light-green">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="big-num text-success"><?= number_format($total_approved); ?></span>
                        <span class="unit-text">คัน</span>
                    </div>
                </div>

                <div class="card-divider d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-check me-1 text-success"></i> สถานะ:</span>
                    <span class="fw-bold text-success">อนุมัติแล้ว</span>
                </div>
            </div>
        </div>

        <!-- การ์ดที่ 3: รอการตรวจสอบ -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card-dash">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="title-text">รอการตรวจสอบ</div>
                            <span class="sub-title" style="color: #d97706;">รอการอนุมัติประจำ<?= htmlspecialchars($region_display); ?></span>
                        </div>
                        <div class="icon-box bg-light-yellow">
                            <i class="bi bi-hourglass-split fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="big-num" style="color: #d97706;"><?= number_format($total_pending); ?></span>
                        <span class="unit-text">รายการ</span>
                    </div>
                </div>

                <div class="card-divider d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-info-circle me-1" style="color: #d97706;"></i> สถานะ:</span>
                    <span class="fw-bold" style="color: #d97706;">รอดำเนินการ</span>
                </div>
            </div>
        </div>

        <!-- การ์ดที่ 4: ทีมพนักงานขาย -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card-dash">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="title-text">ทีมพนักงานขาย</div>
                            <span class="sub-title text-info"><?= htmlspecialchars($region_display); ?></span>
                        </div>
                        <div class="icon-box bg-light-cyan">
                            <i class="bi bi-people-fill fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="big-num text-info"><?= number_format(count($sales_staff)); ?></span>
                        <span class="unit-text">คน</span>
                    </div>
                </div>

                <div class="card-divider d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-badge me-1 text-info"></i> ปฏิบัติงานประจำ:</span>
                    <span class="fw-bold text-dark"><?= htmlspecialchars($region_display); ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- Section 2: รายชื่อทีมขาย & รายการสั่งจอง -->
    <div class="row g-3 mb-4">
        
        <!-- รายชื่อทีมพนักงานขายประจำภาค -->
        <div class="col-lg-4 col-12">
            <div class="dashboard-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2 fs-14">
                        <i class="bi bi-people-fill text-primary fs-5"></i>
                        ทีมพนักงานขายประจำ<?= htmlspecialchars($region_display); ?>
                    </h6>
                    <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill fw-normal fs-12">
                        <?= count($sales_staff); ?> คน
                    </span>
                </div>

                <div>
                    <?php if (!empty($sales_staff)): ?>
                        <div class="row g-2">
                            <?php foreach ($sales_staff as $s): ?>
                                <?php $first_char = mb_substr($s['name'] ?? 'U', 0, 1, 'UTF-8'); ?>
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-3 p-2.5 rounded-3 bg-light bg-opacity-50 border border-light">
                                        <div class="avatar-circle flex-shrink-0">
                                            <?= htmlspecialchars($first_char); ?>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="fw-bold text-dark text-truncate fs-14">
                                                <?= htmlspecialchars($s['name']); ?>
                                            </div>
                                            <div class="text-muted fs-12 text-truncate">
                                                <i class="bi bi-person-badge me-1"></i>พนักงานขาย
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-person-x fs-2 opacity-25 d-block mb-2"></i>
                            ไม่พบข้อมูลพนักงานขายในภาคนี้
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- รายการสั่งจองมอเตอร์ไซค์ทั้งหมด -->
        <div class="col-lg-8 col-12">
            <div class="dashboard-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2 fs-14">
                        <i class="bi bi-journal-check text-success fs-5"></i>
                        รายการสั่งจองมอเตอร์ไซค์ทั้งหมด
                    </h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary px-2.5 py-1 rounded-pill fw-medium fs-12">
                        ทั้งหมด <?= count($orders); ?> รายการ
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>ร้านค้า / ผู้จอง</th>
                                <th>รุ่นมอเตอร์ไซค์ & สี</th>
                                <th class="text-end" style="white-space: nowrap;">ขอจอง</th>
                                <th class="text-end" style="white-space: nowrap;">อนุมัติ</th>
                                <th class="text-center" style="white-space: nowrap;">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders)): ?>
                                <?php $i = 1; foreach ($orders as $o): ?>
                                    <?php 
                                        $status_raw = trim($o['status'] ?? '');
                                        if (in_array($status_raw, ['Approved', 'อนุมัติ', 'อนุมัติแล้ว'])) {
                                            $status_badge = '<span class="badge bg-success bg-opacity-10 text-success fw-semibold px-2 py-1 rounded-pill border border-success border-opacity-25 fs-12"><i class="bi bi-check-circle-fill me-1"></i>อนุมัติแล้ว</span>';
                                        } elseif (in_array($status_raw, ['Rejected', 'ปฏิเสธ'])) {
                                            $status_badge = '<span class="badge bg-danger bg-opacity-10 text-danger fw-semibold px-2 py-1 rounded-pill border border-danger border-opacity-25 fs-12"><i class="bi bi-x-circle-fill me-1"></i>ปฏิเสธ</span>';
                                        } else {
                                            $status_badge = '<span class="badge bg-warning bg-opacity-10 text-warning text-dark fw-semibold px-2 py-1 rounded-pill border border-warning border-opacity-25 fs-12"><i class="bi bi-hourglass-split me-1"></i>รอดำเนินการ</span>';
                                        }

                                        $order_color  = $o['color'] ?? ($o['color_name'] ?? '-');
                                        $qty          = intval($o['quantity'] ?? 0);
                                        $approved_qty = intval($o['approved_quantity'] ?? ($o['approved'] ?? 0));
                                    ?>
                                    <tr>
                                        <td class="text-center fw-bold text-muted fs-12"><?= $i++; ?></td>
                                        
                                        <td>
                                            <div class="fw-bold text-dark fs-13">
                                                <i class="bi bi-shop text-muted me-1"></i><?= htmlspecialchars($o['shop_name'] ?? '-'); ?>
                                            </div>
                                            <div class="text-muted fs-12">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($o['sales_name'] ?? 'ไม่ระบุ'); ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="fw-bold text-primary fs-13">
                                                <?= htmlspecialchars($o['brand'] ?? '') ?> <?= htmlspecialchars($o['model_name'] ?? '-') ?>
                                            </div>
                                            <div class="text-muted fs-12">
                                                สี: <span class="fw-medium text-dark"><?= htmlspecialchars($order_color); ?></span>
                                            </div>
                                        </td>

                                        <td class="text-end fw-semibold text-dark fs-13">
                                            <?= number_format($qty); ?> <span class="fs-12 text-muted fw-normal">คัน</span>
                                        </td>

                                        <td class="text-end fw-bold text-success fs-13">
                                            <?= number_format($approved_qty); ?> <span class="fs-12 text-muted fw-normal">คัน</span>
                                        </td>

                                        <td class="text-center">
                                            <?= $status_badge; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted fs-13">
                                        <i class="bi bi-inbox fs-2 d-block opacity-25 mb-1"></i>
                                        ยังไม่มีรายการสั่งจองในภาคนี้
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</main>

<?php include_once '../includes/footer.php'; ?>