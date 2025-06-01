<?php
require_once 'includes/header.php'; // Bao gồm db.php và functions.php
if (isLoggedIn()) { redirect('dashboard.php'); }

$email_input = '';
$errors = [];
// Không cần $success_message nữa vì sẽ dùng $_SESSION['message'] sau redirect

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_input = trim($_POST['email']);

    if (empty($email_input)) {
        $errors['email'] = "Vui lòng nhập địa chỉ email của bạn.";
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Địa chỉ email không hợp lệ.";
    }

    if (empty($errors)) {
        $stmt_check_email = $conn->prepare("SELECT id, full_name, email_verified_at FROM users WHERE email = ?");
        if (!$stmt_check_email) { 
            $errors['db_error'] = "Lỗi hệ thống: Không thể chuẩn bị truy vấn.";
        } else {
            $stmt_check_email->bind_param("s", $email_input);
            $stmt_check_email->execute();
            $result_email = $stmt_check_email->get_result();

            if ($user = $result_email->fetch_assoc()) {
                if ($user['email_verified_at'] === NULL) {
                    $_SESSION['message'] = "Tài khoản của bạn chưa được xác thực email. Vui lòng xác thực email trước khi khôi phục mật khẩu. <a href='verify_email.php?email=".urlencode($email_input)."'>Xác thực ngay</a>.";
                    $_SESSION['message_type'] = "warning";
                    // Không redirect ngay, để người dùng thấy thông báo trên cùng trang này hoặc redirect về verify_email
                    // redirect('forgot_password.php'); // Hoặc có thể redirect về login/register tùy UX
                } else {
                    $user_id_reset = $user['id'];
                    $user_full_name = $user['full_name'];
                    $token = bin2hex(random_bytes(32));
                    $expires = date("Y-m-d H:i:s", time() + 3600); // Token hết hạn sau 1 giờ

                    $stmt_save_token = $conn->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                    if (!$stmt_save_token) {
                        $errors['db_error'] = "Lỗi hệ thống: Không thể chuẩn bị cập nhật token.";
                    } else {
                        $stmt_save_token->bind_param("ssi", $token, $expires, $user_id_reset);
                        if ($stmt_save_token->execute()) {
                            // Xây dựng link reset, THAY ĐỔI CHO ĐÚNG VỚI DOMAIN/THƯ MỤC CỦA BẠN
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                            $host = $_SERVER['HTTP_HOST'];
                            // Giả sử 'reset_password.php' nằm ở thư mục gốc cùng với 'forgot_password.php'
                            // Nếu không, bạn cần điều chỉnh đường dẫn cho phù hợp
                            $project_path = dirname($_SERVER['PHP_SELF']); 
                            // Loại bỏ tên file hiện tại khỏi đường dẫn nếu nó là file
                            if (basename($_SERVER['PHP_SELF']) == 'forgot_password.php' && $project_path != '/') {
                               // Nếu forgot_password.php không ở gốc, đi lên một cấp để lấy thư mục gốc dự án
                               // Hoặc bạn có thể hardcode đường dẫn gốc của dự án nếu biết trước
                            }
                            // Đơn giản nhất là bạn tự xác định base URL của dự án
                            $base_url = $protocol . "://" . $host . $project_path; 
                            // Nếu $project_path là /folder/thay_vi_goc, link sẽ là /folder/reset_password.php
                            // Nếu $project_path là /, link sẽ là /reset_password.php
                            // Đảm bảo $base_url kết thúc bằng / nếu $project_path không phải là /
                            if (substr($base_url, -1) !== '/' && basename($base_url) !== 'forgot_password.php') {
                                $base_url .= '/';
                            } else if (basename($base_url) == 'forgot_password.php'){
                                $base_url = dirname($base_url) . '/';
                            }


                            $reset_link = rtrim($base_url, '/') . "/reset_password.php?token=" . $token;
                            
                            if (sendPasswordResetEmail($email_input, $user_full_name, $reset_link)) {
                                $_SESSION['message'] = "Một email hướng dẫn khôi phục mật khẩu đã được gửi đến địa chỉ ".htmlspecialchars($email_input).". Vui lòng kiểm tra hộp thư đến (và cả thư mục spam).";
                                $_SESSION['message_type'] = "success";
                                redirect('login.php'); // Chuyển về trang login để hiển thị thông báo
                            } else {
                                $errors['send_mail_error'] = "Lỗi khi gửi email khôi phục. Vui lòng thử lại sau hoặc liên hệ quản trị viên.";
                            }
                        } else {
                            $errors['db_error'] = "Lỗi khi tạo yêu cầu khôi phục (lưu token): " . $stmt_save_token->error;
                        }
                        $stmt_save_token->close();
                    }
                }
            } else {
                // Không thông báo rõ email không tồn tại để tránh bị dò email
                $_SESSION['message'] = "Nếu địa chỉ email bạn cung cấp có trong hệ thống và đã được xác thực, một email hướng dẫn khôi phục mật khẩu sẽ được gửi đến.";
                $_SESSION['message_type'] = "info"; // Dùng info thay vì success để người dùng không chắc chắn email có tồn tại không
                redirect('login.php');
            }
            $stmt_check_email->close();
        }
    }
    // Nếu có lỗi (trừ trường hợp đã redirect), lưu lỗi vào session để hiển thị sau khi redirect lại trang này
    if (!empty($errors) && !isset($_SESSION['message'])) { // Chỉ lưu nếu chưa có thông báo nào được set để redirect
        $_SESSION['_form_errors_forgot_pass'] = $errors;
        $_SESSION['_old_input_forgot_pass']['email'] = $email_input;
        redirect('forgot_password.php'); // Redirect lại chính trang này
    }
}

