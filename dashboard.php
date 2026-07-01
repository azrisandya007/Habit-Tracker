<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Koneksi Database
$host = "127.0.0.1";
$user = "root"; 
$pass = "";     
$db   = "habit_tracker";
$conn = mysqli_connect($host, $user, $pass, $db);
$user_id = $_SESSION['user_id'];

// Fitur Navigasi Tab (Default: dashboard)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// --- LOGIKA PROSES (TAMBAH, UPDATE, HAPUS) ---
if (isset($_POST['add_habit'])) {
    $habit_name = mysqli_real_escape_string($conn, $_POST['habit_name']);
    if (!empty($habit_name)) {
        mysqli_query($conn, "INSERT INTO habits (user_id, habit_name) VALUES ('$user_id', '$habit_name')");
        header("Location: dashboard.php?tab=dashboard");
        exit();
    }
}

if (isset($_GET['toggle_id'])) {
    $habit_id = $_GET['toggle_id'];
    $current_status = $_GET['status'];
    $today = date('Y-m-d');
    
    if ($current_status == 'Belum') {
        mysqli_query($conn, "UPDATE habits SET status='Selesai', total_done = total_done + 1, last_updated='$today' WHERE id='$habit_id' AND user_id='$user_id'");
    } else {
        mysqli_query($conn, "UPDATE habits SET status='Belum', total_done = GREATEST(0, total_done - 1) WHERE id='$habit_id' AND user_id='$user_id'");
    }
    header("Location: dashboard.php?tab=" . $tab);
    exit();
}

if (isset($_GET['delete_id'])) {
    $habit_id = $_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM habits WHERE id='$habit_id' AND user_id='$user_id'");
    header("Location: dashboard.php?tab=dashboard");
    exit();
}

// Respon dari Pop-up AI (Lanjut atau Hapus)
if (isset($_GET['ai_action'])) {
    $habit_id = $_GET['id'];
    $action = $_GET['ai_action'];
    if ($action == 'keep') {
        mysqli_query($conn, "UPDATE habits SET ai_alert_triggered=2 WHERE id='$habit_id'");
    } else if ($action == 'delete') {
        mysqli_query($conn, "DELETE FROM habits WHERE id='$habit_id'");
    }
    header("Location: dashboard.php?tab=dashboard");
    exit();
}

// Ambil Data Habit
$habits_query = mysqli_query($conn, "SELECT * FROM habits WHERE user_id='$user_id' ORDER BY id DESC");
$habits = [];
$ai_alert_habit = null;

while ($row = mysqli_fetch_assoc($habits_query)) {
    // Logika AI Heuristik 7 Hari
    $created_date = new DateTime($row['created_at']);
    $today_date = new DateTime();
    $interval = $today_date->diff($created_date)->days;

    if ($interval >= 7 && $row['ai_alert_triggered'] == 0) {
        $frekuensi = $row['total_done'];
        if ($frekuensi >= 7) { $status_ai = "Selalu"; }
        else if ($frekuensi >= 5) { $status_ai = "Sering"; }
        else if ($frekuensi >= 3) { $status_ai = "Kadang-kadang"; }
        else if ($frekuensi >= 1) { $status_ai = "Pernah"; }
        else { $status_ai = "Jarang"; }

        if ($frekuensi <= 2) {
            $ai_alert_habit = [
                'id' => $row['id'],
                'name' => $row['habit_name'],
                'analisis' => $status_ai,
                'total' => $frekuensi
            ];
            mysqli_query($conn, "UPDATE habits SET ai_alert_triggered=1 WHERE id='{$row['id']}'");
        }
    }
    $habits[] = $row;
}

