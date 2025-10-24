<?php
session_start();
date_default_timezone_set('Europe/Istanbul');
// Tarayıcı Önbelleğini Devre Dışı Bırakma
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_path = '/var/www/data/turans.sqlite';

// Yetkilendirme Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php?error=unauthorized_access');
    exit;
}

$action = $_GET['action'] ?? 'list';
$message = '';
$message_type = '';

$companies = [];
$company = ['name' => '', 'id' => uniqid(), 'admin_name' => '', 'admin_email' => '', 'admin_password' => ''];

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');
    
    // Firma Listesini ve Atanan Yöneticileri Çekme
    $sql_list = "
        SELECT 
            bc.id, bc.name, 
            u.full_name AS admin_name, u.email AS admin_email, u.id AS admin_id 
        FROM Bus_Company bc
        LEFT JOIN User u ON u.company_id = bc.id AND u.role = 'user_company_admin'
        ORDER BY bc.name ASC
    ";
    $companies = $db->query($sql_list)->fetchAll(PDO::FETCH_ASSOC);


    // Oluşturma/Güncelleme Veri İşleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->beginTransaction();

        $company_name = trim($_POST['name'] ?? '');
        $admin_name = trim($_POST['admin_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $company_id_post = $_POST['company_id'] ?? uniqid('comp_');
        $current_admin_id = $_POST['current_admin_id'] ?? null;
        $is_update = isset($_POST['is_update']);
        
        if (empty($company_name) || empty($admin_name) || empty($admin_email) || (!$is_update && empty($admin_password))) {
             throw new Exception("Firma Adı, Yönetici Adı, E-posta ve Şifre (oluşturmada) alanları zorunludur.");
        }

        // Firma Ekleme/Güncelleme
        if ($is_update) { 
            $stmt = $db->prepare("UPDATE Bus_Company SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $company_name, ':id' => $company_id_post]);
        } else { 
            $stmt = $db->prepare("INSERT INTO Bus_Company (id, name) VALUES (:id, :name)");
            $stmt->execute([':id' => $company_id_post, ':name' => $company_name]);
        }

        // Firma Admini İşlemleri
        $admin_id_to_use = $current_admin_id;

        if (!$admin_id_to_use) {
            // Yeni yönetici oluşturma.
            if (empty($admin_password)) {
                 throw new Exception("Yeni yönetici için şifre alanı zorunludur.");
            }
            $stmt = $db->prepare("SELECT id FROM User WHERE email = :email");
            $stmt->execute([':email' => $admin_email]);
            if ($stmt->fetchColumn()) {
                 throw new Exception("Bu e-posta adresi sistemde zaten kayıtlıdır.");
            }
            
            $admin_id_to_use = uniqid('user_');
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO User (id, full_name, email, role, password, company_id, balance) 
                VALUES (:id, :full_name, :email, 'user_company_admin', :password, :company_id, 0.00)
            ");
            $stmt->execute([
                ':id' => $admin_id_to_use,
                ':full_name' => $admin_name,
                ':email' => $admin_email,
                ':password' => $hashed_password,
                ':company_id' => $company_id_post
            ]);
            $is_update = false;
            
        } else {
            // Var olan yöneticiyi güncelleme
            $stmt = $db->prepare("
                UPDATE User SET full_name = :full_name, email = :email, company_id = :company_id 
                WHERE id = :id
            ");
            $stmt->execute([
                ':full_name' => $admin_name,
                ':email' => $admin_email,
                ':company_id' => $company_id_post,
                ':id' => $admin_id_to_use
            ]);
            
            // Şifre güncelleniyorsa
            if (!empty($admin_password)) {
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE User SET password = :password WHERE id = :id");
                $stmt->execute([':password' => $hashed_password, ':id' => $admin_id_to_use]);
            }
        }


        $db->commit();
        $message = $is_update ? "Firma ve yönetici bilgileri başarıyla güncellenmiştir." : "Yeni firma ve yönetici başarıyla oluşturulmuştur.";
        $message_type = 'success';
        header('Location: company_crud.php?success=' . urlencode($message));
        exit;
    }
    
    // Silme İşlemi
    if ($action === 'delete' && isset($_GET['id'])) {
        $delete_id = $_GET['id'];
        $db->beginTransaction();
        
        $db->exec("DELETE FROM User WHERE company_id = '$delete_id' AND role = 'user_company_admin'");
        $db->exec("DELETE FROM Bus_Company WHERE id = '$delete_id'");
        
        $db->commit();
        header('Location: company_crud.php?success=' . urlencode('Firma, yöneticisi ve ilgili tüm seferler başarıyla silinmiştir.'));
        exit;
    }


} catch (Exception $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    
    $message = "Hata: İşlem sırasında bir sorun oluştu.";
    $message_type = 'danger';
}

// Düzenleme Formu Verilerini Çekme
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $stmt = $db->prepare("SELECT * FROM Bus_Company WHERE id = :id");
    $stmt->execute([':id' => $edit_id]);
    $company_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company_data) {
        header('Location: company_crud.php?error=' . urlencode('Düzenlenecek firma bulunamadı.'));
        exit;
    }
    
    $stmt = $db->prepare("SELECT id, full_name, email FROM User WHERE company_id = :id AND role = 'user_company_admin'");
    $stmt->execute([':id' => $edit_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $company['name'] = $company_data['name'];
    $company['id'] = $company_data['id'];
    $company['admin_name'] = $admin_data['full_name'] ?? '';
    $company['admin_email'] = $admin_data['email'] ?? '';
    $company['current_admin_id'] = $admin_data['id'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Yönetimi | Yönetim Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .admin-header { background-color: #dc3545; color: white; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; }
        .btn-custom { background-color: #ff7b00; border: none; font-weight: 600; }
        .btn-custom:hover { background-color: #ffa733; }
        .bg-success-subtle { background-color: #d1e7dd; }

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
        <h1>Otobüs Firmaları ve Yöneticileri</h1>
        <p class="fs-5 mb-0">Yönetici / Firma ve Yönetici CRUD</p>
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
            <?php echo $action === 'edit' ? 'Firma Düzenle' : 'Yeni Firma Ekle'; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="company_crud.php">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="is_update" value="1">
                    <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($company['id']); ?>">
                    <input type="hidden" name="current_admin_id" value="<?php echo htmlspecialchars($company['current_admin_id'] ?? ''); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Firma Adı</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mt-4 mt-md-0">Firma Yöneticisi Bilgileri</h6>
                        <hr class="mt-0">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="admin_name" class="form-label">Yönetici Adı Soyadı</label>
                        <input type="text" class="form-control" id="admin_name" name="admin_name" 
                               value="<?php echo htmlspecialchars($company['admin_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="admin_email" class="form-label">Yönetici E-postası</label>
                        <input type="email" class="form-control" id="admin_email" name="admin_email" 
                               value="<?php echo htmlspecialchars($company['admin_email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="admin_password" class="form-label">Şifre <?php echo $action === 'edit' ? '(Değiştirmek için doldurunuz)' : ''; ?></label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" 
                               placeholder="<?php echo $action === 'edit' ? 'Boş bırakılırsa değişmez' : 'Zorunlu'; ?>">
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <?php if ($action === 'edit'): ?>
                        <a href="company_crud.php" class="btn btn-secondary me-2">İptal</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-custom">
                        <?php echo $action === 'edit' ? 'Firma ve Yöneticiyi Güncelle' : 'Firma ve Yöneticiyi Ekle'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <h3 class="mb-3">Mevcut Firmalar</h3>
    <table class="table table-striped table-hover bg-white rounded shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>Firma Adı</th>
                <th>Yönetici Adı (E-posta)</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($companies as $comp): ?>
            <tr class="align-middle <?php echo $comp['admin_name'] ? 'bg-success-subtle' : 'table-light'; ?>">
                <td class="fw-bold"><?php echo htmlspecialchars($comp['name']); ?></td>
                <td>
                    <?php if ($comp['admin_name']): ?>
                        <span class="badge bg-success"><?php echo htmlspecialchars($comp['admin_name']); ?></span>
                        <br><small class="text-muted"><?php echo htmlspecialchars($comp['admin_email']); ?></small>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Yönetici Atanmamıştır</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="company_crud.php?action=edit&id=<?php echo $comp['id']; ?>" class="btn btn-sm btn-warning me-2">Düzenle</a>
                    <a href="company_crud.php?action=delete&id=<?php echo $comp['id']; ?>" 
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('UYARI: Firma silinmesini onaylıyor musunuz? Yöneticisi, tüm seferleri ve biletleri de silinecektir.');">
                       Sil
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

</body>
</html>