<?php
require_once 'includes/header.php';
if (!isLoggedIn()) { redirect('login.php'); }

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($conn, $user_id); // Hàm này cần SELECT cả profile_picture

$errors = [];
// $_SESSION['message'] sẽ được dùng để hiển thị thông báo thành công/lỗi sau redirect

// Xử lý Profile Info Update
if (isset($_POST['update_info'])) {
    // ... (Logic update_info như cũ, không liên quan profile_picture ở form này) ...
    $full_name = trim($_POST['full_name']); $dob = trim($_POST['dob']); $gender = $_POST['gender'] ?? '';
    if (empty($full_name)) $errors['info_full_name'] = "Họ tên không được trống.";
    if (empty($dob)) $errors['info_dob'] = "Ngày sinh không được trống.";
    if (empty($gender)) $errors['info_gender'] = "Giới tính không được trống.";
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, dob = ?, gender = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $dob, $gender, $user_id);
        if ($stmt->execute()) { $_SESSION['full_name'] = $full_name; $_SESSION['message'] = "Cập nhật thông tin thành công."; $_SESSION['message_type'] = "success"; redirect('settings.php'); exit;
        } else { $_SESSION['message'] = "Lỗi cập nhật: " . $stmt->error; $_SESSION['message_type'] = "danger";}
        $stmt->close();
    } else { $_SESSION['message'] = "Vui lòng kiểm tra lại thông tin."; $_SESSION['message_type'] = "danger"; $_SESSION['_form_errors'] = $errors; $_SESSION['_old_input_info'] = $_POST;}
}

// Xử lý Password Change
if (isset($_POST['change_password'])) {
    // ... (Logic change_password như cũ) ...
    $current_password = $_POST['current_password']; $new_password = $_POST['new_password']; $confirm_new_password = $_POST['confirm_new_password'];
    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) { $errors['pass_all'] = "Các trường mật khẩu là bắt buộc."; }
    elseif ($new_password !== $confirm_new_password) { $errors['pass_confirm'] = "Mật khẩu mới không khớp."; }
    elseif (strlen($new_password) < 6) { $errors['pass_new_len'] = "Mật khẩu mới ít nhất 6 ký tự."; }
    else {
        $stmt_check = $conn->prepare("SELECT password FROM users WHERE id = ?"); $stmt_check->bind_param("i", $user_id); $stmt_check->execute(); $user_data_pass = $stmt_check->get_result()->fetch_assoc(); $stmt_check->close();
        if ($user_data_pass && password_verify($current_password, $user_data_pass['password'])) {
            $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?"); $stmt_update->bind_param("si", $hashed_new_password, $user_id);
            if ($stmt_update->execute()) { $_SESSION['message'] = "Đổi mật khẩu thành công."; $_SESSION['message_type'] = "success"; redirect('settings.php'); exit;
            } else { $_SESSION['message'] = "Lỗi đổi mật khẩu: " . $stmt_update->error; $_SESSION['message_type'] = "danger"; } $stmt_update->close();
        } else { $errors['pass_current'] = "Mật khẩu hiện tại không đúng."; $_SESSION['message'] = "Mật khẩu hiện tại không đúng."; $_SESSION['message_type'] = "danger";}
    }
    if(!empty($errors)) { $_SESSION['message'] = "Lỗi đổi mật khẩu, vui lòng kiểm tra lại."; $_SESSION['message_type'] = "danger"; $_SESSION['_form_errors_pass'] = $errors; }
}


