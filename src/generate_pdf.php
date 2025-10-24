<?php

session_start();
date_default_timezone_set('Europe/Istanbul');

// Kütüphaneyi Dahil Etme
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$db_path = '/var/www/data/turans.sqlite';
$ticket_id = $_GET['ticket_id'] ?? null;

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || !$ticket_id) {
    header('Location: /');
    exit;
}

$user_id = $_SESSION['user_id'];
$bilet = null;

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Bilet detaylarını çekme 
    $sql = "
        SELECT 
            t.id AS ticket_id, t.total_price, t.created_at AS purchase_date,
            u.full_name AS user_name,
            tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
            bc.name AS company_name,
            GROUP_CONCAT(bs.seat_number) AS seats
        FROM Tickets t
        INNER JOIN User u ON t.user_id = u.id
        INNER JOIN Trips tr ON t.trip_id = tr.id
        INNER JOIN Bus_Company bc ON tr.company_id = bc.id
        INNER JOIN Booked_Seats bs ON t.id = bs.ticket_id
        WHERE t.id = :ticket_id AND t.user_id = :user_id AND t.status = 'active'
        GROUP BY t.id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':ticket_id' => $ticket_id, ':user_id' => $user_id]);
    $bilet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bilet) {
        die("Hata: Bilet bulunamadı veya aktif değildir.");
    }

    // --- HTML İÇERİĞİ OLUŞTURMA ---
    $html = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
    $html .= '<style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 0; padding: 0; }
        .ticket { width: 100%; border: 2px solid #ff7b00; padding: 20px; box-sizing: border-box; }
        .header { background-color: #ff7b00; color: white; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 5px; }
        .details table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details th, .details td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #666; }
        strong { font-weight: bold; }
    </style>';
    $html .= '</head><body>';
    
    $html .= '<div class="ticket">';
    $html .= '<div class="header"><h2>Turans Seyahat Biletiniz</h2></div>';
    
    $html .= '<h3>Sefer Bilgileri</h3>';
    $html .= '<div class="details"><table>';
    $html .= '<tr><th>Firma Adı</th><td>' . htmlspecialchars($bilet['company_name']) . '</td></tr>';
    $html .= '<tr><th>Güzergah</th><td>' . htmlspecialchars($bilet['departure_city']) . ' &rarr; ' . htmlspecialchars($bilet['destination_city']) . '</td></tr>';
    $html .= '<tr><th>Kalkış Zamanı</th><td>' . date('d.m.Y H:i', strtotime($bilet['departure_time'])) . '</td></tr>';
    $html .= '<tr><th>Varış Zamanı</th><td>' . date('d.m.Y H:i', strtotime($bilet['arrival_time'])) . '</td></tr>';
    $html .= '<tr><th>Koltuk Numaraları</th><td><strong style="color: #ff7b00;">' . htmlspecialchars($bilet['seats']) . '</strong></td></tr>';
    $html .= '</table></div>';
    
    $html .= '<h3>Yolcu ve Ödeme Bilgileri</h3>';
    $html .= '<div class="details"><table>';
    $html .= '<tr><th>Yolcu Adı Soyadı</th><td>' . htmlspecialchars($bilet['user_name']) . '</td></tr>';
    $html .= '<tr><th>Bilet Numarası</th><td>' . htmlspecialchars($bilet['ticket_id']) . '</td></tr>';
    $html .= '<tr><th>Satın Alma Tarihi</th><td>' . date('d.m.Y H:i', strtotime($bilet['purchase_date'])) . '</td></tr>';
    $html .= '<tr><th>Ödenen Tutar</th><td><strong>' . number_format($bilet['total_price'], 2, ',', '.') . ' ₺</strong></td></tr>';
    $html .= '</table></div>';
    
    $html .= '<div class="footer">İyi yolculuklar dileriz. </div>';
    $html .= '</div>';
    $html .= '</body></html>';
    
    // --- PDF OLUŞTURMA ---
    $options = new Options();

    $options->set('defaultFont', 'DejaVu Sans'); 
    $dompdf = new Dompdf($options);
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // PDF'i Tarayıcıya Gönderme (İndirme)
    $filename = "Bilet-" . $bilet['ticket_id'] . ".pdf";
    $dompdf->stream($filename, ["Attachment" => true]);

} catch (Exception $e) {

    die("Hata: Bilet PDF çıktısı oluşturulamadı. Lütfen teknik destek ile iletişime geçiniz.");
}
?>