<?php
// sales/get_colors.php
require_once '../config/auth.php';
require_once '../config/database.php';

$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;

$stmt = $conn->prepare("SELECT color_name FROM car_colors WHERE car_id = :car_id");
$stmt->bindParam(':car_id', $car_id);
$stmt->execute();
$colors = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($colors);
exit();
?>