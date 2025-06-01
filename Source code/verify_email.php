<?php
require_once 'includes/header.php'; // Đã bao gồm db.php và functions.php

$errors = [];
$email_to_verify = $_GET['email'] ?? '';

// Lấy lại email từ session nếu có lỗi và người dùng đã submit form
if (isset($_SESSION['_old_input_verify']['email_hidden'])) {
    $email_to_verify = $_SESSION['_old_input_verify']['email_hidden'];
}

if (empty($email_to_verify) || !filter_var($email_to_verify, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = "Địa chỉ email không hợp lệ hoặc không được cung cấp để xác thực.";
    $_SESSION['message_type'] = "warning";
    redirect("register.php");
}

// Xử lý khi người dùng submit mã xác thực
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['verification_code']);
    // Lấy email từ hidden input để đảm bảo xử lý đúng email ngay cả khi URL bị thay đổi
    $email_from_form = trim($_POST['email_hidden']); 
    
    // Cập nhật lại email_to_verify để hiển thị đúng trên form nếu có lỗi
    $email_to_verify = $email_from_form; 

    if (empty($code)) {
        $errors['code'] = "Mã xác thực là bắt buộc.";
    }
    if (empty($email_from_form) || !filter_var($email_from_form, FILTER_VALIDATE_EMAIL)) {
        $errors['general'] = "Lỗi: Email không hợp lệ để xác thực.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, email_verification_code, email_verified_at FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email_from_form);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                if ($user['email_verified_at'] !== NULL) {
                    $_SESSION['message'] = "Email " . htmlspecialchars($email_from_form) . " đã được xác thực trước đó. Bạn có thể đăng nhập.";
                    $_SESSION['message_type'] = "info";
                    redirect("login.php");
                } elseif ($user['email_verification_code'] === $code) {
                    // Nên kiểm tra thêm thời gian hết hạn của mã nếu bạn có lưu trữ (hiện tại chưa có)
                    $stmt_update = $conn->prepare("UPDATE users SET email_verified_at = NOW(), email_verification_code = NULL WHERE email = ?");
                    if ($stmt_update) {
                        $stmt_update->bind_param("s", $email_from_form);
                        if ($stmt_update->execute()) {
                            $_SESSION['message'] = "Xác thực email thành công! Bạn có thể đăng nhập ngay bây giờ.";
                            $_SESSION['message_type'] = "success";
                            redirect("login.php");
                        } else {
                            $errors['general'] = "Lỗi khi cập nhật trạng thái xác thực: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                    } else {
                        $errors['general'] = "Lỗi hệ thống (prepare update).";
                    }
                } else {
                    $errors['code'] = "Mã xác thực không hợp lệ hoặc đã hết hạn/đã được sử dụng.";
                }
            } else {
                 $errors['general'] = "Không tìm thấy tài khoản với email này để xác thực.";
            }
        } else {
             $errors['general'] = "Lỗi hệ thống (prepare select user).";
        }
    }
    if (!empty($errors)) {
        $_SESSION['_form_errors_verify'] = $errors;
        $_SESSION['_old_input_verify'] = $_POST; // Lưu lại input cũ
        // Redirect lại chính trang này để hiển thị lỗi và input cũ
        redirect("verify_email.php?email=" . urlencode($email_to_verify)); 
    }
}

