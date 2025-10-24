# Turans Seyahat - Otobüs Bileti Satın Alma Platformu

Bu proje, bir bilet satış platformunun tüm temel işlevlerini (Kayıt, Giriş, Sefer Arama, Bilet Alma ve İptal, Yönetici Panelleri) kapsayan tam teşekküllü bir web uygulamasıdır. Proje, Siber Vatan Yavuzlar takımı seçmeleri için Serdar Umut Turan tarafından geliştirilmiştir.

#Dikkat giriş yaptıktan sonra aşağıdaki çalıştırma talimatlarını uygulayın ve sonrasında http://localhost:8080/init_db.php burayı bir ziyaret edin :D keyifli olacaktır.
---

## Güvenlik ve Mimari Vurguları (OWASP Top 10)

Proje, hassas verilere karşı siber güvenlik tehditlerini en aza indirmek için aşağıdaki OWASP Top 10 prensiplerine göre güçlendirilmiştir:

### 1. Erişim Kontrolü ve Yetkilendirme (A01:2021)
* **Rol Bazlı Kısıtlama:** Kullanıcıların rolleri (`user`, `user_company_admin`, `admin`) Session üzerinden anlık olarak kontrol edilir. Firma Adminleri, sorgularına uygulanan `WHERE company_id` kısıtlaması sayesinde **sadece kendi firmalarına ait verileri** manipüle edebilirler.
* **Kritik Link Koruması:** `Hesabım/Biletlerim` linki sadece Yolcu (`user`) rolüne sahip kullanıcılara gösterilir.
* **Özel Alan Adı Kısıtlaması:** `register.php` üzerinden `@turans.com` uzantılı e-posta ile kayıt engellenmiştir (Sistem rollerinin yetkisiz oluşturulmasını önler).

### 2. Oturum ve Güvenlik Mekanizmaları (A07:2021)
* **Geri Tuşu Koruması:** Tüm yönetim panellerinde (`admin_panel.php`, `firma_admin.php`, vb.) **`Cache-Control: no-store`** HTTP başlıkları kullanılır. Bu sayede, çıkış yapıldıktan sonra tarayıcı önbelleğinden sayfa görüntülenmesi engellenir.
* **Session Sabitleme (Fixation):** Giriş başarılı olduktan sonra **`session_regenerate_id(true)`** fonksiyonu kullanılır. Bu, oturum çalınması (Session Hijacking) saldırılarına karşı koruma sağlar.

### 3. Kod ve Veritabanı Güvenliği (A03:2021 & A09:2021)
* **SQL Injection Koruma:** Uygulamadaki tüm veritabanı işlemleri **PHP PDO** ve **Hazırlanmış Sorgular (Prepared Statements)** ile yapılır.
* **Veri İfşası Önleme:** Tüm kritik `try...catch` bloklarında detaylı `PDOException` mesajları gizlenir (A09: Security Logging).
* **Güvenli Parola:** Parolalar `password_hash()` ile güvenli bir şekilde saklanır.

---

## Teknolojik Yığın ve Kurulum

* **Backend:** PHP 8.3
* **Veritabanı:** SQLite3
* **Frontend:** Bootstrap 5.3
* **Paketleme/Ortam:** Docker & Docker Compose
* **PDF Çıktısı:** Dompdf Kütüphanesi (Composer ile entegre)

### Çalıştırma Talimatları (Docker)

1.  Proje klasörüne gidin.
2.  Gerekli tüm bağımlılıkları kurmak ve sistemi başlatmak için terminalde çalıştırın:
    ```bash
    docker compose up -d --build
    ```
3.  Uygulamaya tarayıcıdan erişin: `http://localhost:8080/index.php`

### Başlangıç Kullanıcıları

| Rol | E-posta | Şifre | Not |
| :--- | :--- | :--- | :--- |
| **Sistem Admini** | `admin@turans.com` | `123456` | İlk kurulumda otomatik oluşturulur. Sistemin ana yöneticisidir. Firmaları, Firma Adminlerini ve tüm indirim kuponlarını (genel/firmaya özel) yönetir.|
| **Firma Admini** | (* Admin yönetim panelinden oluşturulur*) | `*******` | Admin paneli üzerinden oluşturulmalıdır. Otobüs firmasının yetkilisidir. Sadece kendi firmasına ait seferleri (CRUD) ve firmaya özel indirim kuponlarını oluşturur/yönetir. |
| **Yolcu (User)** | (*Kayıt Ol sayfasından oluşturulur*) | `*******` |  Platformun müşterisidir. Sefer arar, koltuk seçimi yapar, kredi ile bilet satın alır, kupon kullanır ve biletini iptal eder. |

***
**Geliştiren:** Serdar Umut Turan

***

