<?php

session_start();
date_default_timezone_set('Europe/Istanbul');

$db_path = '/var/www/data/turans.sqlite';

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php?error=access_denied');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$trip_id = $_POST['trip_id'] ?? null;
$selected_seats_str = $_POST['selected_seats'] ?? '';

// Kupon verilerini POST'tan al
$applied_discount_rate = filter_input(INPUT_POST, 'applied_discount_rate', FILTER_VALIDATE_FLOAT) ?? 0;
$applied_coupon_id = $_POST['applied_coupon_id'] ?? null;

// Veri Doğrulama
if (empty($trip_id) || empty($selected_seats_str)) {
    die("Hata: Sefer ID'si veya seçilen koltuklar belirtilmemiştir.");
}

$selected_seats = array_map('intval', explode(',', $selected_seats_str));
if (empty($selected_seats)) {
    die("Hata: Lütfen en az bir koltuk seçiniz.");
}

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction(); // A04: Atomik işlem başlat

    //Sefer bilgilerini ve birim fiyatı çekme
    $stmt = $db->prepare("SELECT price, capacity FROM Trips WHERE id = :trip_id");
    $stmt->execute([':trip_id' => $trip_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        $db->rollBack();
        die("Hata: Sefer bulunamadı.");
    }
    
    // Temel hesaplama ve indirim uygulama
    $unit_price = $trip['price'];
    $sub_total = $unit_price * count($selected_seats); 
    $total_price = $sub_total * (1 - $applied_discount_rate);

    // Kullanıcının bakiyesini kontrol etme
    $stmt = $db->prepare("SELECT balance FROM User WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user_balance = $stmt->fetchColumn();

    if ($user_balance < $total_price) {
        $db->rollBack();
        die("Hata: Yetersiz Bakiye. Mevcut Bakiye: " . number_format($user_balance, 2) . " ₺. Gereken Tutar: " . number_format($total_price, 2) . " ₺.");
    }

    // Kupon Kullanım Kontrolü ve Kaydı
    if ($applied_coupon_id && $applied_discount_rate > 0) {
        // Kuponun tekrar kullanılmış mıı
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = :cid AND user_id = :uid");
        $stmt_check->execute([':cid' => $applied_coupon_id, ':uid' => $user_id]);
        if ($stmt_check->fetchColumn() > 0) {
             $db->rollBack();
             die("Hata: Kupon zaten kullanılmış veya geçersizdir.");
        }
        
        // Kupon kullanımını kaydet
        $stmt_coupon = $db->prepare("INSERT INTO User_Coupons (id, coupon_id, user_id) VALUES (:id, :cid, :uid)");
        $stmt_coupon->execute([
            ':id' => uniqid('uc_'),
            ':cid' => $applied_coupon_id,
            ':uid' => $user_id
        ]);
    }
    
    // Koltukların Dolu Olup Olmadığını Kontrol Etme 
    $placeholders = implode(',', array_fill(0, count($selected_seats), '?'));
    $sql_check_seats = "
        SELECT bs.seat_number 
        FROM Booked_Seats bs
        INNER JOIN Tickets t ON bs.ticket_id = t.id
        WHERE t.trip_id = :trip_id AND t.status = 'active' AND bs.seat_number IN ($placeholders)
    ";
    
    $stmt_check_seats = $db->prepare($sql_check_seats);
    $params = array_merge([':trip_id' => $trip_id], $selected_seats);
    $stmt_check_seats->execute($params);
    $already_booked = $stmt_check_seats->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($already_booked)) {
        $db->rollBack();
        die("Hata: Seçilen koltuklar (" . implode(', ', $already_booked) . ") kısa süre önce satılmıştır.");
    }
    
    // Biletleri Oluşturma, Koltukları Rezerve Etme ve Bakiyeyi Güncelleme
    $ticket_id = uniqid('tckt_');

    $stmt_ticket = $db->prepare("
        INSERT INTO Tickets (id, trip_id, user_id, total_price) 
        VALUES (:id, :trip_id, :user_id, :total_price)
    ");
    $stmt_ticket->execute([
        ':id' => $ticket_id,
        ':trip_id' => $trip_id,
        ':user_id' => $user_id,
        ':total_price' => $total_price 
    ]);

    $stmt_seat = $db->prepare("
        INSERT INTO Booked_Seats (id, ticket_id, seat_number) 
        VALUES (:id, :ticket_id, :seat_number)
    ");
    
    foreach ($selected_seats as $seat_number) {
        $stmt_seat->execute([
            ':id' => uniqid('seat_'),
            ':ticket_id' => $ticket_id,
            ':seat_number' => $seat_number
        ]);
    }

    $stmt_update_balance = $db->prepare("
        UPDATE User SET balance = balance - :total_price WHERE id = :user_id
    ");
    $stmt_update_balance->execute([
        ':total_price' => $total_price,
        ':user_id' => $user_id
    ]);

    $_SESSION['balance'] -= $total_price; 

    $db->commit();

    header('Location: hesabim.php?success=purchase&ticket_id=' . $ticket_id);
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Hata: İşlem sırasında bir sistem sorunu oluştu.");
}
?>