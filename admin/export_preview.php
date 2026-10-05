<?php
/**
 * ==============================================================================
 * File: admin/export_preview.php
 * Description: หน้าแสดงตัวอย่างข้อมูลก่อน Export Excel (ใช้ขนาดปุ่มและ CSS เดียวกับ orders.php)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_dir = dirname(__DIR__);

// Load Configurations & Authentication
$config_files = [
    '/config/auth.php',
    '/config/database.php',
    '/config/config.php'
];

foreach ($config_files as $file) {
    if (file_exists($base_dir . $file)) {
        require_once $base_dir . $file;
    }
}

if (function_exists('checkAdmin')) {
    checkAdmin();
}

// -----------------------------------------------------------------------------
// 1. Data Initialization & Processing
// -----------------------------------------------------------------------------
$orders = $_SESSION['report_orders'] ?? [];

// -----------------------------------------------------------------------------
// 2. Helper Functions
// -----------------------------------------------------------------------------
function getBrandName(array $order): string 
{
    if (!empty($order['brand_name'])) {
        return $order['brand_name'];
    }
    if (!empty($order['brand'])) {
        return $order['brand'];
    }
    
    $model = strtoupper(trim($order['model_name'] ?? ''));
    
    if (preg_match('/^(X300|G350|V200|X200|LAMBRETTA)/i', $model)) {
        return 'Lambretta';
    }
    if (preg_match('/^(368G|368K|350E|350D|350M|350X|ZONTES)/i', $model)) {
        return 'Zontes';
    }
    
    return '-';
}

// Include Page Layout Headers
if (file_exists($base_dir . '/includes/header.php')) include_once $base_dir . '/includes/header.php';
if (file_exists($base_dir . '/includes/navbar.php')) include_once $base_dir . '/includes/navbar.php';
?>

<!-- Google Fonts & Bootstrap Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    * { 
        font-family: 'Kanit', sans-serif !important; 
    }
    body { 
        background-color: #f8fafc; 
        color: #1e293b; 
        overflow-x: hidden; 
    }

    /* Layout & Sidebar Offset Styles */
    #mainContentArea,
    .main-content,
    body:not(.sidebar-collapsed):not(.collapsed) #mainContentArea,
    body:not(.sidebar-collapsed):not(.collapsed) .main-content {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        min-height: 100vh;
        padding: 28px !important;
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
        }
    }

    /* Excel Grid Table */
    .excel-grid-table {
        border-collapse: collapse !important;
        width: 100%;
        font-size: 0.88rem;
        border: 1px solid #d1d5db !important;
        background-color: #ffffff;
    }
    .excel-grid-table th, 
    .excel-grid-table td {
        border: 1px solid #d1d5db !important;
        padding: 8px 12px;
        vertical-align: middle;
    }
    .excel-grid-table thead th {
        background-color: #ffffff !important;
        color: #1e293b;
        font-weight: 600;
        text-align: center;
    }

    /* Standardized Buttons (Copied directly from admin/orders.php) */
    .btn-excel {
        background-color: #10b981;
        color: #ffffff !important;
        border: none;
        border-radius: 10px;
        padding: 9px 18px;
        font-weight: 500;
        font-size: 0.88rem;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease-in-out;
    }
    .btn-excel:hover {
        background-color: #059669;
        color: #ffffff !important;
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
    }

    .btn-back {
        background-color: #ffffff;
        color: #475569 !important;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 9px 18px;
        font-weight: 500;
        font-size: 0.88rem;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease-in-out;
    }
    .btn-back:hover {
        background-color: #f8fafc;
        color: #1e293b !important;
        border-color: #94a3b8;
    }
</style>

<?php if (file_exists($base_dir . '/includes/sidebar.php')) include_once $base_dir . '/includes/sidebar.php'; ?>

<div id="mainContentArea" class="main-content">
    
    <!-- Top Action Toolbar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="p-3 bg-success bg-opacity-10 text-success rounded-3">
                <i class="bi bi-file-earmark-excel-fill fs-2"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark">ตัวอย่างข้อมูลก่อน Excel</h4>
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>ตรวจสอบข้อมูลทั้งหมดก่อนดาวน์โหลดไฟล์ .xlsx</small>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-flex align-items-center gap-2">
            <a href="orders.php" class="btn-back" title="ย้อนกลับ">
                <i class="bi bi-arrow-left fs-5"></i>
                <span>ย้อนกลับ</span>
            </a>
            <?php if (!empty($orders)): ?>
                <a href="export_excel.php" class="btn-excel" title="ส่งออก Excel">
                    <i class="bi bi-file-earmark-excel-fill fs-5"></i>
                    <span>ดาว์นโหลด Excel</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($orders)): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fs-7 text-muted bg-white px-3 py-2 rounded-3 border">
                <i class="bi bi-box-seam me-1 text-primary"></i>รายการทั้งหมด: <strong class="text-dark"><?= number_format(count($orders)); ?></strong> รายการ
            </div>
        </div>

        <!-- Overview Table -->
        <div class="bg-white rounded-3 shadow-sm p-3 mb-4 border">
            <div class="table-responsive">
                <table class="excel-grid-table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Code</th>
                            <th style="width: 140px;">ยี่ห้อรถ</th>
                            <th style="width: 100px;">ภาค</th>
                            <th style="width: 500px;">ชื่อร้านค้า / ตัวแทนจำหน่าย</th>
                            <th style="width: 180px;">สีรถ</th>
                            <th style="width: 100px;">จำนวน</th>
                            <th style="width: 100px;">อนุมัติแล้ว</th>
                            <th style="width: 100px;">แบ็คออเดอร์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            $qty         = (int)($order['quantity'] ?? 0);
                            $approved    = (int)($order['approved_quantity'] ?? 0);
                            $backorder   = (int)($order['backorder_quantity'] ?? 0);

                            $code        = $order['code'] ?? $order['car_code'] ?? $order['order_code'] ?? '';
                            $brand       = getBrandName($order);
                            $region_name = $order['region_name'] ?? '-';
                            $shop_name   = $order['shop_name'] ?? '-';
                            $color_name  = $order['color_name'] ?? '-';
                        ?>
                            <tr>
                                <td class="text-start"><?= htmlspecialchars($code); ?></td>
                                <td class="text-start"><?= htmlspecialchars($brand); ?></td>
                                <td class="text-start"><?= htmlspecialchars($region_name); ?></td>
                                <td class="text-start"><?= htmlspecialchars($shop_name); ?></td>
                                <td class="text-start"><?= htmlspecialchars($color_name); ?></td>
                                <td class="text-end"><?= number_format($qty); ?> คัน</td>
                                <td class="text-end"><?= number_format($approved); ?> คัน</td>
                                <td class="text-end"><?= number_format($backorder); ?> คัน</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-3 p-5 text-center text-muted border">
            <i class="bi bi-inbox fs-1 d-block opacity-25 mb-2"></i>
            ไม่พบข้อมูลคำสั่งซื้อที่จะส่งออก
        </div>
    <?php endif; ?>
</div>

<?php if (file_exists($base_dir . '/includes/footer.php')) include_once $base_dir . '/includes/footer.php'; ?>