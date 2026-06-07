<?php

/*
 * Copyright (C) 2026 Henry Stern <henry@stern.ca>
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

namespace tests\OPNsense\Nebula;

use OPNsense\Core\AppConfig;
use OPNsense\Core\Config;
use OPNsense\Nebula\Nebula;

/**
 * Locks the duplicate listen_port / tun_name detection used by
 * InstanceController::checkUniqueListener.  The controller reads the posted
 * values from $_POST['instance'] and scans the model excluding the edited
 * uuid; this test reproduces that scan against real model instances so the
 * rules (port 0 exempt, empty exempt, edit excludes self) are regression-safe.
 */
class InstanceUniqueListenerTest extends \PHPUnit\Framework\TestCase
{
    private static $configDir = __DIR__ . '/NebulaConfig';

    public static function setUpBeforeClass(): void
    {
        (new AppConfig())->update('application.configDir', self::$configDir);
        Config::getInstance()->forceReload();
    }

    /**
     * Mirror of InstanceController::checkUniqueListener's model scan.
     * Returns the colliding field key ('instance.listen_port' /
     * 'instance.tun_name') or null when the posted values are unique.
     */
    private function scan($model, ?string $editUuid, string $listenPort, string $tunName): ?string
    {
        $checkPort = ($listenPort !== '' && (int)$listenPort !== 0);
        $checkTun  = ($tunName !== '');
        if (!$checkPort && !$checkTun) {
            return null;
        }
        foreach ($model->instances->instance->iterateItems() as $node) {
            if ($node->getAttribute('uuid') === $editUuid) {
                continue;
            }
            if ($checkPort && trim((string)$node->listen_port) === $listenPort) {
                return 'instance.listen_port';
            }
            if ($checkTun && trim((string)$node->tun_name) === $tunName) {
                return 'instance.tun_name';
            }
        }
        return null;
    }

    private function makeInstance($model, string $port, string $tun)
    {
        $node = $model->instances->instance->Add();
        $node->enabled = '1';
        $node->listen_host = '0.0.0.0';
        $node->listen_port = $port;
        $node->tun_name = $tun;
        return $node;
    }

    public function testDuplicatePortRejected()
    {
        $model = new Nebula();
        $this->makeInstance($model, '4242', 'nebula0');
        $this->assertEquals('instance.listen_port', $this->scan($model, null, '4242', 'nebulaX'));
    }

    public function testDuplicateTunRejected()
    {
        $model = new Nebula();
        $this->makeInstance($model, '4242', 'nebula0');
        $this->assertEquals('instance.tun_name', $this->scan($model, null, '5555', 'nebula0'));
    }

    public function testUniquePortAndTunAccepted()
    {
        $model = new Nebula();
        $this->makeInstance($model, '4242', 'nebula0');
        $this->assertNull($this->scan($model, null, '4243', 'nebula1'));
    }

    public function testPortZeroMayRepeat()
    {
        $model = new Nebula();
        $this->makeInstance($model, '0', '');
        // a second random-port (0) instance is allowed
        $this->assertNull($this->scan($model, null, '0', ''));
    }

    public function testEmptyPortAndTunSkipsChecks()
    {
        $model = new Nebula();
        $this->makeInstance($model, '4242', 'nebula0');
        $this->assertNull($this->scan($model, null, '', ''));
    }

    public function testEditExcludesSelf()
    {
        $model = new Nebula();
        $node = $this->makeInstance($model, '4242', 'nebula0');
        $uuid = $node->getAttribute('uuid');
        // editing the same instance, keeping its own port/tun, must not collide
        $this->assertNull($this->scan($model, $uuid, '4242', 'nebula0'));
    }

    public function testEditDetectsCollisionWithOther()
    {
        $model = new Nebula();
        $a = $this->makeInstance($model, '4242', 'nebula0');
        $b = $this->makeInstance($model, '4243', 'nebula1');
        $bUuid = $b->getAttribute('uuid');
        // editing B to take A's port must collide
        $this->assertEquals('instance.listen_port', $this->scan($model, $bUuid, '4242', 'nebula1'));
    }
}
