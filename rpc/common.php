<?php
function clue_rpc_encrypt($str, $secret=RPC_SECRET){
    $block=8;
    $pad = $block - (strlen($str) % $block);
    $str .= str_repeat(chr($pad), $pad);

    $l = strlen($secret);
    if ($l < 16) $secret = str_repeat($secret, ceil(16/$l));

    if (extension_loaded('mcrypt')) {
        return base64_encode(
            mcrypt_encrypt(MCRYPT_BLOWFISH, $secret, $str, MCRYPT_MODE_ECB)
        );
    }

    if (extension_loaded('openssl')) {
        if (!in_array('BF-ECB', openssl_get_cipher_methods())) {
            throw new RuntimeException(
                'clue_rpc_encrypt: OpenSSL 3.0+ removed BF-ECB (Blowfish). '
                . 'Install mcrypt extension, or configure OpenSSL legacy provider.'
            );
        }
        return base64_encode(
            openssl_encrypt($str, 'BF-ECB', $secret, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING)
        );
    }

    throw new RuntimeException(
        'clue_rpc_encrypt requires mcrypt or openssl extension'
    );
}

function clue_rpc_decrypt($str, $secret=RPC_SECRET){
    $l = strlen($secret);
    if ($l < 16) $secret = str_repeat($secret, ceil(16/$l));

    if (extension_loaded('mcrypt')) {
        $str = mcrypt_decrypt(
            MCRYPT_BLOWFISH, $secret, base64_decode($str), MCRYPT_MODE_ECB
        );
    } elseif (extension_loaded('openssl')) {
        if (!in_array('BF-ECB', openssl_get_cipher_methods())) {
            throw new RuntimeException(
                'clue_rpc_decrypt: OpenSSL 3.0+ removed BF-ECB (Blowfish). '
                . 'Install mcrypt extension, or configure OpenSSL legacy provider.'
            );
        }
        $str = openssl_decrypt(
            base64_decode($str), 'BF-ECB', $secret, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING
        );
    } else {
        throw new RuntimeException(
            'clue_rpc_decrypt requires mcrypt or openssl extension'
        );
    }

    $pad = ord($str[($len = strlen($str)) - 1]);
    return substr($str, 0, strlen($str) - $pad);
}
