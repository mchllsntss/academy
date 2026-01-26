<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        /* Gradient green background */
        body {
            margin: 0;
            height: 100vh;
            overflow: hidden;
            position: relative;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #22c55e, #16a34a, #15803d, #14532d);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }   

        /* Animated gradient background */
        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        /* Optional subtle pattern overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.3;
            z-index: -1;
        }

        /* Login card animations */
        .login-card {
            opacity: 0;
            transform: translateY(30px);
            animation: slideUpFade 1s ease-out forwards;
            background-color: rgba(255, 255, 255, 0.95); /* slightly transparent */
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced logo floating */
        .logo {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-8px) scale(1.02); }
        }

        /* Input glow on focus */
        input:focus {
            box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
            transition: box-shadow 0.3s ease;
            border-color: #22c55e;
        }

        /* Enhanced button pulse */
        .login-btn {
            background: linear-gradient(45deg, #16a34a, #22c55e);
            color: white;
        }

        .login-btn:hover {
            background: linear-gradient(45deg, #15803d, #16a34a);
            animation: pulse 0.8s ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); }
            70% { transform: scale(1.03); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        a:hover {
            transition: color 0.2s ease-in-out;
            color: #14532d;
        }

        /* Login label enhancement */
        .login-label {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(45deg, #14532d, #22c55e);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: 0.5px;
        }

        /* Error message styling */
        .error-message {
            background-color: rgba(254, 226, 226, 0.9);
            border-left-color: #dc2626;
        }

        /* Forgot password link */
        .forgot-password {
            color: #16a34a;
        }

        .forgot-password:hover {
            color: #14532d;
        }
    </style>
</head>


<body>
    <?php include '../components/header.php'; ?>
    
    <div class="flex justify-center items-center min-h-screen">
        <!-- Resized Login Card -->
        <div class="login-card w-full max-w-md p-8 shadow-xl rounded-xl mx-4">
           <!-- Logo -->
           <div class="flex justify-center mb-4">
               <img src="../images/logo.png" alt="Logo" class="logo w-60 h-auto drop-shadow-lg">
           </div>
           
           <!-- Login Label -->
           <label class="block text-center mb-6 login-label">LOGIN</label>
           
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-5 p-3 error-message border-l-4 text-red-700 rounded-r">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="../controller/login_controller.php">
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="username">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                            Username
                        </span>
                    </label>
                    <input type="text" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 transition duration-300" id="username" name="username" required placeholder="Enter username">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="password">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            Password
                        </span>
                    </label>
                    <div class="relative">
                        <input type="password" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 pr-12 transition duration-300" id="password" name="password" required placeholder="Enter password">
                        <img src="../images/eye2.png" id="passwordToggle" class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer w-6 h-6 hover:opacity-80 transition duration-200" onclick="togglePassword()">
                    </div>
                    
                    <div class="mt-8">
                        <button type="submit" class="login-btn w-full py-3 text-white rounded-full text-base font-medium focus:outline-none transition duration-300 shadow-md hover:shadow-lg">
                            Login
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="mt-6 text-center space-y-3">
                <p>
                    <a href="forgot_password.php" class="forgot-password hover:text-green-800 font-medium transition duration-300 inline-flex items-center text-sm">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"/>
                        </svg>
                        Forgot Password?
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- password show toggle -->
    <script>
        function togglePassword() {
            var passwordInput = document.getElementById("password");
            var passwordToggle = document.getElementById("passwordToggle");
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                passwordToggle.src = "../images/eye.png";
            } else {
                passwordInput.type = "password";
                passwordToggle.src = "../images/eye2.png";
            }
        }
    </script>
</body>
</html>