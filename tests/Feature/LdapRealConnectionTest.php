<?php

namespace Tests\Feature;

use App\Ldap\User as LdapUser;
use LdapRecord\Container;
use Tests\TestCase;

class LdapRealConnectionTest extends TestCase
{
    /**
     * @group integration
     * @group ldap
     */
    public function test_can_connect_to_real_ldap_server()
    {
        // 実際のLDAP接続を取得
        // phpunit.xml で設定された LDAP_HOST=openldap などが使用される
        try {
            $connection = Container::getConnection('default');
            $connection->connect();
            
            $this->assertTrue($connection->isConnected(), 'Failed to connect to the LDAP server.');
        } catch (\LdapRecord\Auth\BindException $e) {
            $this->fail('Failed to bind to LDAP server: ' . $e->getMessage());
        } catch (\LdapRecord\ConnectionException $e) {
            $this->fail('Could not connect to LDAP host. Ensure the openldap container is running. Error: ' . $e->getMessage());
        }
    }

    /**
     * @group integration
     * @group ldap
     */
    public function test_can_search_users_in_real_ldap_server()
    {
        // 接続確認
        try {
            Container::getConnection('default')->connect();
        } catch (\Exception $e) {
            $this->markTestSkipped('LDAP server is not available: ' . $e->getMessage());
        }

        // Adminユーザーが存在することを確認 (docker-composeで設定した管理者)
        $adminDn = config('ldap.connections.default.username');
        
        // 低レベル検索を実行して接続と検索権限を確認
        $results = Container::getConnection('default')->query()->where('cn', 'admin')->get();
        
        // rroemhild/test-openldap の仕様により、adminユーザーのCNや属性は構成によるが、
        // 少なくともバインドユーザー自身は検索できるはず
        // ここでは単純にエラーなくクエリが実行できることを確認するだけでも十分だが、
        // 結果が返ってくるかを確認する。
        
        // BaseDN直下のオブジェクトを検索してみる
        $results = Container::getConnection('default')->query()->limit(1)->get();
        
        $this->assertNotEmpty($results, 'LDAP search returned no results.');
    }
}
