<?php

/**
 *    Copyright (C) 2022 agh1467 <agh1467@protonmail.com>
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

namespace OPNsense\Sslh\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

/**
 * An ApiMutableModelControllerBase class used to perform settings related
 * actions for this plugin.
 *
 * This API is accessible at the following URL endpoint:
 *
 * `/api/sslh/settings`
 *
 * Functions with a name ending in "Action" become API endpoints by extending
 * `ApiMutableModelControllerBase`. That class creates the following encpoints:
 * ```
 *   search
 *   get
 *   add
 *   del
 *   set
 *   toggle
 * ```
 *
 * @package OPNsense\Sslh
 */
class SettingsController extends ApiMutableModelControllerBase
{
    /**
     * This variable defines what to call the <model> that is defined for this
     * Class by Phalcon. That is to say the model XML that has the same name as
     * this controller's name, "Settings".
     * In this case, it is the model XML file:
     *
     * `model/OPNsense/Sslh/Settings.xml`
     *
     * The model name is then used as the name of the array returned by setBase()
     * and getBase(). In the form XMLs, the prefix used on the field IDs must
     * match this name as API actions use the same name in their transactions.
     * For example, the key_name in an API JSON response, will be this model
     * name. This name is also used as the API endpoint for this Controller.
     *
     * `/api/sslh/settings`
     *
     * This locks activies of this Class to this specific model, so it won't
     * save to other models, even within the same plugin.
     *
     * @var string $internalModelName
     */
    protected static $internalModelName = 'settings';

    /**
     * Base model class to reference.
     *
     * This variable defines which class to call for getMode(). It is used in a
     * ReflectionClass call to establish the model object. This class is defined
     * in the models directory alongside the model XML, and has the same name
     * as this Controller. This class extends BaseModel which reads the model
     * XML that has the same name as the class.
     *
     * In this case, these are the model XML file, and class definition file:
     *
     * `model/OPNsense/Sslh/Settings.xml`
     *
     * `model/OPNsense/Sslh/Settings.php`
     *
     * These together will establish several API endpoints on this Controller's
     * endpoint including:
     *
     * `/api/sslh/settings/get`
     *
     * `/api/sslh/settings/set`
     *
     * These are both defined in the ApiMutableModelControllerBase Class:
     *
     * `function getAction()`
     *
     * `function setAction()`
     *
     * @var string $internalModelClass
     */
    protected static $internalModelClass = 'OPNsense\Sslh\Settings';

    /**
     * An API endpoint to call when no parameters are
     * provided for the API. Can be used to test the API is working.

     * API endpoint:
     *
     *   `/api/sslh/settings`
     *
     * Usage:
     *
     *   `/api/sslh/settings`
     *
     * Returns an array which gets converted to json in the POST response.
     *
     * @return array    includes status, saying everything is A-OK
     */
    public function indexAction()
    {
        return array('status' => 'ok');
    }
}
