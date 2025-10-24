<?php

session_start();
date_default_timezone_set('Europe/Istanbul'); 

// Tarayıcı Önbelleğini Devre Dışı Bırakma 
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

// YETKİLENDİRME KONTROLÜ 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user_company_admin') {
    header('Location: index.php?error=unauthorized_access');
    exit;
}

$action = $_GET['action'] ?? 'list';
$trip_id = $_GET['id'] ?? null;
$company_id = $_SESSION['company_id'];

$trip = [];
$error_message = '';
$company_name = 'Firma Bilgisi Bulunamadı';

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT name FROM Bus_Company WHERE id = :company_id");
    $stmt->execute([':company_id' => $company_id]);
    $company_name = $stmt->fetchColumn() ?: 'Bilinmeyen Firma';

    // Silme İşlemi
    if ($action === 'delete' && $trip_id) {
        $stmt = $db->prepare("DELETE FROM Trips WHERE id = :id AND company_id = :company_id");
        $stmt->execute([':id' => $trip_id, ':company_id' => $company_id]);
        
        header('Location: firma_admin.php?success=deleted');
        exit;
    }

    // Oluşturma/Güncelleme Veri İşleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Veri Temizleme ve Doğrulama
        $d_city = $_POST['departure_city'] ?? null;
        $a_city = $_POST['destination_city'] ?? null;
        $d_time_str = $_POST['departure_time'] ?? null;
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
        $trip_id_post = $_POST['trip_id'] ?? null;
        
        $a_time_str = date('Y-m-d H:i:s', strtotime($d_time_str . ' +7 hours')); 

        if (empty($d_city) || empty($a_city) || empty($d_time_str) || $price === false || $capacity === false) {
            $error_message = "Hata: Lütfen tüm alanları doğru formatta doldurunuz.";
        } else {
            $db->beginTransaction();

            if ($trip_id_post) { // Güncelleme (UPDATE)
                $sql = "UPDATE Trips SET departure_city = :dc, destination_city = :ac, departure_time = :dt, arrival_time = :at, price = :pr, capacity = :cp WHERE id = :id AND company_id = :cid";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':dc' => $d_city, ':ac' => $a_city, ':dt' => $d_time_str, ':at' => $a_time_str, 
                    ':pr' => $price, ':cp' => $capacity, ':id' => $trip_id_post, ':cid' => $company_id
                ]);
                $db->commit();
                header('Location: firma_admin.php?success=updated');
            } else { // Oluşturma (CREATE)
                $new_id = uniqid('trip_');
                $sql = "INSERT INTO Trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity) VALUES (:id, :cid, :dc, :ac, :dt, :at, :pr, :cp)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':id' => $new_id, ':cid' => $company_id, ':dc' => $d_city, ':ac' => $a_city, 
                    ':dt' => $d_time_str, ':at' => $a_time_str, ':pr' => $price, ':cp' => $capacity
                ]);
                $db->commit();
                header('Location: firma_admin.php?success=created');
            }
            exit;
        }
    }
    
    // Düzenleme Formu Verilerini Çekme (GET)
    if ($action === 'edit' && $trip_id) {
        $stmt = $db->prepare("SELECT * FROM Trips WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $trip_id, ':cid' => $company_id]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            header('Location: firma_admin.php?error=trip_not_found');
            exit;
        }
        $trip['departure_time'] = str_replace(' ', 'T', substr($trip['departure_time'], 0, 16));
    }


} catch (PDOException $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    // OWASP A09: Detaylı hata ifşası yerine genel hata mesajı
    $error_message = "Veritabanı Hatası: İşlem sırasında bir sistem sorunu oluştu.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'create' ? 'Yeni Sefer Ekleme' : 'Sefer Düzenleme'; ?> | <?php echo htmlspecialchars($company_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-header { background-color: #007bff; color: white; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; }
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
    <div class="form-header">
        <h1><?php echo $action === 'create' ? 'Yeni Sefer Ekleme' : 'Sefer Düzenleme'; ?></h1>
        <p class="mb-0">Firma: **<?php echo htmlspecialchars($company_name); ?>**</p>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <form method="POST" action="trip_crud.php?action=<?php echo $action; ?>">
        <?php if ($trip_id): ?>
            <input type="hidden" name="trip_id" value="<?php echo htmlspecialchars($trip_id); ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="departure_city" class="form-label">Kalkış Şehri</label>
                <input type="text" class="form-control" id="departure_city" name="departure_city" 
                       value="<?php echo htmlspecialchars($trip['departure_city'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="destination_city" class="form-label">Varış Şehri</label>
                <input type="text" class="form-control" id="destination_city" name="destination_city" 
                       value="<?php echo htmlspecialchars($trip['destination_city'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="departure_time" class="form-label">Kalkış Tarih ve Saati</label>
                <input type="datetime-local" class="form-control" id="departure_time" name="departure_time" 
                       value="<?php echo htmlspecialchars($trip['departure_time'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="price" class="form-label">Bilet Fiyatı (₺)</label>
                <input type="number" step="0.01" min="1" class="form-control" id="price" name="price" 
                       value="<?php echo htmlspecialchars($trip['price'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
                <label for="capacity" class="form-label">Koltuk Kapasitesi</label>
                <input type="number" min="10" max="80" class="form-control" id="capacity" name="capacity" 
                       value="<?php echo htmlspecialchars($trip['capacity'] ?? ''); ?>" required>
            </div>
        </div>

        <button type="submit" class="btn btn-custom mt-4">
            <?php echo $action === 'create' ? 'Seferi Kaydet' : 'Seferi Güncelle'; ?>
        </button>
        <a href="firma_admin.php" class="btn btn-secondary mt-4">İptal</a>
    </form>
</div>

</body>
</html>