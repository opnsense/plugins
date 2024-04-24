<?php

/**
 *    Copyright (C) 2018 EURO-LOG AG
*
*    All rights reserved.
*
*    Redistribution and use in source and binary forms, with or without
*    modification, are permitted provided that the following conditions are met:
*
*    1. Redistributions of source code must retain the above copyright notice,
*       this list of conditions and the following disclaimer.
*
*    2. Redistributions in binary form must reproduce the above copyright
*       notice, this list of conditions and the following disclaimer in the
*       documentation and/or other materials provided with the distribution.
*
*    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
*    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
*    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
*    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
*    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
  *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
  *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
*    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
*    POSSIBILITY OF SUCH DAMAGE.
*
*/

namespace OPNsense\Relayd;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class Relayd
 * @package OPNsense\Relayd
 */
class Relayd extends BaseModel
{
    /**
     * get configuration state
     * @return bool
     */
    public function configChanged()
    {
        return file_exists("/tmp/relayd.dirty");
    }

    /**
     * mark configuration as changed
     * @return bool
     */
    public function configDirty()
    {
        return @touch("/tmp/relayd.dirty");
    }

    /**
     * mark configuration as consistent with the running config
     * @return bool
     */
    public function configClean()
    {
        return @unlink("/tmp/relayd.dirty");
    }

    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        foreach ($this->virtualserver->iterateItems() as $node) {
            if (!$validateFullModel && !$node->isFieldChanged()) {
                continue;
            }
            $key = $node->__reference;
            if ($node->type == 'redirect') {
                if (!in_array((string)$node->transport_tablemode, ['least-states', 'roundrobin'])) {
                    $messages->appendMessage(
                        new Message(
                            sprintf(gettext('Scheduler "%s" not supported for redirects.'), $node->transport_tablemode),
                            $key . ".transport_tablemode"
                        )
                    );
                }
                if (!in_array((string)$node->backuptransport_tablemode, ['least-states', 'roundrobin'])) {
                    $messages->appendMessage(
                        new Message(
                            sprintf(gettext('Scheduler "%s" not supported for redirects.'), $node->transport_tablemode),
                            $key . ".backuptransport_tablemode"
                        )
                    );
                }
                if ($node->transport_type == 'route' && empty((string)$node->routing_interface)) {
                    $messages->appendMessage(
                        new Message(gettext('Routing interface cannot be empty'), $key . ".routing_interface")
                    );
                }
            } elseif ($node->type == 'relay') {
                if ($node->transport_tablemode == 'least-states') {
                    $messages->appendMessage(
                        new Message(
                            sprintf(gettext('Scheduler "%s" not supported for relays.'), $node->transport_tablemode),
                            $key . ".transport_tablemode"
                        )
                    );
                }
                if ($node->backuptransport_tablemode == 'least-states') {
                    $messages->appendMessage(
                        new Message(
                            sprintf(
                                gettext('Scheduler "%s" not supported for relays.'),
                                $node->backuptransport_tablemode
                            ),
                            $key . ".backuptransport_tablemode"
                        )
                    );
                }
            }
            foreach ($this->tablecheck->iterateItems() as $node) {
                if (!$validateFullModel && !$node->isFieldChanged()) {
                    continue;
                }
                $key = $node->__reference;
                switch ((string)$node->type) {
                    case 'send':
                        if (empty((string)$node->expect)) {
                            $messages->appendMessage(
                                new Message(gettext('Expect Pattern cannot be empty.'), $key . ".expect")
                            );
                        }
                        break;
                    case 'script':
                        if (empty((string)$node->path)) {
                            $messages->appendMessage(
                                new Message(gettext('Script path cannot be empty.'), $key . ".path")
                            );
                        }
                        break;
                    case 'http':
                        if (empty((string)$node->path)) {
                            $messages->appendMessage(
                                new Message(gettext('Path cannot be empty.'), $key . ".path")
                            );
                        }
                        if (empty((string)$node->code) && empty((string)$node->digest)) {
                            $messages->appendMessage(
                                new Message(
                                    gettext('Provide one of Response Code or Message Digest.'),
                                    $key . ".code"
                                )
                            );
                            $messages->appendMessage(
                                new Message(
                                    gettext('Provide one of Response Code or Message Digest.'),
                                    $key . ".digest"
                                )
                            );
                        }
                        break;
                }
            }
        }
        return $messages;
    }

    /**
     * @param string $type type of object (host, table, virtualserver)
     * @param string $name name of the attribute
     * @param string $value value to match
     * @return ArrayField[] items found
     */
    public function getObjectsByAttribute($type, $name, $value)
    {
        $results = [];
        foreach ($this->$type->iterateItems() as $item) {
            if ((string)$item->$name == $value) {
                  $results[] = $item;
            }
        }
        return $results;
    }
}