// Lấy lỗi và input cũ từ session (nếu có sau redirect do lỗi POST)
if (isset($_SESSION['_form_errors_forgot_pass'])) {
    $errors = array_merge($errors, $_SESSION['_form_errors_forgot_pass']); // Merge với lỗi có thể đã có
    unset($_SESSION['_form_errors_forgot_pass']);
}
if (isset($_SESSION['_old_input_forgot_pass']['email'])) {
    $email_input = htmlspecialchars($_SESSION['_old_input_forgot_pass']['email']);
    unset($_SESSION['_old_input_forgot_pass']);
}
?>
<link rel="stylesheet" href="assets/css/auth-pages.css"> 

<div class="auth-card-wrapper">
    <div class="card auth-card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0 text-center"><i class="bi bi-key-fill me-2"></i>Quên mật khẩu</h3>
        </div>
        <div class="card-body p-lg-4 p-3">
            <?php if (isset($_SESSION['message']) && !empty($_SESSION['message']) && $_SESSION['message_type'] == 'warning'): // Hiển thị thông báo warning (ví dụ: chưa xác thực email) ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <?php echo $_SESSION['message']; // Cho phép HTML vì có link ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $field => $error_message): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <p class="text-muted mb-3">Nhập địa chỉ email bạn đã sử dụng để đăng ký và đã xác thực. Chúng tôi sẽ gửi cho bạn một liên kết để đặt lại mật khẩu.</p>
            <form action="forgot_password.php" method="POST" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Địa chỉ Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control <?php if(isset($errors['email']) || isset($errors['db_error']) || isset($errors['send_mail_error'])) echo 'is-invalid'; ?>" id="email" name="email" value="<?php echo $email_input; ?>" required autofocus>
                    <?php if(isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send-fill me-2"></i>Gửi yêu cầu khôi phục</button>
                </div>
            </form>
            <p class="text-center mt-3 mb-0">
                <a href="login.php"><i class="bi bi-arrow-left-circle me-1"></i>Quay lại Đăng nhập</a>
            </p>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>