// --- Xử lý tải lên ảnh đại diện MỚI ---
if (isset($_POST['upload_picture']) && isset($_FILES['profile_picture_file']) && $_FILES['profile_picture_file']['error'] == UPLOAD_ERR_OK) {
    $upload_dir_profile = 'uploads/profile_pictures/'; // Đảm bảo thư mục này tồn tại và có quyền ghi
    // (Kiểm tra và tạo thư mục nếu chưa có, tương tự như ở register.php)
    if (!is_dir($upload_dir_profile)) { if (!mkdir($upload_dir_profile, 0775, true) && !is_dir($upload_dir_profile)) { $_SESSION['message'] = "Lỗi tạo thư mục upload."; $_SESSION['message_type'] = "danger"; redirect('settings.php'); exit;}}

    $original_pic_name = basename($_FILES['profile_picture_file']['name']);
    $safe_pic_name = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", $original_pic_name);
    $new_pic_file_name = $user_id . '_' . time() . '_' . $safe_pic_name; // Dùng user_id để tên file rõ ràng hơn
    $target_pic_path = $upload_dir_profile . $new_pic_file_name;
    $pic_file_type = strtolower(pathinfo($target_pic_path, PATHINFO_EXTENSION));
    $allowed_pic_types = ['jpg', 'jpeg', 'png', 'gif'];
    $max_pic_size = 2 * 1024 * 1024; // 2MB

    $pic_errors = []; // Lỗi riêng cho upload ảnh
    if ($_FILES['profile_picture_file']['size'] > $max_pic_size) { $pic_errors[] = "Ảnh quá lớn (tối đa 2MB)."; }
    if (!in_array($pic_file_type, $allowed_pic_types)) { $pic_errors[] = "Loại tệp ảnh không hợp lệ."; }

    if (empty($pic_errors)) {
        // Xóa ảnh cũ (nếu không phải default.png) trước khi upload ảnh mới
        if ($user['profile_picture'] && $user['profile_picture'] != 'default.png' && file_exists($upload_dir_profile . $user['profile_picture'])) {
            unlink($upload_dir_profile . $user['profile_picture']);
        }
        if (move_uploaded_file($_FILES['profile_picture_file']['tmp_name'], $target_pic_path)) {
            $stmt_update_pic = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            if ($stmt_update_pic) {
                $stmt_update_pic->bind_param("si", $new_pic_file_name, $user_id);
                if ($stmt_update_pic->execute()) {
                    $_SESSION['message'] = "Ảnh đại diện đã được cập nhật.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Lỗi cập nhật ảnh trong CSDL: " . $stmt_update_pic->error;
                    $_SESSION['message_type'] = "danger";
                    // Nếu lỗi DB, xóa file vừa upload
                    if (file_exists($target_pic_path)) unlink($target_pic_path);
                }
                $stmt_update_pic->close();
            } else { $_SESSION['message'] = "Lỗi hệ thống (prepare SQL update pic)."; $_SESSION['message_type'] = "danger"; if (file_exists($target_pic_path)) unlink($target_pic_path); }
        } else {
            $_SESSION['message'] = "Lỗi khi tải ảnh lên máy chủ.";
            $_SESSION['message_type'] = "danger";
        }
    } else { // Có lỗi validation ảnh
        $_SESSION['message'] = implode("<br>", $pic_errors);
        $_SESSION['message_type'] = "danger";
    }
    redirect('settings.php'); exit; // Luôn redirect để làm mới trang và hiển thị session message
} elseif (isset($_FILES['profile_picture_file']) && $_FILES['profile_picture_file']['error'] != UPLOAD_ERR_OK && $_FILES['profile_picture_file']['error'] != UPLOAD_ERR_NO_FILE) {
    $_SESSION['message'] = "Lỗi tải tệp ảnh. Mã: " . $_FILES['profile_picture_file']['error'];
    $_SESSION['message_type'] = "danger";
    redirect('settings.php'); exit;
}


// Lấy lại thông tin user sau khi có thể đã cập nhật (nếu không redirect ở các khối trên)
// Hoặc khi vào trang lần đầu
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_SESSION['_form_errors']) || !empty($_SESSION['_form_errors_pass']) ){
    $user = get_user_by_id($conn, $user_id); // Lấy lại thông tin mới nhất
}
$old_input_info = $_SESSION['_old_input_info'] ?? $user; // Lấy dữ liệu cũ nếu có lỗi form
unset($_SESSION['_old_input_info']); unset($_SESSION['_form_errors']); unset($_SESSION['_form_errors_pass']);
?>

