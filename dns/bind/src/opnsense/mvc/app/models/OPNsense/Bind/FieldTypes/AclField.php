<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\BIND\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;

class ACLField extends ArrayField
{
    /*
     * Extends ArrayField to programmatically add BIND's builtin ACL types to
     * the model. The private property $internalTemplateNode is duplicated.
     * The actionPostLoadingEvent() method is replaced to add the builtin ACLs
     * as child nodes. The ability to add static children is removed. The builtin
     * ACL names are defined by the static $builtinNames property. Values for the
     * builtin ACLs are populated by the getBuiltinChildren() method. The public
     * function add() is also required.
     */

    /**
     * {@inheritdoc}
     */
    private $internalTemplateNode = null;

    /**
     * @var list to define builtin BIND ACL names
     */
    private static $builtinNames = ['none', 'localhost', 'localnets', 'any'];

    /**
     * @return array of builtin BIND ACLs
     */
    protected function getBuiltinChildren()
    {
        $builtins = [];
        foreach (self::$builtinNames as $aclName) {
            $builtins [] = [
                'enabled' => '1',
                'name' => $aclName,
                'networks' => 'system derived'
            ];
        }
        return $builtins;
    }

    /**
     * {@inheritdoc}
     */
    public function add($uuid = null)
    {
        $nodeUUID = empty($uuid) ? $this->generateUUID() : $uuid;
        $container_node = $this->newContainerField($this->__reference . "." . $nodeUUID, $this->internalXMLTagName);

        $template_ref = $this->internalTemplateNode->__reference;
        foreach ($this->internalTemplateNode->iterateItems() as $key => $node) {
            $new_node = clone $node;
            $new_node->setInternalReference($container_node->__reference . "." . $key);
            $new_node->applyDefault();
            $new_node->setChanged();
            $container_node->addChildNode($key, $new_node);

            if ($node->isContainer()) {
                foreach ($node->iterateRecursiveItems() as $subnode) {
                    if (is_a($subnode, "OPNsense\\Base\\FieldTypes\\ArrayField")) {
                        // validate child nodes, nesting not supported in this version.
                        throw new \Exception("Unsupported copy, Array doesn't support nesting.");
                    }
                }

                /**
                 * XXX: incomplete, only supports one nesting level of container fields. In the long run we probably
                 *      should refactor the add() function to push identifiers differently.
                 */
                foreach ($node->iterateItems() as $subkey => $subnode) {
                    $new_subnode = clone $subnode;
                    $new_subnode->setInternalReference($new_node->__reference . "." . $subkey);
                    $new_subnode->applyDefault();
                    $new_subnode->setChanged();
                    $new_node->addChildNode($subkey, $new_subnode);
                }
            }
        }

        // make sure we have a UUID on repeating child items
        $container_node->setAttributeValue("uuid", $nodeUUID);

        // add node to this object
        $this->addChildNode($nodeUUID, $container_node);

        return $container_node;
    }

    /**
     * {@inheritdoc}
     */
    protected function actionPostLoadingEvent()
    {
        // always make sure there's a node to copy our structure from
        if ($this->internalTemplateNode == null) {
            $firstKey = array_keys($this->internalChildnodes)[0];
            $this->internalTemplateNode = $this->internalChildnodes[$firstKey];
            /**
             * if first node is empty, remove reference node.
             */
            if ($this->internalChildnodes[$firstKey]->getInternalIsVirtual()) {
                unset($this->internalChildnodes[$firstKey]);
            }
        }

        // init builtin entries returned by getBuiltinChildren()
        foreach (static::getBuiltinChildren() as $skey => $payload) {
            $nodeUUID = $this->generateUUID();
            $container_node = $this->newContainerField($this->__reference . "." . $nodeUUID, $this->internalXMLTagName);
            $container_node->setAttributeValue("uuid", $nodeUUID);
            $template_ref = $this->internalTemplateNode->__reference;
            foreach ($this->internalTemplateNode->iterateItems() as $key => $value) {
                if ($key == 'name') {
                    foreach ($this->iterateItems() as $pkey => $pnode) {
                        foreach ($pnode->iterateItems() as $subkey => $subnode) {
                            if ($subkey == 'name' && $subnode == $payload[$key]) {
                                // The builtin ACL already exists, let's skip it...
                                continue 4;
                            }
                        }
                    }
                }
                $node = clone $value;
                $node->setInternalReference($container_node->__reference . "." . $key);
                if (isset($payload[$key])) {
                    $node->setValue($payload[$key]);
                }
                $node->setChanged();
                $container_node->addChildNode($key, $node);
            }
            $this->addChildNode($nodeUUID, $container_node);
        }
    }
}
