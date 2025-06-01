<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để thực hiện hành động này.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
    $_SESSION['message'] = "ID lớp học không hợp lệ.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}

$class_id_to_leave = (int)$_GET['class_id'];
$current_user_id = $_SESSION['user_id'];

// Kiểm tra xem người dùng có phải là thành viên của lớp không
if (!isEnrolledInClass($conn, $current_user_id, $class_id_to_leave)) {
    $_SESSION['message'] = "Bạn không phải là thành viên của lớp học này.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}

// Kiểm tra xem người dùng có phải là giáo viên của lớp này không
// Giáo viên không thể "rời lớp" theo cách này, họ phải "xóa lớp"
if (isTeacherOfClass($conn, $current_user_id, $class_id_to_leave)) {
    $_SESSION['message'] = "Bạn là giáo viên của lớp này. Để xóa lớp, vui lòng sử dụng chức năng 'Xóa lớp học'.";
    $_SESSION['message_type'] = "warning";
    redirect('class_view.php?id=' . $class_id_to_leave);
}

// Lấy tên lớp để hiển thị trong thông báo
$stmt_class_name = $conn->prepare("SELECT class_name FROM classes WHERE id = ?");
$stmt_class_name->bind_param("i", $class_id_to_leave);
$stmt_class_name->execute();
$result_class_name = $stmt_class_name->get_result();
$class_name = "Lớp học";
if ($result_class_name->num_rows > 0) {
    $class_name_data = $result_class_name->fetch_assoc();
    $class_name = $class_name_data['class_name'];
}
$stmt_class_name->close();

// Bắt đầu transaction
$conn->begin_transaction();
try {
    // 1. (Tùy chọn) Xóa các bài nộp của học sinh này trong lớp đó
    // Nếu không xóa, bài nộp vẫn còn nhưng học sinh không còn trong lớp.
    // Nếu muốn xóa:
    // $stmt_delete_my_submissions = $conn->prepare("
    // DELETE s FROM submissions s
    // JOIN assignments a ON s.assignment_id = a.id
    // WHERE a.class_id = ? AND s.student_id = ?
    // ");
    // $stmt_delete_my_submissions->bind_param("ii", $class_id_to_leave, $current_user_id);
    // $stmt_delete_my_submissions->execute();
    // $stmt_delete_my_submissions->close();

    // 2. Xóa bản ghi ghi danh (enrollment) của người dùng này khỏi lớp
    $stmt_leave = $conn->prepare("DELETE FROM enrollments WHERE class_id = ? AND user_id = ?");
    $stmt_leave->bind_param("ii", $class_id_to_leave, $current_user_id);
    $stmt_leave->execute();

    if ($stmt_leave->affected_rows > 0) {
        $conn->commit();
        $_SESSION['message'] = "Bạn đã rời khỏi lớp học \"".htmlspecialchars($class_name)."\" thành công.";
        $_SESSION['message_type'] = "success";
    } else {
        $conn->rollback();
        $_SESSION['message'] = "Không thể rời khỏi lớp học. Vui lòng thử lại.";
        $_SESSION['message_type'] = "danger";
    }
    $stmt_leave->close();

} catch (mysqli_sql_exception $exception) {
    $conn->rollback();
    error_log("SQL Error leaving class: " . $exception->getMessage());
    $_SESSION['message'] = "Đã xảy ra lỗi trong quá trình rời lớp. Vui lòng thử lại.";
    $_SESSION['message_type'] = "danger";
}

redirect('dashboard.php');
?>