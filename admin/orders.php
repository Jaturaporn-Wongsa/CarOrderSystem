<?php
/**
 * ==============================================================================
 * File: admin/orders.php
 * Description: ประวัติคำสั่งซื้อมอเตอร์ไซค์ทั้งหมด (รองรับการกรองปีย้อนหลัง & สีรถตามไอคอนและโค้ดสี)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/config.php';

checkAdmin();

// ------------------------------------------------------------------------------
// 1. INITIALIZATION & VARIABLE SETUPS
// ------------------------------------------------------------------------------
$all_orders     = [];
$unique_years   = [];
$unique_models  = [];
$unique_colors  = [];
$error_message  = null;

// รับค่าตัวกรองจาก URL (GET)
$get_color  = trim($_GET['color'] ?? '');
$get_model  = trim($_GET['model'] ?? '');
$get_year   = trim($_GET['year'] ?? '');
$get_month  = trim($_GET['month'] ?? '');
$get_search = trim($_GET['search'] ?? '');

// รายชื่อ 12 เดือนมาตรฐาน
$all_months_list = [
    '01' => 'มกราคม',   '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',    '04' => 'เมษายน',
    '05' => 'พฤษภาคม',   '06' => 'มิถุนายน',   '07' => 'กรกฎาคม',   '08' => 'สิงหาคม',
    '09' => 'กันยายน',   '10' => 'ตุลาคม',    '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
];

// ฟังก์ชันกำหนด Class สีป้ายแยกตามภาค
if (!function_exists('getRegionBadgeClass')) {
    function getRegionBadgeClass($regionName) {
        $r = trim($regionName ?? '');
        if (mb_strpos($r, 'กลาง') !== false) return 'badge-region-central';
        if (mb_strpos($r, 'อีสาน') !== false || mb_strpos($r, 'ตะวันออกเฉียงเหนือ') !== false || mb_strpos($r, 'เฉียงเหนือ') !== false) return 'badge-region-ne';
        if (mb_strpos($r, 'เหนือ') !== false) return 'badge-region-north';
        if (mb_strpos($r, 'ใต้') !== false) return 'badge-region-south';
        if (mb_strpos($r, 'ตะวันออก') !== false) return 'badge-region-east';
        if (mb_strpos($r, 'ตะวันตก') !== false) return 'badge-region-west';
        return 'badge-region-default';
    }
}

// ฟังก์ชันคืนค่า Hex Color Code สำหรับไอคอนและตัวอักษรตามชื่อสีรถ
if (!function_exists('getColorHexCode')) {
    function getColorHexCode($colorName) {
        $c = mb_strtolower(trim($colorName ?? ''));
        if ($c === '' || $c === '-') return '#64748b';

        if (mb_strpos($c, 'green') !== false || mb_strpos($c, 'เขียว') !== false) return '#16a34a';
        if (mb_strpos($c, 'espresso') !== false || mb_strpos($c, 'brown') !== false || mb_strpos($c, 'น้ำตาล') !== false) return '#542a0c';
        if (mb_strpos($c, 'black') !== false || mb_strpos($c, 'ดำ') !== false) return '#1e293b';
        if (mb_strpos($c, 'white') !== false || mb_strpos($c, 'ขาว') !== false) return '#526071';
        if (mb_strpos($c, 'blue') !== false || mb_strpos($c, 'น้ำเงิน') !== false || mb_strpos($c, 'ฟ้า') !== false) return '#2563eb';
        if (mb_strpos($c, 'red') !== false || mb_strpos($c, 'แดง') !== false) return '#dc2626';
        if (mb_strpos($c, 'yellow') !== false || mb_strpos($c, 'เหลือง') !== false) return '#d97706';
        if (mb_strpos($c, 'orange') !== false || mb_strpos($c, 'ส้ม') !== false) return '#ea580c';
        if (mb_strpos($c, 'silver') !== false || mb_strpos($c, 'เงิน') !== false || mb_strpos($c, 'grey') !== false || mb_strpos($c, 'gray') !== false || mb_strpos($c, 'เทา') !== false) return '#718096';
        if (mb_strpos($c, 'gold') !== false || mb_strpos($c, 'ทอง') !== false) return '#d97706';

        return '#475569';
    }
}

// ------------------------------------------------------------------------------
// 2. DATABASE FETCH & DATA PROCESSING
// ------------------------------------------------------------------------------
try {
    $sql = "SELECT o.*, 
                   u.name as sales_name, 
                   u.region as user_region, 
                   c.model_name as car_model_name, 
                   c.brand as car_brand 
            FROM orders o 
            LEFT JOIN users u ON o.sales_id = u.id 
            LEFT JOIN cars c ON o.car_id = c.id 
            ORDER BY o.id ASC";
            
    $stmt = $conn->query($sql);
    $raw_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_orders as $order) {
        $order['shop_name'] = trim($order['shop_name'] ?? $order['shop'] ?? '-');

        // ดึงยี่ห้อรถ
        $brand_name = '-';
        if (!empty($order['car_brand'])) {
            $brand_name = trim((string)$order['car_brand']);
        } else {
            foreach ($order as $key => $val) {
                if (stripos($key, 'brand') !== false || stripos($key, 'make') !== false) {
                    $v_str = trim((string)($val ?? ''));
                    if ($v_str !== '' && $v_str !== '-' && $v_str !== '0') {
                        $brand_name = $v_str;
                        break;
                    }
                }
            }
        }

        // ดึงรุ่นรถ
        $model_name = '-';
        if (!empty($order['car_model_name'])) {
            $model_name = trim((string)$order['car_model_name']);
        } else {
            foreach ($order as $key => $val) {
                if (stripos($key, 'model') !== false || stripos($key, 'car_name') !== false) {
                    $v_str = trim((string)($val ?? ''));
                    if ($v_str !== '' && $v_str !== '-' && $v_str !== '0' && !is_numeric($v_str)) {
                        $model_name = $v_str;
                        break;
                    }
                }
            }
        }

        // ดึงสีรถ
        $color_name = '-';
        foreach ($order as $key => $val) {
            if (stripos($key, 'color') !== false || stripos($key, 'colour') !== false) {
                $v_str = trim((string)($val ?? ''));
                if ($v_str !== '' && $v_str !== '-' && $v_str !== '0') {
                    $color_name = $v_str;
                    break;
                }
            }
        }

        // ดึงภาค / พื้นที่
        $region_name = '-';
        $region_keys = ['region', 'user_region', 'sales_region', 'area', 'zone', 'province'];
        foreach ($region_keys as $rk) {
            if (!empty($order[$rk])) {
                $v_str = trim((string)$order[$rk]);
                if ($v_str !== '' && $v_str !== '-' && $v_str !== '0') {
                    $region_name = $v_str;
                    break;
                }
            }
        }
        if ($region_name === '-') {
            foreach ($order as $key => $val) {
                if (stripos($key, 'region') !== false || stripos($key, 'zone') !== false || stripos($key, 'area') !== false || stripos($key, 'province') !== false) {
                    $v_str = trim((string)($val ?? ''));
                    if ($v_str !== '' && $v_str !== '-' && $v_str !== '0') {
                        $region_name = $v_str;
                        break;
                    }
                }
            }
        }

        // ดึงปีและเดือน
        $order_year  = '';
        $order_month = '';

        foreach ($order as $key => $val) {
            if (stripos($key, 'month') !== false || stripos($key, 'period') !== false) {
                $v_str = trim((string)($val ?? ''));
                if ($v_str !== '' && $v_str !== '-' && $v_str !== '0') {
                    if (preg_match('/^(\d{4})-(\d{2})$/', $v_str, $matches)) {
                        $order_year  = $matches[1];
                        $order_month = $matches[2];
                    } else {
                        $order_month = sprintf("%02d", intval($v_str));
                    }
                    break;
                }
            }
        }

        if (empty($order_year) || empty($order_month)) {
            foreach (['created_at', 'date', 'order_date', 'timestamp'] as $date_col) {
                if (!empty($order[$date_col])) {
                    $timestamp = strtotime(trim((string)$order[$date_col]));
                    if ($timestamp) {
                        if (empty($order_year))  $order_year  = date('Y', $timestamp);
                        if (empty($order_month)) $order_month = date('m', $timestamp);
                        break;
                    }
                }
            }
        }

        $order_year  = $order_year ?: '-';
        $order_month = $order_month ?: '-';

        $order['brand_name']  = trim($brand_name);
        $order['model_name']  = trim($model_name);
        $order['color_name']  = trim($color_name);
        $order['year']        = trim($order_year);
        $order['month']       = trim($order_month); 
        $order['region_name'] = trim($region_name);

        $all_orders[] = $order;

        if ($color_name !== '-' && !in_array($color_name, $unique_colors)) {
            $unique_colors[] = $color_name;
        }
        if ($order_year !== '-' && !in_array($order_year, $unique_years)) {
            $unique_years[] = $order_year;
        }
        if ($model_name !== '-' && !in_array($model_name, $unique_models)) {
            $unique_models[] = $model_name;
        }
    }

    // เพิ่มปีย้อนหลังอัตโนมัติ (ปีปัจจุบัน ย้อนหลังไป 5 ปี) รวมกับปีที่มีในฐานข้อมูล
    $current_year = (int)date('Y');
    for ($y = $current_year; $y >= $current_year - 5; $y--) {
        $str_y = (string)$y;
        if (!in_array($str_y, $unique_years)) {
            $unique_years[] = $str_y;
        }
    }

    // จัดเรียงข้อมูลตัวเลือก
    sort($unique_colors);
    rsort($unique_years);
    sort($unique_models);

    // --------------------------------------------------------------------------
    // 3. FILTER LOGIC
    // --------------------------------------------------------------------------
    $filtered_orders = [];
    foreach ($all_orders as $order) {
        $matchColor = ($get_color === '' || $order['color_name'] === $get_color);
        $matchModel = ($get_model === '' || $order['model_name'] === $get_model);
        $matchYear  = ($get_year === '' || $order['year'] === $get_year);
        
        $matchMonth = true;
        if ($get_month !== '') {
            $normRowMonth = ltrim($order['month'], '0');
            $normSelMonth = ltrim($get_month, '0');
            $matchMonth   = ($order['month'] === $get_month || $normRowMonth === $normSelMonth);
        }

        $matchSearch = true;
        if ($get_search !== '') {
            $stringValues = array_map(function($v) {
                return is_scalar($v) ? (string)$v : '';
            }, $order);
            $rowText     = strtolower(implode(' ', $stringValues));
            $matchSearch = (strpos($rowText, strtolower($get_search)) !== false);
        }

        if ($matchColor && $matchModel && $matchYear && $matchMonth && $matchSearch) {
            $filtered_orders[] = $order;
        }
    }

    $_SESSION['report_orders'] = $filtered_orders;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message   = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage();
    $filtered_orders = [];
}

include_once '../includes/header.php';
include_once '../includes/sidebar.php';

$export_query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
?>

<!-- ============================================================================ -->
<!-- STYLESHEET                                                                   -->
<!-- ============================================================================ -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap');

    :root {
        --font-kanit: 'Kanit', sans-serif;
        --bg-light: #f8fafc;
        --card-border-radius: 16px;
        --primary-blue: #2563eb;
    }

    html {
        scrollbar-gutter: stable;
    }

    body, html, button, input, select, textarea, table {
        font-family: var(--font-kanit) !important;
    }

    body {
        background-color: var(--bg-light);
        overflow-x: hidden;
    }

    #mainContentArea,
    .main-content,
    body:not(.sidebar-collapsed):not(.collapsed) #mainContentArea,
    body:not(.sidebar-collapsed):not(.collapsed) .main-content {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        min-height: 100vh;
        padding: 28px !important;
        padding-bottom: 300px !important; 
        box-sizing: border-box !important;
        transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                    width 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative;
        z-index: 1;
    }

    body.sidebar-collapsed #mainContentArea,
    body.sidebar-collapsed .main-content,
    body.collapsed #mainContentArea,
    body.collapsed .main-content {
        margin-left: 80px !important;
        width: calc(100% - 80px) !important;
    }

    @media (max-width: 991.98px) {
        body #mainContentArea,
        body .main-content,
        body.sidebar-collapsed #mainContentArea,
        body.sidebar-collapsed .main-content {
            margin-left: 80px !important;        
            width: calc(100% - 80px) !important; 
            padding: 16px !important;
            padding-bottom: 300px !important;
        }
    }

    .fade-in-up {
        animation: fadeInUp 0.25s ease-out forwards;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .stat-card {
        border-radius: var(--card-border-radius);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), 
                    box-shadow 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: default;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
    }

    .dashboard-card {
        border-radius: var(--card-border-radius);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    .icon-shape {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }

    .brand-icon-svg {
        width: 26px;
        height: 26px;
        fill: currentColor;
    }

    .form-control-custom, .form-select-custom {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.88rem;
        background-color: #ffffff;
        cursor: pointer;
    }

    .form-select-custom:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }

    select.form-select-custom {
        width: 100%;
    }

    .input-group.flex-nowrap {
        display: flex;
        align-items: stretch;
        width: 100%;
    }

    .input-group .form-control-custom {
        flex: 1 1 auto;
        width: 1%;
    }

    .btn-excel {
        background-color: #10b981;
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 9px 18px;
        font-weight: 500;
        font-size: 0.88rem;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
        text-decoration: none;
    }

    .btn-excel:hover {
        background-color: #059669;
        color: #ffffff;
    }

    .table-clean {
        width: 100% !important;
        margin-bottom: 0;
        border-collapse: collapse;
    }

    .table-clean thead th {
        background-color: #f8fafc;
        border-bottom: 2px solid #e2e8f0 !important;
        border-top: none !important;
        border-left: none !important;
        border-right: none !important;
        color: #475569;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 10px 12px;
        white-space: nowrap;
    }

    .table-clean tbody td {
        padding: 12px 12px;
        border-bottom: 1px solid #f1f5f9 !important;
        border-top: none !important;
        border-left: none !important;
        border-right: none !important;
        vertical-align: middle;
        font-size: 0.88rem;
    }

    .table-clean tbody tr:hover td {
        background-color: #f8fafc;
    }

    .table-clean tfoot td {
        padding: 12px 12px;
        background-color: #f8fafc;
        border-top: 2px solid #e2e8f0 !important;
        border-bottom: none !important;
        border-left: none !important;
        border-right: none !important;
        font-size: 0.88rem;
    }

    /* Badges */
    .badge-region-central { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .badge-region-north   { background-color: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
    .badge-region-ne      { background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
    .badge-region-south   { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-region-east    { background-color: #fce7f3; color: #be185d; border: 1px solid #fbcfe8; }
    .badge-region-west    { background-color: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
    .badge-region-default { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    .badge-brand-tag {
        background-color: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge-soft-success { background-color: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }
    .badge-soft-danger  { background-color: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }
    .badge-soft-warning { background-color: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }

    /* Style รายการสีรถแบบมีไอคอนจานสี */
    .color-item-row {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        font-size: 0.88rem;
        line-height: 1.4;
    }

    .fs-12 { font-size: 0.8rem; }
    .fs-13 { font-size: 0.85rem; }
    .fs-14 { font-size: 0.9rem; }
</style>

<!-- ============================================================================ -->
<!-- MAIN CONTENT AREA                                                            -->
<!-- ============================================================================ -->
<main id="mainContentArea" class="main-content fade-in-up">
    
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-journal-text text-primary"></i> ประวัติคำสั่งซื้อทั้งหมด
            </h3>
            <p class="text-muted small mb-0">ระบบบริหารจัดการและ ตรวจสอบสถิติรายการสั่งจองรถมอเตอร์ไซค์</p>
        </div>
        
        <a href="export_preview.php<?= $export_query ?>" class="btn btn-excel d-inline-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-excel-fill fs-5"></i> ส่งออก Excel
        </a>
    </div>

    <!-- Section 1: สถิติสรุป 4 การ์ด -->
    <?php
        $sum_shop_qty      = 0;
        $sum_admin_qty     = 0;
        $sum_backorder_qty = 0;
        
        foreach ($filtered_orders as $ord) {
            $sum_shop_qty      += intval($ord['quantity'] ?? 0);
            $sum_admin_qty     += intval($ord['approved_quantity'] ?? 0);
            $sum_backorder_qty += intval($ord['backorder_quantity'] ?? 0);
        }
    ?>
    <div class="row g-3 mb-4">
        <!-- 1. คำสั่งซื้อในระบบ -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="text-muted small fw-medium">คำสั่งซื้อในระบบ</span>
                        <h3 class="fw-bold text-dark my-1"><?= number_format(count($filtered_orders)); ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h3>
                    </div>
                    <div class="icon-shape bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-receipt-cutoff fs-4"></i>
                    </div>
                </div>
                <div class="border-top pt-2 small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i> ตามเงื่อนไขการกรอง
                </div>
            </div>
        </div>

        <!-- 2. ยอดขอจองรวม -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="text-muted small fw-medium">ยอดขอจองรวม</span>
                        <h3 class="fw-bold text-dark my-1"><?= number_format($sum_shop_qty); ?> <span class="fs-6 text-muted fw-normal">คัน</span></h3>
                    </div>
                    <div class="icon-shape bg-info bg-opacity-10 text-info">
                        <svg class="brand-icon-svg" viewBox="0 0 24 24">
                            <rect x="1.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                            <rect x="15.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                            <path fill-rule="evenodd" d="M12 2 C13.93 2 15.5 3.57 15.5 5.5 C15.5 7.43 13.93 9 12 9 C10.07 9 8.5 7.43 8.5 5.5 C8.5 3.57 10.07 2 12 2 Z M12 3.8 C11.06 3.8 10.3 4.56 10.3 5.5 C10.3 6.44 11.06 7.2 12 7.2 C12.94 7.2 13.7 6.44 13.7 5.5 C13.7 4.56 12.94 3.8 12 3.8 Z"/>
                            <path d="M12 7.5 C8 7.5 5 10.5 5 14.2 L5 18 C5 19.1 5.9 20 7 20 L8.2 20 C8.4 20 8.5 19.8 8.5 19.6 L8.5 16.5 C8.5 14.6 10.1 13 12 13 C13.9 13 15.5 14.6 15.5 16.5 L15.5 19.6 C15.5 19.8 15.6 20 17 20 C18.1 20 19 19.1 19 18 L19 14.2 C19 10.5 16 7.5 12 7.5 Z"/>
                            <rect x="9.5" y="13.5" width="5" height="8.5" rx="2.5"/>
                        </svg>
                    </div>
                </div>
                <div class="border-top pt-2 small text-muted">
                    <i class="bi bi-shop me-1 text-info"></i> จากหน้าร้านทุกเขต
                </div>
            </div>
        </div>

        <!-- 3. จำนวนอนุมัติแล้ว -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="text-muted small fw-medium">จำนวนอนุมัติแล้ว</span>
                        <h3 class="fw-bold text-success my-1"><?= number_format($sum_admin_qty); ?> <span class="fs-6 text-muted fw-normal">คัน</span></h3>
                    </div>
                    <div class="icon-shape bg-success bg-opacity-10 text-success">
                        <i class="bi bi-patch-check-fill fs-4"></i>
                    </div>
                </div>
                <div class="border-top pt-2 small text-muted">
                    <i class="bi bi-shield-check me-1 text-success"></i> อนุมัติโดยระบบ
                </div>
            </div>
        </div>

        <!-- 4. ยอดแบ็คออเดอร์ -->
        <div class="col-xl-3 col-md-6 col-12">
            <div class="stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="text-muted small fw-medium">ยอดแบ็คออเดอร์</span>
                        <h3 class="fw-bold text-warning my-1"><?= number_format($sum_backorder_qty); ?> <span class="fs-6 text-muted fw-normal">คัน</span></h3>
                    </div>
                    <div class="icon-shape bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split fs-4"></i>
                    </div>
                </div>
                <div class="border-top pt-2 small text-muted">
                    <i class="bi bi-exclamation-triangle me-1 text-warning"></i> ค้างส่งมอบ
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2 & 3: Layout ตัวกรองและตารางแสดงข้อมูล -->
    <div class="row g-4">
        
        <!-- ฝั่งซ้าย: ตัวกรองการค้นหา -->
        <div class="col-12 col-lg-3 col-xl-3">
            <div class="dashboard-card p-3 p-md-4">
                <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-funnel-fill text-primary"></i> ตัวกรองการค้นหา
                </h6>
                
                <div class="d-flex flex-column gap-3">
                    <!-- ค้นหาข้อความ -->
                    <div>
                        <label class="form-label fs-12 fw-medium text-muted mb-1">ค้นหา</label>
                        <div class="input-group flex-nowrap">
                            <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 10px 0 0 10px; border-color: #e2e8f0;">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" id="searchInput" class="form-control form-control-custom border-start-0" 
                                   style="border-radius: 0 10px 10px 0;"
                                   value="<?= htmlspecialchars($get_search); ?>" 
                                   placeholder="พิมพ์ข้อความ (กด Enter)...">
                        </div>
                    </div>

                    <!-- รุ่นรถ -->
                    <div>
                        <label class="form-label fs-12 fw-medium text-muted mb-1">รุ่นรถ</label>
                        <select id="filterModel" class="form-select form-select-custom">
                            <option value="">รุ่นรถทั้งหมด</option>
                            <?php foreach ($unique_models as $mod): ?>
                                <option value="<?= htmlspecialchars($mod); ?>" <?= ($get_model === $mod) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($mod); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- สีรถ -->
                    <div>
                        <label class="form-label fs-12 fw-medium text-muted mb-1">สีรถ</label>
                        <select id="filterColor" class="form-select form-select-custom">
                            <option value="">สีรถทั้งหมด</option>
                            <?php foreach ($unique_colors as $col): ?>
                                <option value="<?= htmlspecialchars($col); ?>" <?= ($get_color === $col) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($col); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ปี (แสดงปีย้อนหลังแบบไดนามิก) -->
                    <div>
                        <label class="form-label fs-12 fw-medium text-muted mb-1">ปี</label>
                        <select id="filterYear" class="form-select form-select-custom">
                            <option value="">ปีทั้งหมด</option>
                            <?php foreach ($unique_years as $y): ?>
                                <option value="<?= htmlspecialchars($y); ?>" <?= ($get_year === (string)$y) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($y); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- เดือน -->
                    <div>
                        <label class="form-label fs-12 fw-medium text-muted mb-1">เดือน</label>
                        <select id="filterMonth" class="form-select form-select-custom">
                            <option value="">เดือนทั้งหมด</option>
                            <?php foreach ($all_months_list as $m_code => $m_name): 
                                $norm_get   = ltrim($get_month, '0');
                                $norm_code  = ltrim($m_code, '0');
                                $is_selected = ($get_month !== '' && ($get_month === $m_code || $norm_get === $norm_code));
                            ?>
                                <option value="<?= $m_code; ?>" <?= $is_selected ? 'selected' : ''; ?>>
                                    <?= $m_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ปุ่มล้างตัวกรอง -->
                    <?php if ($get_search !== '' || $get_model !== '' || $get_color !== '' || $get_year !== '' || $get_month !== ''): ?>
                        <div class="pt-2">
                            <a href="orders.php" class="btn btn-outline-secondary btn-sm w-100 rounded-2">
                                <i class="bi bi-x-circle me-1"></i> ล้างตัวกรองทั้งหมด
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: ตารางแสดงรายการข้อมูล -->
        <div class="col-12 col-lg-9 col-xl-9">
            <div class="dashboard-card p-3 p-md-4 h-100">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger border-0 shadow-sm p-3 rounded-3 d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div><?= htmlspecialchars($error_message); ?></div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-clean align-middle" id="ordersTable">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">คำขอ / ผู้สั่งจอง</th>
                                    <th style="width: 25%;">ยี่ห้อ - รุ่นมอเตอร์ไซค์ & ภาค</th>
                                    <th style="width: 16%;">สีรถ</th>
                                    <th class="text-center" style="width: 10%;">จำนวน</th>
                                    <th class="text-center" style="width: 12%;">จำนวน (อนุมัติ)</th>
                                    <th class="text-center" style="width: 10%;">แบ็คออเดอร์</th>
                                    <th class="text-center" style="width: 12%;">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_shop_qty      = 0;
                                $total_admin_qty     = 0;
                                $total_backorder_qty = 0;

                                if (!empty($filtered_orders)): 
                                    foreach ($filtered_orders as $index => $order): 
                                        $shop_qty      = (int)($order['quantity'] ?? 0);          
                                        $admin_qty     = (int)($order['approved_quantity'] ?? 0); 
                                        $backorder_qty = (int)($order['backorder_quantity'] ?? 0);
                                        $status        = strtolower(trim($order['status'] ?? 'pending'));
                                        
                                        $shop_name     = $order['shop_name'];
                                        $sales_name    = $order['sales_name'] ?? 'ไม่ระบุ';
                                        $brand_name    = $order['brand_name'];
                                        $model_name    = $order['model_name'];
                                        $color_name    = $order['color_name'];
                                        $region_name   = $order['region_name'];
                                        $region_class  = getRegionBadgeClass($region_name);

                                        $total_shop_qty      += $shop_qty;
                                        $total_admin_qty     += $admin_qty;
                                        $total_backorder_qty += $backorder_qty;
                                ?>
                                        <tr>
                                            <!-- คำขอ / ผู้สั่งจอง -->
                                            <td>
                                                <div class="fw-bold text-dark fs-14">
                                                    #<?= ($index + 1); ?>
                                                </div>
                                                <div class="text-muted fs-12 mt-0.5">
                                                    <?= htmlspecialchars($sales_name); ?>
                                                </div>
                                            </td>

                                            <!-- ยี่ห้อ - รุ่นมอเตอร์ไซค์ & ภาค -->
                                            <td>
                                                <div class="fw-bold text-dark fs-14 d-flex align-items-center gap-1.5 flex-wrap">
                                                    <?php if ($brand_name !== '-'): ?>
                                                        <span class="badge badge-brand-tag me-1">
                                                            <?= htmlspecialchars($brand_name); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($model_name); ?></span>
                                                </div>
                                                <div class="text-muted fs-12 mt-1 d-flex align-items-center gap-1 flex-wrap">
                                                    <span><?= htmlspecialchars($shop_name); ?></span>
                                                    <?php if ($region_name !== '-'): ?>
                                                        <span class="badge <?= $region_class; ?> px-2 py-0.5 rounded-pill fs-12 fw-medium ms-1">
                                                            <?= htmlspecialchars($region_name); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- สีรถ (แยกตามบรรทัด พร้อมไอคอนจานสีแบบกำหนดสีเอง) -->
                                            <td>
                                                <?php 
                                                if ($color_name !== '-'): 
                                                    $color_list = array_map('trim', preg_split('/[,\n|]+/', $color_name));
                                                ?>
                                                    <div class="d-flex flex-column gap-1">
                                                        <?php foreach ($color_list as $c_item): 
                                                            if (empty($c_item)) continue;
                                                            $c_hex = getColorHexCode($c_item);
                                                        ?>
                                                            <div class="color-item-row" style="color: <?= $c_hex; ?>;">
                                                                <i class="bi bi-palette-fill fs-14"></i>
                                                                <span><?= htmlspecialchars($c_item); ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted fs-13">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- จำนวน (ขอจอง) -->
                                            <td class="text-center fw-bold text-dark fs-14">
                                                <?= number_format($shop_qty); ?>
                                            </td>

                                            <!-- จำนวน (อนุมัติ) -->
                                            <td class="text-center fw-bold text-success fs-14">
                                                <?= number_format($admin_qty); ?>
                                            </td>

                                            <!-- แบ็คออเดอร์ -->
                                            <td class="text-center fw-bold text-warning fs-14">
                                                <?= number_format($backorder_qty); ?>
                                            </td>

                                            <!-- สถานะ -->
                                            <td class="text-center">
                                                <?php if ($admin_qty > 0 || $backorder_qty > 0 || in_array($status, ['approved', 'อนุมัติ', 'อนุมัติแล้ว', 'success', 'complete'])): ?>
                                                    <span class="badge badge-soft-success px-2.5 py-1.5 rounded-pill fs-12 fw-semibold d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-check-circle-fill"></i> อนุมัติ
                                                    </span>
                                                <?php elseif (in_array($status, ['rejected', 'ปฏิเสธ', 'cancel', 'cancelled'])): ?>
                                                    <span class="badge badge-soft-danger px-2.5 py-1.5 rounded-pill fs-12 fw-semibold d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-x-circle-fill"></i> ปฏิเสธ
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-soft-warning px-2.5 py-1.5 rounded-pill fs-12 fw-semibold d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-hourglass-split"></i> รอดำเนินการ
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted fs-13">
                                            <i class="bi bi-inbox fs-1 d-block opacity-25 mb-2"></i>
                                            ไม่พบรายการสั่งจองตามเงื่อนไขที่ระบุ
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="fw-bold">
                                <tr>
                                    <td colspan="3" class="text-end text-muted">รวมยอดทั้งหมดตามตัวกรอง :</td>
                                    <td class="text-center text-dark fs-14"><?= number_format($total_shop_qty); ?></td>
                                    <td class="text-center text-success fs-14"><?= number_format($total_admin_qty); ?></td>
                                    <td class="text-center text-warning fs-14"><?= number_format($total_backorder_qty); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</main>

<?php include_once '../includes/footer.php'; ?>

<!-- ============================================================================ -->
<!-- SCRIPT ตัวกรองการค้นหา                                                        -->
<!-- ============================================================================ -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterColor = document.getElementById('filterColor');
        const filterModel = document.getElementById('filterModel');
        const filterYear  = document.getElementById('filterYear');
        const filterMonth = document.getElementById('filterMonth');
        const searchInput = document.getElementById('searchInput');

        function applyFilters() {
            const color  = filterColor ? filterColor.value.trim() : '';
            const model  = filterModel ? filterModel.value.trim() : '';
            const year   = filterYear ? filterYear.value.trim() : '';
            const month  = filterMonth ? filterMonth.value.trim() : '';
            const search = searchInput ? searchInput.value.trim() : '';

            let params = new URLSearchParams();
            if (color)  params.set('color', color);
            if (model)  params.set('model', model);
            if (year)   params.set('year', year);
            if (month)  params.set('month', month);
            if (search) params.set('search', search);

            window.location.href = 'orders.php?' + params.toString();
        }

        if (filterColor) filterColor.addEventListener('change', applyFilters);
        if (filterModel) filterModel.addEventListener('change', applyFilters);
        if (filterYear)  filterYear.addEventListener('change', applyFilters);
        if (filterMonth) filterMonth.addEventListener('change', applyFilters);
        
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });
        }
    });
</script>