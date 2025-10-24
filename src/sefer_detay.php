<?php 

session_start();
date_default_timezone_set('Europe/Istanbul'); 

$db_path = '/var/www/data/turans.sqlite';

$trip_id = $_GET['id'] ?? null;

if (!$trip_id) {
    die("Hata: Sefer ID'si belirtilmemiştir.");
}

$sefer = null;
$dolu_koltuklar = [];
$kapasite = 0;

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Sefer detaylarını ve Firma adını çekme 
    $sql_trip = "
        SELECT t.*, bc.name AS company_name 
        FROM Trips t
        INNER JOIN Bus_Company bc ON t.company_id = bc.id 
        WHERE t.id = :trip_id
    ";
    $stmt_trip = $db->prepare($sql_trip);
    $stmt_trip->execute([':trip_id' => $trip_id]);
    $sefer = $stmt_trip->fetch(PDO::FETCH_ASSOC);

    if (!$sefer) {
        die("Hata: Belirtilen ID'ye ait sefer bulunamadı.");
    }

    $kapasite = $sefer['capacity'];

    // Dolu koltukları çekme
    $sql_seats = "
        SELECT bs.seat_number 
        FROM Booked_Seats bs
        INNER JOIN Tickets t ON bs.ticket_id = t.id
        WHERE t.trip_id = :trip_id AND t.status = 'active'
    ";
    $stmt_seats = $db->prepare($sql_seats);
    $stmt_seats->execute([':trip_id' => $trip_id]);
    
    $dolu_koltuklar = $stmt_seats->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
 
    die("Veritabanı Hatası: İşlem başarısız.");
}

