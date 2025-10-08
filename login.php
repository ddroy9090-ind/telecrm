<?php
session_start();

// If already logged in, redirect to dashboard
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {
    header("Location: index.php");
    exit;
}

// Handle login
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Demo credentials - Replace with database check
    if($username === 'admin' && $password === 'admin123') {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at top left, rgba(0, 69, 38, 0.4), rgba(0, 0, 0, 0.9));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            color: #f5f6f7;
        }
        .login-wrapper {
            width: 100%;
            max-width: 1100px;
            padding: 30px 15px;
        }
        .login-shell {
            background: rgba(12, 16, 14, 0.75);
            border-radius: 36px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.55);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 0;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 69, 38, 0.35);
        }
        .visual-panel {
            position: relative;
            background: linear-gradient(145deg, rgba(0, 69, 38, 0.85), rgba(0, 69, 38, 0.25));
            display: flex;
            align-items: flex-end;
            justify-content: center;
            min-height: 420px;
            padding: 40px;
        }
        .visual-panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('https://images.unsplash.com/photo-1489515217757-5fd1be406fef?auto=format&fit=crop&w=900&q=80') center/cover no-repeat;
            opacity: 0.8;
        }
        .visual-panel::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(0, 0, 0, 0) 10%, rgba(0, 0, 0, 0.65) 80%);
        }
        .visual-content {
            position: relative;
            text-align: left;
            z-index: 1;
            width: 100%;
        }
        .visual-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(245, 246, 247, 0.12);
            color: #e9f7ef;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .visual-title {
            font-size: 2rem;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 14px;
        }
        .visual-text {
            color: rgba(233, 247, 239, 0.8);
            line-height: 1.6;
            max-width: 360px;
        }
        .form-panel {
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.85));
        }
        .form-panel h2 {
            font-weight: 600;
            margin-bottom: 6px;
        }
        .form-panel p {
            color: rgba(245, 246, 247, 0.65);
            margin-bottom: 30px;
        }
        .form-floating .form-control {
            background: rgba(18, 26, 22, 0.85);
            border: 1px solid rgba(0, 69, 38, 0.45);
            color: #f1f8f4;
            padding: 18px 20px;
            border-radius: 16px;
        }
        .form-floating label {
            color: rgba(233, 247, 239, 0.65);
        }
        .form-floating .form-control:focus {
            border-color: #004526;
            box-shadow: 0 0 0 0.25rem rgba(0, 69, 38, 0.25);
            background: rgba(18, 26, 22, 0.95);
        }
        .input-group-text {
            background: rgba(18, 26, 22, 0.85);
            border: 1px solid rgba(0, 69, 38, 0.45);
            color: #70ffba;
        }
        .input-group .form-control {
            background: rgba(18, 26, 22, 0.85);
            border-left: none;
            border-radius: 0 16px 16px 0;
        }
        .input-group .form-control:focus {
            background: rgba(18, 26, 22, 0.95);
            color: #f1f8f4;
        }
        .auth-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 0.9rem;
            color: rgba(245, 246, 247, 0.7);
        }
        .auth-actions a {
            color: #70ffba;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00a86b, #004526);
            border: none;
            border-radius: 18px;
            padding: 14px 18px;
            font-weight: 600;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #00c57c, #00673f);
        }
        .login-meta {
            margin-top: 28px;
            color: rgba(245, 246, 247, 0.6);
            font-size: 0.8rem;
        }
        .alert {
            border-radius: 16px;
            background: rgba(220, 53, 69, 0.12);
            border: 1px solid rgba(220, 53, 69, 0.45);
            color: #ffb3bd;
        }
        @media (max-width: 991px) {
            .form-panel {
                padding: 40px 30px;
            }
            .visual-panel {
                min-height: 320px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-shell">
            <div class="visual-panel">
                <div class="visual-content">
                    <span class="visual-pill"><i class="fa-solid fa-bolt"></i> Gen AI</span>
                    <h2 class="visual-title">Create Your Account to Unleash Your Dreams</h2>
                    <p class="visual-text">By signing in, you agree to the latest Terms of Service and Privacy Policy. Start exploring the future today.</p>
                </div>
            </div>
            <div class="form-panel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Welcome Back</h2>
                        <p>Already have an account? Log in to continue.</p>
                    </div>
                    <a href="#" class="btn btn-outline-light btn-sm" style="border-radius: 999px; border-color: rgba(255,255,255,0.2); color: #70ffba;">Log in</a>
                </div>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="auth-actions">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="#">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Start Creating</span>
                    </button>
                    <div class="text-center login-meta">
                        <small>Demo credentials: admin / admin123</small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
