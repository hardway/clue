<?php
function clue_rpc_encrypt($str, $secret=RPC_SECRET){
    $block=8; // mcrypt_get_block_size('des', 'ecb');
    $pad = $block - (strlen($str) % $block);
    $str .= str_repeat(chr($pad), $pad);

    $l = strlen($secret);
    if ($l < 16) $secret = str_repeat($secret, ceil(16/$l));

    if(extension_loaded("openssl")){
        return base64_encode(
            openssl_encrypt($str, 'BF-ECB', $secret, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING)
        );
    }
    else{
        return base64_encode(mcrypt_encrypt(MCRYPT_BLOWFISH, $secret, $str, MCRYPT_MODE_ECB));
    }
}

function clue_rpc_decrypt($str, $secret=RPC_SECRET){
    $l = strlen($secret);
    if ($l < 16) $secret = str_repeat($secret, ceil(16/$l));

    if(extension_loaded("openssl")){
        $str = openssl_decrypt(
            base64_decode($str), 'BF-ECB', $secret, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING
        );
    }
    else{
        $str = mcrypt_decrypt(MCRYPT_BLOWFISH, $secret, base64_decode($str), MCRYPT_MODE_ECB);
    }

    $pad = ord($str[($len = strlen($str)) - 1]);
    return substr($str, 0, strlen($str) - $pad);
}
