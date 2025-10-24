<?php
session_start();
// Hata ayıklamayı kapat
ini_set('display_errors', 0); 
error_reporting(0);

$db_path = '/var/www/data/turans.sqlite';

// KRİTİK YETKİLENDİRME KONTROLÜ 
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Yetkilendirme Hatası: Bu sayfaya erişim için Yönetici (Admin) yetkisi gereklidir.");
}

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // A03: En iyi uygulama olarak prepare/execute kullanılır
    $stmt = $db->prepare("SELECT id, full_name, email, role FROM User ORDER BY full_name ASC");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Kullanıcı Listesi (Admin)</h2>";
    echo "<ul>";
    foreach ($results as $row) {
        // XSS Koruması için htmlspecialchars() kullanılır
        echo "<li>ID: " . htmlspecialchars($row['id']) . 
             " | Ad: " . htmlspecialchars($row['full_name']) . 
             " (E-posta: " . htmlspecialchars($row['email']) . 
             " | Rol: " . htmlspecialchars($row['role']) . ")</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    http_response_code(500);
    die("Veritabanı Hatası: Kullanıcı listesi alınamadı.");
}
?>