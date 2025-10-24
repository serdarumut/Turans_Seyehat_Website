<?php

session_start();
date_default_timezone_set('Europe/Istanbul'); 

// Tarayıcı Önbelleğini Devre Dışı Bırakma 
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

// YETKİLENDİRME KONTROLÜ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=not_logged_in');
    exit;
}

$message = '';
$message_type = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Veri Temizleme ve Doğrulama
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    if ($amount === false || $amount <= 0 || $amount > 10000) {
        $message = "Hata: Geçersiz yükleme miktarı. Lütfen 0.01 ile 10.000 TL arasında bir miktar giriniz.";
        $message_type = 'danger';
    } else {
        $user_id = $_SESSION['user_id'];
        
        try {
            $db = new PDO("sqlite:$db_path");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->beginTransaction();

            // SQL Injection'a karşı korumalı bakiye güncelleme
            $stmt = $db->prepare("UPDATE User SET balance = balance + :amount WHERE id = :user_id");
            $stmt->execute([':amount' => $amount, ':user_id' => $user_id]);

            // Güncel bakiyeyi çekme ve Session'ı güncelleme
            $stmt = $db->prepare("SELECT balance FROM User WHERE id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $_SESSION['balance'] = $stmt->fetchColumn();

            $db->commit();
            
            $message = "Başarılı: Hesabınıza " . number_format($amount, 2, ',', '.') . " ₺ yüklenmiştir. Yeni Bakiyeniz: " . number_format($_SESSION['balance'], 2, ',', '.') . " ₺";
            $message_type = 'success';

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // OWASP A09: Detaylı hata ifşası yerine genel hata mesajı
            $message = "Hata: Sistem hatası oluştu, işlem geri alındı.";
            $message_type = 'danger';
        }
    }
}

// Session'daki mevcut bakiyeyi tekrar kontrol etme
$current_balance = $_SESSION['balance'] ?? 0;

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakiye Yükle | Turans Seyahat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card-custom { border-left: 5px solid #ff7b00; }
        .btn-custom { background-color: #ff7b00; border: none; font-weight: 600; }
        .btn-custom:hover { background-color: #ffa733; }
        
        /* Navbar Stil Zorlaması */
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
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="mb-4">Kredi Yükleme İşlemi</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="card card-custom">
                <div class="card-body">
                    <p class="fs-5 mb-4">Mevcut Bakiyeniz: <span class="fw-bold text-success"><?php echo number_format($current_balance, 2, ',', '.'); ?> ₺</span></p>

                    <form method="POST" action="top_up_balance.php">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Yüklenecek Miktar (₺)</label>
                            <input type="number" step="0.01" min="0.01" max="10000" class="form-control" id="amount" name="amount" placeholder="Örn: 1000.00" required>
                            <div class="form-text">Miktar hesaba otomatik olarak yüklenecektir (Ödeme entegrasyonu simülasyonu).</div>
                        </div>
                        <button type="submit" class="btn btn-custom w-100">Bakiyeyi Yükle</button>
                    </form>
                </div>
            </div>
            
            <p class="mt-3 text-center">
                <a href="hesabim.php">← Hesabım Sayfasına Geri Dön</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>