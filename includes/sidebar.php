<?php
/**
 * ==============================================================================
 * File: includes/sidebar.php
 * Description: Sidebar Navigation + Profile Modal (Desktop & Mobile Responsive)
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

// ------------------------------------------------------------------------------
// PREPARE SESSION & USER DATA
// ------------------------------------------------------------------------------
$current_role = $_SESSION['role'] ?? '';

$full_user_name = $_SESSION['fullname'] 
    ?? $_SESSION['full_name'] 
    ?? $_SESSION['name'] 
    ?? $_SESSION['user_name'] 
    ?? $_SESSION['username'] 
    ?? $_SESSION['display_name']
    ?? $_SESSION['admin_name']
    ?? $_SESSION['sales_name']
    ?? 'ผู้ใช้งาน';

$user_display_name = trim($full_user_name);
$user_initial      = mb_substr($user_display_name, 0, 1, 'UTF-8');
$user_role_label   = ($current_role === 'admin') ? 'ผู้ดูแลระบบ' : (($current_role === 'sales') ? 'พนักงานขาย' : 'ผู้ใช้งาน');

$dashboard_url = BASE_URL . (($current_role === 'sales') ? 'sales/dashboard.php' : 'admin/dashboard.php');
$profile_url   = BASE_URL . (($current_role === 'sales') ? 'sales/profile.php' : 'admin/profile.php');

if (!function_exists('isActive')) {
    function isActive($path) {
        return strpos($_SERVER['REQUEST_URI'], $path) !== false ? 'active' : '';
    }
}
?>

<!-- External Assets -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- ============================================================================ -->
<!-- STYLESHEET (SIDEBAR, BRAND ICON & PROFILE MODAL)                              -->
<!-- ============================================================================ -->
<style>
    :root {
        --font-kanit: 'Kanit', sans-serif;
        --sidebar-bg: #ffffff;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 80px;
        --primary-blue: #2563eb;
        --primary-hover: #eff6ff;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
    }

    /* --- Sidebar Container (Desktop Default) --- */
    #sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        height: 100vh !important;
        width: var(--sidebar-width) !important;
        background-color: var(--sidebar-bg);
        border-right: 1px solid var(--border-color) !important;
        font-family: var(--font-kanit) !important;
        z-index: 1040 !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        white-space: nowrap;
        box-sizing: border-box;
        user-select: none;
        padding: 1rem 0.85rem !important;
        transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.03);
    }

    #sidebar::-webkit-scrollbar { width: 4px; }
    #sidebar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    /* --- Sidebar Header & Brand Logo --- */
    .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 0.85rem;
        margin-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        height: 56px;
        width: 100%;
        box-sizing: border-box;
    }

    .sidebar-brand-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none !important;
        color: inherit !important;
        cursor: pointer;
        flex-shrink: 0;
    }

    .brand-icon {
        width: 44px !important;
        height: 44px !important;
        min-width: 44px !important;
        min-height: 44px !important;
        background-color: #eff6ff;
        border-radius: 12px;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
    }

    .brand-icon-svg { 
        width: 26px !important; 
        height: 26px !important; 
        min-width: 26px !important;
        min-height: 26px !important;
        fill: var(--primary-blue) !important; 
        color: var(--primary-blue) !important;
        flex-shrink: 0 !important;
    }

    .brand-text-box { display: flex; flex-direction: column; line-height: 1.2; }
    .brand-title { font-size: 1.05rem; font-weight: 700; color: #1e293b; }
    .brand-subtitle { font-size: 0.75rem; font-weight: 500; color: #64748b; }

    .sidebar-toggle-btn {
        color: #334155;
        background: transparent !important;
        border: none !important;
        border-radius: 8px;
        width: 36px !important;
        height: 36px !important;
        min-width: 36px !important;
        min-height: 36px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer;
        flex-shrink: 0 !important;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .sidebar-toggle-btn:hover { background-color: #f1f5f9 !important; color: var(--primary-blue); }

    /* --- Navigation Menu --- */
    .sidebar-section-title {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        padding: 0.5rem 0.5rem 0.25rem 0.5rem;
    }

    .sidebar-divider { height: 1px; background-color: #f1f5f9; margin: 0.6rem 0; }

    .sidebar-menu-link {
        display: flex;
        align-items: center;
        padding: 0.65rem 0.85rem;
        color: var(--text-dark);
        font-size: 0.9rem;
        border-radius: 10px;
        text-decoration: none;
        margin-bottom: 0.2rem;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .sidebar-menu-link i, 
    .sidebar-menu-link .menu-icon-svg {
        font-size: 1.25rem !important;
        width: 24px !important;
        height: 24px !important;
        min-width: 24px !important;
        min-height: 24px !important;
        color: var(--text-muted);
        fill: var(--text-muted);
        margin-right: 0.75rem;
        flex-shrink: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .sidebar-menu-link:hover { background-color: var(--primary-hover); color: var(--primary-blue); }
    .sidebar-menu-link:hover i, 
    .sidebar-menu-link:hover .menu-icon-svg { color: var(--primary-blue); fill: var(--primary-blue); }
    
    .sidebar-menu-link.active { background-color: rgba(37, 99, 235, 0.08); color: var(--primary-blue); font-weight: 600; }
    .sidebar-menu-link.active i, 
    .sidebar-menu-link.active .menu-icon-svg { color: var(--primary-blue); fill: var(--primary-blue); }

    /* --- User Footer & Popover --- */
    .sidebar-user-footer {
        margin-top: auto;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
        position: relative;
    }

    .user-footer-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.45rem 0.65rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .user-footer-card:hover { background-color: #f8fafc; border-color: #cbd5e1; }

    .user-avatar-circle {
        width: 38px !important;
        height: 38px !important;
        min-width: 38px !important;
        min-height: 38px !important;
        border-radius: 50%;
        background-color: var(--primary-blue);
        color: #ffffff;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.95rem;
        font-weight: 600;
        flex-shrink: 0 !important;
    }

    .user-info-text { display: flex; flex-direction: column; overflow: hidden; margin-left: 0.5rem; flex-grow: 1; }
    .user-info-name { font-size: 0.85rem; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .user-info-role { font-size: 0.7rem; color: #64748b; }

    .user-popover-menu {
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        margin-bottom: 8px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        padding: 0.35rem;
        display: none;
        flex-direction: column;
        z-index: 1050;
    }

    .user-popover-menu.show { display: flex; }

    .popover-item {
        display: flex;
        align-items: center;
        padding: 0.55rem 0.75rem;
        font-size: 0.88rem;
        color: #334155;
        text-decoration: none !important;
        border-radius: 8px;
        cursor: pointer;
    }

    .popover-item i { font-size: 1.1rem; margin-right: 0.65rem; color: #64748b; flex-shrink: 0; }
    .popover-item:hover { background-color: #f8fafc; color: var(--primary-blue); }
    .popover-item:hover i { color: var(--primary-blue); }
    .popover-item.danger-item:hover { background-color: #fef2f2; color: #ef4444; }
    .popover-item.danger-item:hover i { color: #ef4444; }

    /* --- Profile Modal --- */
    .profile-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background-color: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px);
        z-index: 2000; display: none; align-items: center; justify-content: center;
        padding: 1.5rem; opacity: 0; transition: opacity 0.25s ease;
    }
    .profile-modal-overlay.active { display: flex; opacity: 1; }
    .profile-modal-card {
        background: #ffffff; border-radius: 20px; width: 100%; max-width: 850px;
        height: 85vh; max-height: 700px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden; display: flex; flex-direction: column;
        transform: scale(0.95); transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        font-family: var(--font-kanit);
    }
    .profile-modal-overlay.active .profile-modal-card { transform: scale(1); }
    .profile-modal-header { background: #ffffff; padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
    .profile-modal-close-btn { background: #f1f5f9; border: none; color: #64748b; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; }
    .profile-modal-close-btn:hover { background: #e2e8f0; color: #0f172a; }
    .profile-iframe-container { flex: 1; width: 100%; height: 100%; background-color: #ffffff; }
    .profile-iframe { width: 100%; height: 100%; border: none; }

    /* --- DESKTOP COLLAPSED STATE --- */
    body.sidebar-collapsed #sidebar { 
        width: var(--sidebar-collapsed-width) !important; 
        padding: 1rem 0.5rem !important; 
    }

    body.sidebar-collapsed #sidebar .brand-text-box,
    body.sidebar-collapsed #sidebar .sidebar-section-title,
    body.sidebar-collapsed #sidebar .sidebar-menu-link span,
    body.sidebar-collapsed #sidebar .user-info-text,
    body.sidebar-collapsed #sidebar .user-card-arrow,
    body.sidebar-collapsed #sidebar .sidebar-toggle-btn { 
        display: none !important; 
    }

    body.sidebar-collapsed #sidebar .sidebar-header,
    body.sidebar-collapsed #sidebar .sidebar-brand-box {
        justify-content: center !important;
        margin: 0 auto !important;
    }

    body.sidebar-collapsed #sidebar .sidebar-menu-link { 
        justify-content: center !important; 
        padding: 0.75rem 0 !important;
    }

    body.sidebar-collapsed #sidebar .sidebar-menu-link i, 
    body.sidebar-collapsed #sidebar .sidebar-menu-link .menu-icon-svg { 
        margin-right: 0 !important; 
    }

    body.sidebar-collapsed #sidebar .user-footer-card {
        justify-content: center !important;
        padding: 0.45rem !important;
    }

    body.sidebar-collapsed #mainContentArea {
        margin-left: var(--sidebar-collapsed-width) !important;
    }

    /* ============================================================================ */
    /* MOBILE RESPONSIVE STYLE (ทำงานเฉพาะจอเล็ก <= 767.98px)                      */
    /* ============================================================================ */
    @media (max-width: 767.98px) {
        #sidebar {
            left: -100% !important;
            z-index: 1045 !important;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15) !important;
        }

        #sidebar.show-mobile {
            left: 0 !important;
        }

        /* ซ่อนปุ่มย่อ Sidebar เดิมของคอมเมื่ออยู่บนมือถือ */
        .sidebar-toggle-btn {
            display: none !important;
        }
    }
</style>

<!-- Script ป้องกันหน้าจอกระตุกขณะโหลด บนหน้าจอคอมพิวเตอร์ -->
<script>
    if (window.innerWidth >= 768 && localStorage.getItem('sidebar_state') === 'collapsed') {
        document.body.classList.add('sidebar-collapsed');
    }
</script>

<!-- ============================================================================ -->
<!-- HTML STRUCTURE                                                               -->
<!-- ============================================================================ -->
<div class="custom-sidebar d-flex flex-column" id="sidebar">
    
    <!-- Sidebar Header & Logo -->
    <div class="sidebar-header" id="sidebarHeader">
        <a href="<?= $dashboard_url; ?>" class="sidebar-brand-box" id="brandLink" title="คลิกเพื่อเปิด/ขยายแถบเมนู">
            <div class="brand-icon">
                <svg class="brand-icon-svg text-primary" viewBox="0 0 24 24">
                    <rect x="1.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <rect x="15.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <path fill-rule="evenodd" d="M12 2 C13.93 2 15.5 3.57 15.5 5.5 C15.5 7.43 13.93 9 12 9 C10.07 9 8.5 7.43 8.5 5.5 C8.5 3.57 10.07 2 12 2 Z M12 3.8 C11.06 3.8 10.3 4.56 10.3 5.5 C10.3 6.44 11.06 7.2 12 7.2 C12.94 7.2 13.7 6.44 13.7 5.5 C13.7 4.56 12.94 3.8 12 3.8 Z"/>
                    <path d="M12 7.5 C8 7.5 5 10.5 5 14.2 L5 18 C5 19.1 5.9 20 7 20 L8.2 20 C8.4 20 8.5 19.8 8.5 19.6 L8.5 16.5 C8.5 14.6 10.1 13 12 13 C13.9 13 15.5 14.6 15.5 16.5 L15.5 19.6 C15.5 19.8 15.6 20 15.8 20 L17 20 C18.1 20 19 19.1 19 18 L19 14.2 C19 10.5 16 7.5 12 7.5 Z"/>
                    <rect x="9.5" y="13.5" width="5" height="8.5" rx="2.5"/>
                </svg>
            </div>
            <div class="brand-text-box">
                <span class="brand-title">ระบบสั่งออเดอร์รถ</span>
                <span class="brand-subtitle">Car Order System</span>
            </div>
        </a>
        
        <button class="btn sidebar-toggle-btn" type="button" id="sidebarToggle" title="ย่อ/ขยาย เมนู">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                <rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/>
                <line x1="8.5" y1="8" x2="8.5" y2="16"/>
            </svg>
        </button>
    </div>

    <!-- Navigation Menu Items -->
    <div class="nav flex-column mb-auto mt-1">
        <?php if ($current_role === 'admin'): ?>
            
            <div class="sidebar-section-title">ภาพรวมและคำสั่งซื้อ</div>
            
            <a href="<?= BASE_URL; ?>admin/dashboard.php" class="sidebar-menu-link <?= isActive('admin/dashboard'); ?>" title="แดชบอร์ดตรวจสอบ">
                <i class="bi bi-speedometer2"></i>
                <span>แดชบอร์ดตรวจสอบ</span>
            </a>

            <a href="<?= BASE_URL; ?>admin/orders.php" class="sidebar-menu-link <?= isActive('admin/orders'); ?>" title="ประวัติคำสั่งซื้อทั้งหมด">
                <i class="bi bi-receipt-cutoff"></i>
                <span>ประวัติคำสั่งซื้อทั้งหมด</span>
            </a>

            <div class="sidebar-divider"></div>
            <div class="sidebar-section-title">คลังมอเตอร์ไซค์และโควตา</div>

            <a href="<?= BASE_URL; ?>admin/cars.php" class="sidebar-menu-link <?= isActive('admin/cars'); ?>" title="จัดการคลังมอเตอร์ไซค์">
                <svg class="menu-icon-svg" viewBox="0 0 24 24">
                    <rect x="1.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <rect x="15.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <path fill-rule="evenodd" d="M12 2 C13.93 2 15.5 3.57 15.5 5.5 C15.5 7.43 13.93 9 12 9 C10.07 9 8.5 7.43 8.5 5.5 C8.5 3.57 10.07 2 12 2 Z M12 3.8 C11.06 3.8 10.3 4.56 10.3 5.5 C10.3 6.44 11.06 7.2 12 7.2 C12.94 7.2 13.7 6.44 13.7 5.5 C13.7 4.56 12.94 3.8 12 3.8 Z"/>
                    <path d="M12 7.5 C8 7.5 5 10.5 5 14.2 L5 18 C5 19.1 5.9 20 7 20 L8.2 20 C8.4 20 8.5 19.8 8.5 19.6 L8.5 16.5 C8.5 14.6 10.1 13 12 13 C13.9 13 15.5 14.6 15.5 16.5 L15.5 19.6 C15.5 19.8 15.6 20 15.8 20 L17 20 C18.1 20 19 19.1 19 18 L19 14.2 C19 10.5 16 7.5 12 7.5 Z"/>
                    <rect x="9.5" y="13.5" width="5" height="8.5" rx="2.5"/>
                </svg>
                <span>จัดการคลังมอเตอร์ไซค์</span>
            </a>

            <a href="<?= BASE_URL; ?>admin/add_master_car.php" class="sidebar-menu-link <?= isActive('admin/add_master_car'); ?>" title="เพิ่มมอเตอร์ไซค์เข้าคลัง">
                <svg class="menu-icon-svg" viewBox="0 0 24 24">
                    <rect x="1" y="4.5" width="6.5" height="2.4" rx="1.2"/>
                    <rect x="13.5" y="4.5" width="5" height="2.4" rx="1.2"/>
                    <path fill-rule="evenodd" d="M10.5 2.5 C12.2 2.5 13.5 3.8 13.5 5.5 C13.5 7.2 12.2 8.5 10.5 8.5 C8.8 8.5 7.5 7.2 7.5 5.5 C7.5 3.8 8.8 2.5 10.5 2.5 Z M10.5 4 C9.7 4 9 4.7 9 5.5 C9 6.3 9.7 7 10.5 7 C11.3 7 12 6.3 12 5.5 C12 4.7 11.3 4 10.5 4 Z"/>
                    <path d="M10.5 7.5 C7 7.5 4.5 10 4.5 13.5 L4.5 17.5 C4.5 18.5 5.3 19.2 6.2 19.2 L7.2 19.2 C7.4 19.2 7.5 19 7.5 18.8 L7.5 16 C7.5 14.3 8.8 13 10.5 13 C12.2 13 13.5 14.3 13.5 16 L13.5 18.8 C13.5 19 13.6 19.2 13.8 19.2 L14.8 19.2 C15.7 19.2 16.5 18.5 16.5 17.5 L16.5 13.5 C16.5 10 14 7.5 10.5 7.5 Z"/>
                    <rect x="8.3" y="13.5" width="4.4" height="8" rx="2.2"/>
                    <circle cx="18" cy="6" r="4.5" fill="var(--primary-blue)"/>
                    <path d="M18 3.8 V8.2 M15.8 6 H20.2" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span>เพิ่มมอเตอร์ไซค์เข้าคลัง</span>
            </a>

            <div class="sidebar-divider"></div>
            <div class="sidebar-section-title">จัดการระบบและผู้ใช้งาน</div>

            <a href="<?= BASE_URL; ?>admin/manage_users.php" class="sidebar-menu-link <?= isActive('admin/manage_users'); ?>" title="จัดการพนักงานเขตพื้นที่">
                <i class="bi bi-people"></i>
                <span>จัดการพนักงานเขตพื้นที่</span>
            </a>

            <a href="<?= BASE_URL; ?>admin/manage_shops.php" class="sidebar-menu-link <?= isActive('admin/manage_shops'); ?>" title="จัดการร้านค้า">
                <i class="bi bi-shop"></i>
                <span>จัดการร้านค้า</span>
            </a>

        <?php elseif ($current_role === 'sales'): ?>

            <a href="<?= BASE_URL; ?>sales/dashboard.php" class="sidebar-menu-link <?= isActive('sales/dashboard'); ?>" title="หน้าแรกพนักงานขาย">
                <i class="bi bi-house-door"></i>
                <span>หน้าแรกพนักงานขาย</span>
            </a>

            <a href="<?= BASE_URL; ?>sales/my_orders.php" class="sidebar-menu-link <?= isActive('sales/my_orders'); ?>" title="ประวัติการขายของฉัน">
                <i class="bi bi-journal-check"></i>
                <span>ประวัติการขายของฉัน</span>
            </a>

        <?php endif; ?>
    </div>

    <!-- User Profile Footer -->
    <div class="sidebar-user-footer">
        <div class="user-popover-menu" id="userPopoverMenu">
            <div class="popover-item" id="openProfileModalTrigger">
                <i class="bi bi-person-circle"></i>
                <span>โปรไฟล์</span>
            </div>

            <a href="<?= BASE_URL; ?>logout.php" class="popover-item danger-item" onclick="return confirm('คุณต้องการลงชื่อออกจากระบบหรือไม่?');">
                <i class="bi bi-box-arrow-right"></i>
                <span>ลงชื่อออก</span>
            </a>
        </div>

        <div class="user-footer-card" id="userCardTrigger" title="เมนูผู้ใช้งาน">
            <div class="d-flex align-items-center overflow-hidden">
                <div class="user-avatar-circle">
                    <?= htmlspecialchars($user_initial); ?>
                </div>
                <div class="user-info-text">
                    <span class="user-info-name"><?= htmlspecialchars($user_display_name); ?></span>
                    <span class="user-info-role"><?= $user_role_label; ?></span>
                </div>
            </div>
            <i class="bi bi-chevron-up user-card-arrow text-muted ms-2" style="font-size: 0.8rem;"></i>
        </div>
    </div>

</div>

<!-- Modal Pop-up: Profile Management -->
<div class="profile-modal-overlay" id="profileModalOverlay">
    <div class="profile-modal-card">
        <div class="profile-modal-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-gear text-primary fs-5"></i>
                <h6 class="m-0 fw-bold text-dark">โปรไฟล์ผู้ใช้งาน</h6>
            </div>
            <button type="button" class="profile-modal-close-btn" id="closeProfileModalBtn">&times;</button>
        </div>
        <div class="profile-iframe-container">
            <iframe id="profileIframe" class="profile-iframe" src="about:blank"></iframe>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- JAVASCRIPT LOGIC                                                             -->
<!-- ============================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn           = document.getElementById('sidebarToggle');
    const brandLink           = document.getElementById('brandLink');
    const userCardTrigger     = document.getElementById('userCardTrigger');
    const userPopoverMenu     = document.getElementById('userPopoverMenu');

    const openProfileTrigger  = document.getElementById('openProfileModalTrigger');
    const profileModalOverlay = document.getElementById('profileModalOverlay');
    const closeProfileBtn     = document.getElementById('closeProfileModalBtn');
    const profileIframe       = document.getElementById('profileIframe');

    const mobileMenuBtn       = document.getElementById('mobileMenuBtn');
    const mobileOverlay       = document.getElementById('mobileSidebarOverlay');
    const sidebar             = document.getElementById('sidebar');

    const profileTargetUrl    = "<?= $profile_url; ?>?in_modal=1";

    // 1. Desktop Sidebar State Controller
    function applySidebarState(isCollapsed) {
        if (window.innerWidth >= 768) {
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isCurrentlyCollapsed = document.body.classList.contains('sidebar-collapsed');
            const newCollapsedState    = !isCurrentlyCollapsed;
            
            applySidebarState(newCollapsedState);
            localStorage.setItem('sidebar_state', newCollapsedState ? 'collapsed' : 'expanded');
        });
    }

    if (brandLink) {
        brandLink.addEventListener('click', function(e) {
            if (window.innerWidth >= 768 && document.body.classList.contains('sidebar-collapsed')) {
                e.preventDefault();
                applySidebarState(false);
                localStorage.setItem('sidebar_state', 'expanded');
            }
        });
    }

    // 2. Mobile Sidebar Controller (Slide In/Out)
    function openMobileSidebar() {
        if (sidebar) sidebar.classList.add('show-mobile');
        if (mobileOverlay) mobileOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        if (sidebar) sidebar.classList.remove('show-mobile');
        if (mobileOverlay) mobileOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', openMobileSidebar);
    }

    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobileSidebar);
    }

    // ปิด Mobile Sidebar อัตโนมัติเมื่อกดเลือกเมนู
    const menuLinks = document.querySelectorAll('.sidebar-menu-link');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                closeMobileSidebar();
            }
        });
    });

    // 3. User Footer Popover Menu
    if (userCardTrigger && userPopoverMenu) {
        userCardTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            userPopoverMenu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!userPopoverMenu.contains(e.target) && !userCardTrigger.contains(e.target)) {
                userPopoverMenu.classList.remove('show');
            }
        });
    }

    // 4. Profile Modal Controllers
    function openProfileModal() {
        if (userPopoverMenu) userPopoverMenu.classList.remove('show');
        if (window.innerWidth < 768) closeMobileSidebar();
        
        if (profileIframe.src === "about:blank" || !profileIframe.src.includes("in_modal=1")) {
            profileIframe.src = profileTargetUrl;
        }
        if (profileModalOverlay) profileModalOverlay.classList.add('active');
    }

    function closeProfileModal() {
        if (profileModalOverlay) profileModalOverlay.classList.remove('active');
    }

    if (openProfileTrigger) openProfileTrigger.addEventListener('click', openProfileModal);
    if (closeProfileBtn) closeProfileBtn.addEventListener('click', closeProfileModal);

    if (profileModalOverlay) {
        profileModalOverlay.addEventListener('click', function(e) {
            if (e.target === profileModalOverlay) closeProfileModal();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeProfileModal();
    });
});
</script>