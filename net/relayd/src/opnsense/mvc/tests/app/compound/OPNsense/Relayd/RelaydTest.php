<?php

/*
 * Copyright (C) 2018 EURO-LOG AG
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace tests\OPNsense\Relayd\Api;

use OPNsense\Core\Config;

class RelaydTest extends \PHPUnit\Framework\TestCase
{
    /**
     * list with model node types
     */
    private $nodeTypes = array('host', 'tablecheck', 'table', 'protocol', 'virtualserver');

    // holds the SettingsController object
    protected static $setRelayd;

    public static function setUpBeforeClass(): void
    {
        self::$setRelayd = new \OPNsense\Relayd\Api\SettingsController();
    }

    private function cleanupNodes($nodeType = null)
    {
        $nodes = self::$setRelayd->mdlRelayd->$nodeType->getNodes();
        foreach ($nodes as $nodeUuid => $node) {
            self::$setRelayd->mdlRelayd->$nodeType->del($nodeUuid);
        }
    }

    /**
     * test getAction
     */
    public function testGet()
    {
        $this->assertInstanceOf('\OPNsense\Relayd\Api\SettingsController', self::$setRelayd);
        $this->expectException(\Exception::class);
        $response = self::$setRelayd->getAction('wrong_node_type');
        $testConfig = [];
        $response = self::$setRelayd->getAction('general');
        $testConfig['general'] = $response['relayd']['general'];

        $this->assertEquals($response['status'], 'ok');
        $this->assertArrayHasKey('enabled', $response['relayd']['general']);

        return $testConfig;
    }

    /**
     * test searchAction
     * @depends testGet
     */
    public function testSearch($testConfig)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array('current' => '1', 'rowCount' => '7');

        foreach ($this->nodeTypes as $nodeType) {
            $response = self::$setRelayd->searchAction($nodeType);
            $this->assertArrayHasKey('total', $response);
            $testConfig[$nodeType] = $response['rows'];
        }

        return $testConfig;
    }

    /**
     * test delAction
     * not really a test if the config is empty, but we will delete something later
     * @depends testSearch
     */
    public function testReset($testConfig)
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        foreach (array_reverse($this->nodeTypes) as $nodeType) {
            foreach ($testConfig[$nodeType] as $node) {
                $response = self::$setRelayd->delAction($nodeType, $node['uuid']);
                $this->assertEquals($response['status'], 'ok');
            }
        }
        // need an assertion here to succeed this test on empty config
        $this->assertTrue(true);
    }

    /**
     * test setAction general
     * @depends testReset
     */
    public function testSetGeneral()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // interval too small
        $_POST = array('relayd' => ['general' => ['interval' => '0']]);
        $response = self::$setRelayd->setAction('general');
        $this->assertCount(1, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.general.interval']);

        // set correct interval and incorrect timeout (s. testServiceController)
        $_POST = array('relayd' => ['general' => ['interval' => '10', 'timeout' => 86400, 'enabled' => '0']]);
        $response = self::$setRelayd->setAction('general');
        $this->assertEquals($response['status'], 'ok');
    }

    /**
     * test dirtyAction
     * @depends testSetGeneral
     */
    public function testDirtyAction()
    {
        $this->assertInstanceOf('\OPNsense\Relayd\Api\SettingsController', self::$setRelayd);
        $response = self::$setRelayd->dirtyAction();
        $this->assertEquals($response['status'], 'ok');
        $this->assertEquals($response['relayd']['dirty'], true);
    }

    /**
     * test setAction for hosts
     * @depends testReset
     */
    public function testSetHost()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // empty host name
        $_POST = array('relayd' => ['host' => ['address' => '127.0.0.1']]);
        $response = self::$setRelayd->setAction('host');
        $this->assertCount(1, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.host.name']);
        $this->cleanupNodes('host');

        // check mask
        $_POST = array('relayd' => ['host' => ['name' => 'test$Host', 'address' => '127.0.0.$']]);
        $response = self::$setRelayd->setAction('host');
        $this->assertCount(2, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.host.name']);
        $this->assertNotEmpty($response['validations']['relayd.host.address']);
        $this->cleanupNodes('host');

        // create host for ServiceControllerTest
        $_POST = array('relayd' => ['host' => ['name' => 'testHost', 'address' => '127.0.0.1']]);
        $response = self::$setRelayd->setAction('host');
        $this->assertEquals($response['status'], 'ok');
    }

    /**
     * test setAction for tables
     * @depends testSetHost
     */
    public function testSetTable()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // check mask and missing host
        $_POST = array('relayd' => ['table' => ['name' => 'test$Table', 'hosts' => 'aaa-111-bbb-222']]);
        $response = self::$setRelayd->setAction('table');
        $this->assertCount(2, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.table.name']);
        $this->assertNotEmpty($response['validations']['relayd.table.hosts']);
        $this->cleanupNodes('table');

        // create table for ServiceControllerTest
        $_POST = array('current' => '1', 'rowCount' => '7', 'searchPhrase' => 'testHost');
        $response = self::$setRelayd->searchAction('host');
        $this->assertArrayHasKey('total', $response);
        $_POST = array('relayd' => [
            'table' => ['name' => 'testTable', 'enabled' => 1, 'hosts' => $response['rows'][0]['uuid']]
        ]);
        $response = self::$setRelayd->setAction('table');
    }

    /**
     * test setAction for tablechecks
     * @depends testSearch
     * @depends testReset
     */
    public function testSetTableCheck()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // wrong option
        $_POST = array('relayd' => ['tablecheck' => ['name' => 'test$Check', 'type' => 'ABCXYZ']]);
        $response = self::$setRelayd->setAction('tablecheck');
        $this->assertCount(2, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.tablecheck.name']);
        $this->assertNotEmpty($response['validations']['relayd.tablecheck.type']);
        $this->cleanupNodes('tablecheck');

        // type 'send' without 'expect'
        $_POST = array('relayd' => ['tablecheck' => ['name' => 'testSend', 'type' => 'send']]);
        $response = self::$setRelayd->setAction('tablecheck');
        $this->assertCount(1, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.tablecheck.expect']);
        $this->cleanupNodes('tablecheck');

        // type 'script' without 'path'
        $_POST = array('relayd' => ['tablecheck' => ['name' => 'testScript', 'type' => 'script']]);
        $response = self::$setRelayd->setAction('tablecheck');
        $this->assertCount(1, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.tablecheck.path']);
        $this->cleanupNodes('tablecheck');

        // type 'http' without 'code' and 'digest'
        $_POST = array('relayd' => [
            'tablecheck' => ['name' => 'testTableCheck', 'type' => 'http', 'path' => 'http://www.example.com']
        ]);
        $response = self::$setRelayd->setAction('tablecheck');
        $this->assertCount(2, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.tablecheck.code']);
        $this->assertNotEmpty($response['validations']['relayd.tablecheck.digest']);
        $this->cleanupNodes('tablecheck');

        // create tablecheck for ServiceControllerTest
        $_POST = array('relayd' => [
            'tablecheck' => [
                'name' => 'testTableCheck',
                'type' => 'http',
                'path' => '/',
                'host' => 'localhost',
                'code' => '403',
                'ssl' => '1']]);
        $response = self::$setRelayd->setAction('tablecheck');
        $this->assertEquals($response['status'], 'ok');
    }

    /**
     * test setAction for protocols
     * @depends testSearch
     * @depends testReset
     */
    public function testSetProtocol()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // missing 'name' wrong 'type'
        $_POST = array('relayd' => ['protocol' => ['name' => 'test$Protocol', 'type' => 'ABCXYZ']]);
        $response = self::$setRelayd->setAction('protocol');
        $this->assertCount(2, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.protocol.name']);
        $this->assertNotEmpty($response['validations']['relayd.protocol.type']);
        $this->cleanupNodes('protocol');

        // create protocol for ServiceControllerTest
        $_POST = array('relayd' => [
            'protocol' => ['name' => 'testProtocol', 'type' => 'tcp', 'options' => 'nodelay, socket buffer 65536']
        ]);
        $response = self::$setRelayd->setAction('protocol');
        $this->assertEquals($response['status'], 'ok');
    }

    /**
     * test setAction for virtualservers
     * @depends testSearch
     * @depends testReset
     */
    public function testSetVirtualServer()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // search table and tablecheck
        $_POST = array('current' => '1', 'rowCount' => '7', 'searchPhrase' => 'testTable');
        $response = self::$setRelayd->searchAction('table');
        $this->assertArrayHasKey('total', $response);
        $tableUuid = $response['rows'][0]['uuid'];
        $_POST = array('current' => '1', 'rowCount' => '7', 'searchPhrase' => 'testTableCheck');
        $response = self::$setRelayd->searchAction('tablecheck');
        $this->assertArrayHasKey('total', $response);
        $tableCheckUuid = $response['rows'][0]['uuid'];
        $_POST = array('current' => '1', 'rowCount' => '7', 'searchPhrase' => 'testProtocol');
        $response = self::$setRelayd->searchAction('protocol');
        $this->assertArrayHasKey('total', $response);
        $protocolUuid = $response['rows'][0]['uuid'];

        // check mask, misisng table, tablecheck, wrong/missing listen port/address
        $_POST = array('relayd' => [
            'virtualserver' => [
                'name' => 'test{}VirtualServer',
                'listen_startport' => '123456',
            ]]);
        $response = self::$setRelayd->setAction('virtualserver');
        $this->assertCount(5, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.name']);
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.listen_address']);
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.listen_startport']);
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.transport_table']);
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.transport_tablecheck']);
        $this->cleanupNodes('virtualserver');

        // wrong tablemodes, missing ModelRelationField targets
        $_POST = array('relayd' => [
            'virtualserver' => [
                'name' => 'testVirtualServer',
                'listen_address' => '127.0.0.1',
                'listen_startport' => '444',
                'transport_table' => $tableUuid,
                'transport_tablemode' => 'least-states',
                'transport_tablecheck' => $tableCheckUuid,
            ]]);
        $response = self::$setRelayd->setAction('virtualserver');
        $this->assertCount(1, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.transport_tablemode']);
        $this->cleanupNodes('virtualserver');

        // wron scheduler, missing protocol
        $_POST = array('relayd' => [
            'virtualserver' => [
                'name' => 'testVirtualServer',
                'type' => 'redirect',
                'listen_address' => '127.0.0.1',
                'listen_startport' => '444',
                'transport_table' => $tableUuid,
                'transport_tablemode' => 'least-states',
                'transport_tablecheck' => $tableCheckUuid,
                'backuptransport_table' => $tableUuid,
                'backuptransport_tablemode' => 'random',
                'backuptransport_tablecheck' => $tableCheckUuid,
                'protocol' => 'aaa-bbb-123-456'
            ]]);
        $response = self::$setRelayd->setAction('virtualserver');
        $this->assertCount(2, $response['validations']);
        $this->assertEquals($response['result'], 'failed');
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.backuptransport_tablemode']);
        $this->assertNotEmpty($response['validations']['relayd.virtualserver.protocol']);
        $this->cleanupNodes('virtualserver');

        // create virtualserver for ServiceControllerTest
        $_POST = array('relayd' => [
            'virtualserver' => [
                'name' => 'testVirtualServer',
                'enabled' => '1',
                'listen_address' => '127.0.0.1',
                'listen_startport' => '444',
                'transport_table' => $tableUuid,
                'transport_port' => '443',
                'transport_tablecheck' => $tableCheckUuid,
                'protocol' => $protocolUuid
            ]]);
        $response = self::$setRelayd->setAction('virtualserver');
        $this->assertEquals($response['status'], 'ok');
    }

    /**
     * ServiceControllerTest
     * @depends testSetGeneral
     * @depends testSetHost
     * @depends testSetTable
     * @depends testSetTableCheck
     * @depends testSetProtocol
     * @depends testSetVirtualServer
     */
    public function testServiceController()
    {
        $svcRelayd = new \OPNsense\Relayd\Api\ServiceController();
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // stop possibly running service
        $response = $svcRelayd->stopAction();
        $this->assertEquals($response['response'], "OK\n\n");

        // generate template and test it by Relayd
        $response = $svcRelayd->configtestAction();
        $this->assertEquals($response['template'], 'OK');
        $this->assertEquals(
            $response['result'],
            "global timeout exceeds interval\ntable timeout exceeds interval: testTable:443"
        );
        $_POST = array('relayd' => ['general' => ['timeout' => '200']]);
        $response = self::$setRelayd->setAction('general');
        $this->assertEquals($response['status'], 'ok');
        $response = $svcRelayd->configtestAction();
        $this->assertEquals($response['template'], 'OK');
        $this->assertEquals($response['result'], 'configuration OK');

        // status
        $response = $svcRelayd->statusAction();
        $this->assertEquals($response['status'], 'disabled');

        // enable
        $_POST = array('relayd' => ['general' => ['enabled' => '1']]);
        $response = self::$setRelayd->setAction('general');
        $this->assertEquals($response['status'], 'ok');

        // reconfigure
        $response = $svcRelayd->reconfigureAction();
        $this->assertEquals($response['status'], 'ok');

        // status
        $response = $svcRelayd->statusAction();
        $this->assertEquals($response['status'], 'running');
    }

    /**
     * StatusControllerTest
     * @depends testServiceController
     */
    public function testStatusController()
    {
        $statRelayd = new \OPNsense\Relayd\Api\StatusController();
        $response = $statRelayd->sumAction();
        $this->assertEquals($response['result'], 'ok');
        $this->assertEquals($response['rows'][0]['type'], 'relay');
        $this->assertEquals($response['rows'][0]['name'], 'testVirtualServer');
        $this->assertEquals($response['rows'][0]['tables'][1]['name'], 'testTable:443');
        $this->assertEquals($response['rows'][0]['tables'][1]['status'], 'active (1 hosts)');
        $this->assertEquals($response['rows'][0]['tables'][1]['hosts'][1]['name'], '127.0.0.1');

        $response = $statRelayd->toggleAction('table', 1, 'disable');
        $this->assertEquals($response['result'], 'ok');
        $this->assertEquals($response['output'], 'command succeeded');
    }

    /**
     * cleanup config
     * @depends testStatusController
     */
    public function testCleanup()
    {
        $svcRelayd = new \OPNsense\Relayd\Api\ServiceController();
        $response = $svcRelayd->stopAction();
        $this->assertEquals($response['response'], "OK\n\n");

        foreach (array_reverse($this->nodeTypes) as $nodeType) {
            $this->cleanupNodes($nodeType);
        }

        $general = self::$setRelayd->mdlRelayd->getNodeByReference('general');
        $general->setNodes(array('enabled' => '0'));

        self::$setRelayd->mdlRelayd->serializeToConfig();
        Config::getInstance()->save();
        $this->assertTrue(true);
    }
}
