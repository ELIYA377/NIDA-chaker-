<?php
session_start();

// Configuration za Database
$db_host = 'localhost';
$db_user = 'root'; // Badilisha kulingana na server yako
$db_pass = '';     // Badilisha kulingana na server yako
$db_name = 'nida_system';

// Muunganisho wa Database (PDO)
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // Kama database haijaundwa, tutatengeneza kiotomatiki
    try {
        $pdo_init = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    } catch (PDOException $ex) {
        die("Muunganisho wa Database umefeli: " . $ex->getMessage());
    }
}

// Kuunda Tables kama hazipo
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(100) NOT NULL,
        phone VARCHAR(15) UNIQUE NOT NULL,
        email VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        credits INT DEFAULT 500,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS search_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        searched_phone VARCHAR(15) NOT NULL,
        credits_used INT DEFAULT 70,
        searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
");

// Hakikisha Admin Chaguolililowekwa Lipo
$admin_email = 'eliyaamos377@gmail.com';
$admin_phone = '0784615052';
$stmt_chk = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$stmt_chk->execute([$admin_email, $admin_phone]);
if (!$stmt_chk->fetch()) {
    $admin_pass = password_hash('Admin123!', PASSWORD_BCRYPT);
    $stmt_add_admin = $pdo->prepare("INSERT INTO users (fullname, phone, email, password, credits, role) VALUES (?, ?, ?, ?, 999999, 'admin')");
    $stmt_add_admin->execute(['ELIYA AMOS NYAMWELU', $admin_phone, $admin_email, $admin_pass]);
}

// Logic za Utendaji
$message = '';
$message_type = '';

// Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Register
if (isset($_POST['register'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (fullname, phone, email, password, credits) VALUES (?, ?, ?, ?, 500)");
        $stmt->execute([$fullname, $phone, $email, $password]);
        $message = "Usajili umefanikiwa! Waweza kuingia sasa.";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Namba ya simu au barua pepe tayari ipo kwenye mfumo.";
        $message_type = "danger";
    }
}

// Login
if (isset($_POST['login'])) {
    $login_input = trim($_POST['login_input']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? OR email = ?");
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['fullname'] = $user['fullname'];
        header("Location: index.php");
        exit;
    } else {
        $message = "Taarifa za kuingia sio sahihi.";
        $message_type = "danger";
    }
}

// Ombi la Kutafuta NIDA
$nida_result = null;
if (isset($_POST['search_nida']) && isset($_SESSION['user_id'])) {
    $target_phone = trim($_POST['target_phone']);
    $user_id = $_SESSION['user_id'];

    // Angalia salio la credits
    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_credits = $stmt->fetchColumn();

    if ($current_credits < 70) {
        $message = "Salio lako la Credit halitoshi! Unahitaji Credit 70 kufanya utafutaji huu.";
        $message_type = "danger";
    } else {
        // Kata credits 70
        $new_credits = $current_credits - 70;
        $pdo->prepare("UPDATE users SET credits = ? WHERE id = ?")->execute([$new_credits, $user_id]);
        
        // Hifadhi Log
        $pdo->prepare("INSERT INTO search_logs (user_id, searched_phone, credits_used) VALUES (?, ?, 70)")->execute([$user_id, $target_phone]);

        // Matokeo ya Mfano (Mock NIDA Response)
        $nida_result = [
            'nin' => '19921211-' . rand(10000, 99999) . '-00001-22',
            'name' => 'ELIYA AMOS NYAMWELU',
            'gender' => 'ME',
            'dob' => '11/12/1992',
            'phone' => $target_phone,
            'status' => 'VERIFIED / HAI'
        ];

        $message = "Utafutaji umefanikiwa! Credit 70 zimekattwa.";
        $message_type = "success";
    }
}

// Action ya Admin Ongeza Credit
if (isset($_POST['add_credits']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $target_user_id = $_POST['target_user_id'];
    $amount = (int)$_POST['credit_amount'];

    $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")->execute([$amount, $target_user_id]);
    $message = "Salio la Credit $amount limeongezwa kikamilifu!";
    $message_type = "success";
}

// Pata Taarifa za Aliyeingia
$logged_user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $logged_user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIDA LIVE CHECKER - Digital Sales</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: #06100d;
            color: #e2e8f0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .glass-card {
            background: rgba(16, 24, 20, 0.75);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(16, 185, 129, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5);
        }
        .text-gold {
            color: #f59e0b;
        }
        .bg-emerald-accent {
            background-color: #10b981;
        }
        .border-emerald-accent {
            border-color: #10b981;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

    <!-- Header Marquee -->
    <div class="bg-black/60 border-b border-emerald-500/30 py-2 overflow-hidden whitespace-nowrap">
        <div class="inline-block animate-marquee text-emerald-400 font-semibold text-sm">
            🔥 KARIBU NIDA FINDER SYSTEM | PATA TAARIFA ZA NIDA KWA NAMBA YA SIMU | OMBI MOJA NI CREDIT 70 PEKEE!
        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="glass-card px-6 py-4 flex justify-between items-center mx-4 mt-4 rounded-xl">
        <div class="flex items-center space-x-2">
            <span class="text-2xl font-bold text-emerald-400">DIGITAL <span class="text-gold">🆔</span></span>
        </div>
        <div>
            <?php if ($logged_user): ?>
                <span class="mr-4 text-sm text-gray-300">Akaunti: <strong class="text-emerald-400"><?= htmlspecialchars($logged_user['fullname']) ?></strong></span>
                <span class="mr-4 bg-emerald-900/60 text-emerald-300 px-3 py-1 rounded-full border border-emerald-500/40 text-sm">
                    Salio: <strong><?= number_format($logged_user['credits']) ?> Credits</strong>
                </span>
                <a href="?action=logout" class="bg-red-600/80 hover:bg-red-700 text-white text-sm px-3 py-1.5 rounded-lg transition">Toka</a>
            <?php else: ?>
                <span class="text-sm text-gray-400">Tafadhali Jisajili / Ingia</span>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Content Container -->
    <div class="container mx-auto px-4 py-8 max-w-4xl">

        <!-- Message Alerts -->
        <?php if ($message): ?>
            <div class="p-4 mb-6 rounded-xl border <?= $message_type == 'success' ? 'bg-emerald-900/40 border-emerald-500 text-emerald-200' : 'bg-red-900/40 border-red-500 text-red-200' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$logged_user): ?>
            <!-- SEHEMU YA KUJISAJILI NA KUINGIA (AUTH) -->
            <div class="grid md:grid-cols-2 gap-8">
                
                <!-- Form ya Kuingia (Login) -->
                <div class="glass-card p-6 rounded-2xl">
                    <h2 class="text-xl font-bold text-emerald-400 mb-4 border-b border-gray-700 pb-2">INGIA KWENYE MFUMO</h2>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm mb-1 text-gray-300">Namba ya Simu / Email</label>
                            <input type="text" name="login_input" required class="w-full bg-black/50 border border-emerald-900 focus:border-emerald-500 rounded-lg p-2.5 text-white outline-none">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm mb-1 text-gray-300">Nenosiri (Password)</label>
                            <input type="password" name="password" required class="w-full bg-black/50 border border-emerald-900 focus:border-emerald-500 rounded-lg p-2.5 text-white outline-none">
                        </div>
                        <button type="submit" name="login" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold p-2.5 rounded-lg transition">Ingia Sasa</button>
                    </form>
                </div>

                <!-- Form ya Kujisajili (Register) -->
                <div class="glass-card p-6 rounded-2xl">
                    <h2 class="text-xl font-bold text-gold mb-4 border-b border-gray-700 pb-2">JISAJILI AKAUNTI MPYA</h2>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="block text-sm mb-1 text-gray-300">Jina Kamili</label>
                            <input type="text" name="fullname" required class="w-full bg-black/50 border border-emerald-900 focus:border-emerald-500 rounded-lg p-2 text-white outline-none">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm mb-1 text-gray-300">Namba ya Simu</label>
                            <input type="text" name="phone" placeholder="07xxxxxxxx" required class="w-full bg-black/50 border border-emerald-900 focus:border-emerald-500 rounded-lg p-2 text-white outline-none">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm mb-1 text-gray-300">Barua Pepe (Email)</label>
                            <input type="email" name="email" required class="w-full bg-black/50 border border-emerald-900 focus:border-emerald-500 rounded-lg p-2 text-white outline-none">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm mb-1 text-gray-300">Nenosiri (Password)</label>
                            <input type="password" name="password" required class="w-full bg-black/50 border border-emerald-900 focus:border-emerald-500 rounded-lg p-2 text-white outline-none">
                        </div>
                        <button type="submit" name="register" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold p-2.5 rounded-lg transition">Jisajili (Bure Credit 500)</button>
                    </form>
                </div>

            </div>

        <?php else: ?>

            <!-- DASHBOARD YA MTUMIAJI / ADMIN -->
            <div class="space-y-8">
                
                <!-- SEHEMU YA KUTAFUTA NIDA -->
                <div class="glass-card p-6 rounded-2xl">
                    <div class="flex justify-between items-center mb-4 border-b border-gray-700 pb-2">
                        <h2 class="text-xl font-bold text-emerald-400">TAFUTA NAMBA YA NIDA</h2>
                        <span class="text-xs bg-amber-500/20 text-gold px-3 py-1 rounded-full border border-amber-500/30">Gharama: Credit 70 / ombi</span>
                    </div>

                    <form method="POST" class="flex flex-col md:flex-row gap-4">
                        <input type="text" name="target_phone" placeholder="Ingiza Namba ya Simu (Mfano: 0784615052)" required class="flex-grow bg-black/50 border border-emerald-800 focus:border-emerald-500 rounded-xl p-3 text-white outline-none">
                        <button type="submit" name="search_nida" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-6 py-3 rounded-xl transition">
                            Tafuta NIDA 🔍
                        </button>
                    </form>

                    <!-- Onyesha Matokeo Ya NIDA -->
                    <?php if ($nida_result): ?>
                        <div class="mt-6 p-4 rounded-xl bg-black/60 border border-emerald-500/50">
                            <h3 class="text-gold font-bold text-lg mb-3 border-b border-gray-800 pb-1">MATOKEO YA UTAFUTAJI</h3>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <span class="text-gray-400">NIN (NIDA):</span> <span class="font-bold text-emerald-300"><?= $nida_result['nin'] ?></span>
                                <span class="text-gray-400">Jina Kamili:</span> <span class="font-bold text-white"><?= $nida_result['name'] ?></span>
                                <span class="text-gray-400">Jinsia:</span> <span class="text-white"><?= $nida_result['gender'] ?></span>
                                <span class="text-gray-400">Tarehe ya Kuzaliwa:</span> <span class="text-white"><?= $nida_result['dob'] ?></span>
                                <span class="text-gray-400">Namba ya Simu:</span> <span class="text-white"><?= $nida_result['phone'] ?></span>
                                <span class="text-gray-400">Hali:</span> <span class="text-emerald-400 font-bold"><?= $nida_result['status'] ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ADMIN CONTROL PANEL (INATOKEA KWA ADMIN PEKEE) -->
                <?php if ($logged_user['role'] == 'admin'): ?>
                    <div class="glass-card p-6 rounded-2xl border-2 border-amber-500/40">
                        <h2 class="text-xl font-bold text-gold mb-4 border-b border-gray-700 pb-2">👑 ADMIN CONTROL PANEL</h2>
                        
                        <!-- Ongeza Credit kwa Watumiaji -->
                        <div class="mb-6">
                            <h3 class="text-md font-semibold text-emerald-400 mb-2">Ongeza Salio la Credits kwa User</h3>
                            <form method="POST" class="flex flex-col md:flex-row gap-3">
                                <select name="target_user_id" required class="bg-black/60 border border-gray-700 rounded-lg p-2.5 text-white">
                                    <option value="">-- Chagua Mtumiaji --</option>
                                    <?php
                                    $users_stmt = $pdo->query("SELECT id, fullname, phone, credits FROM users ORDER BY id DESC");
                                    while ($u = $users_stmt->fetch()):
                                    ?>
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?> (<?= $u['phone'] ?>) - Credits: <?= $u['credits'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="number" name="credit_amount" placeholder="Kiasi cha Credit" required class="bg-black/60 border border-gray-700 rounded-lg p-2.5 text-white w-36">
                                <button type="submit" name="add_credits" class="bg-amber-600 hover:bg-amber-500 text-white font-bold px-4 py-2.5 rounded-lg transition">Ongeza Credits</button>
                            </form>
                        </div>

                        <!-- Orodha ya Watumiaji Wote -->
                        <h3 class="text-md font-semibold text-emerald-400 mb-2">Orodha ya Watumiaji na Salio</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm text-gray-300">
                                <thead class="bg-emerald-950/80 text-emerald-400">
                                    <tr>
                                        <th class="p-2">ID</th>
                                        <th class="p-2">Jina</th>
                                        <th class="p-2">Simu</th>
                                        <th class="p-2">Credits</th>
                                        <th class="p-2">Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $users_list = $pdo->query("SELECT * FROM users ORDER BY id DESC");
                                    while ($usr = $users_list->fetch()):
                                    ?>
                                        <tr class="border-b border-gray-800">
                                            <td class="p-2"><?= $usr['id'] ?></td>
                                            <td class="p-2"><?= htmlspecialchars($usr['fullname']) ?></td>
                                            <td class="p-2"><?= htmlspecialchars($usr['phone']) ?></td>
                                            <td class="p-2 font-bold text-amber-400"><?= $usr['credits'] ?></td>
                                            <td class="p-2"><?= $usr['role'] ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

    <!-- Footer -->
    <footer class="text-center py-4 text-xs text-gray-500 border-t border-gray-800">
        Developed by Digital sales
    </footer>

    <style>
        @keyframes marquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }
        .animate-marquee {
            display: inline-block;
            animation: marquee 20s linear infinite;
        }
    </style>
</body>
</html>
