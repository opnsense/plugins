<?php

namespace OPNsense\Bind\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class RpzEntryController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'entry';
    protected static $internalModelClass = '\OPNsense\Bind\RpzEntry';

    public function searchEntryAction()
    {
        $zone = $this->request->get('zone');
        $filter_funct = null;
        if (!empty($zone)) {
            $filter_funct = function ($entry) use ($zone) {
                return $entry->zone == $zone;
            };
        }

        return $this->searchBase(
            'entries.entry',
            array("enabled", "zone", "qname", "action", "redirect_target"),
            null,
            $filter_funct
        );
    }

    public function getEntryAction($uuid = null)
    {
        $zone = $this->request->get('zone');
        $result = $this->getBase('entry', 'entries.entry', $uuid);
        if ($uuid == null && !empty($result['entry']['zone'])) {
            // pre-select the currently-selected zone when adding a new entry
            foreach ($result['entry']['zone'] as $key => &$value) {
                if ($key == $zone) {
                    $value['selected'] = 1;
                } else {
                    $value['selected'] = 0;
                }
            }
        }
        return $result;
    }

    public function addEntryAction()
    {
        return $this->addBase('entry', 'entries.entry');
    }

    public function delEntryAction($uuid)
    {
        return $this->delBase('entries.entry', $uuid);
    }

    public function setEntryAction($uuid = null)
    {
        return $this->setBase('entry', 'entries.entry', $uuid);
    }

    public function toggleEntryAction($uuid)
    {
        return $this->toggleBase('entries.entry', $uuid);
    }
}