// Hitung Progress Bar Total Hari Ini
$total_habits = count($habits);
$done_habits = 0;
foreach ($habits as $h) { if ($h['status'] == 'Selesai') $done_habits++; }
$progress_percent = $total_habits > 0 ? round(($done_habits / $total_habits) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Habit Tracker</title>
    <style>
        /* --- RESET & GLOBAL STYLE --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body { 
            background: #f3f4f6; 
            color: #333;
            min-height: 100vh;
        }
        .app-container { 
            display: flex; 
            min-height: 100vh;
            width: 100%; 
        }

        /* --- SIDEBAR STYLE (LAPTOP / PC) --- */
        .sidebar { 
            width: 260px; 
            background: #1e1e2f; 
            color: #fff; 
            padding: 25px 15px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            flex-shrink: 0;
        }
        .sidebar h2 { 
            font-size: 22px; 
            color: #7494ec; 
            margin-bottom: 30px; 
            padding-left: 10px; 
        }
        .nav-menu { 
            list-style: none; 
        }
        .nav-item a { 
            display: flex; 
            align-items: center; 
            padding: 14px 15px; 
            color: #b3b3b3; 
            text-decoration: none; 
            border-radius: 8px; 
            margin-bottom: 8px; 
            font-weight: 500; 
            transition: 0.2s; 
        }
        .nav-item.active a, .nav-item a:hover { 
            background: #7494ec; 
            color: #fff; 
        }
        .btn-logout-sidebar {
            background: #ff4d4d;
            color: white;
            padding: 10px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            text-decoration: none; 
            font-weight: bold;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            display: block;
            transition: 0.5s;

        }

        .btn-logout-sidebar:hover {
            background: #e01616;
        }

        /* --- AREA KONTEN UTAMA --- */
        .main-content { 
            flex: 1; 
            padding: 40px; 
            background: #f7f9fc; 
        }
        .header-user { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
        }

        /* --- KOMPONEN DASHBOARD --- */
        .progress-card { 
            background: #fff; 
            padding: 20px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
            margin-bottom: 25px; 
        }
        .progress-bar-container { 
            background: #eee; 
            height: 12px; 
            border-radius: 10px; 
            overflow: hidden; 
            margin-top: 10px; 
        }
        .progress-bar-fill { 
            background: linear-gradient(to right, #4ade80, #2ecc71); 
            height: 100%; 
            transition: width 0.5s ease; 
        }

        /* Habit Grid Cards */
        .habit-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); 
            gap: 20px; 
            margin-top: 20px; 
        }
        .habit-card { 
            background: #fff; 
            padding: 20px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
            border-top: 5px solid #7494ec; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
        }
        .habit-card.done { 
            border-top-color: #2ecc71; 
            background: #f0fdf4; 
        }
        .habit-card h3 { font-size: 18px; margin-bottom: 10px; }
        .habit-card.done h3 { text-decoration: line-through; color: #9ca3af; }

        .weekly-dots { display: flex; gap: 5px; margin: 15px 0; justify-content: center; }
        .dot { width: 12px; height: 12px; border-radius: 50%; background: #e5e7eb; }
        .dot.active { background: #2ecc71; }

        .btn-action { padding: 8px 14px; border: none; border-radius: 20px; cursor: pointer; font-weight: 600; font-size: 0.85rem; text-decoration: none; text-align: center; }
        .btn-success { background: #2ecc71; color: white; }
        .btn-warning { background: #f39c12; color: white; }

        /* Form Styling */
        .habit-form { display: flex; gap: 10px; margin-bottom: 30px; }
        .habit-form input { flex: 1; padding: 12px 20px; background: #eee; border: none; outline: none; font-size: 16px; color: #333; border-radius: 40px; }
        .habit-form button { width: auto; padding: 12px 30px; background: #7494ec; color: white; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; }

        /* PERBAIKAN EVALUASI TABEL (ANTI OVERFLOW) */
        .table-container { 
            width: 100%;
            overflow-x: auto; 
            margin-top: 15px;
            background: #fff;
            border-radius: 8px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
            min-width: 550px; 
        }
        table th, table td { padding: 12px; border-bottom: 1px solid #ddd; }

        /* AI Modal Overlay */
        .ai-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 9999; padding: 20px; }
        .ai-modal { background: white; width: 450px; padding: 30px; border-radius: 12px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .ai-modal h3 { color: #e74c3c; margin-bottom: 15px; }
        .ai-modal p { color: #555; margin-bottom: 25px; font-size: 0.95rem; line-height: 1.5; }
        .ai-modal-btns { display: flex; justify-content: center; gap: 15px; }
        .btn-pop { padding: 10px 25px; border-radius: 30px; font-weight: bold; border: none; cursor: pointer; text-decoration: none; }
        .btn-keep { background: #7494ec; color: white; }
        .btn-del { background: #e74c3c; color: white; }

        /* Element pembantu menyembunyikan bottom-nav di laptop */
        .mobile-top-bar, .mobile-bottom-nav { display: none; }

        /* ===================================================
           ⚡ RESPONSIVE MEDIA QUERIES (UNTUK LAYAR HP)
           =================================================== */
        @media (max-width: 768px) {
            .app-container { flex-direction: column; }
            .sidebar { display: none; } /* Sembunyikan Sidebar Laptop */
            
            /* Tampilkan Top Bar khusus HP */
            .mobile-top-bar { 
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #1e1e2f;
                padding: 15px 20px;
                color: #fff;
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            .mobile-top-bar h2 { font-size: 20px; color: #7494ec; }
            
            .btn-logout-mobile { 
            background-color: #ff4d4d; 
            color: white; padding: 6px 14px; 
            border-radius: 20px; 
            font-size: 0.85rem; 
            text-decoration: none; 
            font-weight: bold; 
            transition: background-color 0.2s ease, transform 0.1s ease;
        }

        .btn-logout-mobile:active {
            background: #e01616;
            transform: scale(0.95);    /* Efek membal / mengecil seolah tombolnya benar-benar dipencet */
        }
            
            /* Tampilkan Bottom Nav Menu khusus HP */
            .mobile-bottom-nav { 
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 60px;
                background: #1e1e2f;
                justify-content: space-around; 
                align-items: center;
                z-index: 9999;
                list-style: none;
                padding: 0;
                margin: 0;
            }
            .mobile-bottom-nav .nav-item { width: 33.33%; text-align: center; }
            .mobile-bottom-nav .nav-item a { display: block; color: #b3b3b3; text-decoration: none; font-size: 0.85rem; padding: 10px 0; font-weight: bold; }
            .mobile-bottom-nav .nav-item.active a { color: #fff; background: #7494ec; border-radius: 8px; margin: 0 5px; }
            
            /* Tata ulang area konten utama di HP */
            .main-content { padding: 20px 15px 85px 15px; }
            .header-user { flex-direction: column; align-items: flex-start; }
            .header-user h1 { font-size: 22px; }

            .habit-form { flex-direction: column; gap: 10px; }
            .habit-form input, .habit-form button { width: 100%; }
            .habit-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php if ($ai_alert_habit): ?>
        <div class="ai-modal-overlay">
            <div class="ai-modal">
                <h3>🤖 Peringatan Evaluasi AI</h3>
                <p>
                    Berdasarkan analisis AI selama 1 minggu terakhir, kebiasaan <strong>"<?php echo htmlspecialchars($ai_alert_habit['name']); ?>"</strong> memiliki status performa: <span style="color:#e74c3c; font-weight:bold;"><?php echo $ai_alert_habit['analisis']; ?></span> (Hanya dilakukan <?php echo $ai_alert_habit['total']; ?> kali).<br><br> Apakah kamu ingin melanjutkan habit ini atau menghapusnya secara otomatis?
                </p>
                <div class="ai-modal-btns">
                    <a href="dashboard.php?ai_action=keep&id=<?php echo $ai_alert_habit['id']; ?>" class="btn-pop btn-keep">Lanjut</a>
                    <a href="dashboard.php?ai_action=delete&id=<?php echo $ai_alert_habit['id']; ?>" class="btn-pop btn-del">Tidak (Hapus)</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mobile-top-bar">
        <h2>✨ HabitAI</h2>
        <a href="logout.php" class="btn-logout-mobile">Logout</a>
    </div>

    <div class="app-container">
        <div class="sidebar">
            <div>
                <h2>✨ HabitAI</h2>
                <ul class="nav-menu">
                    <li class="nav-item <?php echo $tab == 'dashboard' ? 'active' : ''; ?>">
                        <a href="dashboard.php?tab=dashboard">📊 Dashboard</a>
                    </li>
                    <li class="nav-item <?php echo $tab == 'challenge' ? 'active' : ''; ?>">
                        <a href="dashboard.php?tab=challenge">🏆 Challenge</a>
                    </li>
                    <li class="nav-item <?php echo $tab == 'evaluasi' ? 'active' : ''; ?>">
                        <a href="dashboard.php?tab=evaluasi">📈 Evaluasi AI</a>
                    </li>
                </ul>
            </div>
            <div>
                <a href="logout.php" class="btn-logout-sidebar">Logout</a>
            </div>
        </div>

        <ul class="mobile-bottom-nav">
            <li class="nav-item <?php echo $tab == 'dashboard' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=dashboard">📊 Dashboard</a>
            </li>
            <li class="nav-item <?php echo $tab == 'challenge' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=challenge">🏆 Challenge</a>
            </li>
            <li class="nav-item <?php echo $tab == 'evaluasi' ? 'active' : ''; ?>">
                <a href="dashboard.php?tab=evaluasi">📈 Evaluation</a>
            </li>
        </ul>

        <div class="main-content">
            <div class="header-user">
                <div>
                    <h1>Sistem Manajemen Kebiasaan</h1>
                    <span style="font-size: 0.95rem; color: #555;">Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</span>
                </div>
            </div>

            <?php if ($tab == 'dashboard'): ?>
                <div class="progress-card">
                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 0.9rem;">
                        <span>Target Progress Hari Ini</span>
                        <span><?php echo $progress_percent; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $progress_percent; ?>%;"></div>
                    </div>
                </div>

                <form action="dashboard.php?tab=dashboard" method="POST" class="habit-form">
                    <input type="text" name="habit_name" placeholder="Tulis rencana kebiasaan baru..." required>
                    <button type="submit" name="add_habit">Tambah</button>
                </form>

                <h2 style="font-size: 20px; margin-bottom: 5px; text-align:center">Daftar Kebiasaan Kamu</h2>
                <div class="habit-grid">
                    <?php if (count($habits) > 0): ?>
                        <?php foreach ($habits as $h): ?>
                            <div class="habit-card <?php echo $h['status'] == 'Selesai' ? 'done' : ''; ?>">
                                <div>
                                    <h3><?php echo htmlspecialchars($h['habit_name']); ?></h3>
                                    <p style="font-size: 0.8rem; color: #888; margin: 0;">Total Check: <?php echo $h['total_done']; ?>x</p>
                                    
                                    <div class="weekly-dots">
                                        <?php for($i=1; $i<=7; $i++): ?>
                                            <div class="dot <?php echo ($i <= $h['total_done']) ? 'active' : ''; ?>"></div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; gap: 10px; align-items: center; margin-top: 15px;">
                                    <?php if ($h['status'] == 'Belum'): ?>
                                        <a href="dashboard.php?tab=dashboard&toggle_id=<?php echo $h['id']; ?>&status=Belum" class="btn-action btn-success" style="flex: 1;">Checklist</a>
                                    <?php else: ?>
                                        <a href="dashboard.php?tab=dashboard&toggle_id=<?php echo $h['id']; ?>&status=Selesai" class="btn-action btn-warning" style="flex: 1;">Batalkan</a>
                                    <?php endif; ?>
                                    
                                    <a href="dashboard.php?tab=dashboard&delete_id=<?php echo $h['id']; ?>" 
                                       style="text-decoration: none; font-size: 1.1rem; background: #fee2e2; padding: 5px 10px; border-radius: 50%;" 
                                       onclick="return confirm('Yakin ingin menghapus habit ini?')">
                                       🗑️
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="grid-column: 1/-1; text-align: center; color: #888; font-style: italic; margin-top: 20px;">Belum ada target kebiasaan.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'challenge'): ?>
                <div class="progress-card">
                    <h2>🏆 Tantangan Spesial Minggu Ini</h2>
                    <p style="color:#666; margin-top:10px; font-size:0.9rem;">Ikuti tantangan komunitas untuk meningkatkan kedisiplinanmu!</p>
                    <div style="background:#f0fdf4; border-left:4px solid #2ecc71; padding: 15px; margin-top: 20px; border-radius: 4px; font-size: 0.9rem;">
                        <strong>⚡ Pemula Konsisten:</strong> Lakukan checklist minimal 1 habit apa saja selama 3 hari berturut-turut. (Hadiah: Badge Pemula).
                    </div>
                </div>

            <?php elseif ($tab == 'evaluasi'): ?>
                <div class="progress-card">
                    <h2>📈 Laporan Analisis Kebiasaan Otomatis</h2>
                    <p style="color:#666; margin-top: 10px; font-size: 0.9rem;">Berikut rangkuman performa konsistensi akunmu:</p>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr style="background: #1e1e2f; color: white;">
                                    <th>Nama Habit</th>
                                    <th>Frekuensi Mingguan</th>
                                    <th>Analisis AI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($habits as $h): 
                                    $f = $h['total_done'];
                                    if ($f >= 7) { $s = "Selalu"; $c = "#2ecc71"; }
                                    else if ($f >= 5) { $s = "Sering"; $c = "#3498db"; }
                                    else if ($f >= 3) { $s = "Kadang-kadang"; $c = "#f1c40f"; }
                                    else if ($f >= 1) { $s = "Pernah"; $c = "#e67e22"; }
                                    else { $s = "Jarang"; $c = "#e74c3c"; }
                                ?>
                                <tr>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($h['habit_name']); ?></td>
                                    <td><?php echo $f; ?> / 7 Hari</td>
                                    <td style="color: <?php echo $c; ?>; font-weight: bold;"> <?php echo $s; ?> Dilakukan</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>