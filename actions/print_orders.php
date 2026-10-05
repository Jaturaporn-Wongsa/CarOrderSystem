<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';
checkAdmin();

try {
    // ใช้ JOIN กับตาราง cars เพื่อดึงชื่อรุ่นรถ (model_name)
    $sql = "SELECT o.*, u.name as sales_name, c.model_name 
            FROM orders o 
            LEFT JOIN users u ON o.sales_id = u.id 
            LEFT JOIN cars c ON o.car_id = c.id 
            ORDER BY c.model_name ASC, o.id DESC";
            
    $stmt = $conn->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($orders as $row) {
        $model = $row['model_name'] ?? 'ไม่ระบุรุ่น';
        $grouped[$model][] = $row;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// อาเรย์สำหรับแปลงเดือนเป็นภาษาไทย
$thai_months = [
    '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
    '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
    '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
    '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตัวอย่างพิมพ์รายงานคำสั่งซื้อ</title>
    <style>
        body { font-family: 'Tahoma', sans-serif; font-size: 14px; color: #333; margin: 20px; background-color: #f8f9fa; }
        .preview-container { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2.title { text-align: center; margin-bottom: 30px; }
        .model-header { font-size: 16px; font-weight: bold; margin-top: 25px; margin-bottom: 8px; border-bottom: 1.5px solid #333; display: inline-block; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #333; padding: 8px 12px; text-align: center; }
        th { background-color: #f2f2f2; font-weight: bold; }
        td.shop-name { text-align: left; }
        
        /* ปุ่มกดจัดการด้านบน */
        .action-bar { margin-bottom: 25px; text-align: right; background: #e9ecef; padding: 15px; border-radius: 6px; }
        .btn { padding: 10px 20px; cursor: pointer; border: none; border-radius: 4px; font-size: 14px; text-decoration: none; display: inline-block; font-weight: bold; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; margin-right: 5px; }

        @media print {
            body { background-color: #fff; margin: 0; }
            .preview-container { box-shadow: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="preview-container">
        <div class="no-print action-bar">
            <a href="orders.php" class="btn btn-secondary">⬅️ กลับหน้าจัดการ</a>
            <button onclick="window.print()" class="btn btn-success">🖨️ พิมพ์ / บันทึกเป็น PDF</button>
        </div>

        <h2 class="title">รายงานสรุปคำสั่งซื้อรถจักรยานยนต์</h2>

        <?php if (empty($grouped)): ?>
            <p style="text-align: center; color: #777;">ไม่พบข้อมูลคำสั่งซื้อ</p>
        <?php else: ?>
            <?php foreach ($grouped as $model => $rows): ?>
                <div class="model-header"><?php echo htmlspecialchars($model); ?></div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%;">ภาค</th>
                            <th style="width: 15%;">เดือน</th>
                            <th style="width: 38%;">ร้านค้า</th>
                            <th style="width: 12%;">จำนวน</th>
                            <th style="width: 12%;">จำนวนสั่ง</th>
                            <th style="width: 10%;">แบ็คออเดอร์</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        foreach ($rows as $r): 
                            $created_at = $r['created_at'] ?? '';
                            $m_key = $created_at ? date('m', strtotime($created_at)) : ($r['month'] ?? '');
                            $display_month = $thai_months[$m_key] ?? ($r['month'] !== '' ? $r['month'] : 'มกราคม');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['region'] ?? 'กลาง'); ?></td>
                            <td><?php echo htmlspecialchars($display_month); ?></td>
                            <td class="shop-name"><?php echo $i . '. ' . htmlspecialchars($r['shop_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['quantity'] ?? 0); ?> คัน</td>
                            <td><?php echo htmlspecialchars($r['approved_quantity'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($r['backorder_quantity'] ?? 0); ?></td>
                        </tr>
                        <?php 
                        $i++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>