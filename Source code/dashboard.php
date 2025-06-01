<?php
require_once 'includes/header.php';
if (!isLoggedIn()) { redirect('login.php'); }

$user_id = $_SESSION['user_id'];
$user_info = get_user_by_id($conn, $user_id);

// Lấy các lớp người dùng này dạy
$teaching_classes = [];
$stmt_teaching = $conn->prepare("SELECT id, class_name, class_code, description FROM classes WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt_teaching->bind_param("i", $user_id);
$stmt_teaching->execute();
$result_teaching = $stmt_teaching->get_result();
while ($row = $result_teaching->fetch_assoc()) {
    $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as member_count FROM enrollments WHERE class_id = ?");
    $count_stmt->bind_param("i", $row['id']);
    $count_stmt->execute();
    $row['member_count'] = $count_stmt->get_result()->fetch_assoc()['member_count'];
    $count_stmt->close();
    $teaching_classes[] = $row;
}
$stmt_teaching->close();

// Lấy các lớp người dùng này học (không bao gồm lớp họ dạy)
$enrolled_classes = [];
$stmt_enrolled = $conn->prepare("
    SELECT c.id, c.class_name, c.class_code, c.description
    FROM classes c
    JOIN enrollments e ON c.id = e.class_id
    WHERE e.user_id = ? AND c.teacher_id != ?
    ORDER BY c.class_name ASC
");
$stmt_enrolled->bind_param("ii", $user_id, $user_id);
$stmt_enrolled->execute();
$result_enrolled = $stmt_enrolled->get_result();
while ($row = $result_enrolled->fetch_assoc()) {
    $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as member_count FROM enrollments WHERE class_id = ?");
    $count_stmt->bind_param("i", $row['id']);
    $count_stmt->execute();
    $row['member_count'] = $count_stmt->get_result()->fetch_assoc()['member_count'];
    $count_stmt->close();
    $enrolled_classes[] = $row;
}
$stmt_enrolled->close();


// Lấy các bài tập sắp đến hạn từ TẤT CẢ các lớp người dùng là thành viên
$upcoming_assignments = [];
$stmt_assignments = $conn->prepare("
    SELECT a.title, a.due_date, c.class_name, a.id as assignment_id, c.id as class_id
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN enrollments e ON c.id = e.class_id
    WHERE e.user_id = ? AND a.due_date >= CURDATE() AND
          NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?)
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt_assignments->bind_param("ii", $user_id, $user_id); // Giả định người dùng này là học sinh đối với bài tập
$stmt_assignments->execute();
$result_assignments = $stmt_assignments->get_result();
while ($row = $result_assignments->fetch_assoc()) {
    $upcoming_assignments[] = $row;
}
$stmt_assignments->close();
$current_page_basename = basename($_SERVER['PHP_SELF']);
?>
<div class="row">
    <div class="col-md-4 col-lg-3">
        <div class="card user-profile-sidebar mb-4">
            <div class="card-body text-center">
                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user_info['profile_picture'] ?: 'default.png'); ?>"
                     alt="Ảnh đại diện" class="profile-picture-lg"
                     onerror="this.onerror=null;this.src='uploads/profile_pictures/default.png';">
                <h4><?php echo htmlspecialchars($user_info['full_name']); ?></h4>
                <p class="text-muted mb-3"><?php echo htmlspecialchars($user_info['email']); ?></p>
                <a href="settings.php" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-gear me-1"></i>Cài đặt tài khoản</a>
            </div>
        </div>
        <div class="list-group user-profile-sidebar shadow-sm">
             <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo ($current_page_basename == 'dashboard.php') ? 'active' : '';?>">
                <i class="bi bi-grid-1x2-fill"></i> Bảng điều khiển
             </a>
             <a href="calendar.php" class="list-group-item list-group-item-action <?php echo ($current_page_basename == 'calendar.php') ? 'active' : '';?>">
                <i class="bi bi-calendar3"></i> Lịch
             </a>
             <a href="todo.php" class="list-group-item list-group-item-action <?php echo ($current_page_basename == 'todo.php') ? 'active' : '';?>">
                <i class="bi bi-check2-square"></i> Việc cần làm
             </a>
            <a href="create_class.php" class="list-group-item list-group-item-action <?php echo ($current_page_basename == 'create_class.php') ? 'active' : '';?>">
                <i class="bi bi-plus-circle"></i> Tạo lớp học mới
            </a>
            <a href="join_class.php" class="list-group-item list-group-item-action <?php echo ($current_page_basename == 'join_class.php') ? 'active' : '';?>">
                <i class="bi bi-person-plus"></i> Tham gia lớp học
            </a>
        </div>
    </div>
<div class="col-md-8 col-lg-9">
    <div class="page-header">
        <h1 class="page-title">Bảng điều khiển</h1>
    </div>

    <?php if (!empty($teaching_classes)): ?>
        <h4 class="mb-3"><i class="bi bi-easel2-fill text-primary me-2"></i>Lớp học bạn giảng dạy</h4>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-2 g-4 mb-4">
            <?php foreach ($teaching_classes as $class): ?>
            <div class="col">
                <div class="card class-card-dashboard shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><a href="class_view.php?id=<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></a></h5>
                        <div class="class-meta">
                            <span class="d-block mb-1"><i class="bi bi-people-fill me-1"></i> <?php echo $class['member_count']; ?> thành viên</span>
                            <span class="d-block">Mã lớp: <strong class="text-info user-select-all"><?php echo htmlspecialchars($class['class_code']); ?></strong></span>
                        </div>
                        <?php if (!empty($class['description'])): ?>
                             <p class="card-text text-muted small mt-2 mb-0" style="max-height: 3.2em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                 <?php echo htmlspecialchars($class['description']); ?>
                             </p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0">
                         <a href="class_view.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary btn-view-class"><i class="bi bi-eye-fill me-1"></i>Quản lý lớp</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($enrolled_classes)): ?>
         <h4 class="mb-3"><i class="bi bi-backpack2-fill text-success me-2"></i>Lớp học bạn tham gia</h4>
         <div class="row row-cols-1 row-cols-md-2 row-cols-lg-2 g-4 mb-4">
            <?php foreach ($enrolled_classes as $class): ?>
            <div class="col">
                <div class="card class-card-dashboard shadow-sm">
                     <div class="card-body">
                        <h5 class="card-title"><a href="class_view.php?id=<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></a></h5>
                         <div class="class-meta">
                            <span class="d-block mb-1"><i class="bi bi-people-fill me-1"></i> <?php echo $class['member_count']; ?> thành viên</span>
                            <?php /* Không hiển thị mã lớp cho HS ở đây để tránh nhầm lẫn, họ đã vào rồi */ ?>
                        </div>
                         <?php if (!empty($class['description'])): ?>
                             <p class="card-text text-muted small mt-2 mb-0" style="max-height: 3.2em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                 <?php echo htmlspecialchars($class['description']); ?>
                             </p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0">
                        <a href="class_view.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-success btn-view-class"><i class="bi bi-door-open-fill me-1"></i>Vào lớp</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($teaching_classes) && empty($enrolled_classes)): ?>
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Chào mừng bạn đến với EduPlatform!</h5>
                <p class="card-text">Có vẻ như bạn chưa tạo hoặc tham gia lớp học nào.</p>
                <a href="create_class.php" class="btn btn-primary me-2"><i class="bi bi-plus-circle me-1"></i>Tạo lớp học</a>
                <a href="join_class.php" class="btn btn-success"><i class="bi bi-person-plus me-1"></i>Tham gia lớp học</a>
            </div>
        </div>
    <?php endif; ?>


    <h4 class="mt-5 mb-3"><i class="bi bi-bell-fill text-warning me-2"></i>Bài tập sắp đến hạn (cần nộp)</h4>
    <?php if (empty($upcoming_assignments)): ?>
        <div class="alert alert-light text-muted">Tuyệt vời! Không có bài tập nào sắp đến hạn cần bạn hoàn thành.</div>
    <?php else: ?>
        <ul class="list-group shadow-sm">
            <?php foreach ($upcoming_assignments as $assignment): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong><a href="class_view.php?id=<?php echo $assignment['class_id']; ?>&tab=assignments" class="text-decoration-none"><?php echo htmlspecialchars($assignment['title']); ?></a></strong>
                    <small class="d-block text-muted"><?php echo htmlspecialchars($assignment['class_name']); ?></small>
                    <small class="text-danger">Hạn nộp: <?php echo date("d/m/Y H:i", strtotime($assignment['due_date'])); ?></small>
                </div>
                <a href="submit_assignment.php?assignment_id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-sm btn-outline-primary">Nộp bài</a>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
</div>
<?php require_once 'includes/footer.php'; ?>