<?php // trong includes/functions.php

// Sử dụng các lớp của PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php'; 


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function generateVerificationCode($length = 6) {
    return substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
}
function sendPasswordResetEmail($recipient_email, $recipient_name, $reset_link) {
    $mail = new PHPMailer(true);

    try {
        // Cấu hình Server (TƯƠNG TỰ NHƯ sendVerificationEmail)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Bật để debug, tắt trên production
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@gmail.com';
        $mail->Password   = 'your_gmail_app_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587; // Hoặc 465 nếu dùng SMTPS

        // Người gửi và người nhận
        $mail->setFrom('your_email@gmail.com', 'EduPlatform - Khôi phục mật khẩu'); // Email và tên người gửi
        $mail->addAddress($recipient_email, $recipient_name); // Thêm người nhận

        // Nội dung Email
        $mail->isHTML(true);
        $mail->Subject = 'Yeu cau khoi phuc mat khau tai khoan EduPlatform'; // Viết không dấu cho Subject để tránh một số vấn đề encoding
        $mail->Body    = "<p>Chào " . htmlspecialchars($recipient_name) . ",</p>" .
                         "<p>Chúng tôi nhận được yêu cầu khôi phục mật khẩu cho tài khoản của bạn trên EduPlatform.</p>" .
                         "<p>Vui lòng nhấp vào liên kết bên dưới để đặt lại mật khẩu của bạn. Liên kết này sẽ có hiệu lực trong 1 giờ:</p>" .
                         "<p><a href='" . $reset_link . "'>" . $reset_link . "</a></p>" .
                         "<p>Nếu bạn không yêu cầu điều này, vui lòng bỏ qua email này.</p>" .
                         "<p>Trân trọng,<br>Đội ngũ EduPlatform</p>";
        $mail->AltBody = "Để đặt lại mật khẩu, vui lòng truy cập liên kết sau (có hiệu lực trong 1 giờ): " . $reset_link . " Nếu bạn không yêu cầu, hãy bỏ qua email này.";
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (Password Reset): Mail could not be sent. Mailer Error: {$mail->ErrorInfo} User Email: {$recipient_email}");
        return false;
    }
}
function sendVerificationEmail($email, $code) {
    $mail = new PHPMailer(true);

    try {
        // Cấu hình Server
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;  // Bật output debug chi tiết, chỉ dùng khi test
        $mail->isSMTP();                               // Gửi bằng SMTP
        $mail->Host       = 'smtp.gmail.com';          // Đặt SMTP server của bạn (ví dụ: Gmail)
        $mail->SMTPAuth   = true;                      // Bật xác thực SMTP
        $mail->Username   = 'your_email@gmail.com';    // Tài khoản SMTP (email của bạn)
        $mail->Password   = 'your_gmail_app_password'; // Mật khẩu SMTP (Mật khẩu ứng dụng nếu dùng Gmail)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Bật mã hóa TLS; PHPMailer::ENCRYPTION_SMTPS cũng được chấp nhận
        $mail->Port       = 587;                       // Cổng TCP để kết nối; sử dụng 465 cho SMTPS

        // Người gửi và người nhận
        $mail->setFrom('your_email@gmail.com', 'EduPlatform'); // Email và tên người gửi
        $mail->addAddress($email);                     // Thêm người nhận (email người dùng đăng ký)
        // $mail->addReplyTo('info@example.com', 'Information');
        // $mail->addCC('cc@example.com');
        // $mail->addBCC('bcc@example.com');

        // Nội dung Email
        $mail->isHTML(true);                                  // Đặt định dạng email là HTML
        $mail->Subject = 'Xac thuc dia chi Email cua ban - EduPlatform';
        $mail->Body    = "Cam on ban da dang ky tai khoan tren EduPlatform!<br>" .
                         "Ma xac thuc cua ban la: <b>" . $code . "</b><br>" .
                         "Ma nay se het han sau 10 phut.<br>" .
                         "Neu ban khong yeu cau ma nay, vui long bo qua email nay.";
        $mail->AltBody = 'Ma xac thuc cua ban la: ' . $code . '. Ma nay se het han sau 10 phut.';
        $mail->CharSet = 'UTF-8'; // Đảm bảo hiển thị tiếng Việt đúng

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Ghi log lỗi chi tiết hơn để debug
        error_log("PHPMailer Error: Mail could not be sent. Mailer Error: {$mail->ErrorInfo} User Email: {$email}");
        // Không nên hiển thị $mail->ErrorInfo trực tiếp cho người dùng trên production
        return false;
    }
}

function get_user_by_id($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, full_name, email, dob, gender, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Các hàm isTeacherOfClass và isEnrolledInClass giữ nguyên
function isTeacherOfClass($conn, $user_id, $class_id) {
    if (!isLoggedIn() || empty($user_id) || empty($class_id)) {
        return false;
    }
    $stmt = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
    if (!$stmt) { return false; }
    $stmt->bind_param("i", $class_id);
    if (!$stmt->execute()) { return false; }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $class_data = $result->fetch_assoc();
        $stmt->close();
        return $class_data['teacher_id'] == $user_id;
    }
    $stmt->close();
    return false;
}

function isEnrolledInClass($conn, $user_id, $class_id) {
    if (!isLoggedIn() || empty($user_id) || empty($class_id)) {
        return false;
    }
    $stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND class_id = ?");
    if (!$stmt) { return false; }
    $stmt->bind_param("ii", $user_id, $class_id);
    if (!$stmt->execute()) { return false; }
    $stmt->store_result();
    $is_enrolled = $stmt->num_rows > 0;
    $stmt->close();
    return $is_enrolled;
}
?>