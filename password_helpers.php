<?php

function app_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function app_verify_password(string $plainPassword, string $storedPassword): bool
{
    if ($storedPassword === '') {
        return false;
    }

    $passwordInfo = password_get_info($storedPassword);
    if (!empty($passwordInfo['algo'])) {
        return password_verify($plainPassword, $storedPassword);
    }

    if (app_verify_wordpress_phpass_password($plainPassword, $storedPassword)) {
        return true;
    }

    return hash_equals($storedPassword, $plainPassword);
}

function app_password_needs_upgrade(string $storedPassword): bool
{
    if ($storedPassword === '') {
        return false;
    }

    $passwordInfo = password_get_info($storedPassword);
    if (!empty($passwordInfo['algo'])) {
        return password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
    }

    return true;
}

function app_verify_wordpress_phpass_password(string $plainPassword, string $storedPassword): bool
{
    if (strlen($storedPassword) !== 34) {
        return false;
    }

    $prefix = substr($storedPassword, 0, 3);
    if ($prefix !== '$P$' && $prefix !== '$H$') {
        return false;
    }

    $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $countLog2 = strpos($itoa64, $storedPassword[3]);
    if (!is_int($countLog2) || $countLog2 < 7 || $countLog2 > 30) {
        return false;
    }

    $salt = substr($storedPassword, 4, 8);
    if (strlen($salt) !== 8) {
        return false;
    }

    $hash = md5($salt . $plainPassword, true);
    $count = 1 << $countLog2;

    do {
        $hash = md5($hash . $plainPassword, true);
    } while (--$count > 0);

    $encoded = substr($storedPassword, 0, 12) . app_encode_wordpress_phpass64($hash, 16, $itoa64);

    return hash_equals($storedPassword, $encoded);
}

function app_encode_wordpress_phpass64(string $input, int $count, string $itoa64): string
{
    $output = '';
    $i = 0;

    do {
        $value = ord($input[$i++]);
        $output .= $itoa64[$value & 0x3f];

        if ($i < $count) {
            $value |= ord($input[$i]) << 8;
        }
        $output .= $itoa64[($value >> 6) & 0x3f];

        if ($i++ >= $count) {
            break;
        }

        if ($i < $count) {
            $value |= ord($input[$i]) << 16;
        }
        $output .= $itoa64[($value >> 12) & 0x3f];

        if ($i++ >= $count) {
            break;
        }

        $output .= $itoa64[($value >> 18) & 0x3f];
    } while ($i < $count);

    return $output;
}
