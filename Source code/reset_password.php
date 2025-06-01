<?php
require_once 'includes/header.php'; // Bao gồm db.php, functions.php và session_start()

// Sử dụng các lớp của PHPMailer (nếu bạn muốn gửi email thông báo sau khi reset thành công)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
// require_once __DIR__ . '/vendor/autoload.php'; // Hoặc đường dẫn đúng


$token = $_GET['token'] ?? '';
$errors = [];
$user_id_to_reset = null;
$email_to_notify = ''; // Để gửi email thông báo nếu muốn

if (empty($token)) {
    $_SESSION['message'] = "Token đặt lại mật khẩu không hợp lệ hoặc bị thiếu.";
    $_SESSION['message_type'] = "danger";
    redirect('login.php');
}

// Kiểm tra token có hợp lệ và chưa hết hạn không
$stmt_check_token = $conn->prepare("SELECT id, email, password_reset_expires FROM users WHERE password_reset_token = ?");
if (!$stmt_check_token) {
    // Lỗi hệ thống, ghi log và hiển thị thông báo chung
    error_log("Lỗi SQL (prepare check token): " . $conn->error);
    $_SESSION['message'] = "Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.";
    $_SESSION['message_type'] = "danger";
    redirect('login.php');
}

$stmt_check_token->bind_param("s", $token);
$stmt_check_token->execute();
$result_token = $stmt_check_token->get_result();
$user_data = $result_token->fetch_assoc();
$stmt_check_token->close();

if (!$user_data) {
    $_SESSION['message'] = "Token đặt lại mật khẩu không hợp lệ hoặc đã được sử dụng.";
    $_SESSION['message_type'] = "danger";
    redirect('login.php');
}

// Kiểm tra token đã hết hạn chưa
$current_time = date("Y-m-d H:i:s");
if ($user_data['password_reset_expires'] < $current_time) {
    // Vô hiệu hóa token đã hết hạn
    $stmt_expire_token = $conn->prepare("UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
    if($stmt_expire_token){
        $stmt_expire_token->bind_param("i", $user_data['id']);
        $stmt_expire_token->execute();
        $stmt_expire_token->close();
    }
    $_SESSION['message'] = "Token đặt lại mật khẩu đã hết hạn. Vui lòng yêu cầu một liên kết mới.";
    $_SESSION['message_type'] = "warning";
    redirect('forgot_password.php');
}

// Nếu token hợp lệ, lưu user_id để sử dụng khi cập nhật mật khẩu
$user_id_to_reset = $user_data['id'];
$email_to_notify = $user_data['email'];


// Xử lý khi người dùng gửi form mật khẩu mới
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['token']) || $_POST['token'] !== $token || !$user_id_to_reset) {
        $errors['token'] = "Yêu cầu không hợp lệ hoặc token đã thay đổi.";
    }
    // if (!isset($_POST['token'])) {
    //     $errors['token'] = "Isset.";
    // }
    // elseif ($_POST['token'] !== $token) {
    //     $errors['token'] = "Token da thay doi.";
    // }
    // elseif (!$user_id_to_reset) {
    //     $errors['token'] = "khong co userid";
    // }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($new_password)) {
        $errors['new_password'] = "Mật khẩu mới là bắt buộc.";
    } elseif (strlen($new_password) < 6) {
        $errors['new_password'] = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    }

    if ($new_password !== $confirm_new_password) {
        $errors['confirm_new_password'] = "Mật khẩu xác nhận không khớp.";
    }

    if (empty($errors)) {
        $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Cập nhật mật khẩu mới và xóa token
        $stmt_update_password = $conn->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
        if (!$stmt_update_password) {
            $errors['db_error'] = "Lỗi hệ thống: Không thể chuẩn bị cập nhật mật khẩu.";
        } else {
            $stmt_update_password->bind_param("si", $hashed_new_password, $user_id_to_reset);
            if ($stmt_update_password->execute()) {
                $_SESSION['message'] = "Mật khẩu của bạn đã được đặt lại thành công. Vui lòng đăng nhập bằng mật khẩu mới.";
                $_SESSION['message_type'] = "success";
                
                // (Tùy chọn) Gửi email thông báo mật khẩu đã được thay đổi
                // sendPasswordChangedNotification($email_to_notify, $user_data['full_name']);

                redirect('login.php');
            } else {
                $errors['db_error'] = "Lỗi khi cập nhật mật khẩu: " . $stmt_update_password->error;
            }
            $stmt_update_password->close();
        }
    }
    // Nếu có lỗi, các lỗi sẽ được hiển thị bên dưới form
}

?>
<!-- <link rel="stylesheet" href="assets/css/auth-pages.css">  -->

<div class="auth-card-wrapper">
    <div class="card auth-card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0 text-center"><i class="bi bi-shield-lock-fill me-2"></i>Đặt lại mật khẩu</h3>
        </div>
        <div class="card-body p-lg-4 p-3">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $field => $error_message): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <p class="text-muted mb-3">Vui lòng nhập mật khẩu mới cho tài khoản của bạn.</p>
            <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" novalidate>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                    <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password" required autofocus>
                    <?php if (isset($errors['new_password'])): ?><div class="invalid-feedback"><?php echo $errors['new_password']; ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="confirm_new_password" class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                    <input type="password" class="form-control <?php echo isset($errors['confirm_new_password']) ? 'is-invalid' : ''; ?>" id="confirm_new_password" name="confirm_new_password" required>
                    <?php if (isset($errors['confirm_new_password'])): ?><div class="invalid-feedback"><?php echo $errors['confirm_new_password']; ?></div><?php endif; ?>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle-fill me-2"></i>Đặt lại mật khẩu</button>
                </div>
            </form>
            <p class="text-center mt-3 mb-0">
                <a href="login.php"><i class="bi bi-arrow-left-circle me-1"></i>Quay lại Đăng nhập</a>
            </p>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>