$giris_yapildi = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sefer['destination_city']); ?> Seferi Detayı | Turans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .trip-header { background-color: #ff7b00; color: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; }
        .seat-map { 
            display: grid; 
            grid-template-columns: 50px 50px 10px 10px 50px 50px;
            gap: 10px; 
            max-width: 400px; 
            margin: 20px auto; 
            padding: 20px; 
            border: 1px solid #ccc; 
            border-radius: 10px; 
            background-color: #fff; 
        }
        .seat { 
            width: 50px; 
            height: 50px; 
            line-height: 50px; 
            text-align: center; 
            border-radius: 5px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: all 0.2s; 
            grid-column-end: span 1;
        }
        
        .seat.empty { 
            background-color: #DDEBF7; 
            color: #004D80; 
            border: 1px solid #004D80; 
        }
        .seat.empty:hover { 
            background-color: #A3C9E8; 
        }
        .seat.booked { 
            background-color: #F7DDE3; 
            color: #A30000; 
            cursor: not-allowed; 
            border: 1px solid #A30000; 
        }
        .seat.selected { 
            background-color: #ff7b00; 
            color: white; 
            border: 1px solid #ff7b00; 
            transform: scale(1.1); 
        }
        
        .driver-seat { grid-column: 1 / 7; text-align: right; margin-bottom: 10px; }
        .driver-seat::before { content: "Şoför"; font-weight: bold; color: #6c757d; }
        .aisle-spacer { 
            grid-column: 3 / 5;
            height: 0; 
        } 
        .summary-card { position: sticky; top: 20px; }
        .btn-custom { background-color: #ff7b00; border: none; font-weight: 600; }
        .btn-custom:hover { background-color: #ffa733; }
        
        /* Navbar Stil Zorlaması */
        .navbar { 
            background-color: #343a40 !important; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.5) !important;
        }
        .navbar a {
            color: #fff !important; 
        }
        .navbar .text-danger {
            color: #ffc107 !important;
            font-weight: 700 !important;
            border: 1px solid #ffc107;
            padding: 5px 10px;
            border-radius: 5px;
            transition: 0.2s;
        }
        .navbar .text-danger:hover {
            background-color: #ffc107 !important;
            color: #343a40 !important;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="trip-header">
                <h2><?php echo htmlspecialchars($sefer['departure_city']); ?> → <?php echo htmlspecialchars($sefer['destination_city']); ?></h2>
                <p class="mb-0 fs-5"><?php echo date('d.m.Y H:i', strtotime($sefer['departure_time'])); ?> | <?php echo htmlspecialchars($sefer['company_name']); ?></p>
            </div>

            <h3 class="mb-4">Koltuk Seçimi</h3>
            
            <form action="purchase_process.php" method="POST" id="purchaseForm">
                <input type="hidden" name="trip_id" value="<?php echo $sefer['id']; ?>">
                <input type="hidden" name="selected_seats" id="selectedSeatsInput" required>
                <input type="hidden" name="applied_discount_rate" id="appliedDiscountRateInput" value="0">
                <input type="hidden" name="applied_coupon_id" id="appliedCouponIdInput" value="">


                <div class="seat-map">
                    <div class="driver-seat"></div> 
                    
                    <?php 
                    $seats_per_row = 4;
                    for ($i = 1; $i <= $kapasite; $i++): 
                        $is_booked = in_array($i, $dolu_koltuklar);
                        $class = $is_booked ? 'booked' : 'empty';
                        
                        if (($i - 1) % $seats_per_row == 2) { 
                            echo '<div class="aisle-spacer"></div>';
                        }
                    ?>
                        <div class="seat <?php echo $class; ?>" 
                             data-seat-id="<?php echo $i; ?>" 
                             <?php echo $is_booked ? '' : 'onclick="selectSeat(this, ' . $i . ')"'; ?>>
                             <?php echo $i; ?>
                        </div>
                    <?php 
                    endfor;
                    ?>
                </div>

                <div class="alert alert-info mt-4">
                    Koltuk seçimi için koltuk numarasına tıklayınız. 
                    <p class="mt-2 text-danger fw-bold">
                    <?php if (!$giris_yapildi): ?>
                        Bilet satın almak için önce <a href="login.php" class="alert-link">Giriş Yapmalısınız</a>.
                    <?php endif; ?>
                    </p>
                </div>
            
        </div>
        <div class="col-lg-4">
            <div class="card summary-card">
                <div class="card-header bg-dark text-white">
                    Özet ve Ödeme
                </div>
                <div class="card-body">
                    <p>Fiyat: <span class="float-end fw-bold"><?php echo number_format($sefer['price'], 2, ',', '.'); ?> ₺</span></p>
                    <p>Seçilen Koltuk Sayısı: <span class="float-end fw-bold" id="seatCount">0</span></p>
                    <hr>
                    <h5 class="text-primary">Toplam Tutar: <span class="float-end" id="totalPrice">0,00 ₺</span></h5>
                    
                    <div class="input-group my-3">
                        <input type="text" class="form-control" name="coupon_code" placeholder="Kupon Kodunuz" id="couponInputForForm">
                        <button class="btn btn-outline-secondary" type="button" id="applyCoupon">Uygula</button>
                    </div>

                    <p class="text-success" id="couponMessage"></p>
                    <p class="text-danger" id="errorSeat"></p>

                    <?php if ($giris_yapildi): ?>
                        <button type="submit" id="purchaseButton" class="btn btn-custom w-100 mt-3" disabled>
                            Satın Al (Krediyle Öde)
                        </button>
                    <?php else: 
                        $redirect_url = urlencode('sefer_detay.php?id=' . $sefer['id']);
                        ?>
                        <a href="login.php?redirect=<?php echo $redirect_url; ?>" 
                           class="btn btn-danger w-100 mt-3">
                           Giriş Yap & Bilet Al
                        </a>
                        <small class="text-danger mt-2 d-block text-center">Bilet satın almak için giriş yapınız.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
    </div>
</div> 
<script>
    const selectedSeats = new Set();
    const seatCountElement = document.getElementById('seatCount');
    const totalPriceElement = document.getElementById('totalPrice');
    const seatPrice = <?php echo $sefer['price']; ?>;
    const selectedSeatsInput = document.getElementById('selectedSeatsInput');
    const purchaseButton = document.getElementById('purchaseButton');
    const couponMessageElement = document.getElementById('couponMessage');
    const couponInput = document.getElementById('couponInputForForm');
    const applyCouponButton = document.getElementById('applyCoupon');

    let appliedDiscountRate = 0;
    let appliedCouponId = null;
    
    const appliedDiscountRateInput = document.getElementById('appliedDiscountRateInput');
    const appliedCouponIdInput = document.getElementById('appliedCouponIdInput');


    function selectSeat(element, seatId) {
        if (element.classList.contains('booked')) return;

        if (selectedSeats.has(seatId)) {
            selectedSeats.delete(seatId);
            element.classList.remove('selected');
        } else {
            selectedSeats.add(seatId);
            element.classList.add('selected');
        }

        updateSummary();
    }

    function updateSummary() {
        const count = selectedSeats.size;
        let total = count * seatPrice;
        
        // İndirimi uygula
        total = total * (1 - appliedDiscountRate);

        seatCountElement.textContent = count;
        totalPriceElement.textContent = total.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
        
        selectedSeatsInput.value = Array.from(selectedSeats).join(',');

        // butonu etkinleştirme kontrolü
        const isReadyToBuy = count > 0 && <?php echo $giris_yapildi ? 'true' : 'false'; ?>;

        if (isReadyToBuy) {
            purchaseButton.disabled = false;
        } else {
            purchaseButton.disabled = true;
        }
    }

    // Kupon uygulama butonu işlevi
    applyCouponButton.addEventListener('click', async function() {
        const couponCode = couponInput.value.trim();
        
        if (!couponCode) {
            appliedDiscountRate = 0;
            appliedCouponId = null;
            couponMessageElement.className = 'text-danger';
            couponMessageElement.textContent = 'Lütfen bir kupon kodu giriniz.';
            updateSummary();
            return;
        }
        
        // AJAX/Fetch isteği ile kupon kontrolü
        try {
            const response = await fetch('check_coupon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `coupon_code=${encodeURIComponent(couponCode)}&trip_id=<?php echo $sefer['id']; ?>`
            });
            const result = await response.json();
            
            if (result.success) {
                appliedDiscountRate = result.discount_rate;
                appliedCouponId = result.coupon_id;
                couponMessageElement.className = 'text-success';
                couponMessageElement.textContent = result.message;
            } else {
                appliedDiscountRate = 0;
                appliedCouponId = null;
                couponMessageElement.className = 'text-danger';
                couponMessageElement.textContent = result.message;
            }
        } catch (error) {
            appliedDiscountRate = 0;
            appliedCouponId = null;
            couponMessageElement.className = 'text-danger';
            couponMessageElement.textContent = 'Kupon kontrolü sırasında bir hata oluştu.';
        }
        
        // Gizli alanları güncelle
        appliedDiscountRateInput.value = appliedDiscountRate;
        appliedCouponIdInput.value = appliedCouponId || '';
        
        // Özeti ve fiyatı güncelle
        updateSummary();
    });

    updateSummary();

</script>

</body>
</html>