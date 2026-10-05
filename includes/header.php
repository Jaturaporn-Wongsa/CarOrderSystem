<?php
/**
 * ==============================================================================
 * File: includes/header.php
 * Description: ส่วนหัวของเว็บไซต์ + Mobile Header แสดงบทบาทผู้ใช้งานมุมขวาบน
 * ==============================================================================
 */

// เริ่มต้น Session เพื่อจัดการสถานะการล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// โหลดไฟล์ตั้งค่าระบบ
$config_path = __DIR__ . '/../config/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// กำหนดค่า BASE_URL สำรองกรณีไม่ได้ตั้งค่าใน config.php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/CarOrderSystem/');
}

// ------------------------------------------------------------------------------
// PREPARE USER ROLE DATA FOR HEADER
// ------------------------------------------------------------------------------
$current_role = $_SESSION['role'] ?? '';
$user_role_label = ($current_role === 'admin') ? 'ผู้ดูแลระบบ' : (($current_role === 'sales') ? 'พนักงานขาย' : 'ผู้ใช้งาน');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบสั่งออเดอร์รถ | Car Order System</title>

    <!-- Google Fonts (Sarabun & Kanit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css">
    
    <style>
        /* ==========================================
           Global Base Style
           ========================================== */
        :root {
            --font-sarabun: 'Sarabun', sans-serif;
            --font-kanit: 'Kanit', sans-serif;
            --primary-blue: #0284c7;
            --border-color: #e2e8f0;
        }

        body { 
            font-family: var(--font-sarabun); 
            background-color: #f8fafc;
            color: #1e293b;
        }
        
        .card { 
            border-radius: 12px; 
            border: 1px solid var(--border-color);
        }

        /* ==========================================
           Mobile Header Style (เฉพาะหน้าจอมือถือ < 768px)
           ========================================== */
        @media (max-width: 767.98px) {
            /* ล็อกการเลื่อนไถลแนวนอนเฉพาะบนมือถือ */
            html, body {
                max-width: 100% !important;
                overflow-x: hidden !important;
            }

            .mobile-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.75rem 1rem;
                background-color: #ffffff !important;
                border-bottom: none !important; /* ลบเส้นกรอบล่าง */
                box-shadow: none !important;     /* ลบเงากรอบ */
                position: sticky;
                top: 0;
                z-index: 1020;
            }

            /* ปุ่มเปิดเมนู - ลบกรอบสี่เหลี่ยมและพื้นหลังออก */
            .btn-mobile-toggle {
                border: none !important;
                background-color: transparent !important;
                background: none !important;
                box-shadow: none !important;
                padding: 0;
                margin: 0;
                color: #334155;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: color 0.2s ease;
            }

            .btn-mobile-toggle:hover,
            .btn-mobile-toggle:active,
            .btn-mobile-toggle:focus {
                border: none !important;
                background-color: transparent !important;
                color: var(--primary-blue);
                outline: none !important;
                box-shadow: none !important;
            }

            .btn-mobile-toggle i {
                font-size: 1.5rem !important;
            }

            .mobile-brand-box {
                display: flex;
                align-items: center;
                font-family: var(--font-kanit);
                text-decoration: none !important; /* ป้องกันเส้นใต้ลิงก์ */
            }

            .mobile-brand-title {
                font-size: 0.95rem;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.2;
                transition: color 0.2s ease;
            }

            .mobile-brand-box:hover .mobile-brand-title {
                color: var(--primary-blue);
            }

            /* Badge แสดงประเภทผู้ใช้งาน (มุมขวาบน) */
            .mobile-user-badge {
                display: flex;
                align-items: center;
                gap: 0.35rem;
                background-color: #ffffff;
                border: 1px solid var(--border-color);
                padding: 0.3rem 0.75rem;
                border-radius: 20px;
                font-size: 0.82rem;
                color: #334155;
                white-space: nowrap;
            }

            .mobile-user-badge i {
                color: var(--primary-blue);
                font-size: 1rem;
                flex-shrink: 0;
            }

            .mobile-user-label {
                color: #64748b;
            }

            .mobile-user-role {
                font-weight: 700;
                color: #0f172a;
            }

            .mobile-sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(2px);
                z-index: 1030;
                transition: opacity 0.2s ease;
            }

            .mobile-sidebar-overlay.active {
                display: block;
            }
        }

        /* ==========================================
           Desktop Protection (หน้าจอ >= 768px)
           ========================================== */
        @media (min-width: 768px) {
            .mobile-header,
            .mobile-sidebar-overlay {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- แถบ Header สำหรับมือถือ -->
    <header class="mobile-header">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn-mobile-toggle" id="mobileMenuBtn" aria-label="Toggle Navigation Menu">
                <i class="bi bi-list"></i>
            </button>

            <!-- เพิ่มแท็ก <a> ครอบข้อความชื่อระบบเพื่อลิงก์กลับหน้าหลัก (BASE_URL) -->
            <a href="<?= BASE_URL; ?>" class="mobile-brand-box">
                <span class="mobile-brand-title">CarOrderSystem</span>
            </a>
        </div>

        <!-- ข้อความผู้ใช้งานมุมขวาบน (ผู้ใช้งาน: พนักงานขาย) -->
        <div class="mobile-user-badge">
            <i class="bi bi-person-circle"></i>
            <span>
                <span class="mobile-user-label">ผู้ใช้งาน:</span>
                <span class="mobile-user-role"><?= htmlspecialchars($user_role_label); ?></span>
            </span>
        </div>
    </header>

    <!-- ฉากหลังมืดสำหรับการเปิด Sidebar บนมือถือ -->
    <div id="mobileSidebarOverlay" class="mobile-sidebar-overlay"></div>