<?php
session_start();

$db_path = '/var/www/data/turans.sqlite';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST verisi temizleme
    $full_name = trim($_POST['ad_soyad'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Veri Doğrulama
    if (empty($full_name) || empty($email) || empty($password) || $password !== $password2) {
        die("Hata: Kayıt verileri eksik veya şifreler eşleşmemektedir.");
    }

    // Alan Adı Kısıtlaması
    $restricted_domain = '@turans.com';
    if (str_ends_with($email, $restricted_domain)) {
        die("Güvenlik Hatası: Alan adıyla kayıt yapılamaz.");
    }

    try {
        $db = new PDO("sqlite:$db_path");
        // Hataları sessize al, sadece başarılı/başarısız durumunu manuel kontrol et (A09 koruması)
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT); 
        $db->beginTransaction();

        // E-posta kontrolü
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM User WHERE email = :email");
        $stmt_check->execute([':email' => $email]);
        if ($stmt_check->fetchColumn() > 0) {
            $db->rollBack();
            die("Hata: Bu e-posta adresi zaten kullanılmaktadır. Lütfen <a href='login.php'>Giriş Yapınız</a>.");
        }

        // Şifreyi hashle(A07)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $user_id = uniqid(); 

        // Kullanıcıyı kaydet
        $stmt_insert = $db->prepare("
            INSERT INTO User (id, full_name, email, role, password, balance) 
            VALUES (:id, :full_name, :email, 'user', :password, 50.00) 
        ");
        
        $success = $stmt_insert->execute([
            ':id' => $user_id,
            // XSS için veritabanına yazmadan önce temizle 
            ':full_name' => htmlspecialchars($full_name), 
            ':email' => htmlspecialchars($email),
            ':password' => $hashed_password
        ]);

        if (!$success) {
            $db->rollBack();
            die("Hata: Kayıt işlemi sırasında beklenmeyen bir sorun oluştu.");
        }
        
        $db->commit();

        // Oturum açma
        session_regenerate_id(true); // Session sabitlemeye karşı koruma
        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_name'] = htmlspecialchars($full_name);
        $_SESSION['role'] = 'user';

        header('Location: index.php?success=register');
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        die("Hata: Kayıt işlemi sırasında bir sistem hatası oluştu.");
    }
} else {
    header('Location: register.php');
    exit;
}
?>