<?php
function clue_rpc_encrypt($str, $secret=RPC_SECRET){
	$block = mcrypt_get_block_size('des', 'ecb');
	$pad = $block - (strlen($str) % $block);
	$str .= str_repeat(chr($pad), $pad);

	return base64_encode(mcrypt_encrypt(MCRYPT_BLOWFISH, $secret, $str, MCRYPT_MODE_ECB));
}

function clue_rpc_decrypt($str, $secret=RPC_SECRET){
	$str = mcrypt_decrypt(MCRYPT_BLOWFISH, $secret, base64_decode($str), MCRYPT_MODE_ECB);

	$block = mcrypt_get_block_size('des', 'ecb');
	$pad = ord($str[($len = strlen($str)) - 1]);
	return substr($str, 0, strlen($str) - $pad);
}
