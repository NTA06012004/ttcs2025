<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$current_page_basename = basename($_SERVER['PHP_SELF']);
$user_info_for_nav = null;
if (isLoggedIn()) {
    $user_info_for_nav = get_user_by_id($conn, $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/images/icon.jpg">
    <title>EduPlatform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg custom-navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-book-half"></i> EduPlatform
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (isLoggedIn() && $user_info_for_nav): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page_basename == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">Bảng điều khiển</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user_info_for_nav['profile_picture'] ?: 'default.png'); ?>"
                                     alt="Avatar" class="profile-picture-nav me-2"
                                     onerror="this.onerror=null;this.src='uploads/profile_pictures/default.png';">
                                <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Tài khoản'); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUser">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid-1x2-fill me-2"></i>Bảng điều khiển</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear-fill me-2"></i>Cài đặt</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page_basename == 'index.php') ? 'active' : ''; ?>" href="index.php">Trang chủ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page_basename == 'login.php') ? 'active' : ''; ?>" href="login.php">Đăng nhập</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page_basename == 'register.php') ? 'active' : ''; ?>" href="register.php">Đăng ký</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show mt-3" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>