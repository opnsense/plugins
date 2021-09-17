<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Dnscryptproxy;

use OPNsense\Base\ControllerRoot;

/**
 * Class ControllerBase implements core controller for OPNsense framework
 *
 * This is a special version of ControllerBase which parses the model XML
 * differently than the standard ControllerBase.
 *
 * It provides primarily a single function from the original, and is only used
 * in the SettingsController class to parse the form XML into an array.
 *
 * @package OPNsense\Dnscryptproxy
 */
class ControllerBase extends ControllerRoot
{
    /**
     * Special version of parseFromNode() which recurses deeper (infinite),
     * and supports arrays at all levels, and attributes at levels deeper than
     * than original.
     *
     * It allows for a lot more flexibility in the design of the form XMLs.
     *
     * @param $xmlNode
     * @return array
     */
    private function parseFormNode($xmlNode)
    {
        $result = array();
        foreach ($xmlNode as $key => $node) {
            $element = array();
            $nodes = $node->children();
            $nodes_count = $nodes->count();
            $attributes = $node->attributes();

            switch ($key) {
                case 'tab':
                    if (! array_key_exists('tabs', $result)) {
                        $result['tabs'] = array();
                    }
                    $tab = array();
                    $tab[] = $node->attributes()->id;
                    $tab[] = gettext((string) $node->attributes()->description);
                    if (isset($node->subtab)) {
                        $tab['subtabs'] = $this->parseFormNode($node);
                    } else {
                        $tab[] = $this->parseFormNode($node);
                    }
                    $result['tabs'][] = $tab;

                    break;
                case 'subtab':
                    $subtab = array();
                    $subtab[] = $node->attributes()->id;
                    $subtab[] = gettext((string) $node->attributes()->description);
                    $subtab[] = $this->parseFormNode($node);
                    $result[] = $subtab;

                    break;
                case 'help':
                case 'hint':
                case 'label':
                    $result[$key] = gettext((string) $node);

                    break;
                default:
                    if (count($attributes) !== 0) { // If there are attributes, let's grab them.
                        foreach ($attributes as $attr_name => $attr_value) {
                            $my_attributes[$attr_name] = $attr_value->__tostring();
                        }
                        $element['@attributes'] = $my_attributes;   // Item which is part of a key set.
                    }

                    if ($nodes_count === 0) {
                        if ($node->attributes()) {
                            if (count($node->xpath('../' . $key)) > 1) {
                                $element[] = $node->__toString();
                                $result[$key][] = $element;
                            } else {
                                // Only a single element, but has attributes.
                                $element[] = $node->__toString();
                                $result[$key] = $element;
                            }
                        } else {
                            if (count($node->xpath('../' . $key)) > 1) {
                                $result[$key][] = $node->__toString();
                            } else {
                                $result[$key] = $node->__toString();
                            }
                        }

                        break;
                    }

                    if (count($node->xpath('../' . $key)) < 2) {
                        $result[$key] = $this->parseFormNode($node);

                        break;
                    }

                    $result[$key][] = array_merge($this->parseFormNode($node), $element);
            }
        }

        return $result;
    }

    /**
     * parse an xml type form
     * @param $formname
     * @return array
     * @throws \Exception
     */
    public function getForm($formname)
    {
        $class_info = new \ReflectionClass($this);
        $filename = dirname($class_info->getFileName()) . '/forms/' . $formname . '.xml';
        if (! file_exists($filename)) {
            throw new \Exception('form xml ' . $filename . ' missing');
        }
        $formXml = simplexml_load_file($filename);
        if ($formXml === false) {
            throw new \Exception('form xml ' . $filename . ' not valid');
        }

        return $this->parseFormNode($formXml);
    }
}
