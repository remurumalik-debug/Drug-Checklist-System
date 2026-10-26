<?php
/**
 * Password Hash Generator
 * Run this script to generate proper bcrypt hashes for user passwords.
 * Usage: php generate_hashes.php
 */

$passwords = [
    'Admin123!',
    'Pharm123!',
    'Staff123!',
];

echo "Password Hash Generator\n";
echo "======================\n\n";

foreach ($passwords as $pw) {
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    echo "Password: {$pw}\n";
    echo "Hash:     {$hash}\n\n";
}

echo "Copy these hashes into your seed.sql file.\n";
echo "===========================================\n";
