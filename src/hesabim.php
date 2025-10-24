<?php 

session_start();
date_default_timezone_set('Europe/Istanbul'); 

// Tarayıcı Önbelleğini Devre Dışı Bırakma 
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

// Yetki Kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=not_logged_in');
    exit;
}

$user_id = $_SESSION['user_id'];
$biletler = [];
$now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Kullanıcının güncel bakiyesini çekme (A01)
    $stmt = $db->prepare("SELECT balance FROM User WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $_SESSION['balance'] = $stmt->fetchColumn() ?? 0.00;


    // Biletleri ve ilgili detayları çekme 
    $sql = "
        SELECT 
            t.id AS ticket_id, t.status, t.total_price, t.created_at AS purchase_date,
            tr.id AS trip_id, tr.departure_city, tr.destination_city, tr.departure_time,
            bc.name AS company_name,
            GROUP_CONCAT(bs.seat_number) AS seats
        FROM Tickets t
        INNER JOIN Trips tr ON t.trip_id = tr.id
        INNER JOIN Bus_Company bc ON tr.company_id = bc.id
        INNER JOIN Booked_Seats bs ON t.id = bs.ticket_id
        WHERE t.user_id = :user_id
        GROUP BY t.id
        ORDER BY tr.departure_time DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $biletler = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı Hatası: Bilet bilgileri alınamadı.");
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabım ve Biletlerim | Turans Seyahat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .balance-card { border-left: 5px solid #ff7b00; }
        .ticket-card { margin-bottom: 1.5rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn-cancel { background-color: #dc3545; color: white; }
        .btn-cancel:hover { background-color: #c82333; color: white; }

        /* NAVBAR STİL ZORLAMASI */
        .navbar { 
            background-color: #343a40 !important; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.5) !important;
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
    
    <div class="card balance-card mb-5">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title">Sayın <?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                <p class="card-text fs-5">Hesap Bakiyeniz (Kredi): <span class="fw-bold text-success"><?php echo number_format($_SESSION['balance'], 2, ',', '.'); ?> ₺</span></p>
            </div>
            <a href="top_up_balance.php" class="btn btn-lg btn-success">Kredi Yükle</a>
        </div>
    </div>

    <h2 class="mb-4">Bilet Geçmişi (Toplam: <?php echo count($biletler); ?>)</h2>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'purchase'): ?>
        <div class="alert alert-success">Bilet alım işlemi başarıyla tamamlanmıştır.</div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'cancel'): ?>
        <div class="alert alert-warning">Bilet başarıyla iptal edilmiştir. İade tutarı hesabınıza aktarılmıştır.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'cancel'): ?>
        <div class="alert alert-danger">Bilet iptali başarısız olmuştur: <?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>


    <?php if (empty($biletler)): ?>
        <div class="alert alert-info">Henüz satın alınmış bir biletiniz bulunmamaktadır.</div>
    <?php else: ?>
        <div class="row">
        <?php foreach ($biletler as $bilet): 
            date_default_timezone_set('Europe/Istanbul'); 
            $departure_time = new DateTime($bilet['departure_time'], new DateTimeZone('Europe/Istanbul'));
            $interval = $now->diff($departure_time);
            
            // 1 SAAT KURALI KONTROLÜ
            $is_past_due = ($departure_time < $now); 
            $is_cancel_restricted = ($interval->h < 1 && $interval->days == 0 && $departure_time > $now); 

            // Bilet durumu sınıflandırması
            $status_class = match($bilet['status']) {
                'active' => $is_past_due ? 'bg-secondary text-white' : ($is_cancel_restricted ? 'bg-danger text-white' : 'bg-primary text-white'),
                'canceled' => 'bg-warning text-dark',
                default => 'bg-secondary text-white',
            };

            // Etiket metni
            $status_text = match($bilet['status']) {
                'active' => $is_past_due ? 'SEFER TAMAMLANDI' : ($is_cancel_restricted ? 'KALKIŞ SAATİ YAKIN' : 'AKTİF'),
                'canceled' => 'İPTAL EDİLDİ',
                'expired' => 'SÜRESİ GEÇTİ',
                default => 'BİLİNMİYOR',
            };

            // İptal butonu etkinliği
            $can_cancel = $bilet['status'] === 'active' && !$is_past_due && !$is_cancel_restricted;
        ?>
            <div class="col-md-6">
                <div class="card ticket-card">
                    <div class="card-header d-flex justify-content-between align-items-center <?php echo $status_class; ?>">
                        <span><?php echo htmlspecialchars($bilet['company_name']); ?> Seferi</span>
                        <span class="badge bg-light text-dark">
                            <strong><?php echo $status_text; ?></strong>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Güzergah:</strong> <?php echo htmlspecialchars($bilet['departure_city']); ?> → <?php echo htmlspecialchars($bilet['destination_city']); ?></p>
                        <p class="mb-1"><strong>Kalkış Zamanı:</strong> <?php echo date('d.m.Y H:i', strtotime($bilet['departure_time'])); ?></p>
                        <p class="mb-1"><strong>Koltuk No:</strong> <span class="fw-bold text-primary"><?php echo htmlspecialchars($bilet['seats']); ?></span></p>
                        <p class="mb-3"><strong>Ödenen Ücret:</strong> <span class="fw-bold text-success"><?php echo number_format($bilet['total_price'], 2, ',', '.'); ?> ₺</span></p>

                        <div class="d-flex justify-content-between">
                            <a href="generate_pdf.php?ticket_id=<?php echo $bilet['ticket_id']; ?>" class="btn btn-sm btn-info text-white me-2" 
                                title="Bilet PDF çıktısını indirir"
                                <?php echo ($bilet['status'] === 'active' && !$is_past_due) ? '' : 'disabled'; ?>>
                                Bilet PDF İndir
                            </a>

                            <?php if ($can_cancel): ?>
                                <a href="cancel_ticket.php?id=<?php echo $bilet['ticket_id']; ?>" 
                                   class="btn btn-sm btn-cancel"
                                   onclick="return confirm('Biletin iptali onaylanacaktır. İade işlemi gerçekleştirilecektir.');">
                                    Bilet İptal Et
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>İptal Edilemez</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>