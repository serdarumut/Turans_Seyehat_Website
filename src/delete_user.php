<?php

session_start();
$db_path = '/var/www/data/turans.sqlite';

// YETKİLENDİRME KONTROLÜ 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Yetkilendirme Hatası: Bu işlem için Yönetici (Admin) yetkisi gereklidir.");
}

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

    // Veri GET metoduyla alınıyor
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        die("İşlem Başarısız: 'id' parametresi zorunludur.");
    }

    // PDO
    $stmt = $db->prepare("DELETE FROM User WHERE id = :id");
    $success = $stmt->execute([':id' => $id]);
    
    if (!$success) {
        http_response_code(404);
        die("İşlem Başarısız: Belirtilen kullanıcı bulunamadı.");
    }

    http_response_code(200);
    echo "Kullanıcı başarıyla silinmiştir (ID: " . htmlspecialchars($id) . ")";

} catch (PDOException $e) {

    http_response_code(500);
    die("İşlem Başarısız: Sistem hatası oluştu.");
}
?>