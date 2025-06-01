<?php
require_once 'includes/header.php'; // Đã bao gồm db.php và functions.php
?>
<link rel="stylesheet" href="assets/css/class-view.css">
<?php
if (!isLoggedIn()) { redirect('login.php'); }

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($class_id <= 0) {
    $_SESSION['message'] = "ID lớp học không hợp lệ.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}

$user_id = $_SESSION['user_id'];
// Lấy thông tin user hiện tại, dùng cho avatar ở khung tạo bài mới và các kiểm tra khác
$user_info_current = get_user_by_id($conn, $user_id);
if (!$user_info_current) {
    session_unset(); session_destroy(); redirect('login.php?error=user_not_found');
}

// Lấy thông tin lớp học, bao gồm tên và avatar của giáo viên
$stmt_class = $conn->prepare("
    SELECT c.*, u.full_name as teacher_name, u.profile_picture as teacher_avatar
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ?
");
if (!$stmt_class) { die("Lỗi chuẩn bị SQL (lấy thông tin lớp): " . $conn->error); }
$stmt_class->bind_param("i", $class_id);
$stmt_class->execute();
$result_class = $stmt_class->get_result();
if ($result_class->num_rows == 0) {
    $_SESSION['message'] = "Lớp học không tồn tại.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}
$class = $result_class->fetch_assoc();
$stmt_class->close();

$current_user_is_teacher_of_this_class = ($class['teacher_id'] == $user_id);
$current_user_is_enrolled = isEnrolledInClass($conn, $user_id, $class_id);

if (!$current_user_is_enrolled) {
    $_SESSION['message'] = "Bạn không phải là thành viên của lớp học này.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}

$active_tab = $_GET['tab'] ?? 'assignments'; // Mặc định là tab Bảng tin & Bài tập

$class_banner_image = 'assets/images/classroom-background.jpg';
// Optional: if (!empty($class['class_theme_image']) && file_exists('uploads/class_themes/' . $class['class_theme_image'])) { $class_banner_image = 'uploads/class_themes/' . $class['class_theme_image']; }

// Fetch members
$members = [];
$stmt_members = $conn->prepare("SELECT u.id, u.full_name, u.email, u.profile_picture FROM users u JOIN enrollments e ON u.id = e.user_id WHERE e.class_id = ? ORDER BY u.full_name ASC");
if (!$stmt_members) { die("Lỗi SQL (lấy thành viên): " . $conn->error); }
$stmt_members->bind_param("i", $class_id);
$stmt_members->execute();
$result_members = $stmt_members->get_result();
$student_count_for_progress = 0;
while ($row_member = $result_members->fetch_assoc()) {
    $row_member['is_teacher_role_in_class'] = ($row_member['id'] == $class['teacher_id']);
    $members[] = $row_member;
    if (!$row_member['is_teacher_role_in_class']) {
        $student_count_for_progress++;
    }
}
$stmt_members->close();
usort($members, function($a, $b) { return $b['is_teacher_role_in_class'] - $a['is_teacher_role_in_class']; });

// Fetch assignments
$assignments = [];
$stmt_assignments = $conn->prepare("
    SELECT a.*,
    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as total_submissions,
    (SELECT s.id FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) as student_submission_id,
    (SELECT s.status FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) as student_submission_status,
    (SELECT s.grade FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) as student_submission_grade
    FROM assignments a
    WHERE a.class_id = ?
    ORDER BY a.created_at DESC
");
if (!$stmt_assignments) { die("Lỗi SQL (lấy bài tập): " . $conn->error); }
$stmt_assignments->bind_param("iiii", $user_id, $user_id, $user_id, $class_id);
$stmt_assignments->execute();
$result_assignments = $stmt_assignments->get_result();
while ($row_assign = $result_assignments->fetch_assoc()) {
    $assignments[] = $row_assign;
}
$stmt_assignments->close();
?>

<div class="class-header-banner mb-4 shadow-sm" style="background-image: url('<?php echo htmlspecialchars($class_banner_image); ?>');">
    <div class="container">
        <div class="class-header-content">
            <h1 class="class-title display-5 animate-on-load"><?php echo htmlspecialchars($class['class_name']); ?></h1>
            <p class="class-teacher lead animate-on-load" style="animation-delay: 0.2s;">
                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($class['teacher_avatar'] ?: 'default.png'); ?>" 
                     alt="<?php echo htmlspecialchars($class['teacher_name']); ?>" 
                     class="profile-picture-nav me-2" 
                     onerror="this.onerror=null;this.src='uploads/profile_pictures/default.png';">
                Giáo viên: <?php echo htmlspecialchars($class['teacher_name']); ?>
            </p>
            <?php if ($current_user_is_teacher_of_this_class): ?>
                <p class="class-code-info mb-0 animate-on-load" style="animation-delay: 0.4s;">Mã lớp: 
                    <strong class="user-select-all bg-light p-1 rounded text-dark" title="Nhấn để sao chép" onclick="copyToClipboard('<?php echo htmlspecialchars($class['class_code']); ?>', this)">
                        <?php echo htmlspecialchars($class['class_code']); ?> <i class="bi bi-clipboard-check ms-1"></i>
                    </strong>
                </p>
            <?php endif; ?>
        </div>
        <div class="class-header-actions animate-on-load" style="animation-delay: 0.6s;">
             <?php if ($current_user_is_teacher_of_this_class): ?>
                <a href="create_assignment.php?class_id=<?php echo $class_id; ?>" class="btn btn-light btn-sm me-2" data-bs-toggle="tooltip" title="Tạo bài tập hoặc thông báo mới">
                    <i class="bi bi-plus-circle-fill me-1"></i> Tạo mới
                </a>
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#deleteClassModal" title="Xóa lớp học này">
                    <i class="bi bi-trash3-fill"></i> <span class="d-none d-md-inline">Xóa lớp</span>
                </button>
            <?php elseif ($current_user_is_enrolled): ?>
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#leaveClassModal" title="Rời khỏi lớp học này">
                    <i class="bi bi-box-arrow-left me-1"></i> Rời lớp
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
    <ul class="nav nav-pills gc-nav-pills mb-4 justify-content-center justify-content-md-start">
        <li class="nav-item"><a class="nav-link <?php echo ($active_tab == 'assignments') ? 'active' : ''; ?>" href="class_view.php?id=<?php echo $class_id; ?>&tab=assignments" id="assignments-tab-link"><i class="bi bi-journals me-1"></i>Bảng tin & Bài tập</a></li>
        <li class="nav-item"><a class="nav-link <?php echo ($active_tab == 'members') ? 'active' : ''; ?>" href="class_view.php?id=<?php echo $class_id; ?>&tab=members" id="members-tab-link"><i class="bi bi-people-fill me-1"></i>Thành viên <span class="badge rounded-pill bg-light text-dark ms-1"><?php echo count($members);?></span></a></li>
        <li class="nav-item"><a class="nav-link <?php echo ($active_tab == 'info') ? 'active' : ''; ?>" href="class_view.php?id=<?php echo $class_id; ?>&tab=info" id="info-tab-link"><i class="bi bi-info-circle-fill me-1"></i>Thông tin lớp</a></li>
        <?php if ($current_user_is_teacher_of_this_class): ?><li class="nav-item"><a class="nav-link <?php echo ($active_tab == 'progress') ? 'active' : ''; ?>" href="class_view.php?id=<?php echo $class_id; ?>&tab=progress" id="progress-tab-link"><i class="bi bi-graph-up-arrow me-1"></i>Tiến độ</a></li><?php endif; ?>
    </ul>

    <div class="tab-content" id="classTabContent">
        <div class="tab-pane fade <?php echo ($active_tab == 'assignments') ? 'show active' : ''; ?>" id="assignmentsPanel" role="tabpanel" aria-labelledby="assignments-tab-link">
            <div class="row">
                <div class="col-lg-3 col-md-4 d-none d-md-block order-md-first class-view-sidebar"><div class="sticky-top" style="top: 80px;"><div class="card mb-4"><div class="card-header bg-light"><h6 class="mb-0 fw-medium"><i class="bi bi-calendar-week-fill text-primary me-2"></i>Sắp đến hạn</h6></div><div class="list-group list-group-flush" style="max-height: 250px; overflow-y:auto;"><?php $upcoming_due_assignments_sidebar = array_filter($assignments, function($a) { return isset($a['due_date']) && strtotime($a['due_date']) >= time() && strtotime($a['due_date']) <= strtotime('+7 days'); }); if (empty($upcoming_due_assignments_sidebar)): ?><span class="list-group-item text-muted small py-3 text-center">Không có gì sắp đến hạn.</span><?php else: usort($upcoming_due_assignments_sidebar, function($a, $b){ return strtotime($a['due_date']) - strtotime($b['due_date']); }); foreach (array_slice($upcoming_due_assignments_sidebar, 0, 5) as $upcoming_assign_sidebar): ?><a href="#assignment-item-<?php echo $upcoming_assign_sidebar['id']; ?>" class="list-group-item list-group-item-action small py-2 class-view-nav-link"><div class="fw-bold text-truncate"><?php echo htmlspecialchars($upcoming_assign_sidebar['title']); ?></div><div class="text-danger small"><i class="bi bi-alarm-fill me-1"></i>Hạn: <?php echo date("D, d/m", strtotime($upcoming_assign_sidebar['due_date'])); ?></div></a><?php endforeach; endif; ?></div></div><div class="card"><div class="card-header bg-light"><h6 class="mb-0 fw-medium"><i class="bi bi-folder2-open text-success me-2"></i>Tài liệu lớp</h6></div><div class="list-group list-group-flush"><a href="view_class_documents.php?class_id=<?php echo $class_id; ?>" class="list-group-item list-group-item-action small py-2"><i class="bi bi-files me-2"></i>Xem tất cả tài liệu</a></div></div></div></div>
                <div class="col-lg-9 col-md-8 order-md-last">
                    <?php if ($current_user_is_teacher_of_this_class && isset($user_info_current) && $user_info_current): // Thêm isset để chắc chắn ?>
                    <div class="card shadow-sm mb-4 new-post-card">
                        <div class="card-body d-flex align-items-center p-3">
                            <img src="assets/images/class-default.png" alt="Không có bài tập" style="max-width: 180px; opacity: 0.6;" class="mb-3">
                            <a href="create_assignment.php?class_id=<?php echo $class_id; ?>" 
                               class="form-control text-muted new-post-trigger" 
                               style="cursor:pointer; line-height: 2.5;">
                                Đăng thông báo hoặc tạo bài tập mới...
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (empty($assignments)): ?><div class="text-center p-5 bg-light rounded shadow-sm"><img src="assets/images/assignment-default.png" alt="Không có bài tập" style="max-width: 180px; opacity: 0.6;" class="mb-3"><h5 class="text-muted">Lớp học hiện chưa có hoạt động nào.</h5><?php if ($current_user_is_teacher_of_this_class): ?><p class="text-muted">Hãy bắt đầu bằng cách tạo bài tập hoặc thông báo đầu tiên cho lớp.</p><a href="create_assignment.php?class_id=<?php echo $class_id; ?>" class="btn btn-primary"><i class="bi bi-plus-circle-fill me-1"></i>Tạo bài tập / Thông báo</a><?php else: ?><p class="text-muted">Vui lòng kiểm tra lại sau hoặc liên hệ với giáo viên của bạn.</p><?php endif; ?></div>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment):
                            $is_past_due = isset($assignment['due_date']) ? strtotime($assignment['due_date']) < time() : false;
                            $due_date_formatted = isset($assignment['due_date']) ? date("d/m/Y H:i", strtotime($assignment['due_date'])) : 'Không có hạn';
                            $assignment_icon = "bi-file-earmark-text-fill"; if (stripos($assignment['title'], 'kiểm tra') !== false || stripos($assignment['title'], 'trắc nghiệm') !== false) $assignment_icon = "bi-patch-question-fill"; elseif (stripos($assignment['title'], 'thảo luận') !== false) $assignment_icon = "bi-chat-left-dots-fill"; elseif (stripos($assignment['title'], 'thông báo') !== false) $assignment_icon = "bi-megaphone-fill";
                            $submission_status_badge_class = ''; $submission_status_text = '';
                            if (!$current_user_is_teacher_of_this_class) { if (isset($assignment['student_submission_id']) && $assignment['student_submission_id']) { $status_display = ''; switch ($assignment['student_submission_status']) { case 'graded': $status_display = 'Đã chấm: ' . (isset($assignment['student_submission_grade']) && $assignment['student_submission_grade'] ? htmlspecialchars($assignment['student_submission_grade']) : 'N/A'); $submission_status_badge_class = 'bg-success'; break; case 'submitted': case 'pending_grading': $status_display = 'Đã nộp'; $submission_status_badge_class = 'bg-info text-dark'; break; case 'late': $status_display = 'Nộp muộn'; if (isset($assignment['student_submission_grade']) && $assignment['student_submission_grade']) { $status_display .= ' - Đã chấm: ' . htmlspecialchars($assignment['student_submission_grade']); $submission_status_badge_class = 'bg-success'; } else { $submission_status_badge_class = 'bg-warning text-dark'; } break; default: $status_display = ucfirst(htmlspecialchars($assignment['student_submission_status'] ?? '')); break; } $submission_status_text = $status_display; } elseif ($is_past_due) { $submission_status_badge_class = 'bg-danger'; $submission_status_text = 'Quá hạn'; } else { $submission_status_badge_class = 'bg-secondary'; $submission_status_text = 'Chưa nộp'; } }
                        ?>
                        <div class="card shadow-sm mb-3 assignment-feed-item" id="assignment-item-<?php echo $assignment['id']; ?>"><div class="card-body p-3"><div class="d-flex align-items-start"><div class="assignment-item-icon me-3"><i class="bi <?php echo $assignment_icon; ?>"></i></div><div class="flex-grow-1"><div class="d-flex justify-content-between align-items-center"><h6 class="mb-0 assignment-title"><a href="view_assignment.php?assignment_id=<?php echo $assignment['id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($assignment['title']); ?></a></h6><?php if (!$current_user_is_teacher_of_this_class && !empty($submission_status_text)): ?><span class="badge <?php echo $submission_status_badge_class; ?> ms-2 flex-shrink-0"><?php echo $submission_status_text; ?></span><?php elseif($current_user_is_teacher_of_this_class): ?><span class="text-muted small flex-shrink-0"><?php echo htmlspecialchars($assignment['total_submissions'] ?? 0); ?>/<?php echo $student_count_for_progress; ?> đã nộp</span><?php endif; ?></div><small class="text-muted assignment-meta d-block">Đăng bởi <?php echo htmlspecialchars($class['teacher_name']); ?> - <?php echo date("d/m/Y", strtotime($assignment['created_at'])); ?><?php if (isset($assignment['due_date']) && $assignment['due_date']): ?><span class="mx-1">•</span> Hạn: <span class="<?php echo ($is_past_due && empty($assignment['student_submission_id']) && !$current_user_is_teacher_of_this_class) ? 'text-danger fw-bold' : ''; ?>"><?php echo $due_date_formatted; ?></span><?php endif; ?></small>
                        <div class="mt-2" id="desc-<?php echo $assignment['id']; ?>"> <?php /* Bỏ class collapse ở đây để hiện mặc định, nếu muốn ẩn thì thêm lại */ ?>
                            <?php if(!empty($assignment['description']) && strlen($assignment['description']) > 120 ): ?><p class="assignment-description-short small text-muted mt-1 mb-1"><?php echo nl2br(htmlspecialchars(substr($assignment['description'], 0, 120))); ?>... <a href="view_assignment.php?assignment_id=<?php echo $assignment['id']; ?>" class="small">Xem thêm</a></p>
                            <?php elseif(!empty($assignment['description'])): ?><p class="assignment-description small text-muted mt-1 mb-1"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p><?php endif; ?>
                            <?php if ($assignment['file_path']): ?><div class="mb-2"><a href="uploads/assignment_files/<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank" download class="btn btn-sm btn-outline-secondary py-1 px-2"><i class="bi bi-paperclip"></i> <?php echo htmlspecialchars(substr($assignment['file_path'], strpos($assignment['file_path'], '_', strpos($assignment['file_path'], '_', strpos($assignment['file_path'], '_') + 1) + 1) + 1)); ?></a></div><?php endif; ?>
                            <div class="assignment-actions mt-2">
                                <?php if ($current_user_is_teacher_of_this_class): ?>
                                    <a href="view_submissions.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2"><i class="bi bi-people-fill me-1"></i>Xem bài nộp</a>
                                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>&class_id=<?php echo $class_id; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 ms-1" data-bs-toggle="tooltip" title="Sửa bài tập"><i class="bi bi-pencil-fill"></i></a>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 ms-1" data-bs-toggle="modal" data-bs-target="#deleteAssignmentModal-<?php echo $assignment['id']; ?>" data-bs-toggle="tooltip" title="Xóa bài tập này"><i class="bi bi-trash3-fill"></i></button>
                                <?php else: ?>
                                    <?php if (isset($assignment['student_submission_id']) && $assignment['student_submission_id']): ?>
                                         <a href="view_assignment.php?assignment_id=<?php echo $assignment['id']; ?>&submission_id=<?php echo $assignment['student_submission_id']; ?>&student_id=<?php echo $user_id; ?>" class="btn btn-sm btn-success py-1 px-2"><i class="bi bi-file-earmark-check-fill me-1"></i>Xem bài của tôi</a>
                                         <?php $can_resubmit_this = (!$is_past_due && isset($assignment['student_submission_status']) && $assignment['student_submission_status'] !== 'graded'); ?>
                                         <?php if ($can_resubmit_this): ?><a href="submit_assignment.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-warning py-1 px-2 ms-1"><i class="bi bi-pencil-fill me-1"></i>Sửa bài nộp</a><?php endif; ?>
                                    <?php elseif (!$is_past_due): ?><a href="submit_assignment.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary py-1 px-2"><i class="bi bi-upload me-1"></i>Nộp bài</a>
                                    <?php else: ?><button class="btn btn-sm btn-secondary py-1 px-2 disabled"><i class="bi bi-upload me-1"></i>Đã quá hạn</button><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div></div></div></div></div>
                        <?php if ($current_user_is_teacher_of_this_class): ?><div class="modal fade" id="deleteAssignmentModal-<?php echo $assignment['id']; ?>" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title fs-6"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Xóa bài tập?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body small">Xóa "<strong><?php echo htmlspecialchars($assignment['title']); ?></strong>" và các bài nộp?</div><div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><a href="delete_assignment.php?id=<?php echo $assignment['id']; ?>&class_id=<?php echo $class_id; ?>" class="btn btn-sm btn-danger">Xóa</a></div></div></div></div><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?php echo ($active_tab == 'members') ? 'show active' : ''; ?>" id="membersPanel" role="tabpanel" aria-labelledby="members-tab-link"><div class="d-flex justify-content-between align-items-center mb-3"><h4><i class="bi bi-people-fill text-primary me-2"></i>Thành viên (<?php echo count($members); ?>)</h4><?php if ($current_user_is_teacher_of_this_class): ?><button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#inviteMemberModal"><i class="bi bi-person-plus-fill me-1"></i> Mời</button><?php endif; ?></div><div class="list-group shadow-sm"><?php foreach ($members as $member): ?><div class="list-group-item d-flex justify-content-between align-items-center member-item p-3"><div class="d-flex align-items-center"><img src="uploads/profile_pictures/<?php echo htmlspecialchars($member['profile_picture'] ?: 'default.png'); ?>" alt="<?php echo htmlspecialchars($member['full_name']); ?>" class="profile-picture-nav me-3" onerror="this.onerror=null;this.src='uploads/profile_pictures/default.png';"><div><h6 class="mb-0 fw-medium"><?php echo htmlspecialchars($member['full_name']); ?></h6><small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small></div></div><div><?php if ($member['is_teacher_role_in_class']): ?><span class="badge bg-primary rounded-pill px-2 py-1">GV</span><?php else: ?><span class="badge bg-light text-dark border rounded-pill px-2 py-1">HS</span><?php if ($current_user_is_teacher_of_this_class && $member['id'] != $user_id): ?><button class="btn btn-sm btn-icon text-danger ms-2" data-bs-toggle="modal" data-bs-target="#removeMemberModal-<?php echo $member['id'];?>" title="Xóa"><i class="bi bi-person-x-fill"></i></button><div class="modal fade" id="removeMemberModal-<?php echo $member['id'];?>" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title fs-6">Xóa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body small">Xóa <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>?</div><div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><a href="remove_member.php?class_id=<?php echo $class_id; ?>&user_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-danger">Xóa</a></div></div></div></div><?php endif; ?><?php endif; ?></div></div><?php endforeach; ?></div></div>
        <div class="tab-pane fade <?php echo ($active_tab == 'info') ? 'show active' : ''; ?>" id="infoPanel" role="tabpanel" aria-labelledby="info-tab-link"><div class="card shadow-sm"><div class="card-header"><h5 class="mb-0"><i class="bi bi-info-square-fill me-2 text-primary"></i>Giới thiệu lớp</h5></div><div class="card-body"><?php if(empty($class['description'])): ?><p class="fst-italic text-muted">Chưa có mô tả.</p><?php else: ?><p class="lead-sm"><?php echo nl2br(htmlspecialchars($class['description'])); ?></p><?php endif; ?></div></div><div class="card shadow-sm mt-4"><div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-check-fill me-2 text-success"></i>Nội quy</h5></div><div class="card-body"><ul><li>Hoàn thành bài tập đúng hạn.</li><li>Tôn trọng mọi người.</li><li>Tích cực tham gia.</li></ul></div></div></div>
        <?php if ($current_user_is_teacher_of_this_class): ?><div class="tab-pane fade <?php echo ($active_tab == 'progress') ? 'show active' : ''; ?>" id="progressPanel" role="tabpanel" aria-labelledby="progress-tab-link"><div class="d-flex justify-content-between align-items-center mb-3"><h4><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Tiến độ lớp</h4><button class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Xuất báo cáo"><i class="bi bi-download me-1"></i> Xuất</button></div><?php if (empty($assignments)): ?><div class="alert alert-light text-center">Chưa có bài tập.</div><?php elseif ($student_count_for_progress == 0): ?><div class="alert alert-light text-center">Chưa có học sinh.</div><?php else: ?><div class="table-responsive card shadow-sm"><table class="table table-hover table-striped align-middle mb-0"><thead class="table-light"><tr><th scope="col" style="width: 40%;">Bài tập</th><th scope="col" class="text-center">Ngày giao</th><th scope="col" class="text-center">Hạn nộp</th><th scope="col" class="text-center">Đã nộp</th><th scope="col" class="text-center" style="min-width: 180px;">Hoàn thành</th></tr></thead><tbody><?php foreach ($assignments as $assignment): $completion_rate = ($student_count_for_progress > 0) ? (($assignment['total_submissions'] / $student_count_for_progress) * 100) : 0; ?><tr><td><a href="view_submissions.php?assignment_id=<?php echo $assignment['id']; ?>" class="text-decoration-none fw-medium"><?php echo htmlspecialchars($assignment['title']); ?></a></td><td class="text-center text-muted small"><?php echo date("d/m/y", strtotime($assignment['created_at'])); ?></td><td class="text-center text-muted small"><?php echo date("d/m/y H:i", strtotime($assignment['due_date'])); ?></td><td class="text-center"><?php echo $assignment['total_submissions']; ?> / <?php echo $student_count_for_progress; ?></td><td><div class="progress" style="height: 20px;" role="progressbar" aria-valuenow="<?php echo $completion_rate; ?>"><div class="progress-bar progress-bar-striped <?php if ($completion_rate == 100) echo 'bg-success'; elseif ($completion_rate >= 70) echo 'bg-info'; elseif ($completion_rate >= 40) echo 'bg-warning text-dark'; else echo 'bg-danger';?>" style="width: <?php echo $completion_rate; ?>%;"><?php echo round($completion_rate); ?>%</div></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><?php endif; ?>
    </div>
</div>

<?php if ($current_user_is_teacher_of_this_class): ?>
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title" id="deleteClassModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Xác nhận Xóa Lớp học</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>Bạn có chắc chắn muốn xóa vĩnh viễn lớp học "<strong><?php echo htmlspecialchars($class['class_name']); ?></strong>"?</p><p class="text-danger small"><strong>CẢNH BÁO:</strong> Hành động này không thể hoàn tác. Toàn bộ dữ liệu liên quan sẽ bị xóa.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><a href="delete_class.php?class_id=<?php echo $class_id; ?>" class="btn btn-danger"><i class="bi bi-trash3-fill me-1"></i> Tôi hiểu, xóa</a></div></div></div></div>
<?php endif; ?>
<?php if ($current_user_is_enrolled && !$current_user_is_teacher_of_this_class): ?>
<div class="modal fade" id="leaveClassModal" tabindex="-1" aria-labelledby="leaveClassModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-warning text-dark"><h5 class="modal-title" id="leaveClassModalLabel"><i class="bi bi-exclamation-circle-fill me-2"></i>Xác nhận Rời Lớp</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>Bạn có chắc muốn rời khỏi lớp "<strong><?php echo htmlspecialchars($class['class_name']); ?></strong>"?</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><a href="leave_class.php?class_id=<?php echo $class_id; ?>" class="btn btn-warning text-dark"><i class="bi bi-box-arrow-left me-1"></i> Rời lớp</a></div></div></div></div>
<?php endif; ?>
<div class="modal fade" id="inviteMemberModal" tabindex="-1" aria-labelledby="inviteMemberModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="inviteMemberModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Mời thành viên</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>Chia sẻ mã lớp: <div class="input-group"><input type="text" class="form-control fs-4 text-center user-select-all" value="<?php echo htmlspecialchars($class['class_code']); ?>" readonly id="classCodeToCopyModal"><button class="btn btn-outline-primary" type="button" onclick="copyToClipboardModal('classCodeToCopyModal', this)" data-bs-toggle="tooltip" title="Sao chép"><i class="bi bi-clipboard-check-fill"></i></button></div></p><p class="small text-muted mt-3">Hoặc gửi email mời (đang phát triển).</p><input type="email" class="form-control" placeholder="Email học sinh (cách nhau bằng dấu phẩy)" disabled></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button><button type="button" class="btn btn-primary" disabled>Gửi lời mời</button></div></div></div></div>

<style>
    .animate-on-load { opacity: 0; transform: translateY(15px); animation: fadeInUpSmooth 0.5s ease-out forwards; }
    @keyframes fadeInUpSmooth { to { opacity: 1; transform: translateY(0); } }
    .class-view-nav-link:hover { background-color: var(--gc-hover-bg); } /* Biến --gc-hover-bg cần định nghĩa trong style.css */
    .assignment-feed-item .assignment-title a .bi-chevron-down { font-size: 0.7em; color: var(--gc-text-secondary); transition: transform 0.2s ease-in-out;}
    .assignment-feed-item .assignment-title a[aria-expanded="true"] .bi-chevron-down { transform: rotate(-180deg); }
    .highlight-item-class-view { animation: highlightAnimationClassView 2s ease-out; border-radius: var(--bs-card-border-radius); } /* Biến --bs-card-border-radius cần định nghĩa */
    @keyframes highlightAnimationClassView { 0%, 70% { background-color: rgba(255, 235, 59, 0.3); } 100% { background-color: transparent; } }
    .assignment-description-short { /* Để đảm bảo giới hạn dòng hoạt động */
        line-height: 1.4em; /* Hoặc giá trị phù hợp */
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    let activeTabQuery = urlParams.get('tab') || 'assignments';
    const validTabs = ['assignments', 'members', 'info', 'progress'];
    if (!validTabs.includes(activeTabQuery)) { activeTabQuery = 'assignments'; }

    const tabLinkQuery = document.querySelector(`.gc-nav-pills .nav-link[href*="tab=${activeTabQuery}"]`);
    const tabPaneQuery = document.getElementById(activeTabQuery + "Panel");

    if (tabLinkQuery && tabPaneQuery) {
        document.querySelectorAll('.gc-nav-pills .nav-link').forEach(link => link.classList.remove('active'));
        document.querySelectorAll('.tab-content .tab-pane').forEach(pane => pane.classList.remove('show', 'active'));
        tabLinkQuery.classList.add('active');
        tabPaneQuery.classList.add('show', 'active');
    } else {
        const firstTabLink = document.querySelector('.gc-nav-pills .nav-link[href*="tab=assignments"]');
        const firstTabPane = document.getElementById("assignmentsPanel");
        if (firstTabLink && firstTabPane) {
            document.querySelectorAll('.gc-nav-pills .nav-link').forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.tab-content .tab-pane').forEach(pane => pane.classList.remove('show', 'active'));
            firstTabLink.classList.add('active');
            firstTabPane.classList.add('show', 'active');
        }
    }
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) });

    if(window.location.hash && window.location.hash.startsWith('#assignment-item-')) {
        const targetElement = document.querySelector(window.location.hash);
        if (targetElement) {
            const collapseTargetId = targetElement.querySelector('[data-bs-toggle="collapse"]')?.getAttribute('data-bs-target');
            if(collapseTargetId) {
                const collapseElement = document.querySelector(collapseTargetId);
                // if (collapseElement && !collapseElement.classList.contains('show')) { new bootstrap.Collapse(collapseElement).show(); }
                // Không tự động mở collapse nữa, để người dùng tự mở nếu muốn xem chi tiết từ link
            }
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetElement.classList.add('highlight-item-class-view');
            setTimeout(() => targetElement.classList.remove('highlight-item-class-view'), 2000);
        }
    }
    const classCodeElements = document.querySelectorAll('.user-select-all[onclick*="copyToClipboard"]');
    classCodeElements.forEach(el => { el.addEventListener('click', function() { const code = this.innerText.trim().split(' ')[0]; copyToClipboard(code, this); }); });
});
function copyToClipboard(text, clickedElement) { navigator.clipboard.writeText(text).then(function() { let originalText = "Sao chép mã lớp"; let tooltipInstance = bootstrap.Tooltip.getInstance(clickedElement); if (clickedElement && clickedElement.getAttribute('data-bs-original-title')) { originalText = clickedElement.getAttribute('data-bs-original-title');} else if (clickedElement.parentNode.matches('[data-bs-toggle="tooltip"]')) { tooltipInstance = bootstrap.Tooltip.getInstance(clickedElement.parentNode); if(tooltipInstance) originalText = clickedElement.parentNode.getAttribute('data-bs-original-title');} if (tooltipInstance) { tooltipInstance.setContent({ '.tooltip-inner': 'Đã sao chép!' }); setTimeout(() => { tooltipInstance.setContent({ '.tooltip-inner': originalText }); }, 2000); } else { const tempText = clickedElement.innerHTML; clickedElement.innerHTML = '<i class="bi bi-check-lg"></i> Đã sao chép!'; setTimeout(() => { clickedElement.innerHTML = tempText;}, 2000);}}, function(err) { console.error('Không thể sao chép: ', err); alert('Không thể sao chép mã lớp.');});}
function copyToClipboardModal(elementId, buttonElement) { const textToCopy = document.getElementById(elementId).value; navigator.clipboard.writeText(textToCopy).then(function() { const tooltip = bootstrap.Tooltip.getInstance(buttonElement); if (tooltip) { tooltip.setContent({ '.tooltip-inner': 'Đã sao chép!' }); setTimeout(() => { tooltip.setContent({ '.tooltip-inner': 'Sao chép mã lớp' }); }, 2000); }}, function(err) { alert('Không thể sao chép mã lớp.');});}
</script>

<?php require_once 'includes/footer.php'; ?>