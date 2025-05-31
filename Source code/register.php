<?php
require_once 'includes/header.php';
if (isLoggedIn()) { redirect('dashboard.php'); }

$full_name = $dob = $gender = $email = '';
$errors = [];
$profile_picture_name_for_db = 'default.png'; // Giá trị mặc định

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $dob = trim($_POST['dob']);
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation (giữ nguyên các validation khác)
    if (empty($full_name)) $errors['full_name'] = "Họ tên là bắt buộc.";
    if (empty($dob)) $errors['dob'] = "Ngày sinh là bắt buộc.";
    if (empty($gender)) $errors['gender'] = "Giới tính là bắt buộc.";
    if (empty($email)) { $errors['email'] = "Email là bắt buộc."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = "Định dạng email không hợp lệ."; }
    else {
        $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if($stmt_check_email){
            $stmt_check_email->bind_param("s", $email);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) { $errors['email'] = "Email đã được sử dụng."; }
            $stmt_check_email->close();
        } else { $errors['db_error'] = "Lỗi kiểm tra email: " . $conn->error; }
    }
    if (empty($password)) { $errors['password'] = "Mật khẩu là bắt buộc."; }
    elseif (strlen($password) < 6) { $errors['password'] = "Mật khẩu phải có ít nhất 6 ký tự."; }
    if ($password !== $confirm_password) { $errors['confirm_password'] = "Mật khẩu xác nhận không khớp."; }

    // --- Xử lý tải lên ảnh đại diện ---
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $upload_dir_profile = 'uploads/profile_pictures/';
        if (!is_dir($upload_dir_profile)) {
            if (!mkdir($upload_dir_profile, 0775, true) && !is_dir($upload_dir_profile)) {
                $errors['profile_picture'] = "Lỗi hệ thống: Không thể tạo thư mục tải lên ảnh đại diện.";
            }
        }

        if (!isset($errors['profile_picture'])) {
            $original_pic_name = basename($_FILES['profile_picture']['name']);
            $safe_pic_name = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", $original_pic_name);
            $email_prefix = substr(preg_replace("/[^a-zA-Z0-9]/", "", $email), 0, 5); // Cần $email ở đây
            if(empty($email_prefix) && !empty($full_name)) $email_prefix = substr(preg_replace("/[^a-zA-Z0-9]/", "", $full_name),0,5); // Fallback nếu email rỗng
            $new_pic_file_name = strtolower($email_prefix) . '_' . time() . '_' . $safe_pic_name;
            $target_pic_path = $upload_dir_profile . $new_pic_file_name;
            $pic_file_type = strtolower(pathinfo($target_pic_path, PATHINFO_EXTENSION));
            $allowed_pic_types = ['jpg', 'jpeg', 'png', 'gif'];
            $max_pic_size = 2 * 1024 * 1024; // 2MB

            if ($_FILES['profile_picture']['size'] > $max_pic_size) {
                $errors['profile_picture'] = "Ảnh đại diện quá lớn (tối đa 2MB).";
            } elseif (!in_array($pic_file_type, $allowed_pic_types)) {
                $errors['profile_picture'] = "Loại tệp ảnh không hợp lệ. Chỉ cho phép: JPG, JPEG, PNG, GIF.";
            }

            if (empty($errors['profile_picture'])) {
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_pic_path)) {
                    $profile_picture_name_for_db = $new_pic_file_name;
                } else {
                    $errors['profile_picture'] = "Lỗi khi tải ảnh đại diện lên máy chủ. Vui lòng thử lại.";
                }
            }
        }
    } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['profile_picture'] = "Lỗi tải tệp ảnh đại diện. Mã lỗi: " . $_FILES['profile_picture']['error'];
    }


    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $verification_code = generateVerificationCode();

        $stmt_insert_user = $conn->prepare("INSERT INTO users (full_name, dob, gender, email, password, profile_picture, email_verification_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt_insert_user) {
            $stmt_insert_user->bind_param("sssssss", $full_name, $dob, $gender, $email, $hashed_password, $profile_picture_name_for_db, $verification_code);
            if ($stmt_insert_user->execute()) {
                // ... (logic gửi email xác thực) ...
                if (sendVerificationEmail($email, $verification_code)) {
                    $_SESSION['message'] = "Đăng ký thành công! Vui lòng kiểm tra email (" . htmlspecialchars($email) . ") để xác thực tài khoản. Mã (mô phỏng): " . $verification_code;
                    $_SESSION['message_type'] = "success";
                    redirect("verify_email.php?email=" . urlencode($email));
                } else {
                    $_SESSION['message'] = "Đăng ký thành công nhưng không thể gửi mail. Mã (mô phỏng): ".$verification_code;
                    $_SESSION['message_type'] = "warning";
                    redirect("login.php");
                }
            } else {
                if ($profile_picture_name_for_db !== 'default.png' && isset($target_pic_path) && file_exists($target_pic_path)) {
                    unlink($target_pic_path); // Xóa ảnh nếu insert user thất bại
                }
                $errors['db_error'] = "Lỗi đăng ký tài khoản: " . $stmt_insert_user->error;
            }
            $stmt_insert_user->close();
        } else {
            $errors['db_error'] = "Lỗi hệ thống: Không thể chuẩn bị câu lệnh SQL đăng ký.";
        }
    }
}
?>
<div class="auth-card-wrapper">
    <div class="card auth-card shadow">
        <div class="card-header bg-primary text-white"><h3 class="mb-0 text-center">Đăng ký tài khoản EduPlatform</h3></div>
        <div class="card-body p-lg-4 p-3">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><p class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Lỗi:</p><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form action="register.php" method="POST" enctype="multipart/form-data" novalidate>
                <div class="mb-3">
                    <label for="full_name" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required autofocus>
                    <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?php echo $errors['full_name']; ?></div><?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="dob" class="form-label">Ngày sinh <span class="text-danger">*</span></label><input type="date" class="form-control <?php echo isset($errors['dob']) ? 'is-invalid' : ''; ?>" id="dob" name="dob" value="<?php echo htmlspecialchars($dob); ?>" required><?php if (isset($errors['dob'])): ?><div class="invalid-feedback"><?php echo $errors['dob']; ?></div><?php endif; ?></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Giới tính <span class="text-danger">*</span></label><div><div class="form-check form-check-inline"><input class="form-check-input <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="male" value="male" <?php echo ($gender == 'male') ? 'checked' : ''; ?> required><label class="form-check-label" for="male">Nam</label></div><div class="form-check form-check-inline"><input class="form-check-input <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="female" value="female" <?php echo ($gender == 'female') ? 'checked' : ''; ?> required><label class="form-check-label" for="female">Nữ</label></div><div class="form-check form-check-inline"><input class="form-check-input <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="other" value="other" <?php echo ($gender == 'other') ? 'checked' : ''; ?> required><label class="form-check-label" for="other">Khác</label></div></div><?php if (isset($errors['gender'])): ?><div class="invalid-feedback d-block"><?php echo $errors['gender']; ?></div><?php endif; ?></div>
                </div>
                <div class="mb-3"><label for="email" class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required><?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label><input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required><?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?></div>
                    <div class="col-md-6 mb-3"><label for="confirm_password" class="form-label">Xác nhận <span class="text-danger">*</span></label><input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required><?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div><?php endif; ?></div>
                </div>
                <div class="mb-3">
                    <label for="profile_picture" class="form-label"><i class="bi bi-person-bounding-box me-1"></i>Ảnh đại diện (Không bắt buộc)</label>
                    <input class="form-control <?php echo isset($errors['profile_picture']) ? 'is-invalid' : ''; ?>" type="file" id="profile_picture" name="profile_picture" accept="image/png, image/jpeg, image/gif">
                    <div class="form-text">Chọn ảnh JPG, JPEG, PNG, GIF. Tối đa 2MB.</div>
                    <?php if (isset($errors['profile_picture'])): ?><div class="invalid-feedback d-block"><?php echo $errors['profile_picture']; ?></div><?php endif; ?>
                </div>
                <hr class="my-4">
                <div class="d-grid"><button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-person-plus-fill me-2"></i>Đăng ký</button></div>
            </form>
            <p class="text-center mt-3 mb-0">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>