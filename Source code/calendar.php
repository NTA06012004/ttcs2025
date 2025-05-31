<?php
require_once 'includes/header.php';
if (!isLoggedIn()) {
    $_SESSION['message'] = "Bạn cần đăng nhập để xem lịch.";
    $_SESSION['message_type'] = "warning";
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$google_calendar_embed_code = '<iframe src="https://calendar.google.com/calendar/embed?height=600&wkst=1&bgcolor=%23ffffff&ctz=Asia%2FHo_Chi_Minh&src=YOUR_PRIMARY_CALENDAR_ID_OR_SHARED_CALENDAR_ID&color=%23039BE5" style="border:solid 1px #ddd; border-radius: 0.375rem;" width="100%" height="700" frameborder="0" scrolling="yes"></iframe>';

?>

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
        <?php echo $google_calendar_embed_code;?>
    </div>
</div>

<?php

?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>
<?php require_once 'includes/footer.php'; ?>
