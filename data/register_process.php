<?php
session_start();

try {
    // 📂 Veritabanı bağlantısı (data klasöründeki turans.sqlite dosyasına)
    $db = new PDO('sqlite:../data/turans.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🧱 Eğer tablo yoksa oluştur
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ad_soyad TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'user'
    )");

    // 📩 Form verilerini al
    $ad_soyad = trim($_POST['ad_soyad']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password2 = $_POST['password2'];

    // ⚠️ Şifre eşleşiyor mu?
    if ($password !== $password2) {
        echo "<script>alert('Şifreler uyuşmuyor!'); window.history.back();</script>";
        exit;
    }

    // ⚠️ Aynı e-posta var mı?
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "<script>alert('Bu e-posta zaten kayıtlı!'); window.history.back();</script>";
        exit;
    }

    // 🔐 Şifreyi hashle
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // 💾 Kullanıcıyı kaydet
    $ekle = $db->prepare("INSERT INTO users (ad_soyad, email, password) VALUES (?, ?, ?)");
    $ekle->execute([$ad_soyad, $email, $hashed]);

    // 🎉 Başarılıysa login sayfasına yönlendir
    echo "<script>alert('Kayıt başarılı! Giriş sayfasına yönlendiriliyorsunuz.'); window.location='../login.php';</script>";

} catch (PDOException $e) {
    // 🚨 Hata yakalama
    echo "<script>alert('Bir hata oluştu: " . $e->getMessage() . "'); window.history.back();</script>";
    exit;
}
?>
