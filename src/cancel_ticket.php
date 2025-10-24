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

$ticket_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$ticket_id) {
    header('Location: hesabim.php?error=cancel&message=' . urlencode("Bilet ID'si belirtilmemiştir."));
    exit;
}

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction(); // A04: Atomik işlem başlat

    // Bilet ve Sefer Bilgilerini Çekme 
    $sql = "
        SELECT 
            t.status, t.total_price, t.user_id,
            tr.departure_time
        FROM Tickets t
        INNER JOIN Trips tr ON t.trip_id = tr.id
        WHERE t.id = :ticket_id AND t.user_id = :user_id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':ticket_id' => $ticket_id, ':user_id' => $user_id]);
    $bilet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bilet) {
        $db->rollBack();
        header('Location: hesabim.php?error=cancel&message=' . urlencode("Bilet bulunamadı veya kullanıcıya ait değildir."));
        exit;
    }

    if ($bilet['status'] !== 'active') {
        $db->rollBack();
        header('Location: hesabim.php?error=cancel&message=' . urlencode("Bilet zaten iptal edilmiş veya süresi dolmuştur."));
        exit;
    }

    // 1 SAAT KURALI KONTROLÜ 
    $departure_time = new DateTime($bilet['departure_time'], new DateTimeZone('Europe/Istanbul'));
    $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
    
    $interval = $now->diff($departure_time);
    $remaining_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    
    if ($departure_time < $now || $remaining_minutes < 60) {
        $db->rollBack();
        header('Location: hesabim.php?error=cancel&message=' . urlencode("Kalkışa son 60 dakikadan az kaldığı için bilet iptal edilemez."));
        exit;
    }
    
    $iade_miktari = $bilet['total_price'];

    // Bilet Durumunu Güncelle (İptal)
    $stmt_ticket = $db->prepare("UPDATE Tickets SET status = 'canceled' WHERE id = :ticket_id");
    $stmt_ticket->execute([':ticket_id' => $ticket_id]);

    // Kullanıcı Bakiyesini İade Et
    $stmt_update_balance = $db->prepare("
        UPDATE User SET balance = balance + :iade_miktari WHERE id = :user_id
    ");
    $stmt_update_balance->execute([
        ':iade_miktari' => $iade_miktari,
        ':user_id' => $user_id
    ]);

    $_SESSION['balance'] += $iade_miktari; 

    $db->commit();
    header('Location: hesabim.php?success=cancel');
    exit;

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    header('Location: hesabim.php?error=cancel&message=' . urlencode("İşlem sırasında bir sistem hatası oluştu."));
    exit;
}
?>