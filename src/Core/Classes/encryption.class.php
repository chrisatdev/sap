<?php
namespace Cryptography;
use OAuth;
class Encryption
{

	private $default_key;
	private $method = 'RC2-CBC';
	private $hmac_hash = 'sha256';
	private $ivlen;

	function __construct()
	{
		$this->default_key = 'a3Tfh91H3G12k23Y';
		$this->ivlen = openssl_cipher_iv_length($this->method);
	}

	public function encrypt($value, $key = null)
	{

		if (is_null($key)) {
			$key = $this->default_key;
		}

		$iv = openssl_random_pseudo_bytes($this->ivlen);
		$ed = openssl_encrypt($value, $this->method, $key, OPENSSL_RAW_DATA, $iv);
		$hm = hash_hmac($this->hmac_hash, $ed, $key, true);

		return rtrim(strtr(base64_encode($iv . $hm . $ed), '+/', '-_'), '=');

	}

	public function decrypt($value, $key = null)
	{

		if (is_null($key)) {
			$key = $this->default_key;
		}

		$data = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($value)) % 4));
		$iv = substr($data, 0, $this->ivlen);
		$hm = substr($data, $this->ivlen, 32);
		$rd = substr($data, $this->ivlen + 32);

		$dd = openssl_decrypt($rd, $this->method, $key, OPENSSL_RAW_DATA, $iv);
		$cm = hash_hmac($this->hmac_hash, $rd, $key, true);

		return $cm == $hm ? $dd : null;
	}

	public function key(){
		//$client_key = bin2hex(random_bytes(18));
		$client_key = bin2hex(openssl_random_pseudo_bytes(18));
		return $client_key;
	}

	public function secret(){
		//$client_secret = bin2hex(random_bytes(8));
		$client_secret = bin2hex(openssl_random_pseudo_bytes(8));
		return $client_secret;
	}
}