<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in to Gatherly | Gatherly</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/logo.png">
    <link rel="stylesheet" href="../../src/output.css?v=<?php echo filemtime(__DIR__ . '/../../src/output.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body>
    <?php
    session_start();

    // Redirect to the right dashboard if already logged in
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'administrator':
                header("Location: admin/admin-dashboard.php");
                exit();
            case 'organizer':
                header("Location: organizer/organizer-dashboard.php");
                exit();
            case 'manager':
                header("Location: manager/manager-dashboard.php");
                exit();
            case 'supplier':
                header("Location: supplier/supplier-dashboard.php");
                exit();
            default:
                header("Location: ../../index.php");
                exit();
        }
    }

    $error = null;
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
    }
    ?>

    <div class="min-h-screen bg-linear-to-br from-indigo-50 via-white to-purple-50 font-['Montserrat'] flex flex-col">
        <div class="flex flex-col items-center justify-center flex-1 w-full px-4 py-8 sm:py-12">
            <div class="flex flex-col items-center mb-6 text-xl font-bold sm:text-2xl sm:mb-8 ">
                <a href="../../index.php" class="flex flex-col items-center group">
                    <img class="w-12 mb-4 transition-transform sm:w-16 sm:mb-8 drop-shadow-lg group-hover:scale-110"
                        src="../assets/images/logo.png" alt="Logo">
                    <span class="text-gray-800">Sign in to your account</span>
                </a>
            </div>
            <div class="flex flex-col items-center w-full mt-4 sm:mt-8">
                <form action="../../src/services/signin-handler.php" method="POST" class="w-full max-w-md sm:max-w-lg">
                    <div
                        class="flex flex-col w-full p-6 bg-white border border-gray-200 shadow-xl sm:p-8 md:p-12 rounded-2xl">
                        <?php if (!empty($error)): ?>
                            <div
                                class="flex items-start gap-2 p-3 mb-4 text-sm text-red-700 border border-red-200 rounded-lg sm:p-4 sm:mb-5 bg-red-50">
                                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                                <span><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        <?php endif; ?>
                        <label for="email" class="mb-2 text-sm font-semibold text-gray-700">Email address</label>
                        <input type="email" id="email" name="email" required
                            class="w-full px-4 py-2.5 mb-5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            placeholder="your@email.com">
                        <label for="password" class="mb-2 text-sm font-semibold text-gray-700">Password</label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 py-2.5 mb-6 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            placeholder="Enter your password">
                        <!-- <div class="flex flex-col gap-3 mb-6 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center">
                                <input type="checkbox" id="remember" name="remember"
                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                                <label for="remember" class="ml-2 text-xs font-medium text-gray-600 sm:text-sm">Remember
                                    me</label>
                            </div>
                            <a href="#forgotPass">
                                <span
                                    class="text-xs font-semibold text-indigo-600 transition-colors sm:text-sm hover:text-indigo-700 hover:underline">Forgot
                                    password?</span>
                            </a>
                        </div> -->
                        <button type="submit"
                            class="cursor-pointer w-full px-4 py-3 font-semibold text-white transition-all bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0">Sign
                            in</button>
                    </div>
                </form>
            </div>
            <div class="flex items-center mt-6 sm:mt-12">
                <p class="text-xs text-gray-600 sm:text-sm">Don't have an account?
                    <a href="signup.php">
                        <span
                            class="font-semibold text-indigo-600 transition-colors hover:text-indigo-700 hover:underline">Sign
                            up</span>
                    </a>
                </p>
            </div>
        </div>
        <?php include '../../src/components/Footer.php'; ?>
    </div>
</body>

</html>
