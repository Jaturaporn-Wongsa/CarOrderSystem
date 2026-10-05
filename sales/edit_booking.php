<?php
/**
 * ==============================================================================
 * File: sales/edit_booking.php - หน้าแก้ไขรายการสั่งจอง (โทนสีฟ้า)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

checkSales();

$sales_id         = $_SESSION['user_id'] ?? 0;
$order_id         = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$error_msg        = "";
$order_data       = null;
$list_shops       = [];
$available_colors = [];

if ($order_id <= 0) {
    header("Location: dashboard.php");
    exit;
}

try {
    // 1. ดึงข้อมูลรายการสั่งจองเดิมของผู้ใช้งาน
    $stmt = $conn->prepare("
        SELECT o.*, c.brand, c.model_name 
        FROM orders o 
        LEFT JOIN cars c ON o.car_id = c.id 
        WHERE o.id = ? AND o.sales_id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id, $sales_id]);
    $order_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order_data) {
        header("Location: dashboard.php");
        exit;
    }

    // ตรวจสอบสถานะ: อนุญาตให้แก้ไขเฉพาะรายการที่ 'รอดำเนินการ'
    $status = trim($order_data['status'] ?? '');
    if ($status === 'Approved' || $status === 'อนุมัติ' || $status === 'Cancel' || $status === 'ยกเลิก') {
        $_SESSION['flash_error'] = "ไม่สามารถแก้ไขรายการที่ได้รับการอนุมัติหรือยกเลิกแล้วได้";
        header("Location: dashboard.php");
        exit;
    }

    // 2. ดึงข้อมูลภาค และ รายชื่อร้านค้าในเขตความรับผิดชอบ
    $current_month = date('Y-m');
    $stmt_region = $conn->prepare("SELECT region FROM region_sales_assignment WHERE user_id = ? AND assigned_month = ? LIMIT 1");
    $stmt_region->execute([$sales_id, $current_month]);
    $res_region = $stmt_region->fetch(PDO::FETCH_ASSOC);
    $user_region = $res_region['region'] ?? '';

    if (empty($user_region)) {
        $stmt_fb = $conn->prepare("SELECT region FROM region_sales_assignment WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_fb->execute([$sales_id]);
        $res_fb = $stmt_fb->fetch(PDO::FETCH_ASSOC);
        $user_region = $res_fb['region'] ?? '';
    }

    // รายการร้านค้า
    $stmt_shops = $conn->prepare("SELECT shop_name FROM shops WHERE region COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci ORDER BY shop_name ASC");
    $stmt_shops->execute([$user_region]);
    $list_shops = $stmt_shops->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // รายการสีรถที่มีให้เลือกสำหรับรุ่นนี้
    $stmt_colors = $conn->prepare("SELECT DISTINCT color_name FROM car_quotas WHERE car_id = ? AND region COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci");
    $stmt_colors->execute([$order_data['car_id'], $user_region]);
    $available_colors = $stmt_colors->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // หากสีเดิมไม่อยู่ในเงื่อนไขการค้นหา ให้แสดงสีเดิมไว้ป้องกันข้อมูลหลุด
    if (!empty($order_data['color_name']) && !in_array($order_data['color_name'], $available_colors)) {
        $available_colors[] = $order_data['color_name'];
    }

    // 3. บันทึกข้อมูลเมื่อส่งฟอร์ม (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_shop_name  = trim($_POST['shop_name'] ?? '');
        $new_color_name = trim($_POST['color_name'] ?? '');
        $new_quantity   = intval($_POST['quantity'] ?? 1);

        if (empty($new_shop_name)) {
            $error_msg = "กรุณาเลือกร้านค้า";
        } elseif (empty($new_color_name)) {
            $error_msg = "กรุณาเลือกสีรถ";
        } elseif ($new_quantity <= 0) {
            $error_msg = "จำนวนต้องมากกว่า 0 คัน";
        } else {
            $stmt_update = $conn->prepare("
                UPDATE orders 
                SET shop_name = ?, color_name = ?, quantity = ?, updated_at = NOW() 
                WHERE id = ? AND sales_id = ?
            ");
            $stmt_update->execute([$new_shop_name, $new_color_name, $new_quantity, $order_id, $sales_id]);

            $_SESSION['flash_success'] = "แก้ไขรายการสั่งจองเรียบร้อยแล้ว";
            header("Location: dashboard.php");
            exit;
        }
    }

} catch (PDOException $e) {
    $error_msg = "เกิดข้อผิดพลาดในระบบ: " . $e->getMessage();
}

include_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --primary-blue: #0284c7;       /* สีฟ้าหลัก Sky Blue */
        --primary-blue-hover: #0369a1; /* สีฟ้าเข้มเมื่อ Hover */
        --bg-blue-light: #f0f9ff;     /* พื้นหลังสีฟ้าอ่อน */
    }

    body { 
        font-family: 'Kanit', sans-serif !important; 
        background-color: #f8fafc; 
    }

    #mainContentArea { 
        margin-left: 260px !important; 
        width: calc(100% - 260px) !important; 
        padding: 24px 28px !important; 
    }

    @media (max-width: 991.98px) { 
        #mainContentArea { 
            margin-left: 0 !important; 
            width: 100% !important; 
            padding: 16px !important; 
        } 
    }

    .form-card {
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        max-width: 720px;
        margin: 0 auto;
    }

    .menu-icon-svg {
        width: 26px;
        height: 26px;
        fill: #475467;
        flex-shrink: 0;
    }

    .icon-blue {
        color: var(--primary-blue) !important;
    }

    .btn-sky-blue {
        background-color: var(--primary-blue);
        color: #ffffff;
        border: none;
        border-radius: 10px;
        padding: 10px 24px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .btn-sky-blue:hover {
        background-color: var(--primary-blue-hover);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
    }

    .btn-outline-custom {
        border: 1px solid #cbd5e1;
        color: #475467;
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 500;
        background-color: #ffffff;
        transition: all 0.2s ease;
    }

    .btn-outline-custom:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    .input-icon-group {
        position: relative;
    }

    .input-icon-group .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.1rem;
        z-index: 5;
    }

    .input-icon-group .form-select,
    .input-icon-group .form-control {
        padding-left: 44px;
        border-radius: 10px;
        border-color: #cbd5e1;
    }

    .input-icon-group .form-select:focus,
    .input-icon-group .form-control:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 0.25rem rgba(2, 132, 199, 0.15);
    }

    .car-info-box {
        background-color: var(--bg-blue-light);
        border: 1px solid #bae6fd;
        border-radius: 12px;
        padding: 16px;
    }
