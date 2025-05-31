<?php
require_once 'includes/header.php';
if (isLoggedIn()) { redirect('dashboard.php'); }

$email_input = ''; // Đổi tên biến để tránh nhầm lẫn
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // require_once 'includes/db.php'; // Đã có trong header

    $email_input = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email_input)) $errors['email'] = "Email là bắt buộc.";
    if (empty($password)) $errors['password'] = "Mật khẩu là bắt buộc.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, full_name, password, email_verified_at, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if ($user['email_verified_at'] === NULL) {
                $errors['general'] = 'Tài khoản của bạn chưa được xác thực. <a href="verify_email.php?email='.urlencode($user['email']).'">Xác thực ngay</a>.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                // Không cần $_SESSION['role'] nữa
                session_regenerate_id(true);

                $_SESSION['message'] = "Đăng nhập thành công!";
                $_SESSION['message_type'] = "success";
                redirect("dashboard.php");
            } else {
                $errors['general'] = "Email hoặc mật khẩu không chính xác.";
            }
        } else {
            $errors['general'] = "Email hoặc mật khẩu không chính xác.";
        }
        $stmt->close();
    }
}
?>
<div class="auth-card-wrapper">
    <div class="card auth-card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Đăng nhập</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Địa chỉ Email</label>
                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email_input); ?>" required autofocus>
                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">Ghi nhớ đăng nhập</label>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Đăng nhập</button>
                </div>
            </form>
            <p class="text-center mt-3">
                <a href="forgot_password.php">Quên mật khẩu?</a> (Chưa phát triển)
            </p>
            <p class="text-center mt-2">
                Chưa có tài khoản? <a href="register.php">Đăng ký tại đây</a>
            </p>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>