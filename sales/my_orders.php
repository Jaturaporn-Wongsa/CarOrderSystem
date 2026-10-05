<?php
/**
 * ==============================================================================
 * File: sales/my_orders.php - ระบบประวัติการสั่งจองรถมอเตอร์ไซค์ของฉัน
 * Description: สรุปสถิติและแสดงรายการสั่งจองสำหรับพนักงานขาย (ปรับแต่งการแสดงสีรถตามคีย์เวิร์ดสี)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

checkSales(); 

$sales_id     = $_SESSION['user_id'] ?? 0;
$sales_region = '';

$all_orders      = [];
$brand_summaries = [];

$total_orders_count    = 0;
$pending_orders_count  = 0;
$approved_orders_count = 0;
$cancel_orders_count   = 0;

// -----------------------------------------------------------------------------
// Helper Function: กำหนดสีไอคอนและตัวอักษรตามชื่อสีรถ (เหมือนภาพที่ 2)
// -----------------------------------------------------------------------------
if (!function_exists('getCarColorStyle')) {
    function getCarColorStyle($color_name) {
        $color = mb_strtolower(trim($color_name ?? ''));
        
        if (empty($color) || $color === '-') {
            return ['text' => '#64748b', 'icon' => '#94a3b8'];
        }

        if (strpos($color, 'red') !== false || strpos($color, 'แดง') !== false) {
            return ['text' => '#dc2626', 'icon' => '#dc2626'];
        }
        if (strpos($color, 'green') !== false || strpos($color, 'เขียว') !== false) {
            return ['text' => '#16a34a', 'icon' => '#16a34a'];
        }
        if (strpos($color, 'blue') !== false || strpos($color, 'น้ำเงิน') !== false || strpos($color, 'ฟ้า') !== false) {
            return ['text' => '#2563eb', 'icon' => '#2563eb'];
        }
        if (strpos($color, 'yellow') !== false || strpos($color, 'เหลือง') !== false || strpos($color, 'gold') !== false) {
            return ['text' => '#d97706', 'icon' => '#d97706'];
        }
        if (strpos($color, 'orange') !== false || strpos($color, 'ส้ม') !== false) {
            return ['text' => '#ea580c', 'icon' => '#ea580c'];
        }
        if (strpos($color, 'espresso') !== false || strpos($color, 'brown') !== false || strpos($color, 'น้ำตาล') !== false) {
            return ['text' => '#543310', 'icon' => '#543310'];
        }
        if (strpos($color, 'black') !== false || strpos($color, 'ดำ') !== false) {
            return ['text' => '#0f172a', 'icon' => '#0f172a'];
        }
        if (strpos($color, 'white') !== false || strpos($color, 'ขาว') !== false) {
            return ['text' => '#475569', 'icon' => '#64748b'];
        }
        if (strpos($color, 'grey') !== false || strpos($color, 'gray') !== false || strpos($color, 'silver') !== false || strpos($color, 'เทา') !== false || strpos($color, 'เงิน') !== false) {
            return ['text' => '#64748b', 'icon' => '#64748b'];
        }

        return ['text' => '#334155', 'icon' => '#0284c7'];
    }
}

try {
    // 1. ดึงข้อมูลเขตพื้นที่ความรับผิดชอบ
    $stmt_user = $conn->prepare("SELECT region FROM users WHERE id = ?");
    $stmt_user->execute([$sales_id]);
    $sales_region = trim($stmt_user->fetchColumn() ?: '');
    $_SESSION['user_region'] = $sales_region;

    // 2. ดึงรายการสั่งจองของพนักงาน
    $sql = "SELECT o.id, o.shop_name, o.status, o.created_at, o.quantity, 
                   o.approved_quantity, o.color_name, o.admin_note, 
                   c.model_name, c.brand 
            FROM orders o 
            LEFT JOIN cars c ON o.car_id = c.id  
            WHERE o.sales_id = ?
            ORDER BY o.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sales_id]);
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3. สรุปสถิติตามสถานะและแยกรายแบรนด์
    $total_orders_count = count($all_orders);

    foreach ($all_orders as $ord) {
        $st    = trim($ord['status'] ?? '');
        $bName = trim($ord['brand'] ?? 'อื่นๆ');
        $qty   = intval($ord['quantity'] ?? 0);

        if (!isset($brand_summaries[$bName])) {
            $brand_summaries[$bName] = [
                'total'    => 0,
                'pending'  => 0,
                'approved' => 0,
                'cancel'   => 0
            ];
        }

        $brand_summaries[$bName]['total'] += $qty;

        if ($st === 'Approved' || $st === 'อนุมัติ') {
            $approved_orders_count++;
            $brand_summaries[$bName]['approved'] += $qty;
        } elseif ($st === 'Pending' || $st === 'รอดำเนินการ' || $st === '') {
            $pending_orders_count++;
            $brand_summaries[$bName]['pending'] += $qty;
        } else {
            $cancel_orders_count++;
            $brand_summaries[$bName]['cancel'] += $qty;
        }
    }

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . htmlspecialchars($e->getMessage()));
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- External Fonts & Icons -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    /* --- CSS Variables & Base --- */
    :root {
        --primary-blue: #0284c7;
        --primary-blue-hover: #0369a1;
        --bg-blue-light: #f0f9ff;
        --border-color: #e2e8f0;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 80px;
    }

    body { 
        font-family: 'Kanit', sans-serif !important; 
        background-color: #f8fafc; 
        color: #334155;
    }

    /* --- Desktop Layout & Sidebar Margins --- */
    #mainContentArea { 
        margin-left: var(--sidebar-width) !important; 
        width: calc(100% - var(--sidebar-width)) !important; 
        padding: 24px 28px; 
        transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1), width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 100vh;
    }

    body.sidebar-collapsed #mainContentArea,
    body.collapsed-sidebar #mainContentArea,
    .sidebar-collapsed #mainContentArea,
    .sidebar.collapsed ~ #mainContentArea,
    #sidebar.collapsed ~ #mainContentArea { 
        margin-left: var(--sidebar-collapsed-width) !important; 
        width: calc(100% - var(--sidebar-collapsed-width)) !important; 
    }

    @media (max-width: 767.98px) { 
        #mainContentArea,
        body.sidebar-collapsed #mainContentArea,
        body.collapsed-sidebar #mainContentArea,
        .sidebar-collapsed #mainContentArea,
        .sidebar.collapsed ~ #mainContentArea,
        #sidebar.collapsed ~ #mainContentArea { 
            margin-left: 0 !important; 
            width: 100% !important; 
            padding: 16px !important; 
        } 
    }

    /* --- Animations --- */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in {
        animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* --- Stat & Data Cards --- */
    .stat-card { 
        border-radius: 18px; 
        border: 1px solid var(--border-color); 
        background: #ffffff; 
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 155px;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        border-color: #cbd5e1;
    }

    .data-card { 
        border-radius: 18px; 
        border: 1px solid var(--border-color); 
        background: #ffffff; 
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); 
    }

    .icon-box { 
        width: 46px; 
        height: 46px; 
        border-radius: 14px; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        flex-shrink: 0;
    }

    .icon-box-blue   { background-color: var(--bg-blue-light); color: var(--primary-blue); }
    .icon-box-green  { background-color: #e6f4ea; color: #16a34a; }
    .icon-box-yellow { background-color: #fef9c3; color: #d97706; }
    .icon-box-red    { background-color: #fee2e2; color: #dc2626; }

    .text-val-dark   { color: #0f172a; }
    .text-val-green  { color: #16a34a; }
    .text-val-yellow { color: #d97706; }
    .text-val-red    { color: #dc2626; }

    .card-divider {
        border-top: 1px solid #f1f5f9;
        margin-top: 14px;
        padding-top: 10px;
    }

    .brand-row { 
        font-size: 0.875rem; 
        color: #64748b; 
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 2px 0;
    }

    .icon-blue { color: var(--primary-blue) !important; }

    @media (max-width: 575.98px) {
        .stat-card {
            padding: 12px 14px;
            min-height: 135px;
            border-radius: 14px;
        }
        .stat-card .fs-2 {
            font-size: 1.35rem !important;
        }
        .stat-card .icon-box {
            width: 36px;
            height: 36px;
            border-radius: 10px;
        }
        .stat-card .icon-box i {
            font-size: 1.1rem !important;
        }
        .brand-row {
            font-size: 0.78rem;
        }
    }

    /* --- Scrollable Table Container --- */
    .table-container {
        border-radius: 14px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid var(--border-color);
        background: #ffffff;
    }

    .table-container::-webkit-scrollbar {
        height: 8px;
    }
    .table-container::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    .table-container::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .table-container::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .custom-table {
        margin-bottom: 0;
        width: 100%;
        min-width: 850px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .custom-table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--border-color);
        white-space: nowrap;
    }

    .custom-table tbody tr {
        transition: background-color 0.15s ease;
    }

    .custom-table tbody tr:hover {
        background-color: #f8fafc;
    }

    .custom-table tbody td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .custom-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* --- Badges & Buttons --- */
    .status-badge {
        font-size: 0.78rem;
        font-weight: 500;
        padding: 5px 12px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .status-approved {
        background-color: #e6f4ea;
        color: #16a34a;
        border: 1px solid #b7e4c7;
    }

    .status-pending {
        background-color: #fef9c3;
        color: #d97706;
        border: 1px solid #fef08a;
    }

    .status-cancel {
        background-color: #fee2e2;
        color: #dc2626;
        border: 1px solid #fca5a5;
    }

    .order-id-tag {
        font-family: monospace;
        font-size: 0.85rem;
        font-weight: 700;
        color: #0284c7;
        background-color: var(--bg-blue-light);
        padding: 3px 8px;
        border-radius: 6px;
        display: inline-block;
    }

    .btn-action-view {
        background-color: #ffffff;
        color: var(--primary-blue);
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 0.82rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-action-view:hover {
        background-color: var(--primary-blue);
        color: #ffffff;
        border-color: var(--primary-blue);
        box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
    }
</style>

<div class="position-relative">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div id="mainContentArea" class="animate-fade-in">
        
        <!-- Header Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-journal-check icon-blue fs-3"></i>
                    <span>ประวัติการสั่งจองรถของฉัน</span>
                </h4>
                <p class="text-muted mb-0 small">
                    เขตพื้นที่ความรับผิดชอบ: 
                    <span class="badge bg-info bg-opacity-10 icon-blue fw-bold rounded-pill px-3 py-1 ms-1">
                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($sales_region ?: 'ไม่ได้ระบุ') ?>
                    </span>
                </p>
            </div>
            <div class="text-end small text-secondary d-none d-md-block">
                <i class="bi bi-person-circle me-1 icon-blue"></i>ผู้ใช้งาน: 
                <strong class="text-dark"><?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['fullname'] ?? 'พนักงานขาย') ?></strong>
            </div>
        </div>

        <!-- Stat Cards Section -->
        <div class="row g-2 g-md-3 mb-4">
            
            <!-- Card 1: Total Orders -->
            <div class="col-xl-3 col-md-6 col-6">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-secondary small fw-normal mb-1">รายการสั่งจองทั้งหมด</div>
                            <div class="fs-2 fw-bold text-val-dark lh-1 mb-1">
                                <?= number_format($total_orders_count); ?> <span class="fs-6 text-muted fw-normal">รายการ</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-blue">
                            <i class="bi bi-box-seam-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.75rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark"><?= number_format($bStat['total']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="brand-row text-muted"><span>ไม่มีข้อมูล</span><span>-</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Card 2: Pending Orders -->
            <div class="col-xl-3 col-md-6 col-6">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-secondary small fw-normal mb-1">รอดำเนินการ</div>
                            <div class="fs-2 fw-bold text-val-yellow lh-1 mb-1">
                                <?= number_format($pending_orders_count); ?> <span class="fs-6 text-muted fw-normal">รายการ</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-yellow">
                            <i class="bi bi-clock-history fs-4"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.75rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark"><?= number_format($bStat['pending']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="brand-row text-muted"><span>ไม่มีข้อมูล</span><span>-</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Card 3: Approved Orders -->
            <div class="col-xl-3 col-md-6 col-6">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-secondary small fw-normal mb-1">อนุมัติแล้ว</div>
                            <div class="fs-2 fw-bold text-val-green lh-1 mb-1">
                                <?= number_format($approved_orders_count); ?> <span class="fs-6 text-muted fw-normal">รายการ</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-green">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.75rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark"><?= number_format($bStat['approved']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="brand-row text-muted"><span>ไม่มีข้อมูล</span><span>-</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Card 4: Cancelled / Rejected -->
            <div class="col-xl-3 col-md-6 col-6">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-secondary small fw-normal mb-1">ปฏิเสธ / ยกเลิก</div>
                            <div class="fs-2 fw-bold text-val-red lh-1 mb-1">
                                <?= number_format($cancel_orders_count); ?> <span class="fs-6 text-muted fw-normal">รายการ</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-red">
                            <i class="bi bi-x-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.75rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark"><?= number_format($bStat['cancel']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="brand-row text-muted"><span>ไม่มีข้อมูล</span><span>-</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Order Table Section -->
        <div class="data-card p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-list-stars icon-blue fs-5"></i>
                    <h6 class="fw-bold m-0 text-dark">รายการสั่งจองทั้งหมด</h6>
                </div>
                <span class="text-muted small">เรียงตามวันที่จองล่าสุด</span>
            </div>

            <div class="table-container">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 18%;">รหัส / วันที่จอง</th>
                            <th style="width: 38%;">รายละเอียดสั่งจอง (รุ่นรถ / สี / ร้านค้า)</th>
                            <th class="text-center" style="width: 16%;">จำนวน (สั่ง / อนุมัติ)</th>
                            <th class="text-center" style="width: 14%;">สถานะ</th>
                            <th class="text-center" style="width: 14%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_orders) > 0): ?>
                            <?php 
                            $running_no = count($all_orders);
                            foreach ($all_orders as $order): 
                                $status     = trim($order['status'] ?? 'รอดำเนินการ'); 
                                $current_no = $running_no--;
                                
                                $status_class = 'status-pending';
                                $status_text  = 'รอดำเนินการ';
                                $status_icon  = 'bi-clock-history';

                                if ($status === 'อนุมัติ' || $status === 'Approved') {
                                    $status_class = 'status-approved';
                                    $status_text  = 'อนุมัติแล้ว';
                                    $status_icon  = 'bi-check-circle-fill';
                                } elseif ($status === 'ปฏิเสธ' || $status === 'Rejected') {
                                    $status_class = 'status-cancel';
                                    $status_text  = 'ปฏิเสธ';
                                    $status_icon  = 'bi-x-circle-fill';
                                } elseif ($status === 'ยกเลิก') {
                                    $status_class = 'status-cancel';
                                    $status_text  = 'ยกเลิก';
                                    $status_icon  = 'bi-slash-circle-fill';
                                }

                                $full_car_name = trim(($order['brand'] ?? '') . ' ' . ($order['model_name'] ?? 'ไม่ระบุ'));
                                $color_style   = getCarColorStyle($order['color_name'] ?? '');
                            ?>
                                <tr>
                                    <!-- 1. รหัส & วันที่จอง -->
                                    <td>
                                        <div class="order-id-tag">#<?= str_pad((string)$current_no, 5, '0', STR_PAD_LEFT); ?></div>
                                        <div class="text-secondary small mt-1">
                                            <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y H:i', strtotime($order['created_at'])); ?> น.
                                        </div>
                                    </td>

                                    <!-- 2. รุ่นรถ / สี / ร้านค้า -->
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($full_car_name); ?></div>
                                        <div class="small d-flex align-items-center gap-2 flex-wrap mt-1">
                                            <!-- แสดงสีรถสไตล์ภาพที่ 2 -->
                                            <span class="fw-semibold" style="color: <?= $color_style['text']; ?>;">
                                                <i class="bi bi-palette-fill me-1" style="color: <?= $color_style['icon']; ?>;"></i><?= htmlspecialchars($order['color_name'] ?? '-'); ?>
                                            </span>
                                            <span class="text-black-50">•</span>
                                            <span class="text-muted">
                                                <i class="bi bi-shop text-secondary me-1"></i><?= htmlspecialchars($order['shop_name'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- 3. จำนวนสั่ง / อนุมัติ -->
                                    <td class="text-center">
                                        <div>
                                            <span class="fw-bold text-dark"><?= number_format($order['quantity'] ?? 0); ?></span> 
                                            <span class="small text-muted">คัน</span>
                                            <?php if ($status === 'อนุมัติ' || $status === 'Approved'): ?>
                                                <div class="small text-success fw-medium mt-1">
                                                    (อนุมัติ: <?= number_format($order['approved_quantity'] ?? $order['quantity']); ?>)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 4. สถานะ -->
                                    <td class="text-center">
                                        <span class="status-badge <?= $status_class ?>">
                                            <i class="bi <?= $status_icon ?>"></i> <?= $status_text ?>
                                        </span>
                                    </td>

                                    <!-- 5. จัดการ -->
                                    <td class="text-center">
                                        <a href="order_detail.php?id=<?= $order['id']; ?>&no=<?= $current_no; ?>" class="btn-action-view" title="ดูรายละเอียดข้อมูล">
                                            <i class="bi bi-search"></i>
                                            <span>รายละเอียด</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-folder2-open fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                        <span>ยังไม่มีรายการสั่งจองรถมอเตอร์ไซค์ในระบบสำหรับคุณ</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>