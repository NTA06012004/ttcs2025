<?php
require_once 'includes/header.php'; // Bao gồm db.php và functions.php
if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để xem lịch.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$user_id = $_SESSION['user_id']; // Có thể dùng để cá nhân hóa mã nhúng nếu cần sau này
$google_calendar_embed_code = '<iframe src="https://calendar.google.com/calendar/embed?height=600&wkst=1&bgcolor=%23ffffff&ctz=Asia%2FHo_Chi_Minh&src=YOUR_PRIMARY_CALENDAR_ID_OR_SHARED_CALENDAR_ID&color=%23039BE5" style="border:solid 1px #ddd; border-radius: 0.375rem;" width="100%" height="700" frameborder="0" scrolling="yes"></iframe>';

// Mẹo: Bạn có thể lưu trữ ID lịch Google của người dùng trong cơ sở dữ liệu
// và tạo động $google_calendar_embed_code dựa trên ID đó nếu muốn mỗi người dùng
// xem một lịch Google khác nhau được liên kết với tài khoản EduPlatform của họ.
// Ví dụ (nếu bạn có cột 'google_calendar_id' trong bảng 'users'):
/*
$user_info_for_calendar = get_user_by_id($conn, $user_id); // Giả sử hàm này lấy cả google_calendar_id
if (isset($user_info_for_calendar['google_calendar_id']) && !empty($user_info_for_calendar['google_calendar_id'])) {
    $calendar_id_to_embed = urlencode($user_info_for_calendar['google_calendar_id']);
    // Bạn có thể cho phép người dùng tùy chỉnh màu sắc, múi giờ,... và lưu vào DB
    $timezone = urlencode('Asia/Ho_Chi_Minh'); // Ví dụ
    $primary_color = urlencode('#0B8043'); // Ví dụ màu xanh lá
    $google_calendar_embed_code = '<iframe src="https://calendar.google.com/calendar/embed?height=700&wkst=1&bgcolor=%23ffffff&ctz='.$timezone.'&src='.$calendar_id_to_embed.'&color='.$primary_color.'" style="border:solid 1px #ddd; border-radius: 0.375rem;" width="100%" height="700" frameborder="0" scrolling="yes"></iframe>';
} else {
    // Fallback về một lịch mặc định hoặc thông báo nếu người dùng chưa liên kết lịch
    $default_calendar_id = 'en.vietnamese#holiday@group.v.calendar.google.com'; // Ví dụ lịch ngày lễ VN
    $google_calendar_embed_code = '<iframe src="https://calendar.google.com/calendar/embed?height=700&wkst=1&bgcolor=%23ffffff&ctz=Asia%2FHo_Chi_Minh&src='.urlencode($default_calendar_id).'&color=%230B8043" style="border:solid 1px #ddd; border-radius: 0.375rem;" width="100%" height="700" frameborder="0" scrolling="yes"></iframe>';
    $_SESSION['message'] = "Lịch Google cá nhân của bạn chưa được liên kết. Đang hiển thị lịch mặc định.";
    $_SESSION['message_type'] = "info";
}
*/

?>
<!-- Có thể thêm CSS riêng cho trang calendar nếu cần nhiều style đặc thù -->
<!-- <link rel="stylesheet" href="assets/css/calendar-page.css"> -->

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-calendar3-event-fill me-2"></i>Lịch của bạn</h1>
    <div>
        <a href="https://calendar.google.com" target="_blank" class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="Mở Google Calendar trong tab mới để quản lý sự kiện">
            <i class="bi bi-google me-1"></i> Mở Google Calendar
        </a>
    </div>
</div>

<?php if (isset($_SESSION['message']) && $_SESSION['message_type'] == 'info'): // Hiển thị thông báo nếu có ?>
    <div class="alert alert-info alert-dismissible fade show mb-3">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">Lịch Google</h5>
    </div>
    <div class="card-body p-2 p-md-3"> 
        <?php echo $google_calendar_embed_code; // In ra mã iframe ?>
    </div>
</div>

<?php
// Phần hiển thị sự kiện từ DB của bạn (nếu muốn giữ lại song song)
/*
$events_from_db = []; // Query lấy sự kiện từ DB của bạn ở đây
if (!empty($events_from_db)) {
    echo '<div class="card shadow-sm mt-4">';
    echo '<div class="card-header bg-light"><h5 class="mb-0"><i class="bi bi-journal-bookmark-fill me-2"></i>Sự kiện từ EduPlatform</h5></div>';
    echo '<ul class="list-group list-group-flush">';
    foreach ($events_from_db as $event_db) {
        echo '<li class="list-group-item">';
        echo '<strong>' . htmlspecialchars($event_db['title']) . '</strong> - ';
        echo '<small class="text-danger">Hạn: ' . date("d/m/Y H:i", strtotime(str_replace('T', ' ', $event_db['start']))) . '</small>';
        echo '</li>';
    }
    echo '</ul></div>';
}
*/
?>
<script>
// Kích hoạt tooltips (nếu bạn dùng)
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>
<?php require_once 'includes/footer.php'; ?>