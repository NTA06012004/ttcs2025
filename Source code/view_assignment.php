<?php
require_once 'includes/header.php'; // Bao gồm db.php và functions.php

// --- KIỂM TRA ĐĂNG NHẬP VÀ CÁC THAM SỐ CẦN THIẾT ---
if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để truy cập trang này.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$submission_id_to_view = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : null; // Dành cho học sinh xem bài của mình
$student_id_param = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null; // Dành cho học sinh xem bài của mình

if ($assignment_id <= 0) {
    $_SESSION['message'] = "ID bài tập không hợp lệ.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}

$current_user_id = $_SESSION['user_id'];

// --- LẤY THÔNG TIN BÀI TẬP VÀ LỚP ---
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

// --- XÁC ĐỊNH VAI TRÒ VÀ QUYỀN TRUY CẬP ---
$is_teacher_viewing_all = ($assignment['class_teacher_id'] == $current_user_id && !$submission_id_to_view);
$is_student_viewing_own = ($submission_id_to_view && $student_id_param && $student_id_param == $current_user_id);

if (!$is_teacher_viewing_all && !$is_student_viewing_own) {
    // Nếu không phải GV xem tất cả, cũng không phải HS xem bài của mình thì không có quyền
    $_SESSION['message'] = "Bạn không có quyền truy cập trang này.";
    $_SESSION['message_type'] = "danger";
    redirect("class_view.php?id={$assignment['class_id']}&tab=assignments");
}

// Nếu là học sinh xem bài của mình, kiểm tra thêm submission_id có hợp lệ không
if ($is_student_viewing_own) {
    $stmt_check_own_submission = $conn->prepare("SELECT id FROM submissions WHERE id = ? AND assignment_id = ? AND student_id = ?");
    if (!$stmt_check_own_submission) { die("Lỗi SQL (check own submission): " . $conn->error); }
    $stmt_check_own_submission->bind_param("iii", $submission_id_to_view, $assignment_id, $current_user_id);
    $stmt_check_own_submission->execute();
    if ($stmt_check_own_submission->get_result()->num_rows == 0) {
        $_SESSION['message'] = "Không tìm thấy bài nộp của bạn hoặc bạn không có quyền xem.";
        $_SESSION['message_type'] = "warning";
        redirect("class_view.php?id={$assignment['class_id']}&tab=assignments");
    }
    $stmt_check_own_submission->close();
}

// --- XỬ LÝ FORM CHẤM ĐIỂM (CHỈ CHO GIÁO VIÊN) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submission_id_to_grade']) && $is_teacher_viewing_all) {
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
    redirect("view_submissions.php?assignment_id=" . $assignment_id . "&open_student=" . $student_id_graded_redirect . "#heading-student-" . $student_id_graded_redirect);
    exit;
}

