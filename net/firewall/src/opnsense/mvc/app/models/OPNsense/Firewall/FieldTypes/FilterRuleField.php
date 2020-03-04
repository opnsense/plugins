<?php

/**
 *    Copyright (C) 2020 Deciso B.V.
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

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;
use OPNsense\Base\FieldTypes\ContainerField;

/**
 * Class FilterRuleContainerField
 * @package OPNsense\Firewall\FieldTypes
 */
class FilterRuleContainerField extends ContainerField
{
    /**
     * map rules
     * @return array
     */
    public function serialize()
    {
        $result = array();
        $map_manual = ['source_net', 'source_not', 'source_port', 'destination_net', 'destination_not',
            'destination_port', 'enabled', 'description', 'sequence', 'action'];
        // 1-on-1 map (with type conversion if needed)
        foreach ($this->iterateItems() as $key => $node) {
            if (!in_array($key, $map_manual)) {
                if (is_a($node, "OPNsense\\Base\\FieldTypes\\BooleanField")) {
                    $result[$key] = !empty((string)$node);
                } else {
                    $result[$key] = (string)$node;
                }
            }
        }
        // source / destination mapping
        $result['source'] = array();
        if (!empty((string)$this->source_net)) {
            $result['source']['network'] = (string)$this->source_net;
            $result['source']['not'] = !empty((string)$this->source_not);
            if (!empty((string)$this->source_port)) {
                $result['source']['port'] = (string)$this->source_port;
            }
        }
        $result['destination'] = array();
        if (!empty((string)$this->destination_net)) {
            $result['destination']['network'] = (string)$this->destination_net;
            $result['destination']['not'] = !empty((string)$this->destination_not);
            if (!empty((string)$this->source_port)) {
                $result['destination']['port'] = (string)$this->destination_port;
            }
        }
        // swap enabled
        $result['disabled'] = empty((string)$this->enabled);
        //
        $result['descr'] = (string)$this->description;
        $result['type'] = (string)$this->action;
        return $result;
    }
}

/**
 * Class FilterRuleField
 * @package OPNsense\Firewall\FieldTypes
 */
class FilterRuleField extends ArrayField
{
    /**
     * @inheritDoc
     */
    public function newContainerField($ref, $tagname)
    {
        $container_node = new FilterRuleContainerField($ref, $tagname);
        $parentmodel = $this->getParentModel();
        $container_node->setParentModel($parentmodel);
        return $container_node;
    }
}
