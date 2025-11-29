<?php

namespace Tests\Feature;

use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LdapRealConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        // 他のテストでのエミュレータ設定が残らないようにクリーンアップ
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    #[Group("ldap")]
    #[Group("integration")]
    #[Test]
    public function test_can_connect_to_real_ldap_server()
    {
        // エミュレータが誤ってセットアップされていないことを確認
        try {
            $connection = Container::getConnection('default');
            if ($connection instanceof \LdapRecord\Testing\LdapFake) {
                 DirectoryEmulator::tearDown();
            }
        } catch (\Exception $e) {
            // Connection might not exist yet, which is fine
        }

        try {
            $connection = Container::getConnection('default');
            
            // 接続設定がテスト用コンテナを向いているか確認 (ローカル開発環境保護のため)
            $config = $connection->getConfiguration()->all();
            if ($config['hosts'][0] !== 'openldap' && $config['hosts'][0] !== '127.0.0.1') {
                 $this->markTestSkipped('Skipping real LDAP test: Host is not openldap or localhost.');
            }

            $connection->connect();
            
            $this->assertTrue($connection->isConnected(), 'Failed to connect to the LDAP server.');
        } catch (\LdapRecord\Auth\BindException $e) {
            $this->fail('Failed to bind to LDAP server. Error: ' . $e->getMessage());
        } catch (\LdapRecord\ConnectionException $e) {
             // コンテナが起動していない、またはネットワークの問題がある場合はテストをスキップする
            $this->fail('Could not connect to LDAP host. Error: ' . $e->getMessage());
        }
    }

    #[Group("integration")]
    #[Group("ldap")]
    #[Test]
    public function test_can_search_root_dse_in_real_ldap_server()
    {
        // 接続確認 (前のテストが失敗していればここも失敗するが、念のため)
        try {
            $connection = Container::getConnection('default');
            $connection->connect();
        } catch (\Exception $e) {
            $this->markTestSkipped('LDAP server is not available: ' . $e->getMessage());
        }

        // Root DSE (ディレクトリサーバ自体の情報) は認証なしでも（設定によるが）またはBindユーザーなら確実に読めるはず
        // LdapRecordでRoot DSEを取得
        try {
            $rootDse = $connection->query()->read()->first();
            $this->assertNotNull($rootDse, 'Could not retrieve Root DSE from LDAP server.');
        } catch (\Exception $e) {
             $this->fail('LDAP search failed: ' . $e->getMessage());
        }
        
        // Base DN のオブジェクトが存在するか確認
        $baseDn = config('ldap.connections.default.base_dn');
        try {
             $entry = $connection->query()->in($baseDn)->read()->first();
             $this->assertNotNull($entry, "Could not find entry at Base DN: $baseDn");
        } catch (\Exception $e) {
            $this->fail("Search at Base DN ($baseDn) failed: " . $e->getMessage());
        }
    }
}
