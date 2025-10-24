<?php
$password = '123456';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Şifre: " . htmlspecialchars($password) . "<br>";
echo "Hash: <strong>" . htmlspecialchars($hashed_password) . "</strong> <br>";
?>