// Xử lý khi người dùng yêu cầu gửi lại mã
if (isset($_GET['resend']) && $_GET['resend'] == 'true' && !empty($_GET['email_resend'])) {
    $email_to_resend = trim($_GET['email_resend']);
    if (!filter_var($email_to_resend, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Địa chỉ email không hợp lệ để gửi lại mã.";
        $_SESSION['message_type'] = "warning";
    } else {
        $stmt = $conn->prepare("SELECT id, email_verified_at, full_name FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email_to_resend);
            $stmt->execute();
            $user_for_resend = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user_for_resend) {
                if ($user_for_resend['email_verified_at'] !== NULL) {
                    $_SESSION['message'] = "Email " . htmlspecialchars($email_to_resend) . " đã được xác thực trước đó.";
                    $_SESSION['message_type'] = "info";
                } else {
                    $new_code = generateVerificationCode(); // Hàm này từ functions.php
                    $stmt_update_code = $conn->prepare("UPDATE users SET email_verification_code = ? WHERE email = ?");
                    if ($stmt_update_code) {
                        $stmt_update_code->bind_param("ss", $new_code, $email_to_resend);
                        if ($stmt_update_code->execute()) {
                            // Gọi hàm sendVerificationEmail đã được cập nhật với PHPMailer
                            if (sendVerificationEmail($email_to_resend, $new_code)) {
                                 $_SESSION['message'] = "Mã xác thực mới đã được gửi tới " . htmlspecialchars($email_to_resend) . ". Vui lòng kiểm tra hộp thư của bạn (bao gồm cả thư mục spam).";
                                 $_SESSION['message_type'] = "info";
                            } else {
                                 $_SESSION['message'] = "Không thể gửi lại mã xác thực vào lúc này do lỗi hệ thống gửi mail. Vui lòng thử lại sau.";
                                 $_SESSION['message_type'] = "danger";
                            }
                        } else {
                             $_SESSION['message'] = "Lỗi khi cập nhật mã xác thực mới trong cơ sở dữ liệu.";
                             $_SESSION['message_type'] = "danger";
                        }
                        $stmt_update_code->close();
                    } else {
                         $_SESSION['message'] = "Lỗi hệ thống (prepare update code).";
                         $_SESSION['message_type'] = "danger";
                    }
                }
            } else {
                $_SESSION['message'] = "Email không tồn tại trong hệ thống để gửi lại mã.";
                $_SESSION['message_type'] = "warning";
            }
        } else {
            $_SESSION['message'] = "Lỗi hệ thống (prepare select user for resend).";
            $_SESSION['message_type'] = "danger";
        }
    }
    // Luôn redirect về trang verify_email với email gốc để hiển thị thông báo và form
    redirect("verify_email.php?email=" . urlencode($email_to_resend ?: $email_to_verify));
}

// Lấy lỗi và input cũ từ session (nếu có sau redirect do lỗi POST)
if (isset($_SESSION['_form_errors_verify'])) {
    $errors = $_SESSION['_form_errors_verify'];
    unset($_SESSION['_form_errors_verify']);
}
$old_input_code = '';
if (isset($_SESSION['_old_input_verify']['verification_code'])) {
    $old_input_code = htmlspecialchars($_SESSION['_old_input_verify']['verification_code']);
    unset($_SESSION['_old_input_verify']);
}

?>
<div class="auth-card-wrapper">
    <div class="card auth-card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Xác thực Email</h3>
        </div>
        <div class="card-body">
            <p class="text-center">Một mã xác thực đã được gửi đến email:<br><strong><?php echo htmlspecialchars($email_to_verify); ?></strong>.<br>Vui lòng nhập mã đó vào ô bên dưới để hoàn tất đăng ký.</p>
            
            <?php if (isset($_SESSION['message']) && !empty($_SESSION['message'])): // Hiển thị thông báo từ session (sau redirect) ?>
                <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error_msg): // Đổi tên biến để không trùng lặp ?>
                        <p class="mb-0"><?php echo htmlspecialchars($error_msg); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="verify_email.php?email=<?php echo urlencode($email_to_verify); ?>" method="POST" novalidate>
                <input type="hidden" name="email_hidden" value="<?php echo htmlspecialchars($email_to_verify); ?>">
                
                <div class="mb-3">
                    <label for="verification_code" class="form-label">Mã xác thực</label>
                    <input type="text" class="form-control <?php echo isset($errors['code']) ? 'is-invalid' : ''; ?>" id="verification_code" name="verification_code" value="<?php echo $old_input_code; ?>" required autofocus maxlength="6" pattern="[A-Za-z0-9]{6}" title="Mã gồm 6 ký tự chữ và số.">
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