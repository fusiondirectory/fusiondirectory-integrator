<?php
/*
  This code is part of FusionDirectory (https://www.fusiondirectory.org/)

  Copyright (C) 2020-2021 FusionDirectory

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
*/

namespace FusionDirectory\Cli;

use Exception;
use SodiumException;
use function random_bytes;
use function sodium_base642bin;
use function sodium_bin2base64;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_keygen;
use function sodium_crypto_secretbox_open;
use function sodium_memzero;
use function sodium_pad;
use function sodium_unpad;

class SecretBox
{
  /**
   * Get a secret key for encrypt/decrypt
   *
   * Use libsodium to generate a secret key.  This should be kept secure.
   *
   * @see encrypt(), decrypt()
   */
  public static function generateSecretKey (): string
  {
    return sodium_crypto_secretbox_keygen();
  }

  /**
   * Encrypt a message
   *
   * Use libsodium to encrypt a string
   *
   * @param string $message - message to encrypt
   * @param string $secret_key - encryption key
   * @param int $block_size - pad the message by $block_size byte chunks to conceal encrypted data size. must match between encrypt/decrypt!
   * @throws SodiumException|\Random\RandomException
   * @see https://github.com/jedisct1/libsodium/issues/392
   * @see decrypt()
   */
  public static function encrypt (string $message, string $secret_key, int $block_size = 1): string
  {
    /* Create a nonce for this operation. it will be stored and recovered in the message itself */
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    /* Pad to $block_size byte chunks (enforce 512 byte limit) */
    $padded_message = sodium_pad($message, min($block_size, 512));

    /* Encrypt message and combine with nonce */
    $cipher = sodium_bin2base64($nonce . sodium_crypto_secretbox($padded_message, $nonce, $secret_key), SODIUM_BASE64_VARIANT_ORIGINAL);

    /* Cleanup */
    sodium_memzero($message);
    sodium_memzero($secret_key);

    return $cipher;
  }

  /**
   * Decrypt a message
   *
   * Use libsodium to decrypt an encrypted string
   *
   * @param int $block_size - pad the message by $block_size byte chunks to conceal encrypted data size. must match between encrypt/decrypt!
   * @throws SodiumException
   * @throws Exception
   * @see https://github.com/jedisct1/libsodium/issues/392
   * @see encrypt()
   */
  public static function decrypt (string $encrypted, string $secret_key, int $block_size = 1): string
  {
    /* Unpack base64 message */
    $decoded = sodium_base642bin($encrypted, SODIUM_BASE64_VARIANT_ORIGINAL);

    if (mb_strlen($decoded, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
      throw new Exception('The message was truncated');
    }

    /* Pull nonce and ciphertext out of unpacked message */
    $nonce      = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
    $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, NULL, '8bit');

    /* Decrypt it and account for extra padding from $block_size (enforce 512 byte limit) */
    $decrypted_padded_message = sodium_crypto_secretbox_open($ciphertext, $nonce, $secret_key);

    /* Check for encryption failures */
    if ($decrypted_padded_message === FALSE) {
      throw new Exception('The message was tampered with in transit');
    }

    $message = sodium_unpad($decrypted_padded_message, min($block_size, 512));

    /* Cleanup */
    sodium_memzero($encrypted);
    sodium_memzero($secret_key);

    return $message;
  }
}
