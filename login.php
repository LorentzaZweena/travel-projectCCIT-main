<?php
session_start();
require_once 'koneksi.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Silakan isi email dan password Anda!';
    } else {
        $sql  = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();

            if ($user) {
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['email']   = $user['email'];

                    $stmt->close();
                    header('Location: index2.php');
                    exit;
                } else {
                    $error = 'Email atau password yang Anda masukkan salah!';
                }
            } else {
                $error = 'Email atau password yang Anda masukkan salah!';
            }
            $stmt->close();
        } else {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Travel Explore & Tours</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/login-style.css">
</head>
<body>

  <div class="container-fluid p-0 login-container">
    <div class="row g-0 m-0 min-vh-100">
      
      <div class="col-lg-6 left-panel">
        <div>
          <a href="index_5.php" class="brand-logo">
            <div class="brand-icon">
              <i class="fa-solid fa-compass"></i>
            </div>
            <div>
              <span class="brand-text">Travel</span>
              <span class="brand-sub">Explore & Tours</span>
            </div>
          </a>
        </div>

        <div class="text-center my-auto py-4">
          <img src="img/login.png" alt="Travel Illustration" class="illustration-img">
        </div>

        <div class="copyright-text">
          &copy; 2026 Travel & Tours. All Rights Reserved.<br>
          <span style="font-size: 0.7rem; opacity: 0.8;">Powered by NOC</span>
        </div>
      </div>

      <div class="col-lg-6 right-panel">
        <div class="mx-auto w-100" style="max-width: 440px;">
          <h2 class="login-title">Login</h2>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 rounded-4 small mb-3" role="alert">
              <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <form action="" method="POST">
            <div class="mb-3">
              <label class="form-label-custom">Username / Email</label>
              <input type="text" name="username" class="form-control form-control-custom" placeholder="Enter username or email" required autocomplete="off">
            </div>

            <div class="mb-2">
              <label class="form-label-custom">Password</label>
              <input type="password" name="password" class="form-control form-control-custom" placeholder="Enter your password" required>
              <a href="#" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-login w-100 mt-4">Login to Travel</button>

            <div class="register-text">
              Don't have an account? <br>
              <a href="register.php" class="register-link">Register Now</a>
            </div>

            <div class="footer-help">
              Have a problem? Contact us at<br>
              <a href="mailto:hello@wandermore-tours.com">hello@wandermore-tours.com</a>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>