</style>

<div class="position-relative">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div id="mainContentArea">
        <!-- Head Bar -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                <a href="dashboard.php" class="btn btn-outline-custom btn-sm">
                    <i class="bi bi-arrow-left"></i> ย้อนกลับ
                </a>
                <h4 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square icon-blue fs-3"></i>
                    <span>แก้ไขรายการสั่งจอง</span>
                </h4>
            </div>
        </div>

        <!-- กล่องฟอร์มแก้ไข -->
        <div class="form-card p-4 p-md-5">
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger rounded-3 mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <form action="edit_booking.php" method="POST">
                <input type="hidden" name="id" value="<?= htmlspecialchars($order_data['id'] ?? '') ?>">

                <!-- ข้อมูลรถมอเตอร์ไซค์ ( Readonly Display ) -->
                <div class="car-info-box mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <svg class="menu-icon-svg" viewBox="0 0 24 24">
                            <rect x="1" y="4.5" width="6.5" height="2.4" rx="1.2"/>
                            <rect x="13.5" y="4.5" width="5" height="2.4" rx="1.2"/>
                            <path fill-rule="evenodd" d="M10.5 2.5 C12.2 2.5 13.5 3.8 13.5 5.5 C13.5 7.2 12.2 8.5 10.5 8.5 C8.8 8.5 7.5 7.2 7.5 5.5 C7.5 3.8 8.8 2.5 10.5 2.5 Z M10.5 4 C9.7 4 9 4.7 9 5.5 C9 6.3 9.7 7 10.5 7 C11.3 7 12 6.3 12 5.5 C12 4.7 11.3 4 10.5 4 Z"/>
                            <path d="M10.5 7.5 C7 7.5 4.5 10 4.5 13.5 L4.5 17.5 C4.5 18.5 5.3 19.2 6.2 19.2 L7.2 19.2 C7.4 19.2 7.5 19 7.5 18.8 L7.5 16 C7.5 14.3 8.8 13 10.5 13 C12.2 13 13.5 14.3 13.5 16 L13.5 18.8 C13.5 19 13.6 19.2 13.8 19.2 L14.8 19.2 C15.7 19.2 16.5 18.5 16.5 17.5 L16.5 13.5 C16.5 10 14 7.5 10.5 7.5 Z"/>
                            <rect x="8.3" y="13.5" width="4.4" height="8" rx="2.2"/>
                            <circle cx="18" cy="6" r="4.5" fill="var(--primary-blue)"/>
                            <path d="M18 3.8 V8.2 M15.8 6 H20.2" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <div>
                            <div class="text-secondary small">รุ่นรถมอเตอร์ไซค์ที่เลือกสั่งจอง</div>
                            <div class="fs-5 fw-bold text-dark">
                                <?= htmlspecialchars(($order_data['brand'] ?? '') . ' ' . ($order_data['model_name'] ?? '')) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 1. เลือกร้านค้า (พร้อมไอคอนร้านค้าสีฟ้า) -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">1. เลือกร้านค้า <span class="text-danger">*</span></label>
                    <div class="input-icon-group">
                        <i class="bi bi-shop input-icon icon-blue"></i>
                        <select name="shop_name" class="form-select" required>
                            <option value="">-- เลือกร้านค้า --</option>
                            <?php foreach ($list_shops as $s): 
                                $selected = ($s['shop_name'] === ($order_data['shop_name'] ?? '')) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($s['shop_name']) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($s['shop_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 2. เลือกสีรถ (พร้อมไอคอนสีรถสีฟ้า) -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">2. เลือกสีรถ <span class="text-danger">*</span></label>
                    <div class="input-icon-group">
                        <i class="bi bi-palette input-icon icon-blue"></i>
                        <select name="color_name" class="form-select" required>
                            <option value="">-- เลือกสีรถ --</option>
                            <?php foreach ($available_colors as $color): 
                                $selected = ($color === ($order_data['color_name'] ?? '')) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($color) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($color) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 3. ระบุจำนวน -->
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">3. จำนวน (คัน) <span class="text-danger">*</span></label>
                    <div class="input-icon-group">
                        <i class="bi bi-box-seam input-icon icon-blue"></i>
                        <input type="number" name="quantity" class="form-control" min="1" value="<?= htmlspecialchars($order_data['quantity'] ?? 1) ?>" required>
                    </div>
                </div>

                <!-- ปุ่มการทำงาน -->
                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="dashboard.php" class="btn btn-outline-custom">
                        ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-sky-blue d-inline-flex align-items-center gap-2">
                        <i class="bi bi-check-lg fs-5"></i>
                        <span>บันทึกการแก้ไข</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>