<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để thực hiện hành động này.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$assignment_id_to_delete = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$class_id_redirect = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0; // Để redirect về đúng lớp

if ($assignment_id_to_delete <= 0 || $class_id_redirect <= 0) {
    $_SESSION['message'] = "Thông tin không hợp lệ để xóa bài tập.";
    $_SESSION['message_type'] = "danger";
    redirect('dashboard.php');
}

$current_user_id = $_SESSION['user_id'];

// Kiểm tra xem người dùng có phải là giáo viên của lớp chứa bài tập này không
$stmt_check_owner = $conn->prepare("SELECT a.title, a.file_path as assignment_file, c.teacher_id 
                                    FROM assignments a 
                                    JOIN classes c ON a.class_id = c.id 
                                    WHERE a.id = ? AND c.id = ?");
if (!$stmt_check_owner) { die("Lỗi SQL (check owner): " . $conn->error); }
$stmt_check_owner->bind_param("ii", $assignment_id_to_delete, $class_id_redirect);
$stmt_check_owner->execute();
$result_check_owner = $stmt_check_owner->get_result();

if ($result_check_owner->num_rows == 0) {
    $_SESSION['message'] = "Bài tập không tồn tại hoặc không thuộc lớp này.";
    $_SESSION['message_type'] = "danger";
    redirect('class_view.php?id=' . $class_id_redirect . '&tab=assignments');
}
$assignment_data = $result_check_owner->fetch_assoc();
$stmt_check_owner->close();

if ($assignment_data['teacher_id'] != $current_user_id) {
    $_SESSION['message'] = "Bạn không có quyền xóa bài tập này.";
    $_SESSION['message_type'] = "danger";
    redirect('class_view.php?id=' . $class_id_redirect . '&tab=assignments');
}

$assignment_title_deleted = $assignment_data['title'];

// Bắt đầu transaction
$conn->begin_transaction();
try {
    // 1. Xóa các tệp đính kèm của các bài nộp (submissions) liên quan đến bài tập này
    $stmt_get_submission_files = $conn->prepare("SELECT file_path FROM submissions WHERE assignment_id = ? AND file_path IS NOT NULL");
    if ($stmt_get_submission_files) {
        $stmt_get_submission_files->bind_param("i", $assignment_id_to_delete);
        $stmt_get_submission_files->execute();
        $submission_files_result = $stmt_get_submission_files->get_result();
        while($file_row = $submission_files_result->fetch_assoc()){
            $submission_file_to_delete = 'uploads/submission_files/' . $file_row['file_path'];
            if(file_exists($submission_file_to_delete)){
                unlink($submission_file_to_delete);
            }
        }
        $stmt_get_submission_files->close();
    }

    // 2. Xóa các bài nộp (submissions) liên quan đến bài tập này
    $stmt_delete_submissions = $conn->prepare("DELETE FROM submissions WHERE assignment_id = ?");
    if (!$stmt_delete_submissions) { throw new Exception("Lỗi SQL (prepare delete submissions): " . $conn->error); }
    $stmt_delete_submissions->bind_param("i", $assignment_id_to_delete);
    $stmt_delete_submissions->execute();
    $stmt_delete_submissions->close();

    // 3. Xóa tệp đính kèm của chính bài tập (nếu có)
    if (!empty($assignment_data['assignment_file'])) {
        $assignment_main_file_to_delete = 'uploads/assignment_files/' . $assignment_data['assignment_file'];
        if (file_exists($assignment_main_file_to_delete)) {
            unlink($assignment_main_file_to_delete);
        }
    }

    // 4. Xóa chính bài tập đó
    $stmt_delete_assignment = $conn->prepare("DELETE FROM assignments WHERE id = ?");
    if (!$stmt_delete_assignment) { throw new Exception("Lỗi SQL (prepare delete assignment): " . $conn->error); }
    $stmt_delete_assignment->bind_param("i", $assignment_id_to_delete);
    $stmt_delete_assignment->execute();

    if ($stmt_delete_assignment->affected_rows > 0) {
        $conn->commit();
        $_SESSION['message'] = "Bài tập \"".htmlspecialchars($assignment_title_deleted)."\" và các bài nộp liên quan đã được xóa.";
        $_SESSION['message_type'] = "success";
    } else {
        $conn->rollback();
        $_SESSION['message'] = "Không thể xóa bài tập hoặc bài tập không tồn tại.";
        $_SESSION['message_type'] = "warning";
    }
    $stmt_delete_assignment->close();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting assignment ID {$assignment_id_to_delete}: " . $e->getMessage());
    $_SESSION['message'] = "Đã xảy ra lỗi trong quá trình xóa bài tập. Vui lòng thử lại.";
    $_SESSION['message_type'] = "danger";
}

redirect('class_view.php?id=' . $class_id_redirect . '&tab=assignments');
?>
