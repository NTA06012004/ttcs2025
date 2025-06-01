<?php
// Bật hiển thị lỗi PHP để debug (xóa hoặc comment lại khi lên production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/header.php';

// --- BƯỚC 1: KIỂM TRA ĐĂNG NHẬP VÀ CÁC THAM SỐ ĐẦU VÀO ---
if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if ($assignment_id <= 0) {
    $_SESSION['message'] = "ID bài tập không hợp lệ.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}

$current_user_id = $_SESSION['user_id'];

// --- BƯỚC 2: LẤY THÔNG TIN BÀI TẬP VÀ LỚP HỌC LIÊN QUAN ---
$stmt_assignment_info = $conn->prepare("
    SELECT a.*, c.id as class_id, c.class_name, c.teacher_id as class_teacher_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ?
");
if (!$stmt_assignment_info) { die("Lỗi SQL (prepare assignment info): " . $conn->error); }
$stmt_assignment_info->bind_param("i", $assignment_id);
$stmt_assignment_info->execute();
$result_assignment_info = $stmt_assignment_info->get_result();
if ($result_assignment_info->num_rows == 0) {
    $_SESSION['message'] = "Bài tập không tồn tại.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}
$assignment = $result_assignment_info->fetch_assoc();
$stmt_assignment_info->close();

// --- BƯỚC 3: XÁC ĐỊNH QUYỀN GIÁO VIÊN ---
// Chỉ giáo viên của lớp này mới có quyền xem trang này theo cách này
if ($assignment['class_teacher_id'] != $current_user_id) {
    $_SESSION['message'] = "Bạn không có quyền xem bài nộp cho bài tập này.";
    $_SESSION['message_type'] = "danger";
    redirect("class_view.php?id={$assignment['class_id']}&tab=assignments");
}

// --- BƯỚC 4: XỬ LÝ FORM CHẤM ĐIỂM ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submission_id_to_grade'])) {
    $submission_id_graded = (int)$_POST['submission_id_to_grade'];
    $student_id_graded_redirect = (int)$_POST['student_id_for_redirect'];
    $grade_input = trim($_POST['grade']);
    $feedback_input = trim($_POST['feedback']);
    $new_status_for_db = 'graded';

    $stmt_grade_submission = $conn->prepare("UPDATE submissions SET grade = ?, feedback = ?, status = ? WHERE id = ? AND assignment_id = ?");
    if (!$stmt_grade_submission) { die("Lỗi SQL (prepare grade submission): " . $conn->error); }
    $stmt_grade_submission->bind_param("sssii", $grade_input, $feedback_input, $new_status_for_db, $submission_id_graded, $assignment_id);
    if ($stmt_grade_submission->execute()) {
        $_SESSION['message'] = "Đã cập nhật điểm và phản hồi.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Lỗi khi cập nhật: " . $stmt_grade_submission->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt_grade_submission->close();
    // Redirect để mở lại accordion của học sinh vừa được chấm
    redirect("view_submissions.php?assignment_id=" . $assignment_id . "&open_student=" . $student_id_graded_redirect . "#accordion-item-student-" . $student_id_graded_redirect);
    exit;
}

// --- BƯỚC 5: LẤY DANH SÁCH HỌC SINH VÀ BÀI NỘP CỦA HỌ ---
$all_students_in_class_list = [];
$submitted_students_data = [];
$not_submitted_students_data = [];

// Lấy tất cả học sinh trong lớp (trừ giáo viên)
$stmt_all_students = $conn->prepare("
    SELECT u.id as student_id, u.full_name, u.email, u.profile_picture
    FROM users u
    JOIN enrollments e ON u.id = e.user_id
    WHERE e.class_id = ? AND u.id != ?
    ORDER BY u.full_name ASC
");
if (!$stmt_all_students) { die("Lỗi SQL (get all students): " . $conn->error); }
$stmt_all_students->bind_param("ii", $assignment['class_id'], $current_user_id);
$stmt_all_students->execute();
$all_students_result = $stmt_all_students->get_result();

while ($student_row = $all_students_result->fetch_assoc()) {
    $all_students_in_class_list[$student_row['student_id']] = $student_row; // Lưu trữ thông tin cơ bản của tất cả HS

    // Kiểm tra bài nộp cho từng học sinh
    $stmt_submission = $conn->prepare("
        SELECT id as submission_id, submission_text, file_path as submission_file, submitted_at, status, grade, feedback
        FROM submissions
        WHERE assignment_id = ? AND student_id = ?
    ");
    if (!$stmt_submission) { die("Lỗi SQL (get student submission): " . $conn->error); }
    $stmt_submission->bind_param("ii", $assignment_id, $student_row['student_id']);
    $stmt_submission->execute();
    $submission_result = $stmt_submission->get_result();

    if ($submission_data = $submission_result->fetch_assoc()) {
        // Học sinh này đã nộp bài
        // Xác định trạng thái đúng hạn/quá hạn dựa trên due_date của assignment và submitted_at của submission
        if (isset($submission_data['submitted_at']) && isset($assignment['due_date'])) {
             if (strtotime($submission_data['submitted_at']) > strtotime($assignment['due_date'])) {
                // Nếu status chưa phải là 'late' (ví dụ do nộp lại sau khi GV yêu cầu), thì cập nhật thành 'late'
                // Tuy nhiên, nếu status đã là 'graded', thì không đổi.
                if ($submission_data['status'] != 'graded' && $submission_data['status'] != 'late') {
                    // $submission_data['status'] = 'late'; // Cân nhắc có nên UPDATE DB ở đây không, hoặc chỉ để hiển thị
                }
            }
        }
        $submitted_students_data[] = array_merge($student_row, $submission_data);
    } else {
        // Học sinh này chưa nộp bài
        $not_submitted_students_data[] = $student_row;
    }
    $stmt_submission->close();
}
$stmt_all_students->close();

// Sắp xếp danh sách đã nộp (nếu cần, ví dụ theo thời gian nộp, trạng thái)
usort($submitted_students_data, function($a, $b) {
    // Ưu tiên: Chưa chấm > Nộp muộn chưa chấm > Nộp đúng hạn chưa chấm > Đã chấm
    $status_order = ['submitted' => 1, 'pending_grading' => 1, 'late' => 2, 'graded' => 3];
    $order_a = $status_order[$a['status']] ?? 4;
    $order_b = $status_order[$b['status']] ?? 4;
    if ($order_a != $order_b) {
        return $order_a - $order_b;
    }
    return ($a['submitted_at'] ?? 0) <=> ($b['submitted_at'] ?? 0); // Cũ hơn lên trước trong cùng status
});


$count_submitted = count($submitted_students_data);
$count_not_submitted = count($not_submitted_students_data);
$total_students_for_assignment = $count_submitted + $count_not_submitted; // Tổng số HS phải nộp
?>
<link rel="stylesheet" href="assets/css/class-view.css">
<link rel="stylesheet" href="assets/css/view-submissions.css"> 

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-card-checklist me-2"></i>Quản lý bài nộp</h1>
        <p class="text-muted mb-0">Bài tập: <strong><?php echo htmlspecialchars($assignment['title']); ?></strong></p>
        <p class="text-muted small">Lớp: <?php echo htmlspecialchars($assignment['class_name']); ?> | Hạn nộp: <?php echo date("d/m/Y H:i", strtotime($assignment['due_date'])); ?></p>
    </div>
    <a href="class_view.php?id=<?php echo $assignment['class_id']; ?>&tab=assignments" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại lớp</a>
</div>

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show mb-3">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center h-100 shadow-sm stat-card">
            <div class="card-body">
                <div class="stat-icon text-success"><i class="bi bi-check-circle-fill"></i></div>
                <h6 class="card-subtitle mt-2 mb-1 text-muted">Đã nộp</h6>
                <p class="card-text fs-2 fw-bold"><?php echo $count_submitted; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center h-100 shadow-sm stat-card">
             <div class="card-body">
                <div class="stat-icon text-danger"><i class="bi bi-x-circle-fill"></i></div>
                <h6 class="card-subtitle mt-2 mb-1 text-muted">Chưa nộp</h6>
                <p class="card-text fs-2 fw-bold"><?php echo $count_not_submitted; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center h-100 shadow-sm stat-card">
             <div class="card-body">
                <div class="stat-icon text-primary"><i class="bi bi-people-fill"></i></div>
                <h6 class="card-subtitle mt-2 mb-1 text-muted">Tổng số HS cần nộp</h6>
                <p class="card-text fs-2 fw-bold"><?php echo $total_students_for_assignment; ?></p>
            </div>
        </div>
    </div>
</div>


<?php if ($total_students_for_assignment == 0): ?>
    <div class="alert alert-light text-center shadow-sm">
        <i class="bi bi-info-circle fs-1 text-muted"></i>
        <p class="mt-2 mb-0">Hiện tại chưa có học sinh nào trong lớp (ngoài bạn) để nộp bài tập này.</p>
    </div>
<?php else: ?>
    <?php if(!empty($submitted_students_data)): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><h5 class="mb-0"><i class="bi bi-person-check-fill text-success me-2"></i>Danh sách học sinh đã nộp bài (<?php echo $count_submitted; ?>)</h5></div>
        <div class="accordion" id="submittedAccordion">
            <?php foreach ($submitted_students_data as $index => $sub_data):
                $accordion_id_s = "submitted-student-" . $sub_data['student_id'];
                // Mở accordion của sinh viên nếu có param open_student hoặc là item đầu tiên (nếu chỉ có 1 bài nộp)
                $is_expanded_s = (isset($_GET['open_student']) && $_GET['open_student'] == $sub_data['student_id']) || ($index == 0 && !isset($_GET['open_student']) && $count_submitted == 1) ;
                $submission_time_status = '';
                if (isset($sub_data['submitted_at']) && isset($assignment['due_date'])) {
                    if (strtotime($sub_data['submitted_at']) > strtotime($assignment['due_date'])) {
                        $submission_time_status = '<span class="badge bg-danger ms-2">Nộp muộn</span>';
                    } else {
                        $submission_time_status = '<span class="badge bg-success ms-2">Đúng hạn</span>';
                    }
                }
            ?>
            <div class="accordion-item" id="accordion-item-<?php echo $accordion_id_s; ?>">
                <h2 class="accordion-header" id="heading-<?php echo $accordion_id_s; ?>">
                    <button class="accordion-button <?php echo !$is_expanded_s ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $accordion_id_s; ?>" aria-expanded="<?php echo $is_expanded_s ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $accordion_id_s; ?>">
                        <span class="fw-medium"><?php echo htmlspecialchars($sub_data['full_name']); ?></span>
                        <?php echo $submission_time_status; ?>
                        <span class="ms-auto">
                            <span class="badge bg-<?php if ($sub_data['status'] == 'graded') echo 'primary'; elseif ($sub_data['status'] == 'late') echo 'warning text-dark'; else echo 'info text-dark';?> me-1 fs-08rem"><?php echo htmlspecialchars(ucfirst($sub_data['status'])); ?></span>
                            <?php if ($sub_data['grade']): ?><span class="badge bg-secondary fs-08rem">Điểm: <?php echo htmlspecialchars($sub_data['grade']); ?></span><?php endif; ?>
                        </span>
                    </button>
                </h2>
                <div id="collapse-<?php echo $accordion_id_s; ?>" class="accordion-collapse collapse <?php echo $is_expanded_s ? 'show' : ''; ?>" data-bs-parent="#submittedAccordion">
                    <div class="accordion-body">
                        <h6 class="mb-2 small text-muted">Nộp lúc: <?php echo date("d/m/Y H:i", strtotime($sub_data['submitted_at'])); ?></h6>
                        <?php if ($sub_data['submission_text']): ?><div class="p-3 bg-light border rounded mb-3 editor-content-view"><?php echo nl2br(htmlspecialchars($sub_data['submission_text'])); ?></div><?php endif; ?>
                        <?php if ($sub_data['submission_file']): ?><p class="mb-3"><strong>Tệp:</strong><a href="uploads/submission_files/<?php echo htmlspecialchars($sub_data['submission_file']); ?>" target="_blank" download class="btn btn-outline-dark btn-sm ms-2 py-0 px-2"><i class="bi bi-download me-1"></i><?php echo htmlspecialchars(substr($sub_data['submission_file'], strrpos($sub_data['submission_file'], '_') + 1));?></a></p><?php endif; ?>
                        <?php if (empty($sub_data['submission_text']) && empty($sub_data['submission_file'])): ?><p class="text-muted fst-italic">Bài nộp rỗng.</p><?php endif; ?>
                        <hr><h6 class="mb-3">Chấm điểm:</h6>
                        <form action="view_submissions.php?assignment_id=<?php echo $assignment_id; ?>" method="POST">
                            <input type="hidden" name="submission_id_to_grade" value="<?php echo $sub_data['submission_id']; ?>">
                            <input type="hidden" name="student_id_for_redirect" value="<?php echo $sub_data['student_id']; ?>">
                            <div class="row"><div class="col-md-5 col-lg-4 mb-2"><label for="grade-<?php echo $sub_data['student_id']; ?>" class="form-label small">Điểm</label><input type="text" class="form-control form-control-sm" name="grade" value="<?php echo htmlspecialchars($sub_data['grade'] ?? ''); ?>"></div></div>
                            <div class="mb-2"><label for="feedback-<?php echo $sub_data['student_id']; ?>" class="form-label small">Phản hồi</label><textarea class="form-control form-control-sm" name="feedback" rows="3"><?php echo htmlspecialchars($sub_data['feedback'] ?? ''); ?></textarea></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save-fill me-1"></i>Lưu</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!empty($not_submitted_students_data)): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light"><h5 class="mb-0"><i class="bi bi-person-x-fill text-danger me-2"></i>Danh sách học sinh chưa nộp bài (<?php echo $count_not_submitted; ?>)</h5></div>
        <div class="list-group list-group-flush">
            <?php foreach($not_submitted_students_data as $student_ns): ?>
            <div class="list-group-item d-flex align-items-center p-3">
                <span class="fw-medium"><?php echo htmlspecialchars($student_ns['full_name']); ?></span>
                <small class="text-muted ms-2">(<?php echo htmlspecialchars($student_ns['email']); ?>)</small>
                <span class="ms-auto badge bg-secondary">Chưa nộp</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<style>
    .editor-content-view { white-space: pre-wrap; font-size: 0.95em; } 
    .fs-08rem {font-size: 0.8rem !important;}
    .stat-card .stat-icon .bi { font-size: 2.5rem; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParamsForScroll = new URLSearchParams(window.location.search);
    const studentToOpen = urlParamsForScroll.get('open_student');
    if (studentToOpen) {
        const elementToScroll = document.getElementById('accordion-item-submitted-student-' + studentToOpen); // Sửa ID cho đúng
        if (elementToScroll) {
            // Đảm bảo accordion cha (nếu có) cũng mở
            const parentAccordionButton = elementToScroll.querySelector('.accordion-button.collapsed');
            if(parentAccordionButton){ // Nếu nó đang collapsed thì mở ra
                new bootstrap.Collapse(document.querySelector(parentAccordionButton.getAttribute('data-bs-target'))).show();
            }
            // Scroll tới
            setTimeout(() => { // Delay một chút để đảm bảo collapse đã mở
                 elementToScroll.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
        }
    }
     var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
