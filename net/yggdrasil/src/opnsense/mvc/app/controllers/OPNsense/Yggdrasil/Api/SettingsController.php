<?php

/*
 * Copyright (C) 2026 Farzad Sadeghi <devi+github@terminaldweller.com>
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

namespace OPNsense\Yggdrasil\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
  protected static $internalModelName = 'yggdrasil';
  protected static $internalModelClass = '\OPNsense\Yggdrasil\Yggdrasil';

  public function searchPeerAction()
  {
    return $this->searchBase('general.Peers.Peer', ['uri']);
  }

  public function getPeerAction($uuid = null)
  {
    return $this->getBase('peer', 'general.Peers.Peer', $uuid);
  }

  public function addPeerAction()
  {
    return $this->addBase('peer', 'general.Peers.Peer');
  }

  public function setPeerAction($uuid)
  {
    return $this->setBase('peer', 'general.Peers.Peer', $uuid);
  }

  public function delPeerAction($uuid)
  {
    return $this->delBase('general.Peers.Peer', $uuid);
  }

  public function searchInterfacePeerAction()
  {
    return $this->searchBase('general.InterfacePeers.Peer', ['Interface', 'URI']);
  }

  public function getInterfacePeerAction($uuid = null)
  {
    return $this->getBase('interfacepeer', 'general.InterfacePeers.Peer', $uuid);
  }

  public function addInterfacePeerAction()
  {
    return $this->addBase('interfacepeer', 'general.InterfacePeers.Peer');
  }

  public function setInterfacePeerAction($uuid)
  {
    return $this->setBase('interfacepeer', 'general.InterfacePeers.Peer', $uuid);
  }

  public function delInterfacePeerAction($uuid)
  {
    return $this->delBase('general.InterfacePeers.Peer', $uuid);
  }

  public function searchListenerAction()
  {
    return $this->searchBase('general.Listen.Listener', ['URI']);
  }

  public function getListenerAction($uuid = null)
  {
    return $this->getBase('listener', 'general.Listen.Listener', $uuid);
  }

  public function addListenerAction()
  {
    return $this->addBase('listener', 'general.Listen.Listener');
  }

  public function setListenerAction($uuid)
  {
    return $this->setBase('listener', 'general.Listen.Listener', $uuid);
  }

  public function delListenerAction($uuid)
  {
    return $this->delBase('general.Listen.Listener', $uuid);
  }

  public function searchMulticastInterfaceAction()
  {
    return $this->searchBase('general.MulticastInterfaces.Interface', ['Regex', 'Beacon', 'Listen', 'Port', 'Priority']);
  }

  public function getMulticastInterfaceAction($uuid = null)
  {
    return $this->getBase('multicastinterface', 'general.MulticastInterfaces.Interface', $uuid);
  }

  public function addMulticastInterfaceAction()
  {
    return $this->addBase('multicastinterface', 'general.MulticastInterfaces.Interface');
  }

  public function setMulticastInterfaceAction($uuid)
  {
    return $this->setBase('multicastinterface', 'general.MulticastInterfaces.Interface', $uuid);
  }

  public function delMulticastInterfaceAction($uuid)
  {
    return $this->delBase('general.MulticastInterfaces.Interface', $uuid);
  }

  public function searchAllowedPublicKeyAction()
  {
    return $this->searchBase('general.AllowedPublicKeys.AllowedPublicKey', ['PublicKey']);
  }

  public function getAllowedPublicKeyAction($uuid = null)
  {
    return $this->getBase('allowedpublickey', 'general.AllowedPublicKeys.AllowedPublicKey', $uuid);
  }

  public function addAllowedPublicKeyAction()
  {
    return $this->addBase('allowedpublickey', 'general.AllowedPublicKeys.AllowedPublicKey');
  }

  public function setAllowedPublicKeyAction($uuid)
  {
    return $this->setBase('allowedpublickey', 'general.AllowedPublicKeys.AllowedPublicKey', $uuid);
  }

  public function delAllowedPublicKeyAction($uuid)
  {
    return $this->delBase('general.AllowedPublicKeys.AllowedPublicKey', $uuid);
  }
}
