<?php

namespace App\Support\Enums;

/**
 * Attendance status for a user in a club schedule.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Sick = 'sick';
    case Excused = 'excused';
}
