<?php
require_once 'includes/header.php';

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if (!isLoggedIn() || $assignment_id <= 0) {
    $_SESSION['message'] = $assignment_id <= 0 ? "ID bài tập không hợp lệ." : "Bạn cần đăng nhập để nộp bài.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$current_student_id = $_SESSION['user_id'];

// Lấy thông tin bài tập và lớp
$stmt_assign = $conn->prepare("
    SELECT a.*, c.id as class_id, c.class_name, c.teacher_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ?
");
if (!$stmt_assign) { /* Lỗi SQL prepare */ die("Lỗi chuẩn bị SQL: " . $conn->error); }
$stmt_assign->bind_param("i", $assignment_id);
$stmt_assign->execute();
$result_assign = $stmt_assign->get_result();

if ($result_assign->num_rows == 0) {
    $_SESSION['message'] = "Bài tập không tồn tại.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}
$assignment = $result_assign->fetch_assoc();
$stmt_assign->close();

// Kiểm tra xem user có phải là thành viên của lớp không VÀ không phải là GV của lớp đó
$is_legit_student_for_this_assignment = isEnrolledInClass($conn, $current_student_id, $assignment['class_id']) && ($assignment['teacher_id'] != $current_student_id);

if (!$is_legit_student_for_this_assignment) {
    $_SESSION['message'] = "Bạn không có quyền nộp bài cho bài tập này.";
    $_SESSION['message_type'] = "danger";
    redirect("class_view.php?id={$assignment['class_id']}");
}

// Kiểm tra bài nộp cũ
$existing_submission = null;
$stmt_check_sub = $conn->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
$stmt_check_sub->bind_param("ii", $assignment_id, $current_student_id);
$stmt_check_sub->execute();
$result_check_sub = $stmt_check_sub->get_result();
if ($result_check_sub->num_rows > 0) {
    $existing_submission = $result_check_sub->fetch_assoc();
}
$stmt_check_sub->close();

$is_view_mode = isset($_GET['view']) && $_GET['view'] == '1' && $existing_submission;
// Chính sách nộp lại: Ví dụ, cho phép nộp lại nếu chưa đến hạn VÀ bài chưa được chấm (hoặc trạng thái không phải là 'graded')
$is_past_due = strtotime($assignment['due_date']) < time();
$can_resubmit = false;
if ($existing_submission) {
    $can_resubmit = !$is_past_due && ($existing_submission['status'] !== 'graded');
}

$submission_text_input = $is_view_mode ? ($existing_submission['submission_text'] ?? '') : ($_POST['submission_text'] ?? ($existing_submission['submission_text'] ?? ''));
$errors = [];
$submission_allowed = !$existing_submission || $can_resubmit;

// Nếu đã quá hạn và chưa nộp lần nào, mặc định vẫn cho nộp (sẽ bị đánh dấu 'late')
// Nếu muốn cấm nộp muộn hoàn toàn, thay đổi logic ở đây.
// if ($is_past_due && !$existing_submission) {
//     $submission_allowed = false; // Cấm nộp muộn
// }


if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_view_mode) {
    if (!$submission_allowed) {
        $_SESSION['message'] = $is_past_due ? "Đã quá hạn nộp bài." : "Bạn không thể nộp lại bài tập này.";
        $_SESSION['message_type'] = "warning";
        // redirect("submit_assignment.php?assignment_id={$assignment_id}" . ($existing_submission ? "&view=1" : "") ); // Chuyển về trang xem/nộp
        // exit;
    } else { // Được phép nộp / nộp lại
        $submission_text_input = trim($_POST['submission_text']);
        $file_path_for_db = $existing_submission['file_path'] ?? null; // Giữ file cũ nếu không tải lên file mới khi nộp lại

        // Validation cho nội dung nộp
        if (empty($submission_text_input) && (empty($_FILES['submission_file']) || $_FILES['submission_file']['error'] == UPLOAD_ERR_NO_FILE) && !$file_path_for_db) {
            // Lỗi nếu là lần nộp đầu và không có gì, HOẶC là nộp lại mà xóa hết nội dung cũ và không tải file mới
            $errors['submission'] = "Bạn phải nhập nội dung hoặc tải lên một tệp.";
        }

        // Xử lý file tải lên (nếu có file mới)
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/submission_files/';
            if (!is_dir($upload_dir)) {
                if(!mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)){
                     $errors['file'] = "Lỗi hệ thống: Không thể tạo thư mục tải lên.";
                }
            }
            
            if(!isset($errors['file'])){
                $original_file_name = basename($_FILES['submission_file']['name']);
                $safe_original_name = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", $original_file_name);
                $new_file_name = $current_student_id . '_' . $assignment_id . '_' . time() . '_' . $safe_original_name;
                $target_file_path = $upload_dir . $new_file_name;
                $file_type = strtolower(pathinfo($target_file_path, PATHINFO_EXTENSION));
                $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'ppt', 'pptx', 'xls', 'xlsx', 'mp4', 'mov', 'mp3', 'webm', 'ogg'];
                $max_file_size = 20 * 1024 * 1024; // 20MB

                if ($_FILES['submission_file']['size'] > $max_file_size) {
                    $errors['file'] = "Tệp quá lớn (tối đa 20MB).";
                } elseif (!in_array($file_type, $allowed_types)) {
                    $errors['file'] = "Loại tệp không được phép. Cho phép: " . implode(', ', $allowed_types);
                }

                if (empty($errors['file'])) {
                    // Nếu là nộp lại và có file mới, xóa file cũ (nếu có)
                    if ($existing_submission && $existing_submission['file_path'] && $existing_submission['file_path'] != $new_file_name) {
                        $old_file_to_delete = $upload_dir . $existing_submission['file_path'];
                        if (file_exists($old_file_to_delete)) {
                            unlink($old_file_to_delete);
                        }
                    }
                    if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_file_path)) {
                        $file_path_for_db = $new_file_name;
                    } else {
                        $errors['file'] = "Lỗi khi tải tệp nộp bài lên máy chủ.";
                    }
                }
            }
        } elseif (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] != UPLOAD_ERR_NO_FILE) {
            $errors['file'] = "Lỗi tải tệp. Mã: " . $_FILES['submission_file']['error'];
        }


        if (empty($errors)) {
            $current_time = date('Y-m-d H:i:s');
            $status = 'submitted';
            if (strtotime($assignment['due_date']) < strtotime($current_time)) {
                $status = 'late';
            }

            if ($existing_submission) { // Cập nhật bài nộp cũ (nếu $can_resubmit = true)
                $stmt_update = $conn->prepare("UPDATE submissions SET submission_text = ?, file_path = ?, status = ?, submitted_at = ? WHERE id = ?");
                if ($stmt_update) {
                    $stmt_update->bind_param("ssssi", $submission_text_input, $file_path_for_db, $status, $current_time, $existing_submission['id']);
                    if ($stmt_update->execute()) {
                        $_SESSION['message'] = "Bài nộp của bạn đã được cập nhật.";
                        $_SESSION['message_type'] = "success";
                        redirect("class_view.php?id={$assignment['class_id']}&tab=assignments");
                    } else {
                        $errors['db_error'] = "Lỗi khi cập nhật bài nộp: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                } else {
                     $errors['db_error'] = "Lỗi hệ thống: Không thể chuẩn bị câu lệnh SQL (update).";
                }
            } else { // Nộp bài lần đầu
                $stmt_insert = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, submission_text, file_path, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt_insert) {
                    $stmt_insert->bind_param("iissss", $assignment_id, $current_student_id, $submission_text_input, $file_path_for_db, $status, $current_time);
                    if ($stmt_insert->execute()) {
                        $_SESSION['message'] = "Nộp bài thành công!";
                        $_SESSION['message_type'] = "success";
                        redirect("class_view.php?id={$assignment['class_id']}&tab=assignments");
                    } else {
                        $errors['db_error'] = "Lỗi khi nộp bài: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                     $errors['db_error'] = "Lỗi hệ thống: Không thể chuẩn bị câu lệnh SQL (insert).";
                }
            }
        }
    } // end if $submission_allowed
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-pencil-square me-2"></i><?php echo $is_view_mode ? 'Bài nộp của bạn' : 'Nộp bài tập'; ?></h1>
        <p class="text-muted mb-0">Bài tập: <strong><?php echo htmlspecialchars($assignment['title']); ?></strong> (Lớp: <?php echo htmlspecialchars($assignment['class_name']); ?>)</p>
        <p class="text-muted">Hạn nộp: <span class="<?php echo $is_past_due ? 'text-danger fw-bold' : '';?>"><?php echo date("d/m/Y H:i", strtotime($assignment['due_date'])); ?> <?php if($is_past_due) echo "(Đã qua hạn)";?></span></p>
    </div>
    <a href="class_view.php?id=<?php echo $assignment['class_id']; ?>&tab=assignments" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại lớp</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <p class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Vui lòng sửa các lỗi sau:</p>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error_message): ?><li class="mb-0"><?php echo htmlspecialchars($error_message); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($is_view_mode): ?>
                    <h5 class="mb-3">Nội dung đã nộp:</h5>
                    <?php if (!empty($existing_submission['submission_text'])): ?>
                        <h6><i class="bi bi-file-text me-1"></i>Văn bản trả lời:</h6>
                        <div class="p-3 bg-light border rounded mb-3 editor-content-view">
                            <?php echo nl2br(htmlspecialchars($existing_submission['submission_text'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($existing_submission['file_path'])): ?>
                        <h6><i class="bi bi-paperclip me-1"></i>Tệp đính kèm:</h6>
                        <p><a href="uploads/submission_files/<?php echo htmlspecialchars($existing_submission['file_path']); ?>" target="_blank" download class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i><?php echo htmlspecialchars($existing_submission['file_path']); ?></a></p>
                    <?php endif; ?>
                    <?php if (empty($existing_submission['submission_text']) && empty($existing_submission['file_path'])): ?>
                        <p class="text-muted">Không có nội dung hoặc tệp nào được nộp.</p>
                    <?php endif; ?>
                    <hr>
                    <p><strong>Nộp lúc:</strong> <?php echo date("d/m/Y H:i", strtotime($existing_submission['submitted_at'])); ?></p>
                    <p><strong>Trạng thái:</strong> <span class="badge bg-<?php
                        if ($existing_submission['status'] == 'graded') echo 'success';
                        elseif ($existing_submission['status'] == 'late') echo 'warning text-dark';
                        else echo 'info text-dark';
                    ?> fs-6"><?php echo htmlspecialchars(ucfirst($existing_submission['status'])); ?></span></p>
                     <?php if ($existing_submission['grade']): ?>
                        <p><strong>Điểm:</strong> <span class="fw-bold fs-5 text-primary"><?php echo htmlspecialchars($existing_submission['grade']); ?></span></p>
                    <?php endif; ?>
                    <?php if ($existing_submission['feedback']): ?>
                        <h6 class="mt-3">Phản hồi của giáo viên:</h6>
                        <div class="p-3 bg-light border rounded">
                            <?php echo nl2br(htmlspecialchars($existing_submission['feedback'])); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($can_resubmit && !$is_past_due): ?>
                         <hr>
                         <a href="submit_assignment.php?assignment_id=<?php echo $assignment_id; ?>" class="btn btn-warning"><i class="bi bi-pencil-fill me-1"></i>Chỉnh sửa bài nộp</a>
                    <?php endif; ?>

                <?php elseif (!$submission_allowed && $existing_submission && !$can_resubmit): // Đã nộp và không cho nộp lại ?>
                     <div class="alert alert-info">
                        <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>Bạn đã nộp bài!</h5>
                        <p>Nộp lúc: <?php echo date("d/m/Y H:i", strtotime($existing_submission['submitted_at'])); ?>.</p>
                        <p>Trạng thái: <strong><?php echo htmlspecialchars(ucfirst($existing_submission['status'])); ?></strong>.</p>
                        <hr>
                        <p class="mb-0"><a href="submit_assignment.php?assignment_id=<?php echo $assignment_id; ?>&view=1" class="btn btn-primary"><i class="bi bi-eye-fill me-1"></i>Xem chi tiết bài nộp</a></p>
                    </div>
                <?php elseif (!$submission_allowed && $is_past_due): // Quá hạn và chưa nộp, hoặc không cho nộp lại khi quá hạn ?>
                     <div class="alert alert-warning text-center">
                        <i class="bi bi-exclamation-triangle-fill fs-1 text-warning"></i>
                        <h5 class="alert-heading mt-2"><?php echo $existing_submission ? 'Không thể nộp lại' : 'Đã quá hạn nộp!'; ?></h5>
                        <p><?php echo $existing_submission ? 'Bài tập này đã quá hạn và/hoặc không cho phép nộp lại.' : 'Rất tiếc, bạn không thể nộp bài cho bài tập này nữa vì đã quá thời gian quy định.';?></p>
                     </div>
                <?php else: // Form để nộp hoặc nộp lại (nếu $can_resubmit=true) ?>
                    <?php if ($is_past_due && !$existing_submission): ?>
                        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Lưu ý:</strong> Bạn đang nộp bài sau thời hạn quy định. Bài nộp sẽ được đánh dấu là "Nộp muộn".</div>
                    <?php elseif ($existing_submission && $can_resubmit): ?>
                        <div class="alert alert-info"><i class="bi bi-pencil-square me-2"></i>Bạn đang chỉnh sửa bài nộp trước đó.</div>
                    <?php endif; ?>
                    <form action="submit_assignment.php?assignment_id=<?php echo $assignment_id; ?>" method="POST" enctype="multipart/form-data" novalidate>
                        <div class="mb-3">
                            <label for="submission_text" class="form-label">Nội dung bài làm (nhập trực tiếp)</label>
                            <textarea class="form-control <?php echo isset($errors['submission']) ? 'is-invalid' : ''; ?>" id="submission_text" name="submission_text" rows="10" placeholder="Nhập câu trả lời của bạn ở đây..."><?php echo htmlspecialchars($submission_text_input); ?></textarea>
                            <?php if (isset($errors['submission'])): ?><div class="invalid-feedback"><?php echo $errors['submission']; ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="submission_file" class="form-label"><i class="bi bi-paperclip"></i> Tải lên tệp bài làm</label>
                            <?php if($existing_submission && $existing_submission['file_path']): ?>
                                <p class="form-text">Tệp hiện tại: <a href="uploads/submission_files/<?php echo htmlspecialchars($existing_submission['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($existing_submission['file_path']); ?></a>. Tải tệp mới sẽ thay thế tệp này.</p>
                            <?php endif; ?>
                            <input type="file" class="form-control <?php echo isset($errors['file']) ? 'is-invalid' : ''; ?>" id="submission_file" name="submission_file">
                            <div class="form-text">Tối đa 20MB. Các định dạng phổ biến.</div>
                            <?php if (isset($errors['file'])): ?><div class="invalid-feedback d-block"><?php echo $errors['file']; ?></div><?php endif; ?>
                        </div>
                        <hr class="my-4">
                        <div class="text-end">
                            <a href="class_view.php?id=<?php echo $assignment['class_id']; ?>&tab=assignments" class="btn btn-outline-secondary me-2">Hủy</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i><?php echo ($existing_submission && $can_resubmit) ? 'Cập nhật bài nộp' : 'Nộp bài'; ?></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<style>
    .editor-content-view { white-space: pre-wrap; font-size: 0.95em; }
</style>
<?php require_once 'includes/footer.php'; ?>