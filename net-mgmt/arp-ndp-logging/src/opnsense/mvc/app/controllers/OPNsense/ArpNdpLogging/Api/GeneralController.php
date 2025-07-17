<?php

/*
 * Copyright (C) 2025 github.com/mr-manuel
 * All rights reserved.
 *
 * License: BSD 2-Clause
 */

namespace OPNsense\ArpNdpLogging\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class GeneralController extends ApiMutableModelControllerBase
{
    protected static $internalModelClass = '\OPNsense\ArpNdpLogging\General';
    protected static $internalModelName = 'general';
}
