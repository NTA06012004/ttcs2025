<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    // ... redirect login
}

$class_id_from = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$user_id_to_remove = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$current_teacher_id = $_SESSION['user_id'];

// 1. Kiểm tra CSRF token (nếu bạn dùng)

// 2. Kiểm tra người thực hiện có phải là GV của lớp này không
if (!isTeacherOfClass($conn, $current_teacher_id, $class_id_from)) {
    $_SESSION['message'] = "Bạn không có quyền xóa thành viên khỏi lớp này.";
    $_SESSION['message_type'] = "danger";
    redirect('class_view.php?id=' . $class_id_from . '&tab=members');
}

// 3. Không cho GV tự xóa mình khỏi vai trò GV (họ phải xóa lớp)
if ($user_id_to_remove == $current_teacher_id) {
    $_SESSION['message'] = "Giáo viên không thể tự xóa mình khỏi lớp. Hãy dùng chức năng Xóa lớp.";
    $_SESSION['message_type'] = "warning";
    redirect('class_view.php?id=' . $class_id_from . '&tab=members');
}

// 4. Xóa enrollment
$stmt_remove = $conn->prepare("DELETE FROM enrollments WHERE class_id = ? AND user_id = ?");
if ($stmt_remove) {
    $stmt_remove->bind_param("ii", $class_id_from, $user_id_to_remove);
    if ($stmt_remove->execute()) {
        if ($stmt_remove->affected_rows > 0) {
            $_SESSION['message'] = "Đã xóa thành viên khỏi lớp.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Không tìm thấy thành viên này trong lớp hoặc đã có lỗi.";
            $_SESSION['message_type'] = "warning";
        }
    } else {
        $_SESSION['message'] = "Lỗi khi xóa thành viên: " . $stmt_remove->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt_remove->close();
} else {
    $_SESSION['message'] = "Lỗi hệ thống (prepare statement).";
    $_SESSION['message_type'] = "danger";
}

redirect('class_view.php?id=' . $class_id_from . '&tab=members');
?>