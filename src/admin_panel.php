<?php
session_start();
date_default_timezone_set('Europe/Istanbul'); 

// Tarayıcı Önbelleğini Devre Dışı Bırakma
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

// YETKİLENDİRME KONTROLÜ (OWASP A01)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php?error=unauthorized_access');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Sistem Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-header { background-color: #dc3545; color: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; }
        .feature-card { border-left: 5px solid #007bff; transition: all 0.3s; cursor: pointer; }
        .feature-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .icon { font-size: 2rem; color: #dc3545; }

        .navbar { 
            background-color: #343a40 !important; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.5) !important;
        }
        .navbar a {
            color: #fff !important; 
        }
        .navbar .text-danger {
            color: #ffc107 !important; 
            font-weight: 700 !important;
            border: 1px solid #ffc107;
            padding: 5px 10px;
            border-radius: 5px;
            transition: 0.2s;
        }
        .navbar .text-danger:hover {
            background-color: #ffc107 !important;
            color: #343a40 !important;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="admin-header">
        <h1>Sistem Yönetim Paneli</h1>
        <p class="fs-5 mb-0">Hoş geldiniz, Yönetici (<?php echo htmlspecialchars($_SESSION['full_name']); ?>).</p>
    </div>

    <h3 class="mb-4">Yönetim Modülleri</h3>

    <div class="row g-4">
        
        <div class="col-md-6">
            <a href="company_crud.php" class="text-decoration-none text-dark">
                <div class="card feature-card">
                    <div class="card-body d-flex align-items-center">
                        <span class="icon me-4">🏢</span>
                        <div>
                            <h5 class="card-title">Otobüs Firmaları ve Firma Adminleri</h5>
                            <p class="card-text text-muted">Yeni firmaları oluşturur, mevcutları düzenler ve Firma Adminlerini atar/yönetir.</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-6">
            <a href="coupon_crud.php" class="text-decoration-none text-dark">
                <div class="card feature-card">
                    <div class="card-body d-flex align-items-center">
                        <span class="icon me-4">🎫</span>
                        <div>
                            <h5 class="card-title">İndirim Kuponu Yönetimi</h5>
                            <p class="card-text text-muted">İndirim kuponlarını (kod, oran, limit, son kullanma tarihi) oluşturur, düzenler ve siler.</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        
    </div>
</div>

</body>
</html>