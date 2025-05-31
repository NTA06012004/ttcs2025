<?php
require_once 'includes/header.php';
$errors = [];
$email_to_verify = $_GET['email'] ?? '';

if (empty($email_to_verify)) {
    $_SESSION['message'] = "Không có email nào được cung cấp để xác thực.";
    $_SESSION['message_type'] = "warning";
    redirect("register.php");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['verification_code']);
    $email_from_form = trim($_POST['email_hidden']); // Lấy từ hidden input
    $email_to_verify = $email_from_form; // Cập nhật lại để hiển thị đúng nếu có lỗi

    if (empty($code)) $errors['code'] = "Mã xác thực là bắt buộc.";
    if (empty($email_from_form)) $errors['general'] = "Lỗi: không tìm thấy email để xác thực.";


    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, email_verification_code FROM users WHERE email = ? AND email_verified_at IS NULL");
        $stmt->bind_param("s", $email_from_form);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && $user['email_verification_code'] === $code) {
            $stmt_update = $conn->prepare("UPDATE users SET email_verified_at = NOW(), email_verification_code = NULL WHERE email = ?");
            $stmt_update->bind_param("s", $email_from_form);
            if ($stmt_update->execute()) {
                $_SESSION['message'] = "Xác thực email thành công! Bạn có thể đăng nhập.";
                $_SESSION['message_type'] = "success";
                redirect("login.php");
            } else {
                $errors['general'] = "Lỗi cập nhật trạng thái xác thực.";
            }
            $stmt_update->close();
        } else {
            $errors['code'] = "Mã xác thực không hợp lệ hoặc đã hết hạn/đã được sử dụng.";
        }
    }
}

if (isset($_GET['resend']) && !empty($_GET['email_resend'])) {
    $email_to_resend = $_GET['email_resend'];
    $stmt = $conn->prepare("SELECT id, email_verified_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $email_to_resend);
    $stmt->execute();
    $user_for_resend = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user_for_resend) {
        if ($user_for_resend['email_verified_at'] !== NULL) {
            $_SESSION['message'] = "Email " . htmlspecialchars($email_to_resend) . " đã được xác thực.";
            $_SESSION['message_type'] = "info";
        } else {
            $new_code = generateVerificationCode();
            $stmt_update_code = $conn->prepare("UPDATE users SET email_verification_code = ? WHERE email = ?");
            $stmt_update_code->bind_param("ss", $new_code, $email_to_resend);
            if ($stmt_update_code->execute() && sendVerificationEmail($email_to_resend, $new_code)) {
                 $_SESSION['message'] = "Mã xác thực mới đã được gửi tới " . htmlspecialchars($email_to_resend) . ". Mã (mô phỏng): " . $new_code;
                 $_SESSION['message_type'] = "info";
            } else {
                 $_SESSION['message'] = "Không thể gửi lại mã xác thực.";
                 $_SESSION['message_type'] = "danger";
            }
            $stmt_update_code->close();
        }
    } else {
        $_SESSION['message'] = "Email không tồn tại trong hệ thống.";
        $_SESSION['message_type'] = "warning";
    }
    redirect("verify_email.php?email=" . urlencode($email_to_resend)); // Redirect lại
}
?>
<div class="auth-card-wrapper">
    <div class="card auth-card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Xác thực Email</h3>
        </div>
        <div class="card-body">
            <p class="text-center">Một mã xác thực đã được gửi đến email:<br><strong><?php echo htmlspecialchars($email_to_verify); ?></strong>.<br>Vui lòng nhập mã đó vào ô bên dưới.</p>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="verify_email.php?email=<?php echo urlencode($email_to_verify); ?>" method="POST">
                <input type="hidden" name="email_hidden" value="<?php echo htmlspecialchars($email_to_verify); ?>">
                <div class="mb-3">
                    <label for="verification_code" class="form-label">Mã xác thực</label>
                    <input type="text" class="form-control <?php echo isset($errors['code']) ? 'is-invalid' : ''; ?>" id="verification_code" name="verification_code" required autofocus>
                     <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?php echo $errors['code']; ?></div><?php endif; ?>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Xác thực</button>
                </div>
            </form>
            <p class="text-center mt-3">
                Không nhận được mã? <a href="verify_email.php?resend=true&email_resend=<?php echo urlencode($email_to_verify); ?>">Gửi lại mã</a>
            </p>
             <p class="text-center mt-2">
                <a href="register.php">Đăng ký tài khoản khác</a> | <a href="login.php">Đăng nhập</a>
            </p>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>