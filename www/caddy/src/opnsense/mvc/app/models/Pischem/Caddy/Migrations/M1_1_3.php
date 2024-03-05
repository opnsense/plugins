<?php

namespace Pischem\Caddy\Migrations;

use OPNsense\Core\Config;
use OPNsense\Base\BaseModelMigration;

class M1_1_3 extends BaseModelMigration
{
    public function run($model)
    {
        // Read the current configuration settings from the system
        $cfgObj = Config::getInstance()->object();

        // Flag to keep track of whether we've made any changes that need saving
        $hasChanges = false;

        // Checking if there's a 'reverseproxy' section under 'Pischem->caddy' in the config
        if (isset($cfgObj->Pischem->caddy->reverseproxy)) {

            // Iterating over each item in the 'reverseproxy' section.
            foreach ($cfgObj->Pischem->caddy->reverseproxy->children() as $item) {

                // If an item has the old 'Description' tag, we need to update it
                if (isset($item->Description)) {

                    // Capturing the old description's value before we remove the tag
                    $descriptionValue = (string)$item->Description;

                    // Removing the old 'Description' tag and creating a new 'description' tag with the same value
                    unset($item->Description);
                    $item->addChild('description', $descriptionValue);

                    // Track a change that needs to be saved later
                    $hasChanges = true;
                }
            }

            // After going through all items, if any changes were made, we save the updated configuration
            if ($hasChanges) {
                Config::getInstance()->save();
            }
        }
    }
}
