<?php

namespace Box\Spout\Writer\Common\Manager;

use Box\Spout\Common\Entity\Style\Style;

/**
 * Class RegisteredStyle
 * Allow to know if this style must replace actual row style.
 */
class RegisteredStyle
{
    public function __construct(private readonly Style $style, private readonly bool $isMatchingRowStyle)
    {
    }

    public function getStyle() : Style
    {
        return $this->style;
    }

    public function isMatchingRowStyle() : bool
    {
        return $this->isMatchingRowStyle;
    }
}
