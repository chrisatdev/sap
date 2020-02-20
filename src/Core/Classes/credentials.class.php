<?php
namespace Certificate;
use Cryptography\Encryption as Cryptography;
use DI\ContainerBuilder;

class Credentials extends \AbstractionModel
{

    var $owner = "";

    var $info = "";

    var $expired = 0;

    private $pub_k_long = 24;
    
    private $pri_k_long = 48;
    
    private $access_token = "";

    private $crypto = "";

    private $is_enabled = false;
    
    function __construct(){
        $this->crypto       = new Cryptography;
        $this->table        = "credentials";

        $containerBuilder   = new ContainerBuilder();
		$settings           = require __DIR__ . '/../../../app/settings.php';
		$settings($containerBuilder);
		$container 		    = $containerBuilder->build();
        $authEnabled	    = $container->get('settings')['authEnabled'];
        $this->is_enabled   = $authEnabled;

    }

    public function generate_keys(){
        
        $phrase     = "{$this->owner}|{$this->info}";

        $encrypt    = $this->crypto->encrypt( $phrase );

        $public_key     = substr( $encrypt, 0, $this->pub_k_long );
        $private_key    = substr( $encrypt, $this->pub_k_long, $this->pri_k_long );
        $secret_key     = substr( $encrypt, $this->pub_k_long + $this->pri_k_long, strlen( $encrypt ) );

        return [
            'public_key'    => $public_key,
            'private_key'   => $private_key,
            'secret_key'    => $secret_key
        ];
    }

    public function generate_access_token( $public_key, $private_key ){
        return base64_encode("{$public_key}:{$private_key}");
    }

    public function decode_keys( $public_key, $private_key, $secret_key ){
        $phrase     = "{$public_key}{$private_key}{$secret_key}";
        $decrypt    = $this->crypto->decrypt( $phrase );
        return $decrypt;
    }

    public function decode_access_token( $access_token ){
        $this->access_token = base64_decode("{$access_token}");
    }

    public function get_decode_access_token(){
        return $this->access_token;
    }

    public function save_credentials( $account ){

        $generate_keys = $this->generate_keys();

        $access_token = $this->generate_access_token( $generate_keys['public_key'], $generate_keys['private_key'] );

        $find_credential = $this->find( "site_id", $account );

        $data = [
            'site_id'               => $account,
            'credential_public_key' => $generate_keys['public_key'],
            'credential_private_key'=> $generate_keys['private_key'],
            'credential_secret_key' => $generate_keys['secret_key'],
            'credential_expired'    => $this->expired,
            'credential_access_token'=> $access_token
        ];

        if( haveRows( $find_credential ) ){

            $this->update( $data, "credential_id = {$find_credential[0]['credential_id']}" );

        }else{

            $this->insert( $data );

        }

        $find_credential = $this->find( "site_id", $account );

        return [
            'credential_id'             => $find_credential[0]['credential_id'],
            'site_id'                   => $find_credential[0]['site_id'],
            'credential_public_key'     => $find_credential[0]['credential_public_key'],
            'credential_private_key'    => $find_credential[0]['credential_private_key'],
            'credential_access_token'   => $find_credential[0]['credential_access_token']
        ];
    }

    public function chenge_expired( $credencial_id, $expired ){
        
        $data = [
            'credential_expired'    => $this->expired
        ];

        $this->update( $data, "credential_id = {$credential_id}" );

        $find_credential = $this->find( "credential_id", $credential_id );

        return $find_credential[0];
    }

    public function valid_access_token( $access_token = "" ){

        if( !$this->is_enabled ){
            return true;
        }

        if( empty( $access_token ) ){
            return false;
        }

        $access_token = haveRows( $access_token ) ? $access_token[0] : $access_token;
        $access_token = str_replace("Bearer ","", $access_token);
        
        $find_credential = $this->find( "credential_access_token", $access_token );
        
        if( haveRows( $find_credential ) ){

            $phrase = $this->decode_keys( $find_credential[0]['credential_public_key'], $find_credential[0]['credential_private_key'], $find_credential[0]['credential_secret_key'] );

            if( !empty( $phrase ) ){

                return true;

            }else{

                return false;

            }

        }else{

            return false;

        }

    }

    public function bad_credentials_message( $statusCode = 401, $message = "Invalid credentials" ){
        return [
            'statusCode'=>$statusCode,
            'message'=>'Unauthorized',
            'error'=>$message
        ];
    }
}
