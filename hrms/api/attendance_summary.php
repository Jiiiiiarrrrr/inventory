<?php
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/guard.php";
$pdo = conn();
hrms_init($pdo);
hrms_require_roles(["superadmin"]); // ONLY superadmin can get whole month attendance summary.

function date_key($dt) { return date('Y-m-d', strtotime($dt)); }
function minutes_between($a, $b) { return max(0, intval(round((strtotime($b) - strtotime($a)) / 60))); }
function ceil_half_hour($m) { return $m <= 0 ? 0 : intval(ceil($m / 30) * 30); }
function hm($min) { return floor($min/60) . "h " . ($min%60) . "m"; }
function parse_days($days) { return array_filter(array_map('trim', explode(',', str_replace(['Mon–Fri','Mon–Sat','Tue–Sat','Weekends'], ['Mon,Tue,Wed,Thu,Fri','Mon,Tue,Wed,Thu,Fri,Sat','Tue,Wed,Thu,Fri,Sat','Sat,Sun'], $days ?? '')))); }

$employeeId = intval($_GET['employee_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');
if ($employeeId <= 0) hrms_json_error('employee_id is required', 400);
if (!preg_match('/^\d{4}-\d{2}$/', $month)) hrms_json_error('month must be YYYY-MM', 400);

$empStmt = $pdo->prepare("SELECT * FROM hrms_employees WHERE id=? LIMIT 1");
$empStmt->execute([$employeeId]);
$employee = $empStmt->fetch();
if (!$employee) hrms_json_error('Employee not found', 404);

$start = $month . '-01';
$end = date('Y-m-d', strtotime($start . ' +1 month'));

// hrms_schedules stores the shift times in columns named `start` / `end`
// (VARCHAR "HH:MM"), not `start_time` / `end_time`. Aliasing here so the
// rest of this file (which reads $schedule['start_time']) doesn't change.
$schedStmt = $pdo->prepare("SELECT days, start AS start_time, end AS end_time FROM hrms_schedules WHERE employee_id=? AND (approval_status='Approved' OR approval_status IS NULL) LIMIT 1");
$schedStmt->execute([$employeeId]);
$schedule = $schedStmt->fetch();
$workDays = parse_days($schedule['days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
$startTime = $schedule['start_time'] ?? '08:00';
$endTime = $schedule['end_time'] ?? '16:00';

$attStmt = $pdo->prepare("SELECT * FROM hrms_attendance_records WHERE employee_id=? AND DATE(created_at)>=? AND DATE(created_at)<? ORDER BY created_at ASC");
$attStmt->execute([$employeeId,$start,$end]);
$records = $attStmt->fetchAll();
$group = [];
foreach ($records as $r) {
    $d = date_key($r['created_at']);
    if (!isset($group[$d])) $group[$d] = ['ins'=>[], 'outs'=>[]];
    if ($r['type']==='time_in') $group[$d]['ins'][] = $r;
    if ($r['type']==='time_out') $group[$d]['outs'][] = $r;
}

$rows=[]; $totWorked=0; $totLate=0; $totUnder=0; $present=0; $absent=0; $incomplete=0; $dayOff=0;
for ($ts=strtotime($start); $ts<strtotime($end); $ts=strtotime('+1 day',$ts)) {
    $date = date('Y-m-d',$ts);
    $day = date('D',$ts);
    $isWorkDay = in_array($day, $workDays);
    $g = $group[$date] ?? ['ins'=>[], 'outs'=>[]];
    $in = $g['ins'][0] ?? null;
    $out = count($g['outs']) ? $g['outs'][count($g['outs'])-1] : null;
    $worked = ($in && $out) ? minutes_between($in['created_at'], $out['created_at']) : 0;
    $rawLate = ($in && $isWorkDay) ? max(0, minutes_between($date.' '.$startTime.':00', $in['created_at'])) : 0;
    $rawUnder = ($out && $isWorkDay) ? max(0, minutes_between($out['created_at'], $date.' '.$endTime.':00')) : 0;
    $late = ceil_half_hour($rawLate);
    $under = ceil_half_hour($rawUnder);
    if (!$isWorkDay && !$in && !$out) { $status='Day Off'; $dayOff++; }
    elseif ($in && $out) { $status = ($late>0 && $under>0) ? 'Late + Undertime' : ($late>0 ? 'Late' : ($under>0 ? 'Undertime' : 'Present')); $present++; }
    elseif ($in || $out) { $status='Incomplete'; $incomplete++; }
    else { $status='Absent'; $absent++; }
    $totWorked += $worked; $totLate += $late; $totUnder += $under;
    $rows[] = ['date'=>$date,'day'=>$day,'time_in'=>$in['created_at'] ?? null,'time_out'=>$out['created_at'] ?? null,'worked_minutes'=>$worked,'worked'=>hm($worked),'late_minutes'=>$late,'late'=>hm($late),'undertime_minutes'=>$under,'undertime'=>hm($under),'status'=>$status,'in_photo'=>$in['photo_path'] ?? null,'out_photo'=>$out['photo_path'] ?? null];
}
response(['success'=>true,'employee'=>$employee,'month'=>$month,'schedule'=>$schedule,'summary'=>['present'=>$present,'absent'=>$absent,'incomplete'=>$incomplete,'day_off'=>$dayOff,'worked_minutes'=>$totWorked,'worked'=>hm($totWorked),'late_minutes'=>$totLate,'late'=>hm($totLate),'undertime_minutes'=>$totUnder,'undertime'=>hm($totUnder)],'rows'=>$rows]);