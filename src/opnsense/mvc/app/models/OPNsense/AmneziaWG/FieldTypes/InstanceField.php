<?php

namespace OPNsense\AmneziaWG\FieldTypes;

use OPNsense\Base\FieldTypes\ArrayField;

class InstanceField extends ArrayField
{
    /**
     * push internal reusable properties as virtuals
     */
    protected function actionPostLoadingEvent()
    {
        foreach ($this->internalChildnodes as $node) {
            if (!$node->getInternalIsVirtual()) {
                $node->cnfFilename = "/usr/local/etc/amneziawg/awg{$node->instance}.conf";
                $node->statFilename = "/usr/local/etc/amneziawg/awg{$node->instance}.stat";
                $node->interface = "awg{$node->instance}";
            }
        }
        return parent::actionPostLoadingEvent();
    }
}
