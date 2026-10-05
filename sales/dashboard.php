<?php
/**
 * ==============================================================================
 * File: sales/dashboard.php - ระบบจัดการโควตารถมอเตอร์ไซค์ (Sales Dashboard)
 * Description: แดชบอร์ดแสดงโควตา รหัสสั่งจอง การตรวจสอบโควตาก่อนสั่งจอง และรายการสั่งจอง (เรียงจากบนลงล่าง)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

checkSales();

// ------------------------------------------------------------------------------
// 1. ตัวแปรเริ่มต้นและระบุเขตพื้นที่การทำงาน (User Region)
// ------------------------------------------------------------------------------
$sales_id          = $_SESSION['user_id'] ?? 0;
$error_msg         = "";
$success_msg       = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);

$user_region       = "";
$total_max         = 0; 
$total_booked      = 0;
$total_remaining   = 0;
$display_quotas    = [];
$unique_brands     = [];
$brand_summaries   = [];
$list_shops        = [];
$my_orders_display = [];
$approved_qty      = 0;
$pending_orders    = 0;

// ดึงเขตพื้นที่ความรับผิดชอบก่อนประมวลผล Action
try {
    $current_month = date('Y-m'); 
    $stmt_region = $conn->prepare("SELECT region FROM region_sales_assignment WHERE user_id = ? AND assigned_month = ? LIMIT 1");
    $stmt_region->execute([$sales_id, $current_month]);
    $res_region = $stmt_region->fetch(PDO::FETCH_ASSOC);

    if ($res_region && !empty($res_region['region'])) {
        $user_region = trim($res_region['region']);
    } else {
        $stmt_fallback = $conn->prepare("SELECT region FROM region_sales_assignment WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_fallback->execute([$sales_id]);
        $res_fallback = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
        $user_region = ($res_fallback && !empty($res_fallback['region'])) ? trim($res_fallback['region']) : ""; 
    }
} catch (PDOException $e) {
    $error_msg = "เกิดข้อผิดพลาดในการตรวจสอบเขตพื้นที่: " . $e->getMessage();
}

// ------------------------------------------------------------------------------
// Helper Function 1: ตรวจสอบและคืนค่าสไตล์สี (Icon + Text Color) ตามชื่อสีรถ
// ------------------------------------------------------------------------------
function getColorStyle($color_name) {
    $c = mb_strtolower(trim($color_name));

    if (strpos($c, 'green') !== false || strpos($c, 'เขียว') !== false) {
        return 'color: #16a34a;';
    }
    if (strpos($c, 'espresso') !== false || strpos($c, 'brown') !== false || strpos($c, 'น้ำตาล') !== false) {
        return 'color: #78350f;';
    }
    if (strpos($c, 'black') !== false || strpos($c, 'ดำ') !== false) {
        return 'color: #1e293b;';
    }
    if (strpos($c, 'blue') !== false || strpos($c, 'น้ำเงิน') !== false || strpos($c, 'ฟ้า') !== false) {
        return 'color: #2563eb;';
    }
    if (strpos($c, 'red') !== false || strpos($c, 'แดง') !== false) {
        return 'color: #dc2626;';
    }
    if (strpos($c, 'yellow') !== false || strpos($c, 'เหลือง') !== false) {
        return 'color: #d97706;';
    }
    if (strpos($c, 'orange') !== false || strpos($c, 'ส้ม') !== false) {
        return 'color: #ea580c;';
    }
    if (strpos($c, 'silver') !== false || strpos($c, 'เงิน') !== false || strpos($c, 'grey') !== false || strpos($c, 'gray') !== false || strpos($c, 'เทา') !== false || strpos($c, 'scuro') !== false) {
        return 'color: #64748b;';
    }
    if (strpos($c, 'gold') !== false || strpos($c, 'ทอง') !== false) {
        return 'color: #ca8a04;';
    }
    return 'color: #64748b;';
}

// ------------------------------------------------------------------------------
// Helper Function 2: ตรวจสอบจำนวนโควตาคงเหลือจริงในฐานข้อมูล
// ------------------------------------------------------------------------------
function getAvailableQuota($conn, $car_id, $color_name, $region, $exclude_order_id = 0) {
    if (!$car_id || empty($color_name) || empty($region)) return 0;

    $stmt_q = $conn->prepare("
        SELECT max_limit 
        FROM car_quotas 
        WHERE car_id = ? 
          AND color_name COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci 
          AND region COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci 
        LIMIT 1
    ");
    $stmt_q->execute([$car_id, $color_name, $region]);
    $quota_row = $stmt_q->fetch(PDO::FETCH_ASSOC);
    if (!$quota_row) return 0;
    
    $max_limit = intval($quota_row['max_limit'] ?? 0);

    $sql_booked = "
        SELECT SUM(quantity) as booked_total 
        FROM orders 
        WHERE car_id = ? 
          AND color_name COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci 
          AND status COLLATE utf8mb4_unicode_ci != 'ยกเลิก' COLLATE utf8mb4_unicode_ci
    ";
    $params = [$car_id, $color_name];

    if ($exclude_order_id > 0) {
        $sql_booked .= " AND id != ?";
        $params[] = $exclude_order_id;
    }

    $stmt_b = $conn->prepare($sql_booked);
    $stmt_b->execute($params);
    $booked_total = intval($stmt_b->fetchColumn() ?: 0);

    return max(0, $max_limit - $booked_total);
}

// ------------------------------------------------------------------------------
// 2. จัดการ Action: สั่งจองใหม่ (Create) & แก้ไขการสั่งจอง (Update)
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- 2.1 สร้างรายการสั่งจองใหม่ ---
    if ($_POST['action'] === 'create_order') {
        $car_id     = intval($_POST['car_id'] ?? 0);
        $color_name = trim($_POST['color_name'] ?? '');
        $shop_name  = trim($_POST['shop_name'] ?? '');
        $quantity   = intval($_POST['quantity'] ?? 1);
        $note       = trim($_POST['remark'] ?? '');

        if ($car_id > 0 && !empty($color_name) && !empty($shop_name) && $quantity > 0) {
            $available_quota = getAvailableQuota($conn, $car_id, $color_name, $user_region);

            if ($quantity > $available_quota) {
                $error_msg = "ไม่สามารถสั่งจองได้! จำนวนที่คุณระบุ ({$quantity} คัน) เกินกว่าโควตาคงเหลือที่มีอยู่ ({$available_quota} คัน)";
            } else {
                try {
                    $stmt_ins = $conn->prepare("
                        INSERT INTO orders (sales_id, car_id, color_name, shop_name, quantity, note, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())
                    ");
                    $stmt_ins->execute([$sales_id, $car_id, $color_name, $shop_name, $quantity, $note]);
                    
                    $_SESSION['flash_success'] = "บันทึกการสั่งจองเรียบร้อยแล้ว";
                    header("Location: dashboard.php");
                    exit;
                } catch (PDOException $e) {
                    $error_msg = "เกิดข้อผิดพลาดในการบันทึกการสั่งจอง: " . $e->getMessage();
                }
            }
        } else {
            $error_msg = "กรุณากรอกข้อมูลการสั่งจองให้ครบถ้วน";
        }
    }

    // --- 2.2 แก้ไขรายการสั่งจองเดิม ---
    if ($_POST['action'] === 'update_order') {
        $update_id    = intval($_POST['order_id'] ?? 0);
        $new_shop     = trim($_POST['shop_name'] ?? '');
        $new_color    = trim($_POST['color_name'] ?? '');
        $new_quantity = intval($_POST['quantity'] ?? 1);
        $new_note     = trim($_POST['remark'] ?? '');

        if ($update_id > 0 && !empty($new_shop) && !empty($new_color) && $new_quantity > 0) {
            try {
                $stmt_curr = $conn->prepare("SELECT car_id FROM orders WHERE id = ? AND sales_id = ?");
                $stmt_curr->execute([$update_id, $sales_id]);
                $curr_order = $stmt_curr->fetch(PDO::FETCH_ASSOC);

                if ($curr_order) {
                    $car_id = intval($curr_order['car_id']);
                    $available_quota = getAvailableQuota($conn, $car_id, $new_color, $user_region, $update_id);

                    if ($new_quantity > $available_quota) {
                        $error_msg = "ไม่สามารถแก้ไขได้! จำนวนใหม่ ({$new_quantity} คัน) เกินกว่าโควตาคงเหลือที่มีอยู่ ({$available_quota} คัน)";
                    } else {
                        $stmt_up = $conn->prepare("
                            UPDATE orders 
                            SET shop_name = ?, color_name = ?, quantity = ?, note = ?, updated_at = NOW() 
                            WHERE id = ? AND sales_id = ? AND (status = 'Pending' OR status = 'รอดำเนินการ' OR status = '' OR status IS NULL)
                        ");
                        $stmt_up->execute([$new_shop, $new_color, $new_quantity, $new_note, $update_id, $sales_id]);
                        
                        $_SESSION['flash_success'] = "อัปเดตข้อมูลการสั่งจองเรียบร้อยแล้ว";
                        header("Location: dashboard.php");
                        exit;
                    }
                } else {
                    $error_msg = "ไม่พบรายการสั่งจอง หรือคุณไม่มีสิทธิ์แก้ไขรายการนี้";
                }
            } catch (PDOException $e) {
                $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
            }
        } else {
            $error_msg = "กรุณากรอกข้อมูลให้ครบถ้วนถูกต้อง";
        }
    }
}

// ------------------------------------------------------------------------------
// 3. ดึงข้อมูล Dashboard และสรุปสถิติ
// ------------------------------------------------------------------------------
try {
    // 3.1 ดึงรายการโควตารถตามเขตพื้นที่
    $sql_quota = "SELECT q.*, c.brand, c.model_name, 
                         (SELECT SUM(quantity) FROM orders o 
                          WHERE o.car_id = q.car_id 
                          AND o.color_name COLLATE utf8mb4_unicode_ci = q.color_name COLLATE utf8mb4_unicode_ci
                          AND o.status COLLATE utf8mb4_unicode_ci != 'ยกเลิก' COLLATE utf8mb4_unicode_ci) as booked_total
                  FROM car_quotas q 
                  LEFT JOIN cars c ON q.car_id = c.id 
                  WHERE q.region COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci 
                  ORDER BY c.brand ASC, c.model_name ASC";
            
    $stmt_list = $conn->prepare($sql_quota);
    $stmt_list->execute([$user_region]);
    $display_quotas = $stmt_list->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // คำนวณยอดสรุปแต่ละยี่ห้อ
    foreach ($display_quotas as $q) { 
        $max    = intval($q['max_limit'] ?? 0);
        $booked = intval($q['booked_total'] ?? 0);
        $brand  = trim($q['brand'] ?? 'อื่นๆ');
        
        $total_max    += $max; 
        $total_booked += $booked;

        if (!isset($brand_summaries[$brand])) {
            $brand_summaries[$brand] = [
                'quota'     => 0,
                'booked'    => 0,
                'remaining' => 0,
                'approved'  => 0,
                'pending'   => 0
            ];
        }

        $brand_summaries[$brand]['quota']     += $max;
        $brand_summaries[$brand]['booked']    += $booked;
        $brand_summaries[$brand]['remaining'] += max(0, $max - $booked);
    }

    $total_remaining = max(0, $total_max - $total_booked);
    $unique_brands   = array_keys($brand_summaries);

    // 3.2 ดึงรายการร้านค้าตามเขตพื้นที่
    $stmt_shops = $conn->prepare("SELECT shop_name FROM shops WHERE region COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci ORDER BY shop_name ASC");
    $stmt_shops->execute([$user_region]);
    $list_shops = $stmt_shops->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3.3 ดึงรายการสั่งจองของ Sales (ปรับการเรียงลำดับจากบนลงล่าง: ASC)
    $sql_orders = "SELECT o.*, c.model_name, c.brand 
                   FROM orders o 
                   LEFT JOIN cars c ON o.car_id = c.id 
                   WHERE o.sales_id = ? 
                   ORDER BY o.id ASC LIMIT 10";
                   
    $stmt_orders = $conn->prepare($sql_orders);
    $stmt_orders->execute([$sales_id]);
    $my_orders_display = $stmt_orders->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($my_orders_display as $o) {
        $st    = trim($o['status'] ?? '');
        $bName = trim($o['brand'] ?? 'อื่นๆ');
        $qty   = intval($o['quantity'] ?? 0);

        if (!isset($brand_summaries[$bName])) {
            $brand_summaries[$bName] = [
                'quota' => 0, 'booked' => 0, 'remaining' => 0, 'approved' => 0, 'pending' => 0
            ];
        }

        if ($st === 'Approved' || $st === 'อนุมัติ') {
            $approved_qty += $qty;
            $brand_summaries[$bName]['approved'] += $qty;
        } elseif ($st === 'รอดำเนินการ' || $st === 'Pending' || $st === '') {
            $pending_orders++;
            $brand_summaries[$bName]['pending'] += $qty;
        }
    }

} catch (PDOException $e) {
    $error_msg = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- External Stylesheets & Fonts -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --primary-blue: #0284c7;
        --primary-blue-hover: #0369a1;
        --bg-blue-light: #f0f9ff;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 80px;
    }

    body { 
        font-family: 'Kanit', sans-serif !important; 
        background-color: #f8fafc; 
        overflow-x: hidden;
    }

    .modal-backdrop.show {
        opacity: 1 !important;
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
        background-color: rgba(15, 23, 42, 0.35) !important;
    }

    #mainContentArea { 
        margin-left: var(--sidebar-width); 
        width: calc(100% - var(--sidebar-width)); 
        padding: 24px 28px; 
        transition: margin-left 0.25s ease, width 0.25s ease;
        min-height: 100vh;
        box-sizing: border-box !important;
        max-width: 100%;
    }

    body.sidebar-collapsed #mainContentArea,
    body.collapsed-sidebar #mainContentArea,
    .sidebar-collapsed #mainContentArea,
    .sidebar.collapsed ~ #mainContentArea,
    #sidebar.collapsed ~ #mainContentArea { 
        margin-left: var(--sidebar-collapsed-width) !important; 
        width: calc(100% - var(--sidebar-collapsed-width)) !important; 
    }

    .select-field-wrapper {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
    }

    .form-select, .form-control, select {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        font-size: 0.875rem !important;
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
        display: block;
        background-color: #ffffff;
        color: #0f172a;
        border: 1px solid #cbd5e1;
    }

    .form-select option, select option {
        font-size: 0.875rem !important;
        padding: 6px 10px;
        max-width: 100% !important;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: normal;
        word-break: break-word;
    }

    .form-select:disabled, select:disabled, .form-control:disabled {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
        opacity: 1 !important;
        cursor: pointer;
    }

    .data-card { 
        border-radius: 18px; 
        border: 1px solid #e2e8f0; 
        background: #ffffff; 
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); 
        width: 100%;
        max-width: 100%;
        overflow: hidden;
        box-sizing: border-box !important;
    }

    .stat-card { 
        border-radius: 18px; 
        border: 1px solid #e2e8f0; 
        background: #ffffff; 
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 155px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        width: 100%;
        box-sizing: border-box !important;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        border-color: #cbd5e1;
    }

    .stat-card-title { color: #64748b; font-size: 0.875rem; font-weight: 400; }
    .stat-card-value { font-size: 2rem; font-weight: 700; line-height: 1; }
    .stat-card-unit  { font-size: 1rem; font-weight: 400; }

    .icon-box { 
        width: 46px; 
        height: 46px; 
        border-radius: 14px; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        flex-shrink: 0;
    }
    .icon-box i { font-size: 1.5rem; }

    .icon-box-blue   { background-color: var(--bg-blue-light); color: var(--primary-blue); }
    .icon-box-green  { background-color: #e6f4ea; color: #16a34a; }
    .icon-box-yellow { background-color: #fef9c3; color: #eab308; }
    .icon-box-cyan   { background-color: #e0f2fe; color: #0284c7; }

    .text-val-dark   { color: #0f172a; }
    .text-val-green  { color: #16a34a; }
    .text-val-yellow { color: #d97706; }
    .text-val-cyan   { color: var(--primary-blue); }

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

    .compact-order-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background-color: #ffffff;
        padding: 14px 18px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease-in-out;
    }
    .compact-order-card:hover {
        border-color: #38bdf8;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);
    }

    .edit-icon-btn {
        color: var(--primary-blue);
        background: transparent;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .edit-icon-btn:hover {
        color: var(--primary-blue-hover);
        background-color: var(--bg-blue-light);
    }

    .menu-icon-svg {
        width: 22px;
        height: 22px;
        fill: #475467;
        flex-shrink: 0;
    }

    .icon-blue { color: var(--primary-blue) !important; }

    .status-badge {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .status-approved { background-color: #e6f4ea; color: #16a34a; border: 1px solid #b7e4c7; }
    .status-pending  { background-color: #fef9c3; color: #d97706; border: 1px solid #fef08a; }
    .status-cancel   { background-color: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }

    .custom-sample-modal .modal-content {
        border-radius: 24px !important;
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18) !important;
        padding: 12px !important;
        background-color: #ffffff;
    }

    .custom-sample-modal .modal-header {
        border-bottom: none !important;
        padding: 16px 20px 8px 20px !important;
    }

    .custom-sample-modal .modal-body {
        padding: 12px 20px 20px 20px !important;
    }

    .custom-sample-modal .modal-footer {
        border-top: none !important;
        padding: 8px 20px 20px 20px !important;
        justify-content: center !important;
        gap: 12px !important;
    }

    .modal-header-icon-box {
        width: 44px;
        height: 44px;
        background-color: #e0edff;
        color: #1677ff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .readonly-field-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 16px;
        font-weight: 600;
        color: #0f172a;
        font-size: 0.95rem;
    }

    .custom-sample-modal .form-control,
    .custom-sample-modal .form-select {
        border-radius: 12px !important;
        border: 1px solid #d9d9d9 !important;
        padding: 10px 16px !important;
        font-size: 0.92rem !important;
        color: #1e293b !important;
        box-shadow: none !important;
    }

    .btn-modal-cancel {
        background-color: #f1f5f9;
        color: #334155;
        border: none;
        border-radius: 12px;
        padding: 10px 28px;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .btn-modal-save {
        background-color: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 10px 28px;
        font-weight: 600;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s ease;
    }
    .btn-modal-save:hover {
        background-color: #1d4ed8;
        color: #ffffff;
    }

    @media (max-width: 991.98px) {
        #mainContentArea { 
            margin-left: var(--sidebar-width) !important; 
            width: calc(100% - var(--sidebar-width)) !important; 
            padding: 16px 14px !important; 
        }

        body.sidebar-collapsed #mainContentArea,
        body.collapsed-sidebar #mainContentArea,
        .sidebar-collapsed #mainContentArea,
        .sidebar.collapsed ~ #mainContentArea,
        #sidebar.collapsed ~ #mainContentArea { 
            margin-left: var(--sidebar-collapsed-width) !important; 
            width: calc(100% - var(--sidebar-collapsed-width)) !important; 
        }
    }

    @media (max-width: 575.98px) {
        #mainContentArea,
        body.sidebar-collapsed #mainContentArea,
        body.collapsed-sidebar #mainContentArea,
        .sidebar-collapsed #mainContentArea,
        .sidebar.collapsed ~ #mainContentArea,
        #sidebar.collapsed ~ #mainContentArea { 
            margin-left: 0 !important; 
            width: 100% !important; 
            padding: 12px 10px !important;
        }
    }
</style>

<div class="position-relative">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div id="mainContentArea">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-speedometer2 icon-blue fs-3"></i>
                    <span>Dashboard พนักงานขาย</span>
                </h4>
                <p class="text-muted mb-0 small">
                    ประจำภาค: <span class="badge bg-info bg-opacity-10 icon-blue fw-bold rounded-pill px-3 py-1 ms-1"><?= htmlspecialchars($user_region ?: 'ไม่ได้ระบุ') ?></span>
                </p>
            </div>
            <div class="text-end small text-secondary d-none d-md-block">
                <i class="bi bi-person-circle me-1 icon-blue"></i>ผู้ใช้งาน: <strong class="text-dark"><?= htmlspecialchars($_SESSION['name'] ?? 'พนักงานขาย') ?></strong>
            </div>
        </div>

        <!-- Alert Notifications -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards Summary -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div>
                            <div class="stat-card-title mb-1">โควตารวมทั้งหมด</div>
                            <div class="stat-card-value text-val-dark mb-1">
                                <?= number_format($total_max); ?> <span class="stat-card-unit text-muted">คัน</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-blue">
                            <i class="bi bi-pie-chart-fill"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark ms-1"><?= number_format($bStat['quota']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div>
                            <div class="stat-card-title mb-1">ออเดอร์อนุมัติแล้ว</div>
                            <div class="stat-card-value text-val-green mb-1">
                                <?= number_format($approved_qty); ?> <span class="stat-card-unit text-muted">รายการ</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark ms-1"><?= number_format($bStat['approved']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div>
                            <div class="stat-card-title mb-1">ยอดจองรวมแล้ว</div>
                            <div class="stat-card-value text-val-yellow mb-1">
                                <?= number_format($total_booked); ?> <span class="stat-card-unit text-muted">คัน</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-yellow">
                            <i class="bi bi-bookmark-check-fill"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark ms-1"><?= number_format($bStat['booked']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div>
                            <div class="stat-card-title mb-1">คงเหลือให้จองรวม</div>
                            <div class="stat-card-value text-val-cyan mb-1">
                                <?= number_format($total_remaining); ?> <span class="stat-card-unit text-muted">คัน</span>
                            </div>
                        </div>
                        <div class="icon-box icon-box-cyan">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                    </div>
                    <div class="card-divider">
                        <?php if (!empty($brand_summaries)): ?>
                            <?php foreach ($brand_summaries as $bName => $bStat): ?>
                                <div class="brand-row">
                                    <span><i class="bi bi-chevron-right text-muted me-1" style="font-size: 0.65rem;"></i><?= htmlspecialchars($bName) ?></span>
                                    <span class="fw-bold text-dark ms-1"><?= number_format($bStat['remaining']) ?> คัน</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Content Layout -->
        <div class="row g-3">
            <!-- ค้นหาและเลือกรถ -->
            <div class="col-lg-4 col-12">
                <div class="data-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-4 pb-2 border-bottom">
                        <i class="bi bi-funnel-fill icon-blue fs-5"></i>
                        <h6 class="fw-bold m-0 text-dark">ค้นหาและเลือกโควต้ารถมอเตอร์ไซค์</h6>
                    </div>
                    <div class="d-flex flex-column gap-3 w-100">
                        <div class="select-field-wrapper">
                            <label class="form-label fw-bold text-dark small mb-1">1. เลือกยี่ห้อ</label>
                            <select id="filterBrand" class="form-select rounded-3 w-100">
                                <option value="">-- เลือกยี่ห้อ --</option>
                                <?php foreach ($brand_summaries as $bName => $bStat): 
                                    if ($bStat['remaining'] <= 0) continue; 
                                    $remVal = number_format($bStat['remaining']);
                                ?>
                                    <option value="<?= htmlspecialchars($bName) ?>">
                                        <?= htmlspecialchars($bName) ?> (<?= $remVal ?> คัน)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="select-field-wrapper">
                            <label class="form-label fw-bold text-dark small mb-1">2. เลือกรุ่นรถมอเตอร์ไซค์</label>
                            <select id="filterModel" class="form-select rounded-3 w-100" disabled>
                                <option value="">-- เลือกรุ่น --</option>
                            </select>
                        </div>
                        <div class="select-field-wrapper">
                            <label class="form-label fw-bold text-dark small mb-1">3. เลือกสีรถ</label>
                            <select id="filterColor" class="form-select rounded-3 w-100" disabled>
                                <option value="">-- เลือกสี --</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- รายการสั่งจองล่าสุด (เรียงจากบนลงล่าง) -->
            <div class="col-lg-8 col-12">
                <div class="data-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history icon-blue fs-5"></i>
                            <h6 class="fw-bold m-0 text-dark">รายการสั่งจองล่าสุดของฉัน</h6>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-3 w-100">
                        <?php if (!empty($my_orders_display)): ?>
                            <?php foreach ($my_orders_display as $o): 
                                $st = trim($o['status'] ?? '');
                                $status_class = 'status-pending';
                                $status_text  = 'รอดำเนินการ';
                                $status_icon  = 'bi-clock-history';
                                $can_edit     = true;

                                if ($st === 'Approved' || $st === 'อนุมัติ') {
                                    $status_class = 'status-approved';
                                    $status_text  = 'อนุมัติแล้ว';
                                    $status_icon  = 'bi-check-circle';
                                    $can_edit     = false;
                                } elseif ($st === 'Cancel' || $st === 'ยกเลิก') {
                                    $status_class = 'status-cancel';
                                    $status_text  = 'ยกเลิก';
                                    $status_icon  = 'bi-x-circle';
                                    $can_edit     = false;
                                }

                                $full_car_name = trim(($o['brand'] ?? '') . ' ' . ($o['model_name'] ?? ''));
                                $color_style   = getColorStyle($o['color_name'] ?? '');
                            ?>
                            <div class="compact-order-card">
                                <div class="d-flex justify-content-between align-items-center mb-2 order-card-header">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <svg class="menu-icon-svg" viewBox="0 0 24 24">
                                            <rect x="1" y="4.5" width="6.5" height="2.4" rx="1.2"/>
                                            <rect x="13.5" y="4.5" width="5" height="2.4" rx="1.2"/>
                                            <path fill-rule="evenodd" d="M10.5 2.5 C12.2 2.5 13.5 3.8 13.5 5.5 C13.5 7.2 12.2 8.5 10.5 8.5 C8.8 8.5 7.5 7.2 7.5 5.5 C7.5 3.8 8.8 2.5 10.5 4 Z M10.5 4 C9.7 4 9 4.7 9 5.5 C9 6.3 9.7 7 10.5 7 C11.3 7 12 6.3 12 5.5 C12 4.7 11.3 4 10.5 4 Z"/>
                                            <path d="M10.5 7.5 C7 7.5 4.5 10 4.5 13.5 L4.5 17.5 C4.5 18.5 5.3 19.2 6.2 19.2 L7.2 19.2 C7.4 19.2 7.5 19 7.5 18.8 L7.5 16 C7.5 14.3 8.8 13 10.5 13 C12.2 13 13.5 14.3 13.5 16 L13.5 18.8 C13.5 19 13.6 19.2 13.8 19.2 L14.8 19.2 C15.7 19.2 16.5 18.5 16.5 17.5 L16.5 13.5 C16.5 10 14 7.5 10.5 7.5 Z"/>
                                            <rect x="8.3" y="13.5" width="4.4" height="8" rx="2.2"/>
                                            <circle cx="18" cy="6" r="4.5" fill="var(--primary-blue)"/>
                                            <path d="M18 3.8 V8.2 M15.8 6 H20.2" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                        
                                        <span class="fw-bold text-dark fs-6">
                                            <?= htmlspecialchars($full_car_name) ?>
                                        </span>

                                        <span class="status-badge <?= $status_class ?>">
                                            <i class="bi <?= $status_icon ?>"></i> <?= $status_text ?>
                                        </span>
                                    </div>

                                    <?php if ($can_edit): ?>
                                        <button type="button" 
                                                class="edit-icon-btn fs-5 btn-trigger-edit" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal"
                                                data-id="<?= $o['id'] ?? '' ?>"
                                                data-car="<?= htmlspecialchars($full_car_name) ?>"
                                                data-shop="<?= htmlspecialchars($o['shop_name'] ?? '') ?>"
                                                data-color="<?= htmlspecialchars($o['color_name'] ?? '') ?>"
                                                data-qty="<?= htmlspecialchars($o['quantity'] ?? 1) ?>"
                                                data-remark="<?= htmlspecialchars($o['note'] ?? '') ?>"
                                                title="แก้ไขการสั่งจอง">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex justify-content-between align-items-center text-secondary small flex-wrap gap-2 order-card-details">
                                    <div class="d-flex align-items-center gap-1">
                                        <i class="bi bi-shop icon-blue me-1"></i>
                                        <span><?= htmlspecialchars($o['shop_name'] ?? 'ร้านค้า') ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="d-flex align-items-center gap-1">
                                            <i class="bi bi-palette me-1 fs-6" style="<?= $color_style ?>"></i>
                                            สีรถ : <strong class="fw-bold" style="<?= $color_style ?>"><?= htmlspecialchars($o['color_name'] ?? '-') ?></strong>
                                        </span>
                                        <span class="border-start ps-3">
                                            จำนวน : <strong class="icon-blue fw-bold"><?= number_format($o['quantity'] ?? 1) ?></strong> คัน
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                ยังไม่มีรายการสั่งจองในขณะนี้
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================================== -->
<!-- 4. MODAL: สั่งจองรถใหม่                                                       -->
<!-- ============================================================================== -->
<div class="modal fade custom-sample-modal" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="modal-header-icon-box">
                        <i class="bi bi-cart-plus-fill"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark m-0" id="bookingModalLabel">
                        ยืนยันการสั่งจองรถมอเตอร์ไซค์
                    </h5>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="dashboard.php" method="POST" id="formCreateOrder">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_order">
                    <input type="hidden" name="car_id" id="book_car_id">
                    <input type="hidden" name="color_name" id="book_color_name">

                    <div class="mb-3 select-field-wrapper">
                        <label class="form-label fw-bold text-secondary small mb-1">รถมอเตอร์ไซค์และสีที่เลือก</label>
                        <div class="readonly-field-box" id="book_car_display">-</div>
                    </div>

                    <div class="mb-3 select-field-wrapper">
                        <label class="form-label fw-bold text-dark small mb-1">เลือกร้านค้า <span class="text-danger">*</span></label>
                        <select name="shop_name" id="book_shop_name" class="form-select w-100" required>
                            <option value="">-- เลือกร้านค้า --</option>
                            <?php foreach ($list_shops as $s): ?>
                                <option value="<?= htmlspecialchars($s['shop_name']) ?>">
                                    <?= htmlspecialchars($s['shop_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 select-field-wrapper">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold text-dark small mb-0">จำนวน (คัน) <span class="text-danger">*</span></label>
                            <span class="text-muted small" id="book_quota_hint"></span>
                        </div>
                        <input type="number" name="quantity" id="book_quantity" class="form-control" value="1" min="1" required>
                    </div>

                    <div class="mb-2 select-field-wrapper">
                        <label class="form-label fw-bold text-dark small mb-1">หมายเหตุ</label>
                        <textarea name="remark" id="book_remark" class="form-control" rows="2" placeholder="ระบุหมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-modal-save">
                        <i class="bi bi-check-lg fs-5"></i>
                        <span>ยืนยันการสั่งจอง</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================================== -->
<!-- 5. MODAL: แก้ไขรายการสั่งจอง                                                -->
<!-- ============================================================================== -->
<div class="modal fade custom-sample-modal" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="modal-header-icon-box">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark m-0" id="editModalLabel">
                        แก้ไขรายการสั่งจอง
                    </h5>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="dashboard.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_order">
                    <input type="hidden" name="order_id" id="modal_order_id">

                    <div class="mb-3 select-field-wrapper">
                        <label class="form-label fw-bold text-secondary small mb-1">รุ่นรถมอเตอร์ไซค์</label>
                        <div class="readonly-field-box" id="modal_car_title">-</div>
                    </div>

                    <div class="mb-3 select-field-wrapper">
                        <label class="form-label fw-bold text-dark small mb-1">ร้านค้า</label>
                        <select name="shop_name" id="modal_shop_name" class="form-select w-100" required>
                            <option value="">-- เลือกร้านค้า --</option>
                            <?php foreach ($list_shops as $s): ?>
                                <option value="<?= htmlspecialchars($s['shop_name']) ?>">
                                    <?= htmlspecialchars($s['shop_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7 col-12 select-field-wrapper">
                            <label class="form-label fw-bold text-dark small mb-1">สีรถ</label>
                            <input type="text" name="color_name" id="modal_color_name" class="form-control" required placeholder="ระบุสีรถ">
                        </div>
                        <div class="col-md-5 col-12 select-field-wrapper">
                            <label class="form-label fw-bold text-dark small mb-1">จำนวน (คัน)</label>
                            <input type="number" name="quantity" id="modal_quantity" class="form-control" min="1" required>
                        </div>
                    </div>

                    <div class="mb-2 select-field-wrapper">
                        <label class="form-label fw-bold text-dark small mb-1">หมายเหตุ</label>
                        <textarea name="remark" id="modal_remark" class="form-control" rows="2" placeholder="ระบุหมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-modal-save">
                        <i class="bi bi-box-arrow-in-down fs-5"></i>
                        <span>บันทึกการแก้ไข</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script Control JavaScript -->
<script>
const quotaData = <?= json_encode($display_quotas, JSON_UNESCAPED_UNICODE); ?>;

function getFullOptionText(nameText, qtyVal) {
    if (!qtyVal && qtyVal !== 0 && qtyVal !== '0') return nameText;
    return `${nameText} (${qtyVal} คัน)`;
}

document.addEventListener('DOMContentLoaded', function () {
    const brandSelect = document.getElementById('filterBrand');
    const modelSelect = document.getElementById('filterModel');
    const colorSelect = document.getElementById('filterColor');

    // 1. ดักจับการเลือกยี่ห้อ
    brandSelect.addEventListener('change', function () {
        const selectedBrand = this.value;
        modelSelect.innerHTML = '<option value="">-- เลือกรุ่น --</option>';
        colorSelect.innerHTML = '<option value="">-- เลือกสี --</option>';
        colorSelect.disabled  = true;

        if (!selectedBrand) {
            modelSelect.disabled = true;
            return;
        }

        const modelMap = {};
        quotaData.forEach(item => {
            if ((item.brand || '').trim() === selectedBrand) {
                const model = (item.model_name || '').trim();
                if (model) {
                    const maxQuota = parseInt(item.max_limit || 0, 10);
                    const booked   = parseInt(item.booked_total || 0, 10);
                    modelMap[model] = (modelMap[model] || 0) + Math.max(0, maxQuota - booked);
                }
            }
        });

        const models = Object.keys(modelMap).filter(m => modelMap[m] > 0);
        if (models.length > 0) {
            models.forEach(model => {
                const opt = document.createElement('option');
                opt.value = model;
                opt.textContent = getFullOptionText(model, modelMap[model].toLocaleString());
                modelSelect.appendChild(opt);
            });
            modelSelect.disabled = false;
        } else {
            modelSelect.disabled = true;
        }
    });

    // 2. ดักจับการเลือกรุ่นรถ
    modelSelect.addEventListener('change', function () {
        const selectedBrand = brandSelect.value;
        const selectedModel = this.value;

        colorSelect.innerHTML = '<option value="">-- เลือกสี --</option>';

        if (!selectedModel) {
            colorSelect.disabled = true;
            return;
        }

        const colorMap = {};
        quotaData.forEach(item => {
            if ((item.brand || '').trim() === selectedBrand && (item.model_name || '').trim() === selectedModel) {
                const color = (item.color_name || '').trim();
                if (color) {
                    const maxQuota = parseInt(item.max_limit || 0, 10);
                    const booked   = parseInt(item.booked_total || 0, 10);
                    colorMap[color] = (colorMap[color] || 0) + Math.max(0, maxQuota - booked);
                }
            }
        });

        const colors = Object.keys(colorMap).filter(c => colorMap[c] > 0);
        if (colors.length > 0) {
            colors.forEach(color => {
                const opt = document.createElement('option');
                opt.value = color;
                opt.textContent = getFullOptionText(color, colorMap[color].toLocaleString());
                colorSelect.appendChild(opt);
            });
            colorSelect.disabled = false;
        } else {
            colorSelect.disabled = true;
        }
    });

    // 3. เปิด Modal สั่งจองอัตโนมัติเมื่อเลือกครบ 3 ช่อง พร้อมตั้งค่าจำกัดโควตา (Max Quota Validation)
    colorSelect.addEventListener('change', function () {
        const selectedBrand = brandSelect.value;
        const selectedModel = modelSelect.value;
        const selectedColor = this.value;

        if (selectedBrand && selectedModel && selectedColor) {
            const matchedQuota = quotaData.find(item => 
                (item.brand || '').trim() === selectedBrand &&
                (item.model_name || '').trim() === selectedModel &&
                (item.color_name || '').trim() === selectedColor
            );

            const carId     = matchedQuota ? matchedQuota.car_id : 0;
            const maxLimit  = matchedQuota ? parseInt(matchedQuota.max_limit || 0, 10) : 0;
            const booked    = matchedQuota ? parseInt(matchedQuota.booked_total || 0, 10) : 0;
            const remaining = Math.max(0, maxLimit - booked);

            document.getElementById('book_car_id').value = carId;
            document.getElementById('book_color_name').value = selectedColor;
            document.getElementById('book_car_display').innerText = `${selectedBrand} ${selectedModel} (สี ${selectedColor})`;
            document.getElementById('book_shop_name').value = '';
            
            const qtyInput = document.getElementById('book_quantity');
            qtyInput.value = remaining > 0 ? 1 : 0;
            qtyInput.max   = remaining;
            
            const hintElem = document.getElementById('book_quota_hint');
            if (hintElem) {
                hintElem.innerText = `(โควตาคงเหลือ ${remaining.toLocaleString()} คัน)`;
            }

            document.getElementById('book_remark').value = '';

            const bookingModalElement = document.getElementById('bookingModal');
            const bookingModal = bootstrap.Modal.getOrCreateInstance(bookingModalElement);
            bookingModal.show();
        }
    });

    // 4. ส่งข้อมูลไปยัง Modal แก้ไข
    document.querySelectorAll('.btn-trigger-edit').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modal_order_id').value   = this.dataset.id || '';
            document.getElementById('modal_car_title').innerText = this.dataset.car || '-';
            document.getElementById('modal_shop_name').value  = this.dataset.shop || '';
            document.getElementById('modal_color_name').value = this.dataset.color || '';
            document.getElementById('modal_quantity').value   = this.dataset.qty || 1;
            document.getElementById('modal_remark').value     = this.dataset.remark || '';
        });
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>