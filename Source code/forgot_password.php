<?php
require_once 'includes/header.php'; // Bao gồm db.php và functions.php
if (isLoggedIn()) { redirect('dashboard.php'); }

$email_input = '';
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_input = trim($_POST['email']);

    if (empty($email_input)) {
        $errors[] = "Vui lòng nhập địa chỉ email của bạn.";
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Địa chỉ email không hợp lệ.";
    } else {
        // Kiểm tra email có tồn tại trong DB không
        $stmt_check_email = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? AND email_verified_at IS NOT NULL");
        if (!$stmt_check_email) { die("Lỗi SQL: " . $conn->error); }
        $stmt_check_email->bind_param("s", $email_input);
        $stmt_check_email->execute();
        $result_email = $stmt_check_email->get_result();

        if ($result_email->num_rows > 0) {
            $user = $result_email->fetch_assoc();
            $user_id_reset = $user['id'];

            // Tạo token reset
            $token = bin2hex(random_bytes(32)); // Tạo token ngẫu nhiên mạnh
            $expires = date("Y-m-d H:i:s", time() + 3600); // Token hết hạn sau 1 giờ

            // Lưu token vào DB
            $stmt_save_token = $conn->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
            if (!$stmt_save_token) { die("Lỗi SQL: " . $conn->error); }
            $stmt_save_token->bind_param("ssi", $token, $expires, $user_id_reset);

            if ($stmt_save_token->execute()) {
                // Gửi email chứa link reset
                // **QUAN TRỌNG: Bạn cần hàm sendPasswordResetEmail thực sự gửi email**
                // Ví dụ tên miền của bạn là http://yourwebsite.com
                $reset_link = "http://localhost/your_project_folder/reset_password.php?token=" . $token; // THAY ĐỔI CHO ĐÚNG URL
                
                if (sendPasswordResetEmail($email_input, $user['full_name'], $reset_link)) {
                    $_SESSION['message'] = "Một email hướng dẫn khôi phục mật khẩu đã được gửi đến địa chỉ email của bạn (nếu tài khoản tồn tại và đã xác thực). Vui lòng kiểm tra hộp thư đến (và cả thư mục spam). Link (mô phỏng): " . $reset_link;
                    $_SESSION['message_type'] = "success";
                    redirect('login.php'); // Chuyển về trang login để hiển thị thông báo
                } else {
                    $errors[] = "Lỗi khi gửi email khôi phục. Vui lòng thử lại sau hoặc liên hệ quản trị viên.";
                }
            } else {
                $errors[] = "Lỗi khi tạo yêu cầu khôi phục. Vui lòng thử lại.";
            }
            $stmt_save_token->close();
        } else {
            // Không thông báo rõ email không tồn tại để tránh bị dò email
            // Chỉ hiển thị thông báo chung là đã gửi nếu email hợp lệ
            $_SESSION['message'] = "Nếu địa chỉ email bạn cung cấp có trong hệ thống và đã được xác thực, một email hướng dẫn khôi phục mật khẩu sẽ được gửi đến.";
            $_SESSION['message_type'] = "info";
            redirect('login.php');
        }
        $stmt_check_email->close();
    }
}

// Hàm gửi email (cần được định nghĩa trong functions.php và sử dụng PHPMailer)
function sendPasswordResetEmail($recipient_email, $recipient_name, $reset_link) {
    $subject = "Yêu cầu khôi phục mật khẩu cho tài khoản EduPlatform";
    $body = "<p>Chào " . htmlspecialchars($recipient_name) . ",</p>";
    $body .= "<p>Chúng tôi nhận được yêu cầu khôi phục mật khẩu cho tài khoản của bạn trên EduPlatform.</p>";
    $body .= "<p>Vui lòng nhấp vào liên kết bên dưới để đặt lại mật khẩu của bạn. Liên kết này sẽ hết hạn sau 1 giờ:</p>";
    $body .= "<p><a href='" . $reset_link . "'>" . $reset_link . "</a></p>";
    $body .= "<p>Nếu bạn không yêu cầu điều này, vui lòng bỏ qua email này.</p>";
    $body .= "<p>Trân trọng,<br>Đội ngũ EduPlatform</p>";

    // Đây là phần MÔ PHỎNG, bạn cần tích hợp PHPMailer thực sự ở đây
    error_log("Password Reset Email to: $recipient_email, Link: $reset_link");
    return true; // Giả định gửi thành công cho mục đích demo
}
?>
<link rel="stylesheet" href="assets/css/auth-pages.css"> {/* Tạo file CSS riêng nếu cần */}

<div class="auth-card-wrapper">
    <div class="card auth-card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0 text-center"><i class="bi bi-key-fill me-2"></i>Quên mật khẩu</h3>
        </div>
        <div class="card-body p-lg-4 p-3">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): // Phần này có thể không cần nếu redirect ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <p class="text-muted mb-3">Nhập địa chỉ email bạn đã sử dụng để đăng ký. Chúng tôi sẽ gửi cho bạn một liên kết để đặt lại mật khẩu.</p>
            <form action="forgot_password.php" method="POST" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Địa chỉ Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control <?php if(!empty($errors) && empty($success_message)) echo 'is-invalid'; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email_input); ?>" required autofocus>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send-fill me-2"></i>Gửi yêu cầu</button>
                </div>
            </form>
            <p class="text-center mt-3 mb-0">
                <a href="login.php"><i class="bi bi-arrow-left-circle me-1"></i>Quay lại Đăng nhập</a>
            </p>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
