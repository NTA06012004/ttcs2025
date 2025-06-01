<?php
require_once 'includes/header.php';
if (isLoggedIn()) { redirect('dashboard.php'); }
?>
<link rel="stylesheet" href="assets/css/landing-page.css">
<link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

<section class="hero-section-landing text-white text-center" style="background-image: url('assets/images/hero-background.jpg');">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <h1 class="display-3 fw-bold mb-4 animate-on-load" data-aos="fade-up">Nền Tảng Học Tập Tương Lai</h1>
        <p class="lead col-lg-8 mx-auto mb-5 animate-on-load" data-aos="fade-up" data-aos-delay="200">
            Kết nối, hợp tác và phát triển kiến thức một cách hiệu quả. EduPlatform mang đến trải nghiệm học tập và giảng dạy trực tuyến toàn diện và hiện đại.
        </p>
        <div class="d-grid gap-3 d-sm-flex justify-content-sm-center animate-on-load" data-aos="fade-up" data-aos-delay="400">
            <a href="register.php" class="btn btn-primary btn-lg px-4 py-3 me-sm-3 fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Đăng ký miễn phí</a>
            <a href="#features" class="btn btn-outline-light btn-lg px-4 py-3"><i class="bi bi-arrow-down-circle-fill me-2"></i>Khám phá tính năng</a>
        </div>
    </div>
</section>

<section id="features" class="py-5 features-section-landing">
    <div class="container px-4 py-5">
        <div class="row text-center mb-5" data-aos="fade-up">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-5 fw-bold">Tại Sao Chọn EduPlatform?</h2>
                <p class="lead text-muted">Chúng tôi cung cấp những công cụ tốt nhất để bạn thành công.</p>
            </div>
        </div>
        <div class="row gx-lg-5 gy-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card text-center p-4 shadow-sm h-100">
                    <div class="feature-icon-landing bg-primary text-white mx-auto mb-3">
                        <i class="bi bi-easel2-fill"></i>
                    </div>
                    <h5 class="fw-bold">Quản lý Lớp học Linh hoạt</h5>
                    <p class="text-muted small">Dễ dàng tạo lớp, mời học sinh, theo dõi tiến độ và quản lý tài liệu khoa học.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card text-center p-4 shadow-sm h-100">
                    <div class="feature-icon-landing bg-success text-white mx-auto mb-3">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h5 class="fw-bold">Bài tập & Đánh giá Hiệu quả</h5>
                    <p class="text-muted small">Giao bài đa dạng, nộp bài trực tuyến, công cụ chấm điểm và phản hồi chi tiết.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card text-center p-4 shadow-sm h-100">
                    <div class="feature-icon-landing bg-info text-white mx-auto mb-3">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h5 class="fw-bold">Tương tác & Cộng tác Tối đa</h5>
                    <p class="text-muted small">Thúc đẩy tương tác qua thảo luận, làm việc nhóm, và thông báo tức thì.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-light how-it-works-section">
    <div class="container px-4 py-5">
        <div class="row gx-lg-5 align-items-center">
            <div class="col-lg-6 order-lg-2" data-aos="fade-left"> 
                <img src="assets/images/eduplatform-workflow.png" alt="Quy trình hoạt động của EduPlatform" class="img-fluid rounded-3 shadow mb-4 mb-lg-0">
            </div>
            <div class="col-lg-6 order-lg-1" data-aos="fade-right">
                <h2 class="display-6 fw-bold mb-3">Bắt đầu thật dễ dàng</h2>
                <p class="lead text-muted mb-4">Chỉ với vài bước đơn giản, bạn có thể tạo lớp học đầu tiên hoặc tham gia vào một không gian học tập đầy cảm hứng.</p>
                <ol class="list-group list-group-flush">
                    <li class="list-group-item bg-transparent border-0 ps-0 py-2 d-flex align-items-start">
                        <span class="badge bg-primary rounded-pill me-3 p-2">1</span>
                        <div><span class="fw-medium">Đăng ký tài khoản</span> nhanh chóng và hoàn toàn miễn phí.</div>
                    </li>
                    <li class="list-group-item bg-transparent border-0 ps-0 py-2 d-flex align-items-start">
                        <span class="badge bg-primary rounded-pill me-3 p-2">2</span>
                        <div>Giáo viên <span class="fw-medium">tạo lớp học</span>, tùy chỉnh và mời học sinh tham gia.</div>
                    </li>
                    <li class="list-group-item bg-transparent border-0 ps-0 py-2 d-flex align-items-start">
                        <span class="badge bg-primary rounded-pill me-3 p-2">3</span>
                        <div>Học sinh <span class="fw-medium">tham gia lớp học</span> bằng mã hoặc lời mời và bắt đầu học.</div>
                    </li>
                    <li class="list-group-item bg-transparent border-0 ps-0 py-2 d-flex align-items-start">
                        <span class="badge bg-primary rounded-pill me-3 p-2">4</span>
                        <div>Cùng nhau <span class="fw-medium">tương tác, làm bài tập,</span> và đạt được mục tiêu!</div>
                    </li>
                </ol>
                <a href="register.php" class="btn btn-primary mt-4 px-4 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Tham gia ngay</a>
            </div>
        </div>
    </div>
</section>

<script src="https://unpkg.com/aos@next/dist/aos.js"></script>
<script>
  AOS.init({
      duration: 800,
      once: true,
  });

  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

const heroElements = document.querySelectorAll('.hero-section-landing .animate-on-load');
window.addEventListener('load', () => {
    heroElements.forEach((el, index) => {
        // Xóa opacity và transform để animation trong CSS (nếu có) hoặc AOS xử lý
        // el.style.opacity = '0'; 
        // el.style.transform = 'translateY(30px)';
        // Chỉ cần đảm bảo class 'loaded' được thêm đúng lúc nếu bạn dùng JS animation
        // AOS sẽ tự xử lý việc này nếu bạn dùng data-aos
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>