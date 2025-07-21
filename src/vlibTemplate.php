<?php

declare(strict_types=1);

namespace Redbird\vlib5;

use Redbird\vlib5\Original\vlibTemplate as vlibTemplateOriginal;

/**
 * PHP8+ compatible version of vlibTemplate
 */
class vlibTemplate extends vlibTemplateOriginal
{
    /**
     * Overrides setLoop - sets arrays as empty, if something else is set.
     * configurable with LOOP_NOT_ARRAY_OVERRIDE setting
     *
     * @param $k
     * @param $v
     * @return bool
     */
    public function setLoop($k, $v) : bool
    {
        return parent::setLoop($k, (((vlibIni::getConfig()['LOOP_NOT_ARRAY_OVERRIDE'] === true) && !is_array($v)) ? [] : $v));
    }
}