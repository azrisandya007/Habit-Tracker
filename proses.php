<?php
session_start();

$host = "127.0.0.1";
$user = "root"; 
$pass = "";     
$db   = "habit_tracker";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// REGISTER
if (isset($_POST['register'])) {
    $username = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $cek_email = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if (mysqli_num_rows($cek_email) > 0) {
        echo "<script>alert('Email sudah terdaftar!'); window.location='index.php';</script>";
    } else {
        $query = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', '$role')";
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Register berhasil! Silakan login.'); window.location='index.php';</script>";
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }
}

// LOGIN
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    
    if (mysqli_num_rows($query) === 1) {
        $row = mysqli_fetch_assoc($query);
        
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            
            echo "<script>alert('Selamat datang, " . $row['username'] . "!'); window.location='dashboard.php';</script>";
        } else {
            echo "<script>alert('Password salah!'); window.location='index.php';</script>";
        }
    } else {
        echo "<script>alert('Email tidak ditemukan!'); window.location='index.php';</script>";
    }
}
?>