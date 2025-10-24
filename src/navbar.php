<?php // navbar.php ?>
<nav class="navbar navbar-expand-lg px-4">
    <a class="navbar-brand fw-bold text-white" href="index.php">Turans Seyahat</a>
    <div class="ms-auto">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="navbar-text me-3 text-white">
                Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            
            <?php if ($_SESSION['role'] === 'user'): ?>
                <a class="nav-link d-inline mx-2 text-white" href="hesabim.php">Hesabım/Biletlerim</a>
            <?php endif; ?>
            
            <?php if ($_SESSION['role'] === 'user_company_admin'): ?>
                <a class="nav-link d-inline mx-2 text-warning" href="firma_admin.php">Firma Yönetimi</a>
            <?php elseif ($_SESSION['role'] === 'admin'): ?>
                <a class="nav-link d-inline mx-2 text-danger" href="admin_panel.php">Admin Panel</a>
            <?php endif; ?>
            
            <a class="nav-link d-inline mx-2 text-white" href="logout.php">Çıkış Yap</a>
        <?php else: ?>
            <a class="nav-link d-inline mx-2 text-white" href="login.php">Giriş Yap</a>
            <a class="nav-link d-inline mx-2 text-white" href="register.php">Kayıt Ol</a>
        <?php endif; ?>
    </div>
</nav>