<?php

/*
    Copyright (C) 2017 Fabian Franz
    Copyright (C) 2017 Michael Muenz <m.muenz@gmail.com>
    All rights reserved.
    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:
    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.
    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.
    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

namespace OPNsense\Quagga;

class DiagnosticsController extends \OPNsense\Base\IndexController
{
    /**
     * {@inheritdoc}
     */
    protected function templateJSIncludes()
    {
        return array_merge(parent::templateJSIncludes(), [
            '/ui/js/tree.jquery.min.js',
            '/ui/js/opnsense-treeview.js'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function templateCSSIncludes()
    {
        return array_merge(parent::templateCSSIncludes(), ['/css/jqtree.css']);
    }

    public function bgpAction()
    {
        $this->view->tabs = [
            [
                'name' => 'routing4',
                'endpoint' => '/api/quagga/diagnostics/search_bgproute4',
                'tabhead' => "IPv4 " . gettext('Routing Table'),
                'type' => 'bgproutetable'
            ],
            [
                'name' => 'routing6',
                'endpoint' => '/api/quagga/diagnostics/search_bgproute6',
                'tabhead' => "IPv6 " . gettext('Routing Table'),
                'type' => 'bgproutetable'
            ],
            [
                'name' => 'neighbors',
                'endpoint' => '/api/quagga/diagnostics/bgpneighbors',
                'tabhead' => gettext('Neighbors'),
                'type' => 'tree'
            ],
            [
                'name' => 'summary',
                'endpoint' => '/api/quagga/diagnostics/bgpsummary',
                'tabhead' => gettext('Summary'),
                'type' => 'tree'
            ]
        ];
        $this->view->default_tab = 'routing4';
        $this->view->pick('OPNsense/Quagga/diagnostics');
    }
    public function ospfAction()
    {
        $this->view->tabs = [
            [
                'name' => 'overview',
                'endpoint' => '/api/quagga/diagnostics/ospfoverview',
                'tabhead' => gettext('Overview'),
                'type' => 'tree'
            ],
            [
                'name' => 'routing',
                'endpoint' => '/api/quagga/diagnostics/search_ospfroute',
                'tabhead' => gettext('Routing Table'),
                'type' => 'ospfroutetable'
            ],
            [
                'name' => 'database',
                'endpoint' => '/api/quagga/diagnostics/ospfdatabase',
                'tabhead' => gettext('Database'),
                'type' => 'tree'
            ],
            [
                'name' => 'neighbors',
                'endpoint' => '/api/quagga/diagnostics/search_ospfneighbor',
                'tabhead' => gettext('Neighbors'),
                'type' => 'ospfneighbors'
            ],
            [
                'name' => 'interfaces',
                'endpoint' => '/api/quagga/diagnostics/ospfinterface',
                'tabhead' => gettext('Interfaces'),
                'type' => 'tree'
            ]
        ];
        $this->view->default_tab = 'routing';
        $this->view->pick('OPNsense/Quagga/diagnostics');
    }
    public function bfdAction()
    {
        $this->view->tabs = [
            [
                'name' => 'summary',
                'endpoint' => '/api/quagga/diagnostics/bfdsummary',
                'tabhead' => gettext('Summary'),
                'type' => 'bfdsummary'
            ],
            [
                'name' => 'neighbors',
                'endpoint' => '/api/quagga/diagnostics/bfdneighbors',
                'tabhead' => gettext('Neighbors'),
                'type' => 'tree'
            ],
            [
                'name' => 'counters',
                'endpoint' => '/api/quagga/diagnostics/bfdcounters',
                'tabhead' => gettext('Counters'),
                'type' => 'tree'
            ]
        ];
        $this->view->default_tab = 'summary';
        $this->view->pick('OPNsense/Quagga/diagnostics');
    }
    public function ospfv3Action()
    {
        $this->view->tabs = [
            [
                'name' => 'overview',
                'endpoint' => '/api/quagga/diagnostics/ospfv3overview',
                'tabhead' => gettext('Overview'),
                'type' => 'tree'

            ],
            [
                'name' => 'routing',
                'endpoint' => '/api/quagga/diagnostics/search_ospfv3route',
                'tabhead' => gettext('Routing'),
                'type' => 'ospfv3routetable'
            ],
            [
                'name' => 'database',
                'endpoint' => '/api/quagga/diagnostics/search_ospfv3database',
                'tabhead' => gettext('Database'),
                'type' => 'ospfv3databasetable'
            ],
            [
                'name' => 'interface',
                'endpoint' => '/api/quagga/diagnostics/ospfv3interface',
                'tabhead' => gettext('Interface'),
                'type' => 'tree'
            ]
        ];
        $this->view->default_tab = 'overview';
        $this->view->pick('OPNsense/Quagga/diagnostics');
    }
    public function generalAction()
    {
        $this->view->tabs = [
            [
                'name' => 'routing4',
                'endpoint' => '/api/quagga/diagnostics/search_generalroute4',
                'tabhead' => gettext('IPv4 Routes'),
                'type' => 'generalroutetable'
            ],
            [
                'name' => 'routing6',
                'endpoint' => '/api/quagga/diagnostics/search_generalroute6',
                'tabhead' => gettext('IPv6 Routes'),
                'type' => 'generalroutetable'
            ],
            [
                'name' => 'runningconfig',
                'endpoint' => '/api/quagga/diagnostics/generalrunningconfig/plain',
                'tabhead' => gettext('Running Configuration'),
                'type' => 'text'
            ]
        ];
        $this->view->default_tab = 'routing4';
        $this->view->pick('OPNsense/Quagga/diagnostics');
    }
}
