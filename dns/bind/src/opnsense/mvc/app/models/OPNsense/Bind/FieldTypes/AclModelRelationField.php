<?php

/*
 * Copyright (C) 2015-2025 Deciso B.V.
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

use OPNsense\Base\Validators\CallbackValidator;
use OPNsense\Base\FieldTypes\BaseListField;
use OPNsense\Base\FieldTypes\ModelRelationField;

class AclModelRelationField extends ModelRelationField
{
    /*
    * Extends ModelRelationField but all private properties and the private
    * member loadModelOptions() require duplication. Public methods
    * getNodeData() and getValidators() are altered to use the grandparent
    * BaseListField:: rather than parent::. The getValidators() method is
    * also modified to call new isValidComboSelection() method via new
    * CallbackValidator(). We also require public methods actionPostLoadingEvent()
    * and setModel() too for this to work.
    */

    /**
     * {@inheritdoc}
     */
    private $internalIsSorted = false;

    /**
     * {@inheritdoc}
     */
    private $mdlStructure = null;

    /**
     * {@inheritdoc}
     */
    private $internalOptionsFromThisModel = false;

    /**
     * {@inheritdoc}
     */
    private $internalCacheKey = "";

    /**
     * {@inheritdoc}
     */
    private static $internalCacheOptionList = [];

    /**
     * {@inheritdoc}
     */
    private static $internalCacheModelStruct = [];

    /**
     * {@inheritdoc}
     */
    private function loadModelOptions($force = false)
    {
        // only collect options once per source/filter combination, we use a static to save our unique option
        // combinations over the running application.
        if (!isset(self::$internalCacheOptionList[$this->internalCacheKey]) || $force) {
            self::$internalCacheOptionList[$this->internalCacheKey] = [];
            foreach ($this->mdlStructure as $modelData) {
                // only handle valid model sources
                if (!isset($modelData['source']) || !isset($modelData['items']) || !isset($modelData['display'])) {
                    continue;
                }
                $className = str_replace('.', '\\', $modelData['source']);
                $groupKey = isset($modelData['group']) ? $modelData['group'] : null;
                $displayKeys = explode(',', $modelData['display']);
                $displayFormat = !empty($modelData['display_format']) ? $modelData['display_format'] : "%s";

                $searchItems = $this->getCachedData($className, $modelData['items'], $force);
                $groups = [];
                foreach ($searchItems as $uuid => $node) {
                    $descriptions = [];
                    foreach ($displayKeys as $displayKey) {
                        $descriptions[] = $node['%' . $displayKey] ?? $node[$displayKey] ?? '';
                    }
                    if (isset($modelData['filters'])) {
                        foreach ($modelData['filters'] as $filterKey => $filterValue) {
                            $fieldData = $node[$filterKey] ?? null;
                            if (!preg_match($filterValue, $fieldData) && $fieldData != null) {
                                continue 2;
                            }
                        }
                    }
                    if (!empty($groupKey)) {
                        if (!isset($node[$groupKey]) || isset($groups[$node[$groupKey]])) {
                            continue;
                        }
                        $groups[$node[$groupKey]] = 1;
                    }
                    self::$internalCacheOptionList[$this->internalCacheKey][$uuid] = vsprintf(
                        $displayFormat,
                        $descriptions
                    );
                }
            }

            if (!$this->internalIsSorted) {
                natcasesort(self::$internalCacheOptionList[$this->internalCacheKey]);
            }
        }
        // Set for use in BaseListField->getNodeData()
        $this->internalOptionList = self::$internalCacheOptionList[$this->internalCacheKey];
    }

    /**
     * {@inheritdoc}
     */
    public function setModel($mdlStructure)
    {
        // only handle array type input
        if (!is_array($mdlStructure)) {
            return;
        } else {
            $this->mdlStructure = $mdlStructure;
            // set internal key for this node based on sources and filter criteria
            $this->internalCacheKey = md5(serialize($mdlStructure));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function actionPostLoadingEvent()
    {
        $this->loadModelOptions();
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeData()
    {
        if ($this->internalIsSorted) {
            $optKeys = array_merge(explode(',', $this->internalValue), array_keys($this->internalOptionList));
            $ordered_option_list = [];
            foreach (array_unique($optKeys) as $key) {
                if (in_array($key, array_keys($this->internalOptionList))) {
                    $ordered_option_list[$key] = $this->internalOptionList[$key];
                }
            }
            $this->internalOptionList = $ordered_option_list;
        }

        return BaseListField::getNodeData();
    }

    /**
     * @param string $input list of ACLs selected to validate
     * @return bool if valid combination of ACLs
     */
    protected function isValidComboSelection($input)
    {
        if (strpos($input, ",") !== false) {
            // Pass validation if we only have a single-select.
            // Otherwise, get the ACL selection data and iterate to see if "any or "none" are included in the multi-select...
            $acls = $this->getNodeData();
            foreach ($acls as $node => $acl_sel_data) {
                if (($acl_sel_data['value'] == 'any' || $acl_sel_data['value'] == 'none') && $acl_sel_data['selected'] == '1') {
                        $this->setValidationMessage("This ACL cannot be used in combination with others: " . $acl_sel_data['value']);
                        return false;
                }
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidators()
    {
        // Use validators from BaseListField, includes validations for multi-select, and single-select.
        $validators = BaseListField::getValidators();
        if ($this->internalValue != null) {
            // XXX: may be improved a bit to prevent the same object being constructed multiple times when used
            //      in different fields (passing of $force parameter)
            $this->loadModelOptions($this->internalOptionsFromThisModel);
            $that = $this;
            $validators[] = new CallbackValidator(["callback" => function ($data) use ($that) {
                $messages = [];
                if (!$that->isValidComboSelection($data)) {
                    $messages[] =  $this->getValidationMessage();
                }
                return $messages;
            }]);
        }
        return $validators;
    }
}
