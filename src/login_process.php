<?php

session_start();
// Hata ayıklamayı kapat 
ini_set('display_errors', 0); 
error_reporting(0);

$db_path = '/var/www/data/turans.sqlite';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. GİRDİ TEMİZLİĞİ VE DOĞRULAMA
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // E-posta temizliği 
    $email = filter_var($email, FILTER_SANITIZE_EMAIL); 

    if (empty($email) || empty($password)) {
        die("Hata: Lütfen tüm alanları doldurunuz."); 
    }

    try {
        $db = new PDO("sqlite:$db_path");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        //  KULLANICIYI ÇEKME 
        $stmt = $db->prepare("SELECT id, full_name, role, password, balance, company_id FROM User WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // KİMLİK DOĞRULAMA
        if ($user && password_verify($password, $user['password'])) {
            
            // 🔒 GÜVENLİK (A07): Session ID'yi yenile (Session Sabitleme Önlemi)
            session_regenerate_id(true);
            
            // OTURUM DEĞİŞKENLERİNİ OLUŞTURMA
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['balance'] = $user['balance'];
            
            if ($user['role'] === 'user_company_admin') {
                $_SESSION['company_id'] = $user['company_id'];
            }

            // YÖNLENDİRME KONTROLÜ (OWASP A05 Koruması)
            $redirect_url = 'index.php?success=login'; // Varsayılan hedef
            
            // Rolüne göre öncelikli yönlendirme
            if ($user['role'] === 'admin') {
                $redirect_url = 'admin_panel.php';
            } elseif ($user['role'] === 'user_company_admin') {
                $redirect_url = 'firma_admin.php';
            }
            
            // Eğer POST'tan güvenli bir redirect URL'i geldiyse
            if (isset($_POST['redirect']) && !empty($_POST['redirect'])) {
                $potential_redirect = $_POST['redirect'];
                
                // Sadece uygulama içi yollara izin ver 
                if (strpos($potential_redirect, 'http') === false && strpos($potential_redirect, '://') === false) {
                    $redirect_url = $potential_redirect;
                }
            }
            
            // Belirlenen URL'e yönlendir
            header("Location: $redirect_url");
            exit;
        }
        
        //  GİRİŞ BAŞARISIZ 
        die("Hata: E-posta veya şifre hatalıdır. Lütfen kontrol ediniz.");

    } catch (PDOException $e) {

        die("Veritabanı Hatası: Giriş işlemi sırasında bir sorun oluştu.");
    }
} else {
    header('Location: login.php');
    exit;
}
?>