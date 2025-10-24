<?php 

session_start(); 
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa | Turans Seyahat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('https://images.pexels.com/photos/1178448/pexels-photo-1178448.jpeg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Poppins', sans-serif;
            color: #fff;
        }

        /* NAVBAR STİLLERİ (TÜM SİSTEM İÇİN KRİTİK) */
        .navbar {
            background-color: #343a40 !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5) !important;
        }

        .navbar a, .navbar-text {
            color: #fff !important; 
            font-weight: 500;
            transition: 0.2s;
        }

        .navbar a:hover {
            color: #ffa733 !important; 
        }

        /* ÇIKIŞ YAP BUTONU VURGUSU */
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

        /* Arama Kartı Stilleri */
        .search-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            padding: 2rem;
            margin-top: 10vh;
        }

        h1 {
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.4);
        }

        .btn-custom {
            background-color: #ff7b00;
            border: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-custom:hover {
            background-color: #ffa733;
            transform: scale(1.03);
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container d-flex justify-content-center">
        <div class="search-box col-md-8 text-center">
            <h1>Sefer Arama</h1>
            <form action="seferler.php" method="GET" class="row g-3 mt-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="nereden" placeholder="Kalkış Noktası" required>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="nereye" placeholder="Varış Noktası" required>
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" name="tarih" required>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-custom w-100">Ara</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>