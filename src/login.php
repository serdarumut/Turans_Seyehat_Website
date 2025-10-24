<?php session_start(); ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giriş Yap | Turans Seyahat</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: url('https://images.pexels.com/photos/2942172/pexels-photo-2942172.jpeg') no-repeat center center/cover;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Poppins', sans-serif;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(20px);
      border-radius: 25px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      width: 400px;
      padding: 2.5rem;
      text-align: center;
      color: white;
      transition: all 0.4s ease;
    }

    .login-card:hover {
      background: rgba(255, 255, 255, 0.12);
      box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6);
    }

    h2 {
      font-weight: 700;
      font-size: 2rem;
      text-align: center;
      margin-bottom: 1.5rem;
      letter-spacing: 1px;
      color: #000;
    }

    .form-control {
      background: rgba(255, 255, 255, 0.25);
      border: none;
      border-radius: 10px;
      color: #574646;
      padding: 10px 15px;
      font-size: 1rem;
      margin-bottom: 1rem;
      transition: 0.3s;
    }

    .form-control:focus {
      background: rgba(255, 255, 255, 0.35);
      box-shadow: 0 0 8px rgba(255, 255, 255, 0.4);
      outline: none;
    }

    .form-control::placeholder {
      color: rgba(73, 57, 57, 0.7);
    }

    .btn-custom {
      background-color: #ff7b00;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1.1rem;
      padding: 10px;
      transition: 0.3s;
    }

    .btn-custom:hover {
      background-color: #ffa733;
      transform: scale(1.03);
    }

    a {
      color: #fff;
      text-decoration: underline;
      transition: 0.2s;
    }

    a:hover {
      color: #ffa733;
    }
  </style>
</head>
<body>

  <div class="login-card">
    <h2>Turans Seyahat</h2>
    <form action="login_process.php" method="POST">
      <input type="email" name="email" class="form-control" placeholder="E-posta" required>
      <input type="password" name="password" class="form-control" placeholder="Şifre" required>
      
      <?php if (isset($_GET['redirect'])): ?>
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
      <?php endif; ?>

      <button type="submit" class="btn btn-custom w-100 mt-2">Giriş Yap</button>
    </form>
    <p class="mt-3 mb-0">Hesabınız yok mu? <a href="register.php">Kayıt ol</a></p>
  </div>

</body>
</html>