<div class="page-header"><h1 class="page-title"><i class="bi bi-gear-fill me-2"></i>Cài đặt tài khoản</h1></div>
<?php if (isset($_SESSION['message'])): ?><div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?php echo $_SESSION['message']; ?></div><?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?><?php endif; ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Ảnh đại diện</h5></div>
            <div class="card-body text-center">
                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profile_picture'] ?: 'default.png'); ?>"
                     alt="Ảnh đại diện" class="profile-picture-lg img-thumbnail mb-3"
                     onerror="this.onerror=null;this.src='uploads/profile_pictures/default.png';">
                <form action="settings.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-2"><label for="profile_picture_file" class="form-label visually-hidden">Chọn ảnh mới:</label><input class="form-control form-control-sm" type="file" id="profile_picture_file" name="profile_picture_file" required accept="image/*"></div>
                    <button type="submit" name="upload_picture" class="btn btn-primary btn-sm w-100"><i class="bi bi-upload me-1"></i>Tải lên & Thay đổi</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h5 class="mb-0">Thông tin cá nhân</h5></div>
            <div class="card-body">
                <form action="settings.php" method="POST">
                    <div class="mb-3"><label for="full_name_settings" class="form-label">Họ và tên <span class="text-danger">*</span></label><input type="text" class="form-control <?php echo isset($_SESSION['_form_errors']['info_full_name']) ? 'is-invalid' : ''; ?>" id="full_name_settings" name="full_name" value="<?php echo htmlspecialchars($old_input_info['full_name']); ?>" required><div class="invalid-feedback"><?php echo $_SESSION['_form_errors']['info_full_name'] ?? ''; ?></div></div>
                    <div class="mb-3"><label for="email_settings" class="form-label">Email</label><input type="email" class="form-control" id="email_settings" value="<?php echo htmlspecialchars($user['email']); ?>" readonly disabled><div class="form-text">Email không thể thay đổi.</div></div>
                    <div class="row"><div class="col-md-6 mb-3"><label for="dob_settings" class="form-label">Ngày sinh <span class="text-danger">*</span></label><input type="date" class="form-control <?php echo isset($_SESSION['_form_errors']['info_dob']) ? 'is-invalid' : ''; ?>" id="dob_settings" name="dob" value="<?php echo htmlspecialchars($old_input_info['dob']); ?>" required><div class="invalid-feedback"><?php echo $_SESSION['_form_errors']['info_dob'] ?? ''; ?></div></div><div class="col-md-6 mb-3"><label class="form-label">Giới tính <span class="text-danger">*</span></label><div><div class="form-check form-check-inline"><input class="form-check-input <?php echo isset($_SESSION['_form_errors']['info_gender']) ? 'is-invalid' : ''; ?>" type="radio" name="gender" id="male_settings" value="male" <?php echo ($old_input_info['gender'] == 'male') ? 'checked' : ''; ?> required><label class="form-check-label" for="male_settings">Nam</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="gender" id="female_settings" value="female" <?php echo ($old_input_info['gender'] == 'female') ? 'checked' : ''; ?> required><label class="form-check-label" for="female_settings">Nữ</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="gender" id="other_settings" value="other" <?php echo ($old_input_info['gender'] == 'other') ? 'checked' : ''; ?> required><label class="form-check-label" for="other_settings">Khác</label></div></div><div class="invalid-feedback d-block"><?php echo $_SESSION['_form_errors']['info_gender'] ?? ''; ?></div></div></div>
                    <button type="submit" name="update_info" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu thông tin</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Thay đổi mật khẩu</h5></div>
            <div class="card-body">
                <form action="settings.php" method="POST">
                    <div class="mb-3"><label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label><input type="password" class="form-control <?php echo isset($_SESSION['_form_errors_pass']['pass_current']) ? 'is-invalid' : ''; ?>" id="current_password" name="current_password" required><div class="invalid-feedback"><?php echo $_SESSION['_form_errors_pass']['pass_current'] ?? ''; ?></div></div>
                    <div class="mb-3"><label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label><input type="password" class="form-control <?php echo (isset($_SESSION['_form_errors_pass']['pass_new_len']) || isset($_SESSION['_form_errors_pass']['pass_all'])) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password" required><div class="invalid-feedback"><?php echo $_SESSION['_form_errors_pass']['pass_new_len'] ?? ($_SESSION['_form_errors_pass']['pass_all'] ?? ''); ?></div></div>
                    <div class="mb-3"><label for="confirm_new_password" class="form-label">Xác nhận <span class="text-danger">*</span></label><input type="password" class="form-control <?php echo isset($_SESSION['_form_errors_pass']['pass_confirm']) ? 'is-invalid' : ''; ?>" id="confirm_new_password" name="confirm_new_password" required><div class="invalid-feedback"><?php echo $_SESSION['_form_errors_pass']['pass_confirm'] ?? ''; ?></div></div>
                    <button type="submit" name="change_password" class="btn btn-primary"><i class="bi bi-shield-lock-fill me-1"></i>Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>