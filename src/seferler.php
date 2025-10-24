<?php 

session_start();
date_default_timezone_set('Europe/Istanbul');

// Tarayıcı Önbelleğini Devre Dışı Bırakma 
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

$nereden = $_GET['nereden'] ?? '';
$nereye = $_GET['nereye'] ?? '';
$tarih = $_GET['tarih'] ?? ''; 

// Arama kriterleri eksikse ana sayfaya yönlendirme
if (empty($nereden) || empty($nereye) || empty($tarih)) {
    header('Location: index.php');
    exit;
}

$seferler = [];
$hata_mesaji = '';

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Geçerli tarih ve saati al
    $today_date = date('Y-m-d');
    $current_datetime = date('Y-m-d H:i:s'); 
    
    // İstenilen güzergah saat SQL sorgusu
    $sql = "
        SELECT 
            t.*, 
            bc.name AS company_name 
        FROM Trips t
        INNER JOIN Bus_Company bc ON t.company_id = bc.id
        WHERE 
            t.departure_city = :nereden AND 
            t.destination_city = :nereye AND 
            DATE(t.departure_time) = :tarih 
            -- KRİTİK FİLTRE: Eğer aranan tarih BUGÜN ise, sadece GELECEK saatteki seferleri göster
            " . (($tarih === $today_date) ? " AND t.departure_time >= :current_datetime" : "") . "
        ORDER BY t.departure_time ASC
    ";
    
    $stmt = $db->prepare($sql);
    
    $params = [
        ':nereden' => $nereden,
        ':nereye' => $nereye,
        ':tarih' => $tarih 
    ];
    
    // Parametreye mevcut zamanı ekle
    if ($tarih === $today_date) {
        $params[':current_datetime'] = $current_datetime;
    }
    
    $stmt->execute($params);
    $seferler = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // OWASP A09: Detaylı hata ifşası yerine genel hata mesajı
    $hata_mesaji = "Sistem hatası oluştu, lütfen daha sonra tekrar deneyiniz.";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seferler | <?php echo htmlspecialchars($nereden); ?> - <?php echo htmlspecialchars($nereye); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .trip-card {
            border: none;
            border-left: 5px solid #ff7b00;
            margin-bottom: 1rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .trip-card.past {
            opacity: 0.6;
            filter: grayscale(80%);
            border-left-color: #6c757d;
        }
        .trip-card:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
            transform: translateY(-2px);
        }
        .price-col {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff7b00;
        }
        .btn-custom {
            background-color: #ff7b00;
            border: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h1 class="mb-4">
        <?php echo htmlspecialchars($nereden); ?> → <?php echo htmlspecialchars($nereye); ?> Seferleri 
        <small class="text-muted" style="font-size: 0.5em;"><?php echo date('d.m.Y', strtotime($tarih)); ?></small>
    </h1>

    <?php if ($hata_mesaji): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($hata_mesaji); ?></div>
    <?php elseif (empty($seferler)): ?>
        <div class="alert alert-warning">Seçilen güzergahta sefer bulunamamıştır.</div>
    <?php else: ?>
        
        <?php foreach ($seferler as $sefer): 
            $kalkis_saati = date('H:i', strtotime($sefer['departure_time']));
            $varis_saati = date('H:i', strtotime($sefer['arrival_time']));
            $bos_koltuk = $sefer['capacity']; 

            $is_past = (strtotime($sefer['departure_time']) < time()); // Kullanıcı arama tarihini değil, sadece UI'ı etkiler
            $card_class = 'trip-card';
        ?>
            <div class="card <?php echo $card_class; ?>">
                <div class="card-body row align-items-center">
                    
                    <div class="col-md-3">
                        <small class="text-muted">Firma</small>
                        <h5><?php echo htmlspecialchars($sefer['company_name']); ?></h5>
                    </div>

                    <div class="col-md-2 text-center">
                        <small class="text-muted">Kalkış</small>
                        <h4 class="mb-0"><?php echo $kalkis_saati; ?></h4>
                        <small><?php echo htmlspecialchars($sefer['departure_city']); ?></small>
                    </div>

                    <div class="col-md-2 text-center text-muted">
                        <small>Varış</small>
                        <h4 class="mb-0"><?php echo $varis_saati; ?></h4>
                        <small><?php echo htmlspecialchars($sefer['destination_city']); ?></small>
                    </div>

                    <div class="col-md-2 text-center">
                        <small class="text-muted">Boş Koltuk</small>
                        <p class="mb-0 fw-bold text-success"><?php echo $bos_koltuk; ?></p>
                    </div>

                    <div class="col-md-1 text-end price-col">
                        <?php echo number_format($sefer['price'], 2, ',', '.'); ?> ₺
                    </div>

                    <div class="col-md-2 text-end">
                        <a href="sefer_detay.php?id=<?php echo $sefer['id']; ?>" class="btn btn-custom w-100">Bilet Al</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>