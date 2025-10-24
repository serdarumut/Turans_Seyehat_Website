<?php
session_start();
date_default_timezone_set('Europe/Istanbul'); 

// Tarayıcı Önbelleğini
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

// Yetkilendirme Kontrolü
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'user_company_admin')) {
    header('Location: index.php?error=unauthorized_access');
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');
$company_id = $is_admin ? null : $_SESSION['company_id']; 

$action = $_GET['action'] ?? 'list';
$coupon_id = $_GET['id'] ?? null;
$message = '';
$message_type = '';
$coupon = ['code' => '', 'discount' => '', 'usage_limit' => '', 'user_limit' => 1, 'expire_date' => ''];
$coupons = [];

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL sorgusunu role göre filtreleme
    $sql_filter = "";
    $sql_params = [];
    
    if (!$is_admin) {
        // Firma admini kupon kontrolü 
        $sql_filter = " WHERE company_id = :company_id OR company_id IS NULL"; 
        $sql_params[':company_id'] = $company_id;
    }

    // Kupon Listesini Çekme
    $sql_list = "SELECT * FROM Coupons " . $sql_filter . " ORDER BY expire_date DESC";
    $stmt_list = $db->prepare($sql_list);
    $stmt_list->execute($sql_params);
    $coupons = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

    // Silme İşlemi
    if ($action === 'delete' && $coupon_id) {
        $stmt = $db->prepare("DELETE FROM Coupons WHERE id = :id " . (!$is_admin ? "AND company_id = :cid" : ""));
        $params = [':id' => $coupon_id];
        if (!$is_admin) { $params[':cid'] = $company_id; }
        
        $stmt->execute($params);
        header('Location: coupon_crud.php?success=' . urlencode('Kupon başarıyla silinmiştir.'));
        exit;
    }

    // Oluşturma/Güncelleme Veri İşleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
        $usage_limit = filter_input(INPUT_POST, 'usage_limit', FILTER_VALIDATE_INT);
        $user_limit = filter_input(INPUT_POST, 'user_limit', FILTER_VALIDATE_INT); 
        $expire_date_str = $_POST['expire_date'] ?? '';
        $coupon_id_post = $_POST['coupon_id'] ?? null;
        
        // Veri Doğrulama
        if (empty($code) || $discount === false || $discount <= 0 || $discount > 1 || $usage_limit === false || $usage_limit < 1 || $user_limit === false || $user_limit < 1 || empty($expire_date_str)) {
            $message = "Hata: Lütfen tüm alanları doğru ve geçerli aralıkta doldurunuz.";
            $message_type = 'danger';
        } else {
            $expire_date = date('Y-m-d H:i:s', strtotime($expire_date_str));
            $db->beginTransaction();
            
            $coupon_company_id = $is_admin ? null : $company_id;

            if ($coupon_id_post) { // Güncelleme (UPDATE)
                $sql = "UPDATE Coupons SET code = :cd, discount = :dsc, usage_limit = :ul, user_limit = :usl, expire_date = :ed WHERE id = :id " . (!$is_admin ? "AND company_id = :cid" : "");
                $params = [':cd' => $code, ':dsc' => $discount, ':ul' => $usage_limit, ':usl' => $user_limit, ':ed' => $expire_date, ':id' => $coupon_id_post];
                if (!$is_admin) { $params[':cid'] = $company_id; }

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $db->commit();
                header('Location: coupon_crud.php?success=' . urlencode('Kupon başarıyla güncellenmiştir.'));
            } else { // Oluşturma (CREATE)
                $new_id = uniqid('coupon_');
                $sql = "INSERT INTO Coupons (id, code, discount, usage_limit, user_limit, expire_date, company_id) VALUES (:id, :cd, :dsc, :ul, :usl, :ed, :cid)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':id' => $new_id, ':cd' => $code, ':dsc' => $discount, 
                    ':ul' => $usage_limit, ':usl' => $user_limit, ':ed' => $expire_date, ':cid' => $coupon_company_id
                ]);
                $db->commit();
                header('Location: coupon_crud.php?success=' . urlencode('Yeni kupon başarıyla oluşturulmuştur.'));
            }
            exit;
        }
    }
    
    // Düzenleme Formu Verilerini Çekme
    if ($action === 'edit' && $coupon_id) {
        $sql = "SELECT * FROM Coupons WHERE id = :id " . (!$is_admin ? "AND company_id = :cid" : "");
        $params = [':id' => $coupon_id];
        if (!$is_admin) { $params[':cid'] = $company_id; }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            header('Location: coupon_crud.php?error=' . urlencode('Düzenlenecek kupon bulunamadı veya erişim yetkiniz yoktur.'));
            exit;
        }
        $coupon['expire_date'] = str_replace(' ', 'T', substr($coupon['expire_date'], 0, 16));
    }


} catch (Exception $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    // OWASP A09: Detaylı hata ifşası yerine genel hata mesajı
    $message = "Hata: İşlem sırasında bir sistem sorunu oluştu.";
    $message_type = 'danger';
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Yönetimi | <?php echo $is_admin ? 'Sistem' : 'Firma'; ?> Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-header { background-color: <?php echo $is_admin ? '#dc3545' : '#198754'; ?>; color: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; }
        .btn-custom { background-color: #ff7b00; border: none; font-weight: 600; }
        .btn-custom:hover { background-color: #ffa733; }
        .bg-system { background-color: #f8d7da; }
        .bg-company { background-color: #d1e7dd; }

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
    <div class="admin-header">
        <h1>İndirim Kuponu Yönetimi</h1>
        <p class="mb-0"><?php echo $is_admin ? 'Sistem Yöneticisi' : 'Firma Yöneticisi'; ?></p>
    </div>

    <?php 
    $get_msg = $_GET['success'] ?? $_GET['error'] ?? null;
    if ($get_msg): 
        $type = isset($_GET['success']) ? 'success' : 'danger';
        $message = htmlspecialchars(urldecode($get_msg));
    endif;
    
    if ($message): ?>
        <div class="alert alert-<?php echo $type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    
    <div class="card mb-5">
        <div class="card-header bg-dark text-white">
            <?php echo $action === 'edit' ? 'Kupon Düzenle' : 'Yeni Kupon Oluştur'; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="coupon_crud.php">
                <?php if ($coupon_id): ?>
                    <input type="hidden" name="coupon_id" value="<?php echo htmlspecialchars($coupon_id); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="code" class="form-label">Kupon Kodu</label>
                        <input type="text" class="form-control" id="code" name="code" 
                               value="<?php echo htmlspecialchars($coupon['code'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="discount" class="form-label">İndirim Oranı (Örn: 0.15)</label>
                        <input type="number" step="0.01" min="0.01" max="1" class="form-control" id="discount" name="discount" 
                               value="<?php echo htmlspecialchars($coupon['discount'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="usage_limit" class="form-label">Genel Kullanım Limiti</label>
                        <input type="number" min="1" class="form-control" id="usage_limit" name="usage_limit" 
                               value="<?php echo htmlspecialchars($coupon['usage_limit'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="user_limit" class="form-label">Kişisel Kullanım Limiti</label>
                        <input type="number" min="1" class="form-control" id="user_limit" name="user_limit" 
                               value="<?php echo htmlspecialchars($coupon['user_limit'] ?? 1); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="expire_date" class="form-label">Son Kullanma Tarihi</label>
                        <input type="datetime-local" class="form-control" id="expire_date" name="expire_date" 
                               value="<?php echo htmlspecialchars($coupon['expire_date'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <?php if ($action === 'edit'): ?>
                        <a href="coupon_crud.php" class="btn btn-secondary me-2">İptal</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-custom">
                        <?php echo $action === 'edit' ? 'Kuponu Güncelle' : 'Kuponu Oluştur'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <h3 class="mb-3">Mevcut Kuponlar</h3>
    <table class="table table-striped table-hover bg-white rounded shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>Kod</th>
                <th>İndirim</th>
                <th>Genel Limit</th>
                <th>Kişisel Limit</th>
                <th>Son Kullanma Tarihi</th>
                <th>Kapsam</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($coupons as $c): ?>
            <?php 
                $scope = $c['company_id'] ? 'Firma Özel' : 'Sistem Genel'; 
                $scope_class = $c['company_id'] ? 'bg-company' : 'bg-system';
                $is_expired = (strtotime($c['expire_date']) < time()); // Süre kontrolü
            ?>
            <tr class="align-middle <?php echo $is_expired ? 'table-danger' : $scope_class; ?>">
                <td class="fw-bold"><?php echo htmlspecialchars($c['code']); ?></td>
                <td>%<?php echo number_format($c['discount'] * 100, 0); ?></td>
                <td><?php echo $c['usage_limit']; ?></td>
                <td><?php echo $c['user_limit']; ?></td>
                <td>
                    <?php if ($is_expired): ?>
                        <span class="badge bg-danger">SÜRESİ DOLMUŞ</span><br>
                        <small class="text-danger"><?php echo date('d.m.Y H:i', strtotime($c['expire_date'])); ?></small>
                    <?php else: ?>
                        <?php echo date('d.m.Y H:i', strtotime($c['expire_date'])); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo $scope; ?></td>
                <td>
                    <?php if ($is_admin || ($c['company_id'] === $company_id)): ?>
                        <a href="coupon_crud.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-warning me-2">Düzenle</a>
                        <a href="coupon_crud.php?action=delete&id=<?php echo $c['id']; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Kuponun silinmesini onaylıyor musunuz?');">
                           Sil
                        </a>
                    <?php else: ?>
                        <span class="text-muted">Yetkisiz</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

</body>
</html>