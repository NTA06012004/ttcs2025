<?php
require_once 'includes/header.php';
if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để tham gia lớp học.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$class_code_input = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_code_input = trim(strtoupper($_POST['class_code']));
    $user_id = $_SESSION['user_id'];

    if (empty($class_code_input)) {
        $errors['class_code'] = "Mã lớp là bắt buộc.";
    } elseif (strlen($class_code_input) > 10) { // Giới hạn độ dài mã lớp
        $errors['class_code'] = "Mã lớp không hợp lệ.";
    }


    if (empty($errors)) {
        // Tìm lớp theo mã
        $stmt = $conn->prepare("SELECT id, class_name, teacher_id FROM classes WHERE class_code = ?");
        $stmt->bind_param("s", $class_code_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $class = $result->fetch_assoc();
            $class_id_to_join = $class['id'];
            $class_name_to_join = $class['class_name'];

            // Kiểm tra xem người dùng có phải là giáo viên của lớp này không
            if ($class['teacher_id'] == $user_id) {
                $errors['class_code'] = "Bạn đã là giáo viên của lớp \"".htmlspecialchars($class_name_to_join)."\".";
            } else {
                // Kiểm tra xem đã tham gia lớp này chưa (với vai trò không phải là giáo viên)
                $stmt_check_enroll = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND class_id = ?");
                $stmt_check_enroll->bind_param("ii", $user_id, $class_id_to_join);
                $stmt_check_enroll->execute();
                $stmt_check_enroll->store_result();

                if ($stmt_check_enroll->num_rows > 0) {
                    $errors['class_code'] = "Bạn đã tham gia lớp học \"".htmlspecialchars($class_name_to_join)."\" rồi.";
                } else {
                    // Ghi danh học sinh vào lớp
                    $stmt_enroll = $conn->prepare("INSERT INTO enrollments (user_id, class_id) VALUES (?, ?)");
                    $stmt_enroll->bind_param("ii", $user_id, $class_id_to_join);
                    if ($stmt_enroll->execute()) {
                        $_SESSION['message'] = "Tham gia lớp học \"".htmlspecialchars($class_name_to_join)."\" thành công!";
                        $_SESSION['message_type'] = "success";
                        redirect('class_view.php?id=' . $class_id_to_join);
                    } else {
                        $errors['db_error'] = "Lỗi khi tham gia lớp học: " . $stmt_enroll->error;
                    }
                    $stmt_enroll->close();
                }
                $stmt_check_enroll->close();
            }
        } else {
            $errors['class_code'] = "Mã lớp không hợp lệ hoặc không tồn tại.";
        }
        $stmt->close();
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Tham gia lớp học</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Nhập mã lớp để tham gia</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['db_error'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['db_error']; ?></div>
                <?php endif; ?>
                <form action="join_class.php" method="POST">
                    <div class="mb-3">
                        <label for="class_code" class="form-label">Mã lớp <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['class_code']) ? 'is-invalid' : ''; ?>" id="class_code" name="class_code" value="<?php echo htmlspecialchars($class_code_input); ?>" placeholder="VD: ABC123" style="text-transform:uppercase" required autofocus maxlength="10">
                        <div class="form-text">Nhập mã lớp được giáo viên cung cấp. Mã lớp thường có 6 ký tự.</div>
                        <?php if (isset($errors['class_code'])): ?><div class="invalid-feedback"><?php echo $errors['class_code']; ?></div><?php endif; ?>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i>Tham gia</button>
                    </div>
                </form>
            </div>
             <div class="card-footer text-center text-muted small">
                Nếu bạn là giáo viên, hãy <a href="create_class.php">tạo lớp học mới</a>.
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>