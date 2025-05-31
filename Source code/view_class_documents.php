<?php
require_once 'includes/header.php';
if (!isLoggedIn()) { redirect('login.php'); }

$class_id_docs = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($class_id_docs <= 0) {
    $_SESSION['message'] = "ID lớp học không hợp lệ."; $_SESSION['message_type'] = "danger"; redirect('dashboard.php');
}

$user_id_docs = $_SESSION['user_id'];

// Lấy thông tin lớp
$stmt_class_docs = $conn->prepare("SELECT id, class_name FROM classes WHERE id = ?");
if (!$stmt_class_docs) { die("Lỗi SQL: " . $conn->error); }
$stmt_class_docs->bind_param("i", $class_id_docs);
$stmt_class_docs->execute();
$result_class_docs = $stmt_class_docs->get_result();
if ($result_class_docs->num_rows == 0) {
    $_SESSION['message'] = "Lớp học không tồn tại."; $_SESSION['message_type'] = "danger"; redirect('dashboard.php');
}
$class_docs_info = $result_class_docs->fetch_assoc();
$stmt_class_docs->close();

// Kiểm tra thành viên
if (!isEnrolledInClass($conn, $user_id_docs, $class_id_docs)) {
    $_SESSION['message'] = "Bạn không có quyền xem tài liệu của lớp này."; $_SESSION['message_type'] = "danger"; redirect('dashboard.php');
}

// Lấy tất cả các tệp đính kèm từ các bài tập của lớp này
$class_documents = [];
$stmt_docs = $conn->prepare("SELECT id, title, file_path, created_at FROM assignments WHERE class_id = ? AND file_path IS NOT NULL ORDER BY created_at DESC");
if (!$stmt_docs) { die("Lỗi SQL: " . $conn->error); }
$stmt_docs->bind_param("i", $class_id_docs);
$stmt_docs->execute();
$result_docs = $stmt_docs->get_result();
while ($doc_row = $result_docs->fetch_assoc()) {
    $class_documents[] = $doc_row;
}
$stmt_docs->close();
?>
<link rel="stylesheet" href="assets/css/class-view.css"> 

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Bảng điều khiển</a></li>
            <li class="breadcrumb-item"><a href="class_view.php?id=<?php echo $class_id_docs; ?>"><?php echo htmlspecialchars($class_docs_info['class_name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Tài liệu lớp học</li>
        </ol>
    </nav>

    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-folder2-open me-2"></i>Tài liệu lớp: <?php echo htmlspecialchars($class_docs_info['class_name']); ?></h1>
    </div>

    <?php if (empty($class_documents)): ?>
        <div class="alert alert-info text-center shadow-sm">
            <i class="bi bi-info-circle-fill fs-1 mb-2"></i>
            <p class="mb-0">Lớp học này hiện chưa có tài liệu nào được đính kèm trong các bài tập.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
            <?php foreach ($class_documents as $document):
                $file_extension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
                $file_icon = "bi-file-earmark-text-fill"; // Mặc định
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) $file_icon = "bi-file-earmark-image-fill text-success";
                elseif ($file_extension == 'pdf') $file_icon = "bi-file-earmark-pdf-fill text-danger";
                elseif (in_array($file_extension, ['doc', 'docx'])) $file_icon = "bi-file-earmark-word-fill text-primary";
                elseif (in_array($file_extension, ['xls', 'xlsx'])) $file_icon = "bi-file-earmark-excel-fill text-success";
                elseif (in_array($file_extension, ['ppt', 'pptx'])) $file_icon = "bi-file-earmark-ppt-fill text-warning";
                elseif ($file_extension == 'zip') $file_icon = "bi-file-earmark-zip-fill text-secondary";
            ?>
            <div class="col">
                <div class="card h-100 shadow-hover document-card">
                    <div class="card-body text-center">
                        <a href="uploads/assignment_files/<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" download class="text-decoration-none d-block">
                            <i class="bi <?php echo $file_icon; ?> display-4 mb-2"></i>
                            <h6 class="card-title text-truncate mt-2" title="<?php echo htmlspecialchars(substr($document['file_path'], strpos($document['file_path'], '_', strpos($document['file_path'], '_', strpos($document['file_path'], '_') + 1) + 1) + 1)); ?>">
                                <?php echo htmlspecialchars(substr($document['file_path'], strpos($document['file_path'], '_', strpos($document['file_path'], '_', strpos($document['file_path'], '_') + 1) + 1) + 1)); // Tên file gốc ?>
                            </h6>
                        </a>
                        <p class="card-text small text-muted mb-1">Từ bài tập: "<?php echo htmlspecialchars($document['title']); ?>"</p>
                        <p class="card-text small text-muted">Ngày đăng: <?php echo date("d/m/Y", strtotime($document['created_at'])); ?></p>
                    </div>
                    <div class="card-footer text-center bg-light border-top-0">
                         <a href="uploads/assignment_files/<?php echo htmlspecialchars($document['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                            <i class="bi bi-download me-1"></i> Tải xuống
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<style>
.shadow-hover:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; transition: box-shadow .2s ease-in-out; }
.document-card .card-title { font-size: 0.9rem; }
.document-card .display-4 { font-size: 3rem; }
</style>
<?php require_once 'includes/footer.php'; ?>