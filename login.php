<?php
session_start();

require_once __DIR__ . '/includes/config.php';

$email = '';

// If already logged in, redirect to dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email and password.';
    } else {
        $stmt = $mysqli->prepare('SELECT id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');

        if ($stmt) {
            $stmt->bind_param('s', $email);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $user = $result ? $result->fetch_assoc() : null;

                if ($user && $user['password_hash'] !== '' && password_verify($password, $user['password_hash'])) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['username'] = $user['full_name'] !== '' ? $user['full_name'] : $user['email'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    header('Location: index.php');
                    exit;
                }
            }

            $stmt->close();
        }

        $error = 'Invalid email or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Houzz Hunt CRM - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/images/logo/favicon.svg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap');

        body, html {
            height: 100%;
            margin: 0;
            font-family: "Open Sans", sans-serif;
        }

        /* Particle Background */
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #013b2a, #3db174);
            top: 0;
            left: 0;
            z-index: 0;
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            color: #d1d1c9;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        span.whiteYellow {
            color: #fff;
            font-weight: 300;
        }

        .login-logo {
            width: 240px;
            position: relative;
            right: 10px;
            margin-bottom: 20px;
        }

        .login-wrapper h2 {
            margin-bottom: 0px;
            font-size: 40px;
            font-weight: 300;
            line-height: 1.3;
            color: #fff;
        }

        .login-wrapper p {
            margin: 0;
            color: #fff;
            padding-bottom: 5px;
            font-size: 14px;
        }

        .login-wrapper .feature-icon img {
            width: 36px;
            height: 36px;
        }

        .login-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
        }

        .login-box h3 {
            font-weight: 400;
            margin-bottom: 5px;
            color: #111;
            text-align: center;
        }

        .login-form .form-group label {
            margin-bottom: 5px;
            color: #111;
        }

        .login-form .form-group input {
            box-shadow: none;
            border: 1px #004a44 solid;
        }

        .login-box p {
            color: #6a7c7c;
            text-align: center;
            margin-bottom: 20px;
        }

        .login-box .form-control {
            border-radius: 8px;
        }

        .login-box button {
            background-color: #319965;
            border: none;
            border-radius: 8px;
            width: 100%;
            padding: 10px;
            color: #fff;
        }

        .login-box button:hover {
            background-color: #154442;
        }

        .login-box .forgot {
            color: #eebd2b;
            font-size: 14px;
            font-weight: 500;
        }

        .login-box .forgot:hover {
            text-decoration: underline;
        }

        .login-box .contact {
            color: #f0c22b;
            text-decoration: none;
            font-weight: 600;
        }

        .login-box .contact:hover {
            text-decoration: underline;
        }

        .footer-text {
            font-size: 13px;
            color: #fff;
            margin-top: 30px;
            text-align: center;
        }

        .text-golden {
            color: #fff;
        }

        .form-group .form-check label {
            color: #6a7c7c;
        }

        small {
            color: #6a7c7c;
        }
    </style>
</head>

<body>
    <!-- Particle background -->
    <div id="particles-js"></div>

    <div class="login-wrapper">
        <div class="container">
            <div class="row justify-content-between">
                <!-- Left Section -->
                <div class="col-lg-7 d-flex flex-column justify-content-center">
                    <a href="index.php"><img src="assets/images/logo/crm-logo.svg" alt="" class="login-logo"></a>
                    <h2><span class="whiteYellow">REAL ESTATE CRM Portal</span></h2>
                    <p>Your all-in-one platform for managing Dubai’s real estate operations.</p>
                    <p>Log in to manage your leads, track client journeys, monitor listings, and drive sales performance with real-time insights.</p>

                    <div class="row mt-5">
                        <div class="col-4">
                            <div class="feature-icon mb-2">
                                <img src="assets/icons/listing.png" alt="Lead Management">
                            </div>
                            <strong class="text-golden">Lead Management</strong>
                            <p class="m-0">Capture, qualify, and convert potential buyers efficiently.</p>
                        </div>
                        <div class="col-4">
                            <div class="feature-icon mb-2">
                                <img src="assets/icons/client-management.png" alt="Client Relationship">
                            </div>
                            <strong class="text-golden">Client Relationship</strong>
                            <p class="m-0">Track communications, follow-ups, and engagement history.</p>
                        </div>
                        <div class="col-4">
                            <div class="feature-icon mb-2">
                                <img src="assets/icons/analytics.png" alt="Analytics Dashboard">
                            </div>
                            <strong class="text-golden">Analytics Dashboard</strong>
                            <p class="m-0">View key performance indicators and campaign results in real time.</p>
                        </div>
                    </div>
                </div>

                <!-- Right Section -->
                <div class="col-lg-4 align-items-center">
                    <div class="login-box w-100">
                        <h3>Welcome Back</h3>
                        <p>Sign in to your partner account</p>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bx bx-error-circle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form class="login-form" method="POST" action="">
                            <div class="mb-3 form-group">
                                <label>Email Address</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>

                            <div class="mb-3 form-group position-relative">
                                <label>Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <span id="togglePassword" style="position:absolute; right:15px; top:55%; cursor:pointer; font-size:14px; user-select:none;">
                                    👁️
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check form-group">
                                    <input class="form-check-input" type="checkbox" id="rememberMe">
                                    <label class="form-check-label" for="rememberMe">Remember me</label>
                                </div>
                                <a href="#" class="forgot">Forgot password?</a>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Sign In to Portal</button>
                        </form>

                        <script>
                            // Password toggle functionality
                            const togglePassword = document.querySelector("#togglePassword");
                            const password = document.querySelector("#password");

                            togglePassword.addEventListener("click", function() {
                                const type = password.getAttribute("type") === "password" ? "text" : "password";
                                password.setAttribute("type", type);
                                this.textContent = type === "password" ? "👁️" : "🙈";
                            });
                        </script>

                        <div class="text-center mt-3">
                            <small>Need access? <a href="#" class="contact">Contact Admin</a></small>
                        </div>
                    </div>
                    <div class="footer-text">
                        Powered by Houzz Hunt Real Estate Technology
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Particle.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>

   <script>
    particlesJS("particles-js", {
        "particles": {
            "number": {
                "value": 150,  /* increased from 70 to 150 */
                "density": { "enable": true, "value_area": 1000 }
            },
            "color": { "value": "#ffffff" },
            "shape": { "type": "circle" },
            "opacity": {
                "value": 0.5,  /* slightly brighter */
                "random": false
            },
            "size": {
                "value": 5,
                "random": true
            },
            "line_linked": {
                "enable": true,
                "distance": 130,  /* more lines by reducing distance */
                "color": "#ffffff",
                "opacity": 0.4,   /* make lines more visible */
                "width": 1
            },
            "move": {
                "enable": true,
                "speed": 2,
                "direction": "none",
                "random": false,
                "straight": false,
                "out_mode": "out",
                "bounce": false
            }
        },
        "interactivity": {
            "detect_on": "canvas",
            "events": {
                "onhover": { "enable": true, "mode": "grab" },
                "onclick": { "enable": true, "mode": "push" },
                "resize": true
            },
            "modes": {
                "grab": { "distance": 180, "line_linked": { "opacity": 0.6 } },
                "push": { "particles_nb": 5 }
            }
        },
        "retina_detect": true
    });
</script>


</body>
</html>
