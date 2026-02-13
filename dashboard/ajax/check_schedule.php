<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

$db = new Database();

$teacher_id = $_POST['teacher_id'];
$day_id = $_POST['day_id'];
$shift_id = $_POST['shift_id'];
$schedule_id = $_POST['schedule_id'];

$sql = "SELECT COUNT(*) as total FROM teacher_schedule 
        WHERE teacher_id = ? 
        AND day_id = ? 
        AND shift_id = ? 
        AND schedule_id != ?";

$stmt = $db->query($sql, [$teacher_id, $day_id, $shift_id, $schedule_id]);
$result = $stmt->fetch();

echo json_encode(['conflict' => $result['total'] > 0]);
?>