<?php
namespace Certificate;
use Cryptography\Encryption as Cryptography;
use DI\ContainerBuilder;

/**
 * Basic credentials manager
 * Pub: VSDuaBnWMBnoUORXYPJBhTAg5ffVl68y
 * Priv: NG0fVD6bIdd5DN9G
 */

class Credentials extends \AbstractionModel
{

    var $expired = 0;
    
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

        $public_key     = uniqcode($minLen=32,$maxLen=32,false);
        $private_key    = uniqcode($minLen=16,$maxLen=16,false);

        return [
            'public_key'    => $public_key,
            'private_key'   => $private_key
        ];
    }

    public function generate_access_token( $public_key, $private_key ){
        return base64_encode("{$public_key}:{$private_key}");
    }

    public function decode_access_token( $access_token ){
        $this->access_token = base64_decode("{$access_token}");
    }

    public function get_decode_access_token(){
        return $this->access_token;
    }

    public function save_credentials( $account ){

        $generate_keys = $this->generate_keys();

        $find_credential = $this->find( "credential_id", $account );

        $data = [
            'credential_public_key' => $generate_keys['public_key'],
            'credential_private_key'=> $generate_keys['private_key'],
            'credential_expired'    => $this->expired
        ];

        if( haveRows( $find_credential ) ){

            $this->update( $data, "credential_id = {$find_credential[0]['credential_id']}" );

        }else{

            $this->insert( $data );

        }

        $find_credential = $this->find( "credential_id", $account );

        return [
            'credential_id'             => $find_credential[0]['credential_id'],
            'credential_public_key'     => $find_credential[0]['credential_public_key'],
            'credential_private_key'    => $find_credential[0]['credential_private_key']
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
        
        $find_credential = [];

        if( !$this->is_enabled ){
            return true;
        }

        if( empty( $access_token ) ){
            return false;
        }

        $token = haveRows( $access_token ) ? $access_token[0] : $access_token;

        $token = str_replace("Bearer ","", $token);

        if( strlen( $token ) > 60 ){
            
            $this->decode_access_token( $token );
            
            $decode_token = $this->get_decode_access_token();

            list( $public_key, $private_key ) = explode( ':',$decode_token );
            
            $find_credential = $this->find( "credential_public_key", $public_key, "credential_private_key='{$private_key}'" );
        }
        
        if( haveRows( $find_credential ) ){

            return true;

        }else{

            return false;

        }

    }

    public function bad_credentials_message( $statusCode = 401, $message = "Invalid credentials" ){
        return [
            'statusCode'=>$statusCode,
            'message'=> $statusCode == 401 ? 'Unauthorized' : 'Bad request',
            'error'=>$message
        ];
    }

    public function get_credentials( $account_id ){
        
        $find_credential = $this->find( "credential_id", $account_id );
        
        if( haveRows( $find_credential ) ){

            return $find_credential;

        }else{

            return false;

        }
    }

    public function setting_credentials(){
        $sql = "
        CREATE TABLE `credentials` (
        `credential_id` int(11) NOT NULL AUTO_INCREMENT,
        `credential_public_key` varchar(32) NOT NULL,
        `credential_private_key` varchar(32) NOT NULL,
        `credential_expired` varchar(60) NOT NULL DEFAULT '0',
        PRIMARY KEY (`credential_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        $r = $this->create( $sql );

        $r = json_decode( $r, true );

        if( !empty( $r["queryString"] ) ){

            $keys = $this->generate_keys();
            
            $sql = "INSERT INTO credentials (credential_public_key,credential_private_key) VALUES ('{$keys['public_key']}','{$keys['private_key']}');";
            
            $id = $this->create( $sql, "insert" );

            return $id;
        }else{
            return false;
        }

    }
}