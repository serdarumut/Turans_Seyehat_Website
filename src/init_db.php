<?php

date_default_timezone_set('Europe/Istanbul'); 

$db_path = '/var/www/data/turans.sqlite';
try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec('PRAGMA foreign_keys = ON;');

    echo "Veritabanı bağlantısı başarılı: " . htmlspecialchars($db_path) . "<br>";

    // 1. Bus_Company
    $db->exec("
    CREATE TABLE IF NOT EXISTS Bus_Company (
        id      TEXT PRIMARY KEY NOT NULL,
        name    TEXT NOT NULL,
        logo_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ");
    
    // 2. User
    $db->exec("
    CREATE TABLE IF NOT EXISTS User (
        id          TEXT PRIMARY KEY NOT NULL,
        full_name   TEXT NOT NULL,
        email       TEXT UNIQUE NOT NULL,
        role        TEXT NOT NULL DEFAULT 'user',
        password    TEXT NOT NULL,
        company_id  TEXT,
        balance     REAL NOT NULL DEFAULT 0.00,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (company_id) REFERENCES Bus_Company (id) ON DELETE SET NULL
    );
    ");

    // 3. Trips 
    $db->exec("
    CREATE TABLE IF NOT EXISTS Trips (
        id              TEXT PRIMARY KEY NOT NULL,
        company_id      TEXT NOT NULL,
        destination_city TEXT NOT NULL,
        arrival_time    DATETIME NOT NULL,
        departure_city  TEXT NOT NULL,
        departure_time  DATETIME NOT NULL,
        price           INTEGER NOT NULL,
        capacity        INTEGER NOT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (company_id) REFERENCES Bus_Company (id) ON DELETE CASCADE
    );
    ");

    // 4. Tickets
    $db->exec("
    CREATE TABLE IF NOT EXISTS Tickets (
        id          TEXT PRIMARY KEY NOT NULL,
        trip_id     TEXT NOT NULL,
        user_id     TEXT NOT NULL,
        status      TEXT NOT NULL DEFAULT 'active',
        total_price INTEGER NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (trip_id) REFERENCES Trips (id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES User (id) ON DELETE CASCADE
    );
    ");

    // 5. Booked_Seats 
    $db->exec("
    CREATE TABLE IF NOT EXISTS Booked_Seats (
        id          TEXT PRIMARY KEY NOT NULL,
        ticket_id   TEXT NOT NULL,
        seat_number INTEGER NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (ticket_id) REFERENCES Tickets (id) ON DELETE CASCADE,
        UNIQUE (ticket_id, seat_number)
    );
    ");
    
    // 6. Coupons (user_limit dahil)
    $db->exec("
    CREATE TABLE IF NOT EXISTS Coupons (
        id          TEXT PRIMARY KEY NOT NULL,
        code        TEXT UNIQUE NOT NULL,
        discount    REAL NOT NULL,
        usage_limit INTEGER NOT NULL,
        user_limit  INTEGER NOT NULL DEFAULT 1,
        expire_date DATETIME NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        company_id  TEXT
    );
    ");

    // 7. User_Coupons 
    $db->exec("
    CREATE TABLE IF NOT EXISTS User_Coupons (
        id          TEXT PRIMARY KEY NOT NULL,
        coupon_id   TEXT NOT NULL,
        user_id     TEXT NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (coupon_id) REFERENCES Coupons (id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES User (id) ON DELETE CASCADE
    );
    ");

    echo "Tüm tablolar başarıyla oluşturulmuştur/kontrol edilmiştir.<br>";
    
    // --- BAŞLANGIÇ VERİSİ EKLEME (Admin)
    
    $admin_email = 'admin@turans.com';
    $admin_password_hash = password_hash('123456', PASSWORD_DEFAULT);
    $admin_id = uniqid();

    $stmt = $db->prepare("SELECT COUNT(*) FROM User WHERE email = :email");
    $stmt->execute([':email' => $admin_email]);

    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("
            INSERT INTO User (id, full_name, email, role, password, balance) 
            VALUES (:id, :full_name, :email, 'admin', :password, 10000.00)
        ");
        $stmt->execute([
            ':id' => $admin_id,
            ':full_name' => 'Sistem Admini',
            ':email' => $admin_email,
            ':password' => $admin_password_hash
        ]);
        echo "İlk Admin kullanıcısı oluşturulmuştur (E-posta: " . htmlspecialchars($admin_email) . " | Şifre: 123456)<br>";
    } else {
        echo "Admin kullanıcısı zaten mevcuttur.<br>";
    }

    // Örnek Kuponları Ekle 
    $stmt = $db->query("SELECT COUNT(*) FROM Coupons");
    if ($stmt->fetchColumn() == 0) {
        $kuponlar = [
            [
                'code' => 'YAZ20',
                'discount' => 0.20,
                'usage_limit' => 50,
                'user_limit' => 2,
                'expire_date' => date('Y-m-d H:i:s', strtotime('+6 months'))
            ],
            [
                'code' => 'ILKBILET10',
                'discount' => 0.10,
                'usage_limit' => 200,
                'user_limit' => 1,
                'expire_date' => date('Y-m-d H:i:s', strtotime('+1 year'))
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO Coupons (id, code, discount, usage_limit, user_limit, expire_date) 
            VALUES (:id, :code, :discount, :usage_limit, :user_limit, :expire_date)
        ");

        foreach ($kuponlar as $kupon) {
            $stmt->execute(array_merge([':id' => uniqid('coupon_')], $kupon));
        }
        echo count($kuponlar) . " adet örnek indirim kuponu eklenmiştir.<br>";
    } else {
        echo "Örnek kuponlar zaten mevcuttur.<br>";
    }
    
    echo "<hr>Kurulum tamamlanmıştır.";

} catch (PDOException $e) {
    http_response_code(500);
    die("Veritabanı Kurulum Hatası: Sistem hatası oluştu.");
}
?>