<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Europe/Istanbul');

$db_path = '/var/www/data/turans.sqlite';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Hata: Giriş oturumu bulunamadı.']);
    exit;
}

$coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));
$user_id = $_SESSION['user_id'];
$trip_id = $_POST['trip_id'] ?? null; // Firma kupon kontrolü için gönderilen veri

if (empty($coupon_code)) {
    echo json_encode(['success' => false, 'message' => 'Hata: Kupon kodu boş bırakılamaz.']);
    exit;
}

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //Kuponu koduna göre çekme 
    $stmt = $db->prepare("SELECT id, discount, usage_limit, user_limit, expire_date, company_id FROM Coupons WHERE code = :code");
    $stmt->execute([':code' => $coupon_code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Hata: Kupon kodu geçersizdir.']);
        exit;
    }
    
    $coupon_id = $coupon['id'];
    $coupon_company_id = $coupon['company_id'];
    $coupon_user_limit = $coupon['user_limit'];

    //Kuponun Sürese kontrol
    if (strtotime($coupon['expire_date']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Hata: Kuponun kullanım süresi dolmuştur.']);
        exit;
    }

    // Firma kupon kontrolü
    if ($coupon_company_id) {
        if (!$trip_id) {
            echo json_encode(['success' => false, 'message' => 'Hata: Bu kuponun firmaya özel olduğu doğrulanamadı.']);
            exit;
        }
        
        // Sefer - firma kontrolü
        $stmt = $db->prepare("SELECT company_id FROM Trips WHERE id = :trip_id");
        $stmt->execute([':trip_id' => $trip_id]);
        $trip_company_id = $stmt->fetchColumn();

        if ($trip_company_id !== $coupon_company_id) {
            echo json_encode(['success' => false, 'message' => 'Hata: Bu kupon, seçilen sefere ait firma için geçerli değildir.']);
            exit;
        }
    }
    
    // Kullanım Limiti Kontrolü
    $stmt = $db->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = :id");
    $stmt->execute([':id' => $coupon_id]);
    $used_count_global = $stmt->fetchColumn();

    if ($used_count_global >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'Hata: Kuponun genel kullanım limiti dolmuştur.']);
        exit;
    }
    
    // KİŞİSEL KULLANIM LİMİTİ KONTROLÜ 
    $stmt = $db->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = :id AND user_id = :uid");
    $stmt->execute([':id' => $coupon_id, ':uid' => $user_id]);
    $used_count_user = $stmt->fetchColumn();

    if ($used_count_user >= $coupon_user_limit) {
        echo json_encode(['success' => false, 'message' => 'Hata: Bu kuponu ' . $coupon_user_limit . ' kez kullandınız. Kişisel limit dolmuştur.']);
        exit;
    }

    // Başarılı Sonuç
    echo json_encode([
        'success' => true, 
        'discount_rate' => $coupon['discount'], 
        'coupon_id' => $coupon_id,
        // Çıktı XSS'e karşı temizlenir
        'message' => 'Kupon kodu **' . htmlspecialchars($coupon_code) . '** başarıyla uygulandı! (%' . ($coupon['discount'] * 100) . ' indirim)'
    ]);

} catch (PDOException $e) {
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Hata: Kupon kontrolü sırasında bir sistem hatası oluştu.']);
}
?>