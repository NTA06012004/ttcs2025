<?php
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

function sendVerificationEmail($email, $code) {
    $subject = "Xác thực địa chỉ Email của bạn";
    $messageBody = "Mã xác thực của bạn là: <b>" . $code . "</b><br>";
    $messageBody .= "Mã này sẽ hết hạn sau 10 phút.<br>";
    $messageBody .= "Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email này.";
    // Thực tế: dùng PHPMailer ở đây
    error_log("Verification email to $email: Code $code (Simulated)");
    return true;
}

function get_user_by_id($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, full_name, email, dob, gender, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Kiểm tra xem user hiện tại có phải là giáo viên của một lớp cụ thể không
function isTeacherOfClass($conn, $user_id, $class_id) {
    if (!isLoggedIn() || empty($user_id) || empty($class_id)) {
        return false;
    }
    $stmt = $conn->prepare("SELECT teacher_id FROM classes WHERE id = ?");
    if (!$stmt) { return false; } // Kiểm tra lỗi prepare
    $stmt->bind_param("i", $class_id);
    if (!$stmt->execute()) { return false; } // Kiểm tra lỗi execute
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $class_data = $result->fetch_assoc();
        $stmt->close();
        return $class_data['teacher_id'] == $user_id;
    }
    $stmt->close();
    return false;
}

// Kiểm tra xem người dùng có được ghi danh (là thành viên) của lớp không
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