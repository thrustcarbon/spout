<?php

namespace Box\Spout\Writer\Common\Manager\Style;

use Box\Spout\Common\Entity\Style\Style;

/**
 * Class PossiblyUpdatedStyle
 * Indicates if style is updated.
 * It allow to know if style registration must be done.
 */
class PossiblyUpdatedStyle
{
    public function __construct(private readonly Style $style, private readonly bool $isUpdated)
    {
    }

    public function getStyle() : Style
    {
        return $this->style;
    }

    public function isUpdated() : bool
    {
        return $this->isUpdated;
    }
}
