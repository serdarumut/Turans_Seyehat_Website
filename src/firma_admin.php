<?php

session_start();
date_default_timezone_set('Europe/Istanbul');

// Tarayıcı Önbelleğini Devre Dışı Bırakma
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

//YETKİLENDİRME KONTROLÜ
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user_company_admin') {
    header('Location: index.php?error=unauthorized_access');
    exit;
}

$company_id = $_SESSION['company_id'];
$trips = [];
$company_name = 'Firma Bilgisi Bulunamadı';
$message = $_GET['message'] ?? '';
$message_type = $_GET['message_type'] ?? '';

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Firma Adını Çekme 
    $stmt = $db->prepare("SELECT name FROM Bus_Company WHERE id = :company_id");
    $stmt->execute([':company_id' => $company_id]);
    $company_name = $stmt->fetchColumn() ?: $company_name;

    // Sadece Kendi Firmasına Ait Seferleri Çekme
    $sql = "
        SELECT 
            id, departure_city, destination_city, departure_time, price, capacity 
        FROM Trips 
        WHERE company_id = :company_id 
        ORDER BY departure_time DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':company_id' => $company_id]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı Hatası: İşlem sırasında bir sorun oluştu.");
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company_name); ?> | Firma Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-header { background-color: #007bff; color: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; }
        .feature-card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
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
        <h1>Firma Yönetim Paneli</h1>
        <p class="fs-5 mb-0">Yönetilen Firma: **<?php echo htmlspecialchars($company_name); ?>**</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars(urldecode($message)); ?></div>
    <?php endif; ?>

    <div class="row mb-4 g-3">
        <div class="col-md-6">
            <div class="card feature-card h-100 bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Sefer Yönetimi</h5>
                    <p class="card-text">Yeni seferler oluşturun, mevcut seferleri güncelleyin veya silin.</p>
                    <a href="trip_crud.php?action=create" class="btn btn-light btn-sm mt-2">Yeni Sefer Ekle</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card feature-card h-100 bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Kupon Yönetimi (Firmaya Özel)</h5>
                    <p class="card-text">Sadece **<?php echo htmlspecialchars($company_name); ?>** firmasında geçerli kuponlar oluşturun.</p>
                    <a href="coupon_crud.php?role=company" class="btn btn-light btn-sm mt-2">Kuponları Yönet</a>
                </div>
            </div>
        </div>
    </div>
    <h3 class="mb-3">Aktif Seferler (<?php echo count($trips); ?> Adet)</h3>

    <?php if (empty($trips)): ?>
        <div class="alert alert-info">Firmanıza ait aktif sefer bulunmamaktadır.</div>
    <?php else: ?>
        <table class="table table-striped table-hover bg-white rounded shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>Kalkış / Varış</th>
                    <th>Zaman</th>
                    <th>Kapasite</th>
                    <th>Fiyat (₺)</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trips as $trip): ?>
                <tr class="align-middle">
                    <td>
                        <span class="fw-bold"><?php echo htmlspecialchars($trip['departure_city']); ?></span> 
                        → 
                        <span class="fw-bold"><?php echo htmlspecialchars($trip['destination_city']); ?></span>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($trip['departure_time'])); ?></td>
                    <td><?php echo $trip['capacity']; ?></td>
                    <td><?php echo number_format($trip['price'], 2, ',', '.'); ?></td>
                    <td>
                        <a href="trip_crud.php?action=edit&id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-warning me-2">Düzenle</a>
                        <a href="trip_crud.php?action=delete&id=<?php echo $trip['id']; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Seferin silinmesini onaylıyor musunuz? Bu sefere ait tüm biletler silinecektir.');">
                           Sil
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

</body>
</html>