<?php
/**
 * ==============================================================================
 * File: admin/export_excel.php
 * Description: ส่งออกไฟล์ Excel ปรับแก้ไม่ให้ติด Hilight/Selection แถบแถวสุดท้าย
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------------------------
// 1. Dependencies & Initialization
// -----------------------------------------------------------------------------
$base_dir = dirname(__DIR__);

if (file_exists($base_dir . '/vendor/autoload.php')) {
    require_once $base_dir . '/vendor/autoload.php';
} else {
    die("กรุณาติดตั้ง PhpSpreadsheet ผ่าน Composer ก่อนใช้งาน");
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

$orders = $_SESSION['report_orders'] ?? [];

if (empty($orders)) {
    die("ไม่พบข้อมูลสำหรับการสร้างไฟล์ Excel");
}

// -----------------------------------------------------------------------------
// 2. Helper Functions
// -----------------------------------------------------------------------------
function getBrandName(array $order): string 
{
    if (!empty($order['brand_name'])) return $order['brand_name'];
    if (!empty($order['brand'])) return $order['brand'];
    
    $model = strtoupper(trim($order['model_name'] ?? ''));
    if (preg_match('/^(X300|G350|V200|X200|LAMBRETTA)/i', $model)) return 'Lambretta';
    if (preg_match('/^(368G|368K|350E|350D|350M|350X|ZONTES)/i', $model)) return 'Zontes';
    
    return '-';
}

function getRegionColorHex(?string $region_name): string 
{
    $r = mb_strtolower(trim((string)$region_name));
    if (mb_strpos($r, 'กลาง') !== false || mb_strpos($r, 'กรุงเทพ') !== false || mb_strpos($r, 'ปริมณฑล') !== false || mb_strpos($r, 'bkk') !== false) return '3B82F6';
    if (mb_strpos($r, 'ตะวันตก') !== false || mb_strpos($r, 'west') !== false) return 'EC4899';
    if (mb_strpos($r, 'ตะวันออก') !== false && mb_strpos($r, 'เฉียงเหนือ') === false) return '06B6D4';
    if (mb_strpos($r, 'อีสาน') !== false || mb_strpos($r, 'เฉียงเหนือ') !== false) return 'F59E0B';
    if (mb_strpos($r, 'เหนือ') !== false || mb_strpos($r, 'north') !== false) return '8B5CF6';
    if (mb_strpos($r, 'ใต้') !== false || mb_strpos($r, 'south') !== false) return '10B981';
    return '1E293B';
}

// -----------------------------------------------------------------------------
// 3. Spreadsheet Setup
// -----------------------------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('รายงานการสั่งจอง');

$spreadsheet->getDefaultStyle()->getFont()->setName('TH SarabunPSK')->setSize(15);

$styleBorderThin = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['rgb' => 'D1D5DB'],
        ],
    ],
];

// -----------------------------------------------------------------------------
// 4. Header Setup
// -----------------------------------------------------------------------------
$headers = [
    'Code', 
    'ยี่ห้อรถ', 
    'ภาค', 
    'ชื่อร้านค้า / ตัวแทนจำหน่าย', 
    'สีรถ', 
    'จำนวน', 
    'อนุมัติแล้ว', 
    'แบ็คออเดอร์'
];
$cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

foreach ($headers as $idx => $headerTitle) {
    $col  = $cols[$idx];
    $cell = "{$col}1";
    
    $sheet->setCellValue($cell, $headerTitle);
    $sheet->getStyle($cell)->getFont()->setBold(true);
    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($cell)->applyFromArray($styleBorderThin);
}

// -----------------------------------------------------------------------------
// 5. Data Rows
// -----------------------------------------------------------------------------
$row = 2;

foreach ($orders as $order) {
    $raw_code = $order['code'] 
             ?? $order['car_code'] 
             ?? $order['order_code'] 
             ?? $order['booking_code'] 
             ?? '';

    $code        = trim((string)$raw_code);
    $brand       = getBrandName($order);
    $region_name = $order['region_name'] ?? $order['region'] ?? '-';
    $shop_name   = $order['shop_name'] ?? $order['shop'] ?? '-';
    $color_name  = $order['color_name'] ?? $order['color'] ?? '-';

    $qty       = (int)($order['quantity'] ?? $order['qty'] ?? 0);
    $approved  = (int)($order['approved_quantity'] ?? $order['approved_qty'] ?? $order['approved'] ?? 0);
    $backorder = (int)($order['backorder_quantity'] ?? $order['backorder_qty'] ?? $order['backorder'] ?? 0);

    $region_color = getRegionColorHex($region_name);

    $sheet->setCellValue("A{$row}", $code);
    $sheet->setCellValue("B{$row}", $brand);
    $sheet->setCellValue("C{$row}", $region_name);
    $sheet->setCellValue("D{$row}", $shop_name);
    $sheet->setCellValue("E{$row}", $color_name);
    $sheet->setCellValue("F{$row}", $qty);
    $sheet->setCellValue("G{$row}", $approved);
    $sheet->setCellValue("H{$row}", $backorder);

    // Format ตัวเลข
    $sheet->getStyle("F{$row}:H{$row}")->getNumberFormat()->setFormatCode('#,##0" คัน"');

    // สีตัวหนังสือเฉพาะภาค
    $sheet->getStyle("C{$row}")->getFont()->setColor(new Color($region_color))->setBold(true);

    // จัดตำแหน่งข้อความ
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("F{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // ใส่เส้นขอบ
    $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($styleBorderThin);

    $row++;
}

// -----------------------------------------------------------------------------
// 6. Column Widths Setup
// -----------------------------------------------------------------------------
$sheet->getColumnDimension('A')->setWidth(22); // Code
$sheet->getColumnDimension('B')->setWidth(16); // ยี่ห้อรถ
$sheet->getColumnDimension('C')->setWidth(12); // ภาค
$sheet->getColumnDimension('D')->setWidth(50); // ชื่อร้านค้า / ตัวแทนจำหน่าย
$sheet->getColumnDimension('E')->setWidth(20); // สีรถ
$sheet->getColumnDimension('F')->setWidth(12); // จำนวน
$sheet->getColumnDimension('G')->setWidth(12); // อนุมัติแล้ว
$sheet->getColumnDimension('H')->setWidth(12); // แบ็คออเดอร์

// -----------------------------------------------------------------------------
// 7. Reset Selected Cell (ป้องกันการติด Hilight/คลุมดำ ที่แถวสุดท้าย)
// -----------------------------------------------------------------------------
$sheet->setSelectedCell('A1');

// -----------------------------------------------------------------------------
// 8. Output & Download
// -----------------------------------------------------------------------------
$filename = 'รายงานการสั่งจอง_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;