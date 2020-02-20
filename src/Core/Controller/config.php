<?php
namespace Controller;
use Classes\db\Adapter as Adapter;

class GetConfig extends Adapter
{
    public function site( $site_url ){
        $result['site'] = [];
        $adapter = new Adapter;
        
        $sql = "
            SELECT * FROM sites s 
                INNER JOIN profiles p ON ( s.site_id = p.site_id )
                INNER JOIN templates t ON ( t.template_id = p.template_id )
            WHERE s.site_url LIKE '%{$site_url}%' AND s.site_status = 1 AND p.profile_status = 1 AND t.template_status = 1;
        ";
        $result['site']['profile'] = $adapter->query( $sql );
        if( haveRows( $result['site']['profile'] ) ){
            $sql = "
            SELECT * FROM menu m 
            WHERE m.site_id = {$result['site']['profile'][0]['site_id']} AND m.menu_status = 1;
            ";
            $result['site']['menu'] = $adapter->query( $sql );
            
            if( haverows( $result['site']['menu'] ) ){
                foreach( $result['site']['menu'] as $menu ){
                    $sql = "
                        SELECT m.*, a.* FROM articles a
                        INNER JOIN menu m ON ( a.menu_id = m.menu_id ) 
                        WHERE a.menu_id = {$menu['menu_id']} AND a.article_status = 1;
                    ";
                    $article = $adapter->query( $sql );
                    if( haveRows( $article ) ){
                        $result['site']['listing'][$menu['menu_slugit']]['articles'] = $article;
                    }
                }
            }
    
            $sql = "
            SELECT * FROM banners b 
            WHERE b.profile_id = {$result['site']['profile'][0]['profile_id']} AND b.banner_status = 1;
            ";
            $result['site']['banners'] = $adapter->query( $sql );
    
            $sql = "
            SELECT * FROM blocks b 
            WHERE b.profile_id = {$result['site']['profile'][0]['profile_id']} AND b.block_status = 1;
            ";
            $result['site']['blocks'] = $adapter->query( $sql );
    
            $sql = "
            SELECT * FROM cards c 
            WHERE c.profile_id = {$result['site']['profile'][0]['profile_id']} AND c.card_status = 1;
            ";
            $result['site']['cards'] = $adapter->query( $sql );
        }
        
        return $result;
    }
}