// --- LẤY DỮ LIỆU BÀI NỘP ĐỂ HIỂN THỊ ---
$submissions_data_to_display = [];
if ($is_student_viewing_own) {
    $stmt_single = $conn->prepare("
        SELECT u.id as student_id, u.full_name, u.email, u.profile_picture, s.*
        FROM submissions s JOIN users u ON s.student_id = u.id
        WHERE s.id = ? AND s.assignment_id = ? AND s.student_id = ?");
    if(!$stmt_single) { die("Lỗi SQL: " . $conn->error); }
    $stmt_single->bind_param("iii", $submission_id_to_view, $assignment_id, $current_user_id);
    $stmt_single->execute();
    $result_single = $stmt_single->get_result();
    if($row = $result_single->fetch_assoc()) { $submissions_data_to_display[] = $row; }
    $stmt_single->close();
} elseif ($is_teacher_viewing_all) {
    $stmt_all = $conn->prepare("
        SELECT u.id as student_id, u.full_name, u.email, u.profile_picture,
               s.id as submission_id, s.submission_text, s.submission_file as submission_file, s.submitted_at, s.status, s.grade, s.feedback
        FROM users u JOIN enrollments e ON u.id = e.user_id
        LEFT JOIN submissions s ON u.id = s.student_id AND s.assignment_id = ?
        WHERE e.class_id = ? AND u.id != ? ORDER BY u.full_name ASC");
    if(!$stmt_all) { die("Lỗi SQL: " . $conn->error); }
    $stmt_all->bind_param("iii", $assignment_id, $assignment['class_id'], $current_teacher_id);
    $stmt_all->execute();
    $result_all = $stmt_all->get_result();
    while ($row = $result_all->fetch_assoc()) { $submissions_data_to_display[] = $row; }
    $stmt_all->close();
}
?>
<link rel="stylesheet" href="assets/css/class-view.css">

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi <?php echo $is_student_viewing_own ? 'bi-file-earmark-person-fill' : 'bi-card-checklist'; ?> me-2"></i><?php echo $is_student_viewing_own ? 'Bài nộp của bạn' : 'Quản lý bài nộp'; ?></h1>
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


<?php if (empty($submissions_data_to_display)): ?>
    <div class="alert alert-info text-center shadow-sm">
        <i class="bi <?php echo $is_student_viewing_own ? 'bi-emoji-frown' : 'bi-people-fill'; ?> fs-1 text-muted"></i>
        <p class="mt-2 mb-0"><?php echo $is_student_viewing_own ? 'Không tìm thấy bài nộp của bạn cho bài tập này.' : 'Chưa có học sinh nào nộp bài hoặc không có học sinh trong lớp (ngoài bạn).'; ?></p>
    </div>
<?php elseif ($is_student_viewing_own):
    $submission_detail = $submissions_data_to_display[0];
?>
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Chi tiết bài nộp của bạn</h5>
            <span class="badge bg-<?php
                if ($submission_detail['status'] == 'graded') echo 'success';
                elseif ($submission_detail['status'] == 'late') echo 'warning text-dark';
                else echo 'info text-dark';
            ?> fs-08rem"><?php echo htmlspecialchars(ucfirst($submission_detail['status'])); ?></span>
        </div>
        <div class="card-body">
            <p><strong>Nộp lúc:</strong> <?php echo date("d/m/Y H:i", strtotime($submission_detail['submitted_at'])); ?></p>
            <?php if ($submission_detail['submission_text']): ?>
                <h6><i class="bi bi-file-text-fill me-1 text-primary"></i>Nội dung văn bản:</h6>
                <div class="p-3 bg-light border rounded mb-3 editor-content-view">
                    <?php echo nl2br(htmlspecialchars($submission_detail['submission_text'])); ?>
                </div>
            <?php endif; ?>
            <?php if ($submission_detail['submission_file']): ?>
                <h6><i class="bi bi-paperclip me-1 text-success"></i>Tệp đính kèm:</h6>
                <p><a href="uploads/submission_files/<?php echo htmlspecialchars($submission_detail['submission_file']); ?>" target="_blank" download class="btn btn-outline-success btn-sm">
                    <i class="bi bi-download me-1"></i><?php echo htmlspecialchars(substr($submission_detail['submission_file'], strpos($submission_detail['submission_file'], '_', strpos($submission_detail['submission_file'], '_', strpos($submission_detail['submission_file'], '_')+1)+1)+1)); ?>
                </a></p>
            <?php endif; ?>
            <?php if (empty($submission_detail['submission_text']) && empty($submission_detail['submission_file'])): ?>
                <p class="text-muted fst-italic">Không có nội dung hoặc tệp nào được nộp.</p>
            <?php endif; ?>

            <?php if ($submission_detail['status'] == 'graded'): ?>
                <hr class="my-4">
                <h5 class="text-success"><i class="bi bi-check-circle-fill me-2"></i>Kết quả & Phản hồi</h5>
                <?php if ($submission_detail['grade']): ?>
                    <p><strong>Điểm số:</strong> <span class="fw-bold fs-5 text-primary"><?php echo htmlspecialchars($submission_detail['grade']); ?></span></p>
                <?php endif; ?>
                <?php if ($submission_detail['feedback']): ?>
                    <h6 class="mt-3">Phản hồi từ giáo viên:</h6>
                    <div class="p-3 bg-light border rounded editor-content-view">
                        <?php echo nl2br(htmlspecialchars($submission_detail['feedback'])); ?>
                    </div>
                <?php endif; ?>
                 <?php if (empty($submission_detail['grade']) && empty($submission_detail['feedback'])): ?>
                    <p class="text-muted">Bài của bạn đã được chấm nhưng chưa có điểm hoặc phản hồi chi tiết.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: // Giáo viên xem tất cả bài nộp ?>
    <div class="card shadow-sm">
        <div class="card-header bg-light"><h5 class="mb-0">Danh sách bài nộp của học sinh (<?php echo count($submissions_data_to_display); ?>)</h5></div>
        <div class="accordion" id="submissionsAccordionView">
            <?php foreach ($submissions_data_to_display as $index => $sub_data):
                $accordion_item_id_prefix = "student-" . $sub_data['student_id'];
                $is_accordion_expanded = (isset($_GET['open_student']) && $_GET['open_student'] == $sub_data['student_id']) || ($index == 0 && !isset($_GET['open_student']) && count($submissions_data_to_display) == 1); // Mở item đầu nếu chỉ có 1 hoặc có param
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?php echo $accordion_item_id_prefix; ?>">
                    <button class="accordion-button <?php echo !$is_accordion_expanded ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $accordion_item_id_prefix; ?>" aria-expanded="<?php echo $is_accordion_expanded ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $accordion_item_id_prefix; ?>">
                        <img src="uploads/profile_pictures/<?php echo htmlspecialchars($sub_data['profile_picture'] ?: 'default.png'); ?>" alt="<?php echo htmlspecialchars($sub_data['full_name']); ?>" class="profile-picture-nav me-2" onerror="this.onerror=null;this.src='uploads/profile_pictures/default.png';">
                        <span class="fw-medium"><?php echo htmlspecialchars($sub_data['full_name']); ?></span>
                        <small class="text-muted ms-2">(<?php echo htmlspecialchars($sub_data['email']); ?>)</small>
                        <span class="ms-auto">
                        <?php if ($sub_data['submission_id']): ?>
                            <span class="badge bg-<?php if ($sub_data['status'] == 'graded') echo 'success'; elseif ($sub_data['status'] == 'late') echo 'warning text-dark'; else echo 'info text-dark';?> me-1 fs-08rem"><?php echo htmlspecialchars(ucfirst($sub_data['status'])); ?></span>
                            <?php if ($sub_data['grade']): ?><span class="badge bg-primary fs-08rem">Điểm: <?php echo htmlspecialchars($sub_data['grade']); ?></span><?php endif; ?>
                        <?php else: ?><span class="badge bg-danger fs-08rem">Chưa nộp</span><?php endif; ?>
                        </span>
                    </button>
                </h2>
                <div id="collapse-<?php echo $accordion_item_id_prefix; ?>" class="accordion-collapse collapse <?php echo $is_accordion_expanded ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $accordion_item_id_prefix; ?>" data-bs-parent="#submissionsAccordionView">
                    <div class="accordion-body">
                        <?php if ($sub_data['submission_id']): ?>
                            <h6 class="mb-2">Nội dung nộp <small class="text-muted fw-normal fst-italic">- Nộp lúc: <?php echo date("d/m/Y H:i", strtotime($sub_data['submitted_at'])); ?></small></h6>
                            <?php if ($sub_data['submission_text']): ?><div class="p-3 bg-light border rounded mb-3 editor-content-view"><?php echo nl2br(htmlspecialchars($sub_data['submission_text'])); ?></div><?php endif; ?>
                            <?php if ($sub_data['submission_file']): ?><p class="mb-3"><strong>Tệp đính kèm:</strong><a href="uploads/submission_files/<?php echo htmlspecialchars($sub_data['submission_file']); ?>" target="_blank" download class="btn btn-outline-success btn-sm ms-2"><i class="bi bi-download me-1"></i><?php echo htmlspecialchars(substr($sub_data['submission_file'], strpos($sub_data['submission_file'], '_', strpos($sub_data['submission_file'], '_', strpos($sub_data['submission_file'], '_')+1)+1)+1)); ?></a></p><?php endif; ?>
                            <?php if (empty($sub_data['submission_text']) && empty($sub_data['submission_file'])): ?><p class="text-muted fst-italic">Không có nội dung hoặc tệp nào được nộp.</p><?php endif; ?>
                            <hr class="my-3"><h6 class="mb-3">Chấm điểm và Phản hồi:</h6>
                            <form action="view_submissions.php?assignment_id=<?php echo $assignment_id; ?>" method="POST">
                                <input type="hidden" name="submission_id_to_grade" value="<?php echo $sub_data['submission_id']; ?>">
                                <input type="hidden" name="student_id_for_redirect" value="<?php echo $sub_data['student_id']; ?>">
                                <div class="row"><div class="col-md-5 col-lg-4 mb-3"><label for="grade-<?php echo $sub_data['student_id']; ?>" class="form-label">Điểm số</label><input type="text" class="form-control form-control-sm" id="grade-<?php echo $sub_data['student_id']; ?>" name="grade" value="<?php echo htmlspecialchars($sub_data['grade'] ?? ''); ?>" placeholder="VD: 8.5/10"></div></div>
                                <div class="mb-3"><label for="feedback-<?php echo $sub_data['student_id']; ?>" class="form-label">Phản hồi</label><textarea class="form-control form-control-sm" id="feedback-<?php echo $sub_data['student_id']; ?>" name="feedback" rows="4" placeholder="Nhận xét..."><?php echo htmlspecialchars($sub_data['feedback'] ?? ''); ?></textarea></div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save-fill me-1"></i>Lưu</button>
                            </form>
                        <?php else: ?><p class="text-muted fst-italic">Học sinh này chưa nộp bài.</p><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<style>.editor-content-view { white-space: pre-wrap; font-size: 0.95em; } .fs-08rem {font-size: 0.8rem !important;}</style>
<?php require_once 'includes/footer.php'; ?>