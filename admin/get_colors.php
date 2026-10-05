<?php
/**
 * admin/get_colors.php - ดึงรายการสีตามยี่ห้อและรุ่นรถ
 */
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$brand = $_GET['brand'] ?? '';
$model = $_GET['model'] ?? '';

if (empty($brand) || empty($model)) {
    echo json_encode([]);
    exit;
}

try {
    // ค้นหาสีทั้งหมดจากตาราง car_colors ผ่านตาราง cars ที่ตรงกับ brand และ model_name
    $sql = "SELECT DISTINCT cc.color_name 
            FROM car_colors cc
            JOIN cars c ON cc.car_id = c.id
            WHERE c.brand = ? AND c.model_name = ?
            ORDER BY cc.color_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$brand, $model]);
    $colors = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($colors);
} catch (Exception $e) {
    echo json_encode([]);
}