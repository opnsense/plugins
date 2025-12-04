<?php

/*
 * Copyright (C) 2024 Volodymyr Paprotski
 * 
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

namespace OPNsense\StepCA\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Core\Config;

// FIXME: contribute back to core (added PrivKeyRequired)
/**
 * Class CertificateField field type to select certificates from the internal cert manager
 * package to glue legacy certificates into the model.
 * @package OPNsense\Base\FieldTypes
 */
class CertificateField extends BaseListField
{
    /**
     * @var string certificate type cert/ca, reflects config section to use as source
     */
    private $certificateType = "cert";

    /**
     * @var bool Certificate must have private key
     */
    private $internalPrivKeyRequired = false;

    /**
     * @var array cached collected certs
     */
    private static $internalStaticOptionList = array();

    /**
     * set certificate type (cert/ca)
     * @param $value certificate type
     */
    public function setType($value)
    {
        if (trim(strtolower($value)) == "ca") {
            $this->certificateType = "ca";
        } elseif (trim(strtolower($value)) == "crl") {
            $this->certificateType = "crl";
        } else {
            $this->certificateType = "cert";
        }
    }

    /**
     * Whether Certificate must have a private key
     * @param string $value Y/N
     */
    public function setPrivKeyRequired($value)
    {
        $this->internalPrivKeyRequired = trim(strtoupper($value)) == "Y";
    }

    /**
     * generate validation data (list of certificates)
     */
    protected function actionPostLoadingEvent()
    {
        if ($this->internalPrivKeyRequired) {
            $configObj = Config::getInstance()->object();
            foreach ($configObj->{$this->certificateType} as $cert) {
                if ($this->certificateType == 'ca' && (string)$cert->x509_extensions == 'ocsp') {
                    // skip ocsp signing certs
                    continue;
                }
                if (empty($cert->prv)) {
                    // skip cert without private key
                    continue;
                }
                $this->internalOptionList[(string)$cert->refid] = (string)$cert->descr;
            }
            natcasesort($this->internalOptionList);
            return;
        }

        if (!isset(self::$internalStaticOptionList[$this->certificateType])) {
            self::$internalStaticOptionList[$this->certificateType] = array();
            $configObj = Config::getInstance()->object();
            foreach ($configObj->{$this->certificateType} as $cert) {
                if ($this->certificateType == 'ca' && (string)$cert->x509_extensions == 'ocsp') {
                    // skip ocsp signing certs
                    continue;
                }
                // if ($this->internalPrivKeyRequired && empty($cert->prv)) {
                //     // skip cert without private key
                //     continue;
                // }
                self::$internalStaticOptionList[$this->certificateType][(string)$cert->refid] = (string)$cert->descr;
            }
            natcasesort(self::$internalStaticOptionList[$this->certificateType]);
        }
        $this->internalOptionList = self::$internalStaticOptionList[$this->certificateType];
    }
}
