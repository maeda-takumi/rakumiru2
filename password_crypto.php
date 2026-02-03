<?php
declare(strict_types=1);

function getPasswordEncryptionKey(): string {
  $key = '';
  if (defined('PASSWORD_ENCRYPTION_KEY')) {
    $key = (string) PASSWORD_ENCRYPTION_KEY;
  }
  if ($key === '') {
    $envKey = getenv('PASSWORD_ENCRYPTION_KEY');
    if ($envKey !== false) {
      $key = (string) $envKey;
    }
  }
  if ($key === '') {
    throw new RuntimeException('PASSWORD_ENCRYPTION_KEY is not configured.');
  }
  return hash('sha256', $key, true);
}

function encryptPassword(string $plainText): string {
  $key = getPasswordEncryptionKey();
  $cipher = 'aes-256-cbc';
  $ivLength = openssl_cipher_iv_length($cipher);
  $iv = random_bytes($ivLength);
  $cipherText = openssl_encrypt($plainText, $cipher, $key, OPENSSL_RAW_DATA, $iv);
  if ($cipherText === false) {
    throw new RuntimeException('Password encryption failed.');
  }
  $hmac = hash_hmac('sha256', $iv . $cipherText, $key, true);
  return base64_encode($iv . $hmac . $cipherText);
}

function decryptPassword(?string $encryptedText): ?string {
  if ($encryptedText === null || $encryptedText === '') {
    return null;
  }
  $key = getPasswordEncryptionKey();
  $cipher = 'aes-256-cbc';
  $ivLength = openssl_cipher_iv_length($cipher);
  $hmacLength = 32;
  $decoded = base64_decode($encryptedText, true);
  if ($decoded === false || strlen($decoded) < $ivLength + $hmacLength) {
    return null;
  }
  $iv = substr($decoded, 0, $ivLength);
  $hmac = substr($decoded, $ivLength, $hmacLength);
  $cipherText = substr($decoded, $ivLength + $hmacLength);
  $calculatedHmac = hash_hmac('sha256', $iv . $cipherText, $key, true);
  if (!hash_equals($hmac, $calculatedHmac)) {
    return null;
  }
  $plainText = openssl_decrypt($cipherText, $cipher, $key, OPENSSL_RAW_DATA, $iv);
  return $plainText === false ? null : $plainText;
}
