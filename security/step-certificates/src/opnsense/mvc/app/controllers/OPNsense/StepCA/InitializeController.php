<?php

/**
 *    Copyright (C) 2024 Volodymyr Paprotski
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

namespace OPNsense\StepCA;

const exampleTemplate = <<<TPL
{
    "subject": {{ toJson .Subject }},
    "sans": {{ toJson .SANs }},
{{- if typeIs "*rsa.PublicKey" .Insecure.CR.PublicKey }}
    "keyUsage": ["keyEncipherment", "digitalSignature"],
{{- else }}
    "keyUsage": ["digitalSignature"],
{{- end }}
    "extKeyUsage": ["serverAuth", "clientAuth"],
    "extensions": [
        {"id": "1.2.3.4", "value": {{ asn1Marshal .AuthorizationCrt.NotAfter | toJson }}},
        {"id": "1.2.3.5", "value": {{ asn1Set (asn1Marshal (first .Insecure.CR.DNSNames) "utf8") (asn1Enc "int:123456") | toJson }}},
        {"id": "1.2.3.6", "value": {{ asn1Seq (asn1Enc "YubiKey") (asn1Enc "int:123456") | toJson }}}
    ]
}
TPL;
const fullTemplate = <<<TPL
{
    "subject": {{ toJson .Subject }},
    "sans": {{ toJson .SANs }},
{{- if typeIs "*rsa.PublicKey" .Insecure.CR.PublicKey }}
    "keyUsage": ["keyEncipherment", "digitalSignature"],
{{- else }}
    "keyUsage": ["digitalSignature"],
{{- end }}
    "extKeyUsage": ["serverAuth", "clientAuth"],
    "extensions": [
        {"id": "1.2.3.4", "value": {{ asn1Marshal .AuthorizationCrt.NotAfter | toJson }}},
        {"id": "1.2.3.5", "value": {{ asn1Set (asn1Marshal (first .Insecure.CR.DNSNames) "utf8") (asn1Enc "int:123456") | toJson }}},
        {"id": "1.2.3.6", "value": {{ asn1Seq (asn1Enc "YubiKey") (asn1Enc "int:123456") | toJson }}}
    ]
}
TPL;

/**
 * Class IndexController
 * @package OPNsense\StepCA
 */
class InitializeController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/StepCA/initialize');
        $this->view->initializeForm = $this->getForm("initialize");
        $this->view->exampleTemplate = exampleTemplate;
        $this->view->fullTemplate = fullTemplate;
    }
}
