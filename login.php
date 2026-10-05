<?php
/**
 * ==============================================================================
 * File: login.php
 * Description: หน้าเข้าสู่ระบบ - ปรับปรุงโครงสร้าง UI 50/50 และระบบความปลอดภัย
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/config/database.php';

// ป้องกันกรณีเข้าสู่ระบบอยู่แล้ว ให้เปลี่ยนหน้าตามสิทธิ์ทันที
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'sales') {
        header("Location: sales/dashboard.php");
        exit();
    }
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $conn->prepare("SELECT id, username, password, name, role, region FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                
                session_regenerate_id(true); 

                $_SESSION['user_id']     = (int)$user['id'];
                $_SESSION['user_name']   = $user['name'] ?? 'ผู้ใช้งานระบบ';
                $_SESSION['role']        = strtolower(trim($user['role'] ?? ''));
                $_SESSION['user_region'] = $user['region'] ?? 'ไม่ระบุภาค';

                if ($_SESSION['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                    exit();
                } elseif ($_SESSION['role'] === 'sales') {
                    header("Location: sales/dashboard.php");
                    exit();
                } else {
                    $error_msg = "บัญชีของคุณไม่มีสิทธิ์เข้าถึงระบบ (Role ไม่ถูกต้อง)";
                    session_destroy();
                }

            } else {
                $error_msg = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
        } catch (PDOException $e) {
            $error_msg = "ระบบขัดข้อง: เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล";
        }
    } else {
        $error_msg = "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | ระบบสั่งจองรถมอเตอร์ไซค์</title>
    
    <!-- Google Fonts: Kanit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-light: #f8fafc;
            --card-border-radius: 20px;
            --font-kanit: 'Kanit', sans-serif;
        }

        body, input, button, select, textarea {
            font-family: var(--font-kanit) !important;
        }

        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
            overflow-x: hidden;
        }

        /* 50/50 Split Container สำหรับ Desktop */
        .login-split-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            max-width: 880px;
            min-height: 480px;
            background: #ffffff;
            border-radius: var(--card-border-radius);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ฝั่งซ้าย (50%): Hero Section */
        .login-hero-side {
            background-color: #f8fafc;
            border-right: 1px solid #e2e8f0;
            padding: 2.5rem 2.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .hero-brand-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
            transition: transform 0.3s ease;
        }

        .login-split-card:hover .hero-brand-icon {
            transform: scale(1.05);
        }

        .page-header-icon-svg {
            width: 32px;
            height: 32px;
            fill: var(--primary-color);
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.88rem;
            color: #475569;
            margin-bottom: 0.65rem;
        }

        .feature-item i {
            color: var(--primary-color);
        }

        /* ฝั่งขวา (50%): Form Section */
        .login-form-side {
            padding: 2.5rem 2.25rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #ffffff;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.88rem;
            color: #334155;
            margin-bottom: 0.4rem;
            display: block;
        }

        /* กรอบกรอกข้อมูลแบบใหม่ (Unified Input Container Style) */
        .form-group-custom {
            margin-bottom: 1.2rem;
        }

        .custom-input-group {
            display: flex;
            align-items: center;
            background-color: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            transition: all 0.2s ease-in-out;
            overflow: hidden;
            width: 100%;
        }

        .custom-input-group:focus-within {
            background-color: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .custom-input-icon {
            padding: 0.72rem 0.2rem 0.72rem 1rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: color 0.2s ease;
        }

        .custom-input-group:focus-within .custom-input-icon {
            color: var(--primary-color);
        }

        .custom-form-control {
            border: none !important;
            background: transparent !important;
            padding: 0.72rem 0.85rem;
            font-size: 0.92rem;
            color: #0f172a;
            width: 100%;
            outline: none !important;
            box-shadow: none !important;
        }

        .custom-form-control::placeholder {
            color: #94a3b8;
        }

        .btn-toggle-pwd {
            border: none !important;
            background: transparent !important;
            padding: 0.72rem 1rem;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .btn-toggle-pwd:hover {
            color: var(--primary-color);
        }

        /* ปุ่มกดเข้าสู่ระบบ */
        .btn-submit {
            background-color: var(--primary-color);
            border: none;
            border-radius: 12px;
            padding: 0.78rem 1.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            transition: all 0.25s ease;
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.3);
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            background-color: #fef2f2;
            color: #dc2626;
            font-size: 0.85rem;
            padding: 0.75rem 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .login-split-card {
                grid-template-columns: 1fr;
            }
            .login-hero-side {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
                padding: 1.75rem 1.5rem;
            }
            .login-form-side {
                padding: 1.75rem 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="login-split-card">
    
    <!-- ฝั่งซ้าย (50%): SVG Icon + Details -->
    <div class="login-hero-side">
        <div>
            <div class="hero-brand-icon">
                <svg class="page-header-icon-svg" viewBox="0 0 24 24">
                    <rect x="1.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <rect x="15.5" y="4.2" width="7" height="2.6" rx="1.3"/>
                    <path fill-rule="evenodd" d="M12 2 C13.93 2 15.5 3.57 15.5 5.5 C15.5 7.43 13.93 9 12 9 C10.07 9 8.5 7.43 8.5 5.5 C8.5 3.57 10.07 2 12 2 Z M12 3.8 C11.06 3.8 10.3 4.56 10.3 5.5 C10.3 6.44 11.06 7.2 12 7.2 C12.94 7.2 13.7 6.44 13.7 5.5 C13.7 4.56 12.94 3.8 12 3.8 Z"/>
                    <path d="M12 7.5 C8 7.5 5 10.5 5 14.2 L5 18 C5 19.1 5.9 20 7 20 L8.2 20 C8.4 20 8.5 19.8 8.5 19.6 L8.5 16.5 C8.5 14.6 10.1 13 12 13 C13.9 13 15.5 14.6 15.5 16.5 L15.5 19.6 C15.5 19.8 15.6 20 15.8 20 L17 20 C18.1 20 19 19.1 19 18 L19 14.2 C19 10.5 16 7.5 12 7.5 Z"/>
                    <rect x="9.5" y="13.5" width="5" height="8.5" rx="2.5"/>
                </svg>
            </div>
            <h4 class="fw-bold text-dark mb-2">ระบบสั่งจองรถมอเตอร์ไซค์</h4>
            <p class="text-muted small mb-4" style="line-height: 1.55;">
                จัดการคำสั่งจอง ตรวจสอบสต็อกรถมอเตอร์ไซค์ และติดตามสถานะออเดอร์ได้อย่างรวดเร็ว
            </p>
        </div>

        <div>
            <div class="feature-item">
                <i class="bi bi-check-circle-fill"></i>
                <span>จัดการออเดอร์สั่งจองรวดเร็ว</span>
            </div>
            <div class="feature-item">
                <i class="bi bi-shield-check-fill"></i>
                <span>ระบบสิทธิ์การเข้าถึงปลอดภัย</span>
            </div>
            <div class="feature-item">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>ติดตามสถานะเรียลไทม์</span>
            </div>
        </div>

        <div class="pt-3 border-top mt-4">
            <span class="text-muted small d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
                <i class="bi bi-lock-fill text-primary"></i>
                <span>Car Order Management System</span>
            </span>
        </div>
    </div>

    <!-- ฝั่งขวา (50%): Form เข้าสู่ระบบ -->
    <div class="login-form-side">
        <div class="mb-3">
            <h4 class="fw-bold text-dark mb-1">เข้าสู่ระบบ</h4>
            <p class="text-muted small mb-0">ระบุข้อมูลบัญชีเพื่อเข้าใช้งานระบบ</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert-custom mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-6 flex-shrink-0"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- ช่อง Username -->
            <div class="form-group-custom">
                <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                <div class="custom-input-group">
                    <span class="custom-input-icon"><i class="bi bi-person-circle"></i></span>
                    <input type="text" name="username" class="custom-form-control" placeholder="กรอกชื่อผู้ใช้งาน" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <!-- ช่อง Password -->
            <div class="form-group-custom mb-4">
                <label class="form-label">รหัสผ่าน (Password)</label>
                <div class="custom-input-group">
                    <span class="custom-input-icon"><i class="bi bi-shield-lock-fill"></i></span>
                    <input type="password" name="password" id="passwordInput" class="custom-form-control" placeholder="กรอกรหัสผ่าน" required autocomplete="current-password">
                    <button class="btn-toggle-pwd" type="button" id="togglePassword">
                        <i class="bi bi-eye-slash" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-submit w-100 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-in-right fs-5"></i>
                <span>เข้าสู่ระบบ</span>
            </button>
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#passwordInput');
    const toggleIcon = document.querySelector('#toggleIcon');

    if (togglePassword && passwordInput && toggleIcon) {
        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('bi-eye-slash', !isPassword);
            toggleIcon.classList.toggle('bi-eye', isPassword);
        });
    }
});
</script>

</body>
</html>