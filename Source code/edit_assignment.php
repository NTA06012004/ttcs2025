<?php
require_once 'includes/header.php';

$assignment_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$class_id_context = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if (!isLoggedIn() || $assignment_id_to_edit <= 0 || $class_id_context <= 0) {
    $_SESSION['message'] = "Thông tin không hợp lệ hoặc bạn cần đăng nhập.";
    $_SESSION['message_type'] = "warning";
    redirect('dashboard.php');
}

$current_user_id = $_SESSION['user_id'];

// Lấy thông tin bài tập hiện tại và kiểm tra quyền sở hữu
$stmt_get_assignment = $conn->prepare("
    SELECT a.*, c.class_name, c.teacher_id 
    FROM assignments a 
    JOIN classes c ON a.class_id = c.id
    WHERE a.id = ? AND c.id = ?");
if (!$stmt_get_assignment) { die("Lỗi SQL (get assignment for edit): " . $conn->error); }
$stmt_get_assignment->bind_param("ii", $assignment_id_to_edit, $class_id_context);
$stmt_get_assignment->execute();
$result_assignment = $stmt_get_assignment->get_result();
if ($result_assignment->num_rows == 0) {
    $_SESSION['message'] = "Bài tập không tồn tại hoặc không thuộc lớp này.";
    $_SESSION['message_type'] = "danger";
    redirect('class_view.php?id=' . $class_id_context . '&tab=assignments');
}
$assignment_current_data = $result_assignment->fetch_assoc();
$stmt_get_assignment->close();

if ($assignment_current_data['teacher_id'] != $current_user_id) {
    $_SESSION['message'] = "Bạn không có quyền sửa bài tập này.";
    $_SESSION['message_type'] = "danger";
    redirect('class_view.php?id=' . $class_id_context . '&tab=assignments');
}

// Khởi tạo biến cho form với dữ liệu hiện tại
$title_input = $assignment_current_data['title'];
$description_input = $assignment_current_data['description'];
// Chuyển đổi due_date từ DB (Y-m-d H:i:s) sang định dạng cho datetime-local input (Y-m-d\TH:i)
$due_date_input_form = '';
if ($assignment_current_data['due_date']) {
    $due_date_timestamp_edit = strtotime($assignment_current_data['due_date']);
    $due_date_input_form = date('Y-m-d\TH:i', $due_date_timestamp_edit);
}
$existing_file_path = $assignment_current_data['file_path'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // (Tùy chọn) Xác thực CSRF Token
    $title_input = trim($_POST['title']);
    $description_input = trim($_POST['description']);
    $due_date_input_form = trim($_POST['due_date']);
    $file_path_for_db = $existing_file_path; // Giữ file cũ nếu không có file mới
    $delete_current_file = isset($_POST['delete_current_file']) && $_POST['delete_current_file'] == '1';


    // --- Validation (Tương tự create_assignment.php) ---
    if (empty($title_input)) $errors['title'] = "Tiêu đề không được trống.";
    // ... (thêm các validation khác) ...
    $due_date_for_db = null;
    if (empty($due_date_input_form)) { $errors['due_date'] = "Hạn nộp là bắt buộc."; }
    else { $ts = strtotime($due_date_input_form); if ($ts === false) {$errors['due_date'] = "Định dạng ngày giờ sai.";} else {$due_date_for_db = date('Y-m-d H:i:s', $ts);} }


    // --- Xử lý File Upload (Nếu có file mới hoặc yêu cầu xóa file cũ) ---
    if ($delete_current_file && $existing_file_path) {
        $file_to_delete_path = 'uploads/assignment_files/' . $existing_file_path;
        if (file_exists($file_to_delete_path)) {
            unlink($file_to_delete_path);
        }
        $file_path_for_db = null; // Đã xóa file
        $existing_file_path = null; // Cập nhật biến trạng thái
    }

    if (isset($_FILES['assignment_file_edit']) && $_FILES['assignment_file_edit']['error'] == UPLOAD_ERR_OK) {
        // ... (Logic upload file tương tự create_assignment.php) ...
        // Nếu upload thành công:
        // 1. Xóa file cũ $existing_file_path (nếu có và khác file mới)
        // 2. $file_path_for_db = $new_uploaded_filename;
        $upload_dir_edit = 'uploads/assignment_files/';
        // ... (tạo thư mục nếu chưa có) ...
        $original_edit_name = basename($_FILES['assignment_file_edit']['name']);
        $safe_edit_name = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", $original_edit_name);
        $new_edit_file_name = $class_id_context . '_' . time() . '_' . $safe_edit_name;
        $target_edit_path = $upload_dir_edit . $new_edit_file_name;
        // ... (validation type, size) ...
        if (empty($errors['file_edit'])) { // Giả sử $errors['file_edit'] dùng cho lỗi file này
            if ($existing_file_path && file_exists($upload_dir_edit . $existing_file_path)) {
                unlink($upload_dir_edit . $existing_file_path); // Xóa file cũ
            }
            if (move_uploaded_file($_FILES['assignment_file_edit']['tmp_name'], $target_edit_path)) {
                $file_path_for_db = $new_edit_file_name;
                $existing_file_path = $new_edit_file_name; // Cập nhật lại để hiển thị đúng nếu có lỗi sau đó
            } else { $errors['file_edit'] = "Lỗi tải file mới."; }
        }
    } elseif (isset($_FILES['assignment_file_edit']) && $_FILES['assignment_file_edit']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['file_edit'] = "Lỗi file: " . $_FILES['assignment_file_edit']['error'];
    }


    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ?, file_path = ? WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("ssssi", $title_input, $description_input, $due_date_for_db, $file_path_for_db, $assignment_id_to_edit);
            if ($stmt_update->execute()) {
                $_SESSION['message'] = "Bài tập đã được cập nhật thành công.";
                $_SESSION['message_type'] = "success";
                redirect("class_view.php?id={$class_id_context}&tab=assignments");
            } else {
                $errors['db_error'] = "Lỗi khi cập nhật bài tập: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else { $errors['db_error'] = "Lỗi hệ thống (prepare SQL update assignment)."; }
    }
}
?>
<link rel="stylesheet" href="assets/css/class-view.css"> 

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-pencil-square me-2"></i>Chỉnh sửa bài tập</h1>
        <p class="text-muted mb-0">Lớp: <strong><?php echo htmlspecialchars($assignment_current_data['class_name']); ?></strong></p>
    </div>
    <a href="class_view.php?id=<?php echo $class_id_context; ?>&tab=assignments" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Hủy</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-9">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php if (!empty($errors)): /* ... (Hiển thị lỗi như create_assignment.php) ... */ endif; ?>
                <form action="edit_assignment.php?id=<?php echo $assignment_id_to_edit; ?>&class_id=<?php echo $class_id_context; ?>" method="POST" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="title" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($title_input); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($description_input); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Hạn nộp <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="due_date" name="due_date" value="<?php echo htmlspecialchars($due_date_input_form); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="assignment_file_edit" class="form-label"><i class="bi bi-paperclip"></i> Tệp đính kèm</label>
                            <?php if ($existing_file_path): ?>
                                <div class="mb-2 small">
                                    Tệp hiện tại: <a href="uploads/assignment_files/<?php echo htmlspecialchars($existing_file_path); ?>" target="_blank"><?php echo htmlspecialchars(substr($existing_file_path, strpos($existing_file_path, '_', strpos($existing_file_path, '_', strpos($existing_file_path, '_') + 1) + 1) + 1)); ?></a>
                                    <div class="form-check form-check-inline ms-2">
                                      <input class="form-check-input" type="checkbox" id="delete_current_file" name="delete_current_file" value="1">
                                      <label class="form-check-label small" for="delete_current_file">Xóa tệp này?</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="assignment_file_edit" name="assignment_file_edit">
                            <div class="form-text">Tải lên tệp mới sẽ thay thế tệp cũ (nếu có). Tối đa 15MB.</div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="text-end">
                        <a href="class_view.php?id=<?php echo $class_id_context; ?>&tab=assignments" class="btn btn-outline-secondary me-2">Hủy</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill me-2"></i>Lưu thay đổi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>