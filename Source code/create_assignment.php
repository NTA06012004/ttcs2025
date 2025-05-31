<?php
require_once 'includes/header.php'; // Đã bao gồm db.php và functions.php

// Kiểm tra đăng nhập và class_id hợp lệ
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if (!isLoggedIn() || $class_id <= 0) {
    $_SESSION['message'] = $class_id <= 0 ? "ID lớp học không hợp lệ." : "Bạn cần đăng nhập để tạo bài tập.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$current_user_id = $_SESSION['user_id'];

// Kiểm tra xem người dùng có phải là giáo viên của lớp này không
if (!isTeacherOfClass($conn, $current_user_id, $class_id)) {
    $_SESSION['message'] = "Bạn không có quyền tạo bài tập cho lớp này.";
    $_SESSION['message_type'] = "danger";
    // Chuyển hướng về dashboard hoặc trang xem lớp (nếu họ là thành viên nhưng không phải GV)
    if (isEnrolledInClass($conn, $current_user_id, $class_id)) {
        redirect('class_view.php?id=' . $class_id);
    } else {
        redirect('dashboard.php');
    }
}

// Lấy tên lớp để hiển thị
$stmt_class_info = $conn->prepare("SELECT class_name FROM classes WHERE id = ? AND teacher_id = ?");
$stmt_class_info->bind_param("ii", $class_id, $current_user_id);
$stmt_class_info->execute();
$class_info_result = $stmt_class_info->get_result();
if ($class_info_result->num_rows == 0) {
    $_SESSION['message'] = "Lớp học không tồn tại hoặc bạn không phải giáo viên của lớp này.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}
$class_info = $class_info_result->fetch_assoc();
$stmt_class_info->close();

$title_input = $description_input = $due_date_input_form = ''; // Giữ giá trị input nếu có lỗi
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title_input = trim($_POST['title']);
    $description_input = trim($_POST['description']);
    $due_date_input_form = trim($_POST['due_date']); // Giá trị từ datetime-local
    $file_path_for_db = null; // Tên tệp lưu trong DB

    // --- Validation ---
    if (empty($title_input)) {
        $errors['title'] = "Tiêu đề bài tập là bắt buộc.";
    } elseif (strlen($title_input) > 255) {
        $errors['title'] = "Tiêu đề không được vượt quá 255 ký tự.";
    }

    if (strlen($description_input) > 5000) { // Giới hạn mô tả
        $errors['description'] = "Mô tả quá dài (tối đa 5000 ký tự).";
    }

    $due_date_for_db = null;
    if (empty($due_date_input_form)) {
        $errors['due_date'] = "Thời hạn nộp bài là bắt buộc.";
    } else {
        $due_date_timestamp = strtotime($due_date_input_form);
        if ($due_date_timestamp === false) {
            $errors['due_date'] = "Định dạng ngày giờ không hợp lệ.";
        } else {
            $due_date_for_db = date('Y-m-d H:i:s', $due_date_timestamp);
            // Optional: Kiểm tra ngày phải ở tương lai
            // if ($due_date_timestamp < time() && date('Y-m-d H:i', $due_date_timestamp) != date('Y-m-d H:i')) { // Cho phép cùng ngày
            //     $errors['due_date'] = "Thời hạn nộp bài phải sau thời điểm hiện tại.";
            // }
        }
    }

    // --- File Upload Handling ---
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/assignment_files/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) { // Thử tạo thư mục
                $errors['file'] = "Lỗi hệ thống: Không thể tạo thư mục tải lên.";
            }
        }

        if (!isset($errors['file'])) { // Chỉ xử lý nếu không có lỗi tạo thư mục
            $original_file_name = basename($_FILES['assignment_file']['name']);
            // Sanitize filename: loại bỏ ký tự đặc biệt, giữ lại dấu chấm và gạch dưới/ngang
            $safe_original_name = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", $original_file_name);
            // Tạo tên file duy nhất: classID_timestamp_tenfile
            $new_file_name = $class_id . '_' . time() . '_' . $safe_original_name;
            $target_file_path = $upload_dir . $new_file_name;
            $file_type = strtolower(pathinfo($target_file_path, PATHINFO_EXTENSION));
            $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'ppt', 'pptx', 'xls', 'xlsx', 'mp4', 'mov', 'mp3', 'webm', 'ogg'];
            $max_file_size = 15 * 1024 * 1024; // 15MB

            if ($_FILES['assignment_file']['size'] > $max_file_size) {
                $errors['file'] = "Tệp quá lớn (tối đa 15MB).";
            } elseif (!in_array($file_type, $allowed_types)) {
                $errors['file'] = "Loại tệp không được phép. Cho phép: " . implode(', ', $allowed_types) . ".";
            }

            if (empty($errors['file'])) { // Chỉ move_uploaded_file nếu không có lỗi file
                if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $target_file_path)) {
                    $file_path_for_db = $new_file_name; // Lưu tên tệp mới vào DB
                } else {
                    // Ghi log lỗi chi tiết hơn ở đây nếu cần
                    $errors['file'] = "Lỗi khi tải tệp lên máy chủ. Vui lòng thử lại.";
                }
            }
        }
    } elseif (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] != UPLOAD_ERR_NO_FILE) {
        // Xử lý các mã lỗi tải lên khác của PHP
        switch ($_FILES['assignment_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors['file'] = "Tệp quá lớn.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors['file'] = "Tệp chỉ được tải lên một phần.";
                break;
            default:
                $errors['file'] = "Lỗi không xác định khi tải tệp lên.";
        }
    }

    // --- Insert into Database ---
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO assignments (class_id, title, description, due_date, file_path) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issss", $class_id, $title_input, $description_input, $due_date_for_db, $file_path_for_db);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Bài tập \"".htmlspecialchars($title_input)."\" đã được tạo thành công.";
                $_SESSION['message_type'] = "success";
                redirect("class_view.php?id={$class_id}&tab=assignments");
            } else {
                $errors['db_error'] = "Lỗi khi lưu bài tập vào cơ sở dữ liệu: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors['db_error'] = "Lỗi hệ thống: Không thể chuẩn bị câu lệnh SQL.";
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="bi bi-journal-plus me-2"></i>Tạo bài tập mới</h1>
        <p class="text-muted mb-0">Cho lớp: <strong><?php echo htmlspecialchars($class_info['class_name']); ?></strong></p>
    </div>
    <a href="class_view.php?id=<?php echo $class_id; ?>&tab=assignments" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Hủy</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8"> 
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <p class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Vui lòng sửa các lỗi sau:</p>
                        <ul class="mb-0">
                            <?php foreach ($errors as $field => $error_message): ?>
                                <li><?php echo htmlspecialchars($error_message); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="create_assignment.php?class_id=<?php echo $class_id; ?>" method="POST" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label for="title" class="form-label">Tiêu đề bài tập <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" id="title" name="title" value="<?php echo htmlspecialchars($title_input); ?>" required autofocus>
                        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?php echo $errors['title']; ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả chi tiết (Yêu cầu, hướng dẫn)</label>
                        <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="6" placeholder="Nhập mô tả chi tiết cho bài tập..."><?php echo htmlspecialchars($description_input); ?></textarea>
                        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?php echo $errors['description']; ?></div><?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Thời hạn nộp bài <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control <?php echo isset($errors['due_date']) ? 'is-invalid' : ''; ?>" id="due_date" name="due_date" value="<?php echo htmlspecialchars($due_date_input_form); ?>" required>
                            <?php if (isset($errors['due_date'])): ?><div class="invalid-feedback"><?php echo $errors['due_date']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="assignment_file" class="form-label"><i class="bi bi-paperclip"></i> Tệp đính kèm (tài liệu, đề bài...)</label>
                            <input type="file" class="form-control <?php echo isset($errors['file']) ? 'is-invalid' : ''; ?>" id="assignment_file" name="assignment_file">
                            <div class="form-text">Tối đa 15MB. Các định dạng phổ biến được chấp nhận.</div>
                            <?php if (isset($errors['file'])): ?><div class="invalid-feedback d-block"><?php echo $errors['file']; ?></div><?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="text-end">
                        <a href="class_view.php?id=<?php echo $class_id; ?>&tab=assignments" class="btn btn-outline-secondary me-2">Hủy bỏ</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle-fill me-2"></i>Giao bài tập</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>