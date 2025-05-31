<?php
// Bật hiển thị lỗi PHP để debug (XÓA hoặc COMMENT LẠI khi lên PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/header.php'; // Bao gồm db.php và functions.php

if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để xem việc cần làm.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Mảng chứa tất cả các task, sẽ được phân loại sau
$all_tasks_for_user = [];

// --- 1. LẤY CÁC BÀI TẬP CẦN NỘP (KHI NGƯỜI DÙNG LÀ HỌC SINH CỦA LỚP) ---
$stmt_assignments_to_submit = $conn->prepare("
    SELECT 
        a.id as item_id,        
        a.title as item_title, 
        a.due_date, 
        c.id as class_id, 
        c.class_name,
        'assignment_to_submit' as item_type 
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN enrollments e ON c.id = e.class_id
    WHERE e.user_id = ?                 -- Là thành viên của lớp
      AND c.teacher_id != ?           -- Và KHÔNG PHẢI là giáo viên của lớp đó
      AND NOT EXISTS (                -- Và CHƯA NỘP BÀI
          SELECT 1 
          FROM submissions s 
          WHERE s.assignment_id = a.id AND s.student_id = e.user_id
      )
    ORDER BY a.due_date ASC
");

if ($stmt_assignments_to_submit) {
    $stmt_assignments_to_submit->bind_param("ii", $user_id, $user_id);
    if ($stmt_assignments_to_submit->execute()) {
        $result_student_tasks = $stmt_assignments_to_submit->get_result();
        while ($task = $result_student_tasks->fetch_assoc()) {
            $all_tasks_for_user[] = $task;
        }
    } else { error_log("Lỗi SQL execute (assignments_to_submit): " . $stmt_assignments_to_submit->error); }
    $stmt_assignments_to_submit->close();
} else { error_log("Lỗi SQL prepare (assignments_to_submit): " . $conn->error); }


// --- 2. LẤY CÁC BÀI TẬP CẦN CHẤM (KHI NGƯỜI DÙNG LÀ GIÁO VIÊN CỦA LỚP) ---
$stmt_assignments_to_grade = $conn->prepare("
    SELECT DISTINCT 
        a.id as item_id, 
        a.title as item_title, 
        a.due_date, 
        c.id as class_id, 
        c.class_name,
        (SELECT COUNT(*) 
         FROM submissions s_count 
         WHERE s_count.assignment_id = a.id 
           AND (s_count.status = 'submitted' OR s_count.status = 'late' OR s_count.status = 'pending_grading')
        ) as pending_submissions_count,
        'needs_grading' as item_type
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN submissions s ON a.id = s.assignment_id 
    WHERE c.teacher_id = ?  
      AND (s.status = 'submitted' OR s.status = 'late' OR s.status = 'pending_grading')
    ORDER BY a.due_date ASC 
");

if ($stmt_assignments_to_grade) {
    $stmt_assignments_to_grade->bind_param("i", $user_id);
    if ($stmt_assignments_to_grade->execute()) {
        $result_grading_tasks = $stmt_assignments_to_grade->get_result();
        while ($task = $result_grading_tasks->fetch_assoc()) {
            if ($task['pending_submissions_count'] > 0) { 
                 $all_tasks_for_user[] = $task;
            }
        }
    } else { error_log("Lỗi SQL execute (assignments_to_grade): " . $stmt_assignments_to_grade->error); }
    $stmt_assignments_to_grade->close();
} else { error_log("Lỗi SQL prepare (assignments_to_grade): " . $conn->error); }

// --- PHÂN LOẠI TẤT CẢ CÁC TASK VÀO CÁC MỤC ---
$tasks_categorized = [
    'overdue_submit' => [],      // HS: Quá hạn nộp
    'due_today_submit' => [],    // HS: Hạn nộp hôm nay
    'due_week_submit' => [],     // HS: Hạn nộp trong tuần
    'due_later_submit' => [],    // HS: Hạn nộp xa hơn
    'needs_grading_urgent' => [],// GV: Cần chấm (assignment đã qua hạn của HS)
    'needs_grading_normal' => [],// GV: Cần chấm (assignment chưa qua hạn hoặc không ưu tiên)
];

$today_start_ts = strtotime('today midnight');
$today_end_ts = strtotime('tomorrow midnight') - 1;
$seven_days_from_now_ts = strtotime('+7 days midnight');

foreach ($all_tasks_for_user as $task) {
    if ($task['item_type'] == 'assignment_to_submit') {
        if (empty($task['due_date'])) {
            $tasks_categorized['due_later_submit'][] = $task;
            continue;
        }
        $due_timestamp = strtotime($task['due_date']);
        if ($due_timestamp < $today_start_ts) {
            $tasks_categorized['overdue_submit'][] = $task;
        } elseif ($due_timestamp <= $today_end_ts) {
            $tasks_categorized['due_today_submit'][] = $task;
        } elseif ($due_timestamp < $seven_days_from_now_ts) {
            $tasks_categorized['due_week_submit'][] = $task;
        } else {
            $tasks_categorized['due_later_submit'][] = $task;
        }
    } elseif ($task['item_type'] == 'needs_grading') {
        if (isset($task['due_date']) && strtotime($task['due_date']) < $today_start_ts) {
            $tasks_categorized['needs_grading_urgent'][] = $task;
        } else {
            $tasks_categorized['needs_grading_normal'][] = $task;
        }
    }
}

// Hàm render (truyền các biến thời gian cần thiết)
function render_todo_section($tasks_array, $title, $icon_html, $badge_bg_class = 'bg-secondary', $no_tasks_msg = "Không có việc nào.", $current_today_start_ts, $current_today_end_ts) {
    if (empty($tasks_array)) {
        echo '<div class="alert alert-light text-muted small p-3 text-center shadow-sm">' . htmlspecialchars($no_tasks_msg) . '</div>';
        return;
    }
    echo '<h4 class="dashboard-section-title-legacy mt-4">' . $icon_html . ' ' . htmlspecialchars($title) . ' <span class="badge ' . $badge_bg_class . ' rounded-pill">' . count($tasks_array) . '</span></h4>';
    echo '<div class="list-group shadow-sm todo-list-group">';
    foreach ($tasks_array as $task) {
        $link = '#'; $action_text = 'Xem'; $due_text_color = 'text-muted'; $due_prefix = 'Hạn: ';
        $additional_info = '';

        if ($task['item_type'] == 'assignment_to_submit') {
            $link = "submit_assignment.php?assignment_id=" . $task['item_id'];
            $action_text = 'Nộp bài';
            if (isset($task['due_date'])) {
                $due_ts_task = strtotime($task['due_date']);
                if ($due_ts_task < time()) $due_text_color = 'text-danger fw-bold';
                elseif ($due_ts_task <= $current_today_end_ts ) $due_text_color = 'text-warning fw-bold';
                else $due_text_color = 'text-primary';
            }
        } elseif ($task['item_type'] == 'needs_grading') {
            $link = "view_submissions.php?assignment_id=" . $task['item_id'];
            $action_text = 'Chấm bài';
            if (isset($task['pending_submissions_count']) && $task['pending_submissions_count'] > 0) {
                $additional_info = ' <span class="badge bg-info text-dark ms-1">' . $task['pending_submissions_count'] . ' bài chờ</span>';
            }
            $due_prefix = 'Hạn nộp (HS): ';
            if (isset($task['due_date']) && strtotime($task['due_date']) < time()) $due_text_color = 'text-secondary fst-italic';
        }

        echo '<div class="list-group-item">';
        echo '  <div class="row align-items-center gy-2">';
        echo '      <div class="col">';
        echo '          <h6 class="mb-1 task-title">' . htmlspecialchars($task['item_title']) . $additional_info . '</h6>';
        echo '          <small class="text-muted d-block">Lớp: ' . htmlspecialchars($task['class_name']) . '</small>';
        if (isset($task['due_date'])) {
            echo '          <small class="task-due-date ' . $due_text_color . '">';
            echo '              <i class="bi bi-alarm-fill me-1"></i>' . $due_prefix . date("d/m/Y H:i", strtotime($task['due_date']));
            if ($task['item_type'] == 'assignment_to_submit' && strtotime($task['due_date']) < $current_today_start_ts) echo ' (Đã quá hạn!)';
            echo '          </small>';
        } elseif ($task['item_type'] == 'needs_grading') {
             echo '         <small class="text-primary d-block">Có bài nộp cần được chấm điểm.</small>';
        }
        echo '      </div>';
        echo '      <div class="col-md-auto text-md-end">';
        echo '          <a href="' . $link . '" class="btn btn-sm btn-outline-primary py-1 px-3">' . $action_text . '</a>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';
    }
    echo '</div>';
}
?>
<link rel="stylesheet" href="assets/css/todo-page.css">

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-list-check me-2"></i>Việc cần làm</h1>
    <span class="text-muted">Tổng cộng: <?php echo count($all_tasks_for_user); ?> mục cần chú ý</span>
</div>

<?php if (empty($all_tasks_for_user)): ?>
    <div class="alert alert-success text-center shadow-sm empty-state-dashboard p-5">
        <div class="empty-icon"><i class="bi bi-patch-check-fill text-success"></i></div>
        <h4 class="mt-3">Tuyệt vời!</h4>
        <p class="lead">Bạn không có công việc nào cần giải quyết vào lúc này.</p>
    </div>
<?php else: ?>
    <?php // Hiển thị các mục cần nộp của học sinh (nếu có) ?>
    <?php if (!empty($tasks_categorized['overdue_submit']) || !empty($tasks_categorized['due_today_submit']) || !empty($tasks_categorized['due_week_submit']) || !empty($tasks_categorized['due_later_submit'])): ?>
        <h3 class="text-muted fst-italic my-4 text-center">- Dành cho vai trò Học sinh -</h3>
        <?php render_todo_section($tasks_categorized['overdue_submit'], "Bài tập quá hạn nộp", '<i class="bi bi-calendar-x-fill text-danger"></i>', "bg-danger", "Không có bài tập nào quá hạn.", $today_start_ts, $today_end_ts); ?>
        <?php render_todo_section($tasks_categorized['due_today_submit'], "Bài tập đến hạn hôm nay", '<i class="bi bi-calendar-day-fill text-warning"></i>', "bg-warning text-dark", "Không có bài tập nào đến hạn hôm nay.", $today_start_ts, $today_end_ts); ?>
        <?php render_todo_section($tasks_categorized['due_week_submit'], "Bài tập đến hạn trong 7 ngày tới", '<i class="bi bi-calendar-range-fill text-info"></i>', "bg-info", "Không có bài tập nào đến hạn trong tuần này.", $today_start_ts, $today_end_ts); ?>
        <?php render_todo_section($tasks_categorized['due_later_submit'], "Các bài tập khác cần nộp", '<i class="bi bi-journals text-secondary"></i>', "bg-secondary", "Không có bài tập nào khác.", $today_start_ts, $today_end_ts); ?>
    <?php endif; ?>

    <?php // Hiển thị các mục cần chấm của giáo viên (nếu có) ?>
    <?php if (!empty($tasks_categorized['needs_grading_urgent']) || !empty($tasks_categorized['needs_grading_normal'])): ?>
        <hr class="my-5">
        <h3 class="text-muted fst-italic my-4 text-center">- Dành cho vai trò Giảng dạy -</h3>
        <?php render_todo_section($tasks_categorized['needs_grading_urgent'], "Bài tập cần chấm (Ưu tiên - Assignment đã qua hạn của HS)", '<i class="bi bi-pen-fill text-danger"></i>', "bg-danger", "Không có bài tập nào cần chấm gấp.", $today_start_ts, $today_end_ts); ?>
        <?php render_todo_section($tasks_categorized['needs_grading_normal'], "Bài tập cần chấm (Thông thường)", '<i class="bi bi-pencil-square text-primary"></i>', "bg-primary", "Không có bài tập nào khác đang chờ chấm.", $today_start_ts, $today_end_ts); ?>
    <?php endif; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>