<?php
class DatabaseQueries {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Query konsisten untuk data guru
    public function getTeacherData($teacher_id = null) {
        $sql = "SELECT 
            t.id,
            COALESCE(t.teacher_name, ts.teacher_name) as teacher_name,
            COALESCE(t.teacher_code, ts.teacher_code) as teacher_code,
            t.subject as default_subject,
            t.email,
            t.phone,
            t.is_active
        FROM teacher t
        LEFT JOIN teacher_schedule ts ON t.id = ts.teacher_id";
        
        if ($teacher_id) {
            $sql .= " WHERE t.id = ?";
            return $this->db->query($sql, [$teacher_id])->fetch();
        }
        
        return $this->db->query($sql)->fetchAll();
    }
    
    // Query untuk jadwal dengan data konsisten
    public function getStudentSchedule($student_id) {
        $sql = "SELECT 
            ts.schedule_id,
            ts.subject,
            ts.class_id,
            COALESCE(t.teacher_name, ts.teacher_name) as teacher_name,
            COALESCE(t.teacher_code, ts.teacher_code) as teacher_code,
            d.day_name,
            d.day_order,
            sh.shift_name,
            sh.time_in,
            sh.time_out,
            CASE 
                WHEN sh.shift_name = 'Full Day' THEN 0
                ELSE 1
            END as check_attendance_time,
            (SELECT COUNT(*) FROM presence p 
             JOIN student_schedule ss ON p.student_schedule_id = ss.student_schedule_id
             WHERE ss.teacher_schedule_id = ts.schedule_id 
             AND p.student_id = ? 
             AND DATE(p.presence_date) = CURDATE()) as attendance_count
        FROM teacher_schedule ts
        LEFT JOIN teacher t ON ts.teacher_id = t.id
        JOIN day d ON ts.day_id = d.day_id
        JOIN shift sh ON ts.shift_id = sh.shift_id
        WHERE ts.class_id = (SELECT class_id FROM student WHERE id = ?)
        AND d.is_active = 'Y'
        ORDER BY d.day_order, sh.time_in";
        
        return $this->db->query($sql, [$student_id, $student_id])->fetchAll();
    }
}
?>