<?php
/**
 * ==============================================================================
 * File: sales/order_detail.php - รายละเอียดใบสั่งจองรถมอเตอร์ไซค์ของฉัน
 * Description: แสดงรายละเอียดข้อมูลการสั่งจองรถมอเตอร์ไซค์และสถานะการพิจารณา (ฉบับสมบูรณ์)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

checkSales();

// ------------------------------------------------------------------------------
// Helper Function: แปลงชื่อสีเป็นรหัสสี CSS (Hex Color) สำหรับไอคอนและชื่อสี
// ------------------------------------------------------------------------------
function getDynamicColorHex($colorName) {
    if (empty($colorName)) return '#475569';
    
    $color = mb_strtolower(trim($colorName));
    
    $colorMap = [
        'espresso' => '#542a0c',
        'latte'    => '#16a34a',
        'red'      => '#dc2626', 'แดง'      => '#dc2626',
        'green'    => '#16a34a', 'เขียว'    => '#16a34a',
        'blue'     => '#2563eb', 'น้ำเงิน'  => '#2563eb', 'ฟ้า' => '#0284c7',
        'yellow'   => '#d97706', 'เหลือง'   => '#d97706',
        'orange'   => '#ea580c', 'ส้ม'      => '#ea580c',
        'black'    => '#1e293b', 'ดำ'       => '#1e293b',
        'white'    => '#64748b', 'ขาว'      => '#64748b',
        'grey'     => '#64748b', 'gray'     => '#64748b', 'เทา' => '#64748b', 'scuro' => '#64748b',
        'silver'   => '#94a3b8', 'เงิน'     => '#94a3b8',
        'gold'     => '#b45309', 'ทอง'      => '#b45309',
        'pink'     => '#db2777', 'ชมพู'     => '#db2777',
        'purple'   => '#7e22ce', 'ม่วง'     => '#7e22ce',
        'brown'    => '#542a0c', 'น้ำตาล'   => '#542a0c',
    ];

    foreach ($colorMap as $keyword => $hex) {
        if (mb_strpos($color, $keyword) !== false) {
            return $hex;
        }
    }

    return '#475569';
}

// ------------------------------------------------------------------------------
// 1. ตรวจสอบข้อมูลและสิทธิ์การเข้าถึง
// ------------------------------------------------------------------------------
$sales_id   = $_SESSION['user_id'] ?? 0;
$order_id   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$display_no = filter_input(INPUT_GET, 'no', FILTER_VALIDATE_INT) ?: 1;

if (!$order_id) {
    $_SESSION['error'] = "ไม่พบรหัสใบสั่งจองที่ต้องการ";
    header("Location: my_orders.php");
    exit();
}

// ------------------------------------------------------------------------------
// 2. ดึงข้อมูลใบสั่งจองจากฐานข้อมูล
// ------------------------------------------------------------------------------
try {
    // ดึงเขตพื้นที่พนักงานขาย
    $stmt_user = $conn->prepare("SELECT region FROM users WHERE id = ?");
    $stmt_user->execute([$sales_id]);
    $sales_region = trim($stmt_user->fetchColumn() ?: '');

    // ดึงรายละเอียดคำขอสั่งจอง
    $sql = "SELECT o.id, o.sales_id, o.car_id, o.shop_name, o.color_name, o.note, o.admin_note, 
                   o.region, o.status, o.quantity, o.approved_quantity, o.created_at, 
                   c.model_name, c.brand 
            FROM orders o 
            LEFT JOIN cars c ON o.car_id = c.id  
            WHERE o.id = :order_id AND o.sales_id = :sales_id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':order_id' => $order_id,
        ':sales_id' => $sales_id
    ]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "ไม่พบข้อมูลใบสั่งจอง หรือคุณไม่มีสิทธิ์เข้าถึงรายการนี้";
        header("Location: my_orders.php");
        exit();
    }

} catch (PDOException $e) {
    error_log("Order Detail Error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการดึงข้อมูล กรุณาลองใหม่อีกครั้ง");
}

// ------------------------------------------------------------------------------
// 3. จัดเตรียมตัวแปรและ Mapping สถานะสำหรับแสดงผล UI
// ------------------------------------------------------------------------------
$full_car_name  = trim(($order['brand'] ?? '') . ' ' . ($order['model_name'] ?? 'ไม่ระบุ'));
$sales_note     = trim($order['note'] ?? '');
$admin_note     = trim($order['admin_note'] ?? '');
$status         = trim($order['status'] ?? 'รอดำเนินการ');
$color_name     = trim($order['color_name'] ?? 'ไม่ระบุ');
$color_hex_code = getDynamicColorHex($color_name);

$status_map = [
    'รอดำเนินการ' => [
        'card_class' => 'status-big-pending', 
        'box_class'  => 'icon-box-yellow', 
        'icon'       => 'bi-clock-history', 
        'title'      => 'รอดำเนินการ', 
        'desc'       => 'คำขอของคุณอยู่ระหว่างการตรวจสอบข้อมูล'
    ],
    'Pending'     => [
        'card_class' => 'status-big-pending', 
        'box_class'  => 'icon-box-yellow', 
        'icon'       => 'bi-clock-history', 
        'title'      => 'รอดำเนินการ', 
        'desc'       => 'คำขอของคุณอยู่ระหว่างการตรวจสอบข้อมูล'
    ],
    'อนุมัติ'     => [
        'card_class' => 'status-big-approved', 
        'box_class'  => 'icon-box-green', 
        'icon'       => 'bi-check-circle-fill', 
        'title'      => 'อนุมัติแล้ว', 
        'desc'       => 'รายการสั่งจองได้รับการอนุมัติเรียบร้อย'
    ],
    'Approved'    => [
        'card_class' => 'status-big-approved', 
        'box_class'  => 'icon-box-green', 
        'icon'       => 'bi-check-circle-fill', 
        'title'      => 'อนุมัติแล้ว', 
        'desc'       => 'รายการสั่งจองได้รับการอนุมัติเรียบร้อย'
    ],
    'ปฏิเสธ'     => [
        'card_class' => 'status-big-rejected', 
        'box_class'  => 'icon-box-red', 
        'icon'       => 'bi-x-circle-fill', 
        'title'      => 'ถูกปฏิเสธ', 
        'desc'       => 'คำขอสั่งจองนี้ไม่ผ่านการอนุมัติ'
    ],
    'Rejected'    => [
        'card_class' => 'status-big-rejected', 
        'box_class'  => 'icon-box-red', 
        'icon'       => 'bi-x-circle-fill', 
        'title'      => 'ถูกปฏิเสธ', 
        'desc'       => 'คำขอสั่งจองนี้ไม่ผ่านการอนุมัติ'
    ],
    'ยกเลิก'      => [
        'card_class' => 'status-big-canceled', 
        'box_class'  => 'icon-box-gray', 
        'icon'       => 'bi-slash-circle-fill', 
        'title'      => 'ยกเลิกรายการ', 
        'desc'       => 'รายการสั่งจองถูกยกเลิกแล้ว'
    ]
];

$current_status = $status_map[$status] ?? [
    'card_class' => 'status-big-canceled',
    'box_class'  => 'icon-box-gray',
    'icon'       => 'bi-question-circle-fill',
    'title'      => htmlspecialchars($status),
    'desc'       => 'สถานะรายการสั่งจองในระบบ'
];

include_once __DIR__ . '/../includes/header.php';
?>

<!-- External Styles & Fonts -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
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

    /* Sidebar Collapsed State */
    body.sidebar-collapsed #mainContentArea,
    body.collapsed-sidebar #mainContentArea,
    .sidebar-collapsed #mainContentArea,
    .sidebar.collapsed ~ #mainContentArea,
    #sidebar.collapsed ~ #mainContentArea { 
        margin-left: var(--sidebar-collapsed-width) !important; 
        width: calc(100% - var(--sidebar-collapsed-width)) !important; 
    }

    /* Mobile Responsive Shift (Under 768px only) */
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

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in {
        animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* --- Cards & UI Elements --- */
    .data-card { 
        border-radius: 18px; 
        border: 1px solid var(--border-color); 
        background: #ffffff; 
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03); 
    }

    .icon-box { 
        width: 44px; 
        height: 44px; 
        border-radius: 12px; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        flex-shrink: 0;
    }
    
    .icon-box-blue   { background-color: var(--bg-blue-light); color: var(--primary-blue); }
    .icon-box-green  { background-color: #e6f4ea; color: #16a34a; }
    .icon-box-yellow { background-color: #fef9c3; color: #d97706; }
    .icon-box-red    { background-color: #fee2e2; color: #dc2626; }
    .icon-box-gray   { background-color: #f1f5f9; color: #64748b; }

    .icon-blue { color: var(--primary-blue) !important; }

    .order-id-badge {
        font-family: monospace;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--primary-blue);
        background-color: var(--bg-blue-light);
        padding: 4px 12px;
        border-radius: 8px;
        display: inline-block;
    }

    .detail-item {
        padding: 14px 0;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-label {
        font-weight: 500;
        color: #64748b;
        font-size: 0.9rem;
    }

    .detail-value {
        font-weight: 600;
        color: #0f172a;
        font-size: 0.95rem;
    }

    .note-box {
        background-color: #f8fafc;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 14px 16px;
        font-size: 0.9rem;
        color: #334155;
    }

    .status-card-big {
        border-radius: 16px;
        padding: 24px 20px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .status-big-pending {
        background-color: #fffbeb;
        border: 1px solid #fef08a;
        color: #b45309;
    }

    .status-big-approved {
        background-color: #f0fdf4;
        border: 1px solid #b7e4c7;
        color: #15803d;
    }

    .status-big-rejected {
        background-color: #fef2f2;
        border: 1px solid #fca5a5;
        color: #b91c1c;
    }

    .status-big-canceled {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #475569;
    }

    .btn-back-custom {
        background-color: #ffffff;
        color: #475569;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 8px 18px;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-back-custom:hover {
        background-color: #f1f5f9;
        color: #0f172a;
        border-color: #cbd5e1;
    }
</style>

<div class="position-relative">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div id="mainContentArea" class="animate-fade-in">
        
        <!-- Header Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h4 class="fw-bold mb-0 text-dark">รายละเอียดใบสั่งจอง</h4>
                    <span class="order-id-badge">#<?= str_pad($display_no, 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                <p class="text-muted mb-0 small">
                    ตรวจสอบข้อมูลการสั่งจองรถมอเตอร์ไซค์และสถานะการพิจารณา
                </p>
            </div>
            <div>
                <a href="my_orders.php" class="btn-back-custom">
                    <i class="bi bi-arrow-left fs-6"></i>
                    <span>ย้อนกลับ</span>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- คอลัมน์ซ้าย: รายละเอียดการจอง -->
            <div class="col-xl-8 col-lg-7 col-12">
                <div class="data-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
                        <div class="icon-box icon-box-blue">
                            <i class="bi bi-file-earmark-text-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold m-0 text-dark">ข้อมูลการสั่งจองรถมอเตอร์ไซค์</h6>
                            <span class="text-muted small">รายละเอียดรุ่นรถ จำนวน และข้อมูลร้านค้า</span>
                        </div>
                    </div>

                    <div class="row detail-item">
                        <div class="col-sm-4 detail-label">รุ่นรถมอเตอร์ไซค์ / สี</div>
                        <div class="col-sm-8 detail-value text-primary">
                            <?= htmlspecialchars($full_car_name); ?> 
                            <!-- แสดงผลไอคอนและข้อความชื่อสีแบบ Inline โดยปรับตามสีจริงตามตัวอย่าง -->
                            <span class="ms-2 fw-semibold d-inline-flex align-items-center gap-1" style="color: <?= $color_hex_code; ?>;">
                                <i class="bi bi-palette fs-6"></i><?= htmlspecialchars($color_name); ?>
                            </span>
                        </div>
                    </div>

                    <div class="row detail-item">
                        <div class="col-sm-4 detail-label">จำนวนที่สั่งจอง</div>
                        <div class="col-sm-8 detail-value">
                            <?= number_format($order['quantity'] ?? 1); ?> <span class="text-muted fw-normal fs-6">คัน</span>
                        </div>
                    </div>

                    <div class="row detail-item">
                        <div class="col-sm-4 detail-label">จำนวนที่ได้รับการอนุมัติ</div>
                        <div class="col-sm-8 detail-value">
                            <?php if ($status === 'อนุมัติ' || $status === 'Approved'): ?>
                                <span class="text-success">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    <?= number_format($order['approved_quantity'] ?? $order['quantity']); ?> คัน
                                </span>
                            <?php else: ?>
                                <span class="text-muted fw-normal">-</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row detail-item">
                        <div class="col-sm-4 detail-label">ร้านค้าผู้สั่งจอง</div>
                        <div class="col-sm-8 detail-value">
                            <i class="bi bi-shop text-secondary me-1"></i><?= htmlspecialchars($order['shop_name'] ?? '-'); ?>
                        </div>
                    </div>

                    <div class="row detail-item">
                        <div class="col-sm-4 detail-label">วันที่และเวลาที่ทำรายการ</div>
                        <div class="col-sm-8 detail-value text-secondary fw-normal">
                            <i class="bi bi-calendar3 me-1"></i>
                            <?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) . ' น.' : '-'; ?>
                        </div>
                    </div>

                    <!-- หมายเหตุจากพนักงานขาย -->
                    <?php if (!empty($sales_note)): ?>
                    <div class="mt-3">
                        <div class="detail-label mb-2">
                            <i class="bi bi-chat-left-text me-1"></i>หมายเหตุการสั่งจอง (จากพนักงาน)
                        </div>
                        <div class="note-box">
                            <?= nl2br(htmlspecialchars($sales_note)); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- หมายเหตุจาก Admin -->
                    <?php if (!empty($admin_note)): ?>
                    <div class="mt-3">
                        <div class="detail-label mb-2 text-warning">
                            <i class="bi bi-info-circle me-1"></i>หมายเหตุการพิจารณา (จากผู้อนุมัติ / Admin)
                        </div>
                        <div class="note-box border-warning bg-warning bg-opacity-10">
                            <?= nl2br(htmlspecialchars($admin_note)); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- คอลัมน์ขวา: สถานะการพิจารณา -->
            <div class="col-xl-4 col-lg-5 col-12">
                <div class="data-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
                            <div class="icon-box icon-box-blue">
                                <i class="bi bi-shield-check fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold m-0 text-dark">สถานะการพิจารณา</h6>
                                <span class="text-muted small">ความคืบหน้าคำขอจอง</span>
                            </div>
                        </div>

                        <!-- แสดงผลการ์ดสถานะ -->
                        <div class="status-card-big <?= $current_status['card_class']; ?> my-3">
                            <div class="icon-box <?= $current_status['box_class']; ?> mb-3" style="width: 56px; height: 56px;">
                                <i class="bi <?= $current_status['icon']; ?> fs-2"></i>
                            </div>
                            <h5 class="fw-bold mb-1"><?= $current_status['title']; ?></h5>
                            <p class="small text-muted mb-0"><?= $current_status['desc']; ?></p>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border text-center small text-muted">
                        <i class="bi bi-info-circle me-1 icon-blue"></i>
                        หากพบข้อมูลผิดพลาด กรุณาติดต่อผู้ดูแลระบบ
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>