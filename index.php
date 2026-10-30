<?php
// Mfumo kamili wa NIDA Live Chaker - SQLite & PHP Built-in Server Compatible
session_start();

$db_file = __DIR__ . '/nida.db';
try {
    $pdo = new PDO("sqlite:" . $db_file);$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Kutengeneza Database Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        credits INTEGER DEFAULT 70,
        role TEXT DEFAULT 'user'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        nida_number TEXT,
        result_data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Kutengeneza Admin wa Kwanza (eliyaamos377@gmail.com)
    $stmt =$pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['eliyaamos377@gmail.com']);
    if (!$stmt->fetch()) {$admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $ins =$pdo->prepare("INSERT INTO users (username, password, credits, role) VALUES (?, ?, ?, ?)");
        $ins->execute(['eliyaamos377@gmail.com', $admin_pass, 99999, 'admin']);
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$msg = "";
$result_box = "";

// Usajili (Register)
if (isset($_POST['register'])) {
    $u = trim($_POST['username']);
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    try {
        $stmt =$pdo->prepare("INSERT INTO users (username, password, credits, role) VALUES (?, ?, 70, 'user')");
        $stmt->execute([$u, $p]);$msg = "Umefaulu kusajiliwa! Sasa unaweza kuingia (Login). Salio lako ni Credits 70.";
    } catch (Exception $e) {$msg = "Barua pepe hii tayari imesajiliwa!";
    }
}

// Login
if (isset($_POST['login'])) {
    $u = trim($_POST['username']);
    $p =$_POST['password'];
    $stmt =$pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user =$stmt->fetch();
    
    if ($user && password_verify($p,$user['password'])) {
        $_SESSION['user_id'] =$user['id'];
        $_SESSION['username'] =$user['username'];
        $_SESSION['credits'] =$user['credits'];
        $_SESSION['role'] =$user['role'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $msg = "Barua pepe au neno la siri si sahihi!";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Kitendo cha Kutafuta NIDA (-70 Credits)
if (isset($_POST['check_nida']) && isset($_SESSION['user_id'])) {
    $nida_no = trim($_POST['nida_number']);
    
    // Angalia kama ana salio la kutosha
    $stmt =$pdo->prepare("SELECT credits FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_credits =$stmt->fetchColumn();

    if ($current_credits >= 70) {
        // Punguza credit 70
        $new_credits =$current_credits - 70;
        $up =$pdo->prepare("UPDATE users SET credits = ? WHERE id = ?");
        $up->execute([$new_credits,$_SESSION['user_id']]);
        $_SESSION['credits'] =$new_credits;

        // Hapa unaweza kuweka matokeo ya NIDA au mfano huu wa majibu
        $result_box = "Taarifa za NIDA kwa Namba: <b>$nida_no</b> zimepatikana kwa mafanikio! (Hali: Hai / Imetikiwa)";
    } else {
        $result_box = "Salio lako halitoshi! Unahitaji angalau Credits 70 kufanya ombi hili.";
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIDA Live Chaker - Digital sales</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-slate-950 text-white min-h-screen flex flex-col justify-between">
    <div class="max-w-md mx-auto p-6 w-full bg-slate-900 rounded-xl shadow-lg mt-10 border border-emerald-500/30">
        <h1 class="text-2xl font-bold text-center text-emerald-400 mb-2">NIDA LIVE CHAKER</h1>
        <p class="text-center text-slate-400 mb-6 text-sm">Mfumo Rasmi wa Uhakiki</p>

        <?php if (!empty($msg)): ?>
            <div class="bg-emerald-950 border border-emerald-500 text-emerald-300 p-3 rounded mb-4 text-sm text-center">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- SEHEMU YA LOGIN NA REGISTER -->
            <div class="space-y-6">
                <form method="POST" class="space-y-4 bg-slate-900/50 p-4 rounded-lg border border-slate-800">
                    <h2 class="text-lg font-semibold text-emerald-400">Ingia (Login)</h2>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Email:</label>
                        <input type="email" name="username" required class="w-full p-2.5 rounded bg-slate-800 border border-slate-700 text-white text-sm focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Password:</label>
                        <input type="password" name="password" required class="w-full p-2.5 rounded bg-slate-800 border border-slate-700 text-white text-sm focus:outline-none focus:border-emerald-500">
                    </div>
                    <button type="submit" name="login" class="w-full bg-emerald-600 hover:bg-emerald-500 p-2.5 rounded text-sm font-bold transition">Ingia</button>
                </form>

                <form method="POST" class="space-y-4 bg-slate-900/50 p-4 rounded-lg border border-slate-800">
                    <h2 class="text-lg font-semibold text-emerald-400">Jisajili (Register - Free 70 Credits)</h2>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Email yako:</label>
                        <input type="email" name="username" required class="w-full p-2.5 rounded bg-slate-800 border border-slate-700 text-white text-sm focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-300 mb-1">Password:</label>
                        <input type="password" name="password" required class="w-full p-2.5 rounded bg-slate-800 border border-slate-700 text-white text-sm focus:outline-none focus:border-emerald-500">
                    </div>

