<?php
require_once 'includes/db.php'; // Kết nối DB và session_start()
require_once 'includes/functions.php'; // Các hàm tiện ích

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

$class_id_to_delete = (int)$_GET['class_id'];
$current_user_id = $_SESSION['user_id'];

// Kiểm tra xem người dùng hiện tại có phải là giáo viên của lớp này không
if (!isTeacherOfClass($conn, $current_user_id, $class_id_to_delete)) {
    $_SESSION['message'] = "Bạn không có quyền xóa lớp học này.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php'); // Hoặc về class_view.php của lớp đó
}

// (Tùy chọn) Thêm kiểm tra CSRF token nếu bạn đã triển khai
// if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
//     $_SESSION['message'] = "Yêu cầu không hợp lệ (CSRF token).";
//     $_SESSION['message_type'] = "danger";
//     redirect('class_view.php?id=' . $class_id_to_delete);
// }


// Lấy tên lớp để hiển thị trong thông báo
$stmt_class_name = $conn->prepare("SELECT class_name FROM classes WHERE id = ?");
$stmt_class_name->bind_param("i", $class_id_to_delete);
$stmt_class_name->execute();
$result_class_name = $stmt_class_name->get_result();
$class_name = "Lớp học"; // Mặc định
if ($result_class_name->num_rows > 0) {
    $class_name_data = $result_class_name->fetch_assoc();
    $class_name = $class_name_data['class_name'];
}
$stmt_class_name->close();


// Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
$conn->begin_transaction();

try {
    // 1. Xóa các submissions liên quan đến các assignments của lớp này
    // (Cần JOIN assignments để lấy assignment_id)
    $stmt_delete_submissions = $conn->prepare("
        DELETE s FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE a.class_id = ?
    ");
    $stmt_delete_submissions->bind_param("i", $class_id_to_delete);
    $stmt_delete_submissions->execute();
    $stmt_delete_submissions->close();

    // 2. Xóa các tệp đính kèm của assignments (nếu có) - phần này cần xử lý file system
    $stmt_get_assignment_files = $conn->prepare("SELECT file_path FROM assignments WHERE class_id = ? AND file_path IS NOT NULL");
    $stmt_get_assignment_files->bind_param("i", $class_id_to_delete);
    $stmt_get_assignment_files->execute();
    $assignment_files_result = $stmt_get_assignment_files->get_result();
    while($file_row = $assignment_files_result->fetch_assoc()){
        $assignment_file_to_delete = 'uploads/assignment_files/' . $file_row['file_path'];
        if(file_exists($assignment_file_to_delete)){
            unlink($assignment_file_to_delete);
        }
    }
    $stmt_get_assignment_files->close();


    // 3. Xóa các assignments của lớp này
    $stmt_delete_assignments = $conn->prepare("DELETE FROM assignments WHERE class_id = ?");
    $stmt_delete_assignments->bind_param("i", $class_id_to_delete);
    $stmt_delete_assignments->execute();
    $stmt_delete_assignments->close();

    // 4. Xóa các enrollments (ghi danh) của lớp này
    $stmt_delete_enrollments = $conn->prepare("DELETE FROM enrollments WHERE class_id = ?");
    $stmt_delete_enrollments->bind_param("i", $class_id_to_delete);
    $stmt_delete_enrollments->execute();
    $stmt_delete_enrollments->close();

    // 5. Xóa chính lớp học đó
    $stmt_delete_class = $conn->prepare("DELETE FROM classes WHERE id = ? AND teacher_id = ?"); // Thêm teacher_id để chắc chắn
    $stmt_delete_class->bind_param("ii", $class_id_to_delete, $current_user_id);
    $stmt_delete_class->execute();


    if ($stmt_delete_class->affected_rows > 0) {
        $conn->commit(); // Hoàn tất transaction
        $_SESSION['message'] = "Đã xóa thành công lớp học \"".htmlspecialchars($class_name)."\".";
        $_SESSION['message_type'] = "success";
    } else {
        $conn->rollback(); // Hoàn tác nếu có lỗi ở bước cuối
        $_SESSION['message'] = "Không thể xóa lớp học hoặc lớp không tồn tại/không thuộc quyền sở hữu của bạn.";
        $_SESSION['message_type'] = "danger";
    }
    $stmt_delete_class->close();

} catch (mysqli_sql_exception $exception) {
    $conn->rollback(); // Hoàn tác nếu có lỗi SQL
    error_log("SQL Error deleting class: " . $exception->getMessage());
    $_SESSION['message'] = "Đã xảy ra lỗi trong quá trình xóa lớp học. Vui lòng thử lại. " . $exception->getMessage();
    $_SESSION['message_type'] = "danger";
}


redirect('dashboard.php');
?>