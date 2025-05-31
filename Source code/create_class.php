<?php
require_once 'includes/header.php';
if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để tạo lớp học.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$class_name = $description = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_name = trim($_POST['class_name']);
    $description = trim($_POST['description']);
    $teacher_id = $_SESSION['user_id']; // Người tạo lớp chính là giáo viên của lớp đó

    if (empty($class_name)) {
        $errors['class_name'] = "Tên lớp học là bắt buộc.";
    }
    if (strlen($class_name) > 100) {
        $errors['class_name'] = "Tên lớp học không được vượt quá 100 ký tự.";
    }
    if (strlen($description) > 1000) { // Giới hạn độ dài mô tả
        $errors['description'] = "Mô tả không được vượt quá 1000 ký tự.";
    }


    $class_code = '';
    if (empty($errors)) { // Chỉ tạo mã nếu không có lỗi validation trước đó
        $is_code_unique = false;
        $max_tries = 10; // Tránh vòng lặp vô hạn nếu có vấn đề
        $try_count = 0;
        while(!$is_code_unique && $try_count < $max_tries) {
            $class_code = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6));
            $stmt_check_code = $conn->prepare("SELECT id FROM classes WHERE class_code = ?");
            $stmt_check_code->bind_param("s", $class_code);
            $stmt_check_code->execute();
            $stmt_check_code->store_result();
            if($stmt_check_code->num_rows == 0) {
                $is_code_unique = true;
            }
            $stmt_check_code->close();
            $try_count++;
        }
        if (!$is_code_unique) {
            $errors['db_error'] = "Không thể tạo mã lớp duy nhất. Vui lòng thử lại.";
        }
    }


    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO classes (class_name, class_code, teacher_id, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $class_name, $class_code, $teacher_id, $description);

        if ($stmt->execute()) {
            $new_class_id = $stmt->insert_id;
            // Tự động ghi danh giáo viên (người tạo lớp) vào lớp
            $stmt_enroll = $conn->prepare("INSERT INTO enrollments (user_id, class_id) VALUES (?, ?)");
            $stmt_enroll->bind_param("ii", $teacher_id, $new_class_id);
            if (!$stmt_enroll->execute()) {
                // Ghi log lỗi nếu không enroll được giáo viên, nhưng vẫn tiếp tục
                error_log("Lỗi khi tự động ghi danh giáo viên vào lớp mới: " . $stmt_enroll->error);
            }
            $stmt_enroll->close();

            $_SESSION['message'] = "Lớp học \"".htmlspecialchars($class_name)."\" đã được tạo thành công! Mã lớp: <strong class='user-select-all'>" . $class_code . "</strong>";
            $_SESSION['message_type'] = "success";
            redirect('class_view.php?id=' . $new_class_id);
        } else {
            $errors['db_error'] = "Lỗi khi tạo lớp học: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Tạo lớp học mới</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Thông tin lớp học</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['db_error'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['db_error']; ?></div>
                <?php endif; ?>
                <form action="create_class.php" method="POST">
                    <div class="mb-3">
                        <label for="class_name" class="form-label">Tên lớp học <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['class_name']) ? 'is-invalid' : ''; ?>" id="class_name" name="class_name" value="<?php echo htmlspecialchars($class_name); ?>" required autofocus maxlength="100">
                        <?php if (isset($errors['class_name'])): ?><div class="invalid-feedback"><?php echo $errors['class_name']; ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả lớp học (tùy chọn)</label>
                        <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="4" maxlength="1000"><?php echo htmlspecialchars($description); ?></textarea>
                        <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?php echo $errors['description']; ?></div><?php endif; ?>
                        <div class="form-text">Mô tả ngắn gọn về lớp học của bạn, mục tiêu, đối tượng học sinh,...</div>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Hủy</a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Tạo lớp học</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
