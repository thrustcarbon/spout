<?php

namespace Box\Spout\Writer\Common\Entity;

use Box\Spout\Writer\Common\Manager\SheetManager;

/**
 * Class Sheet
 * External representation of a worksheet
 */
class Sheet
{
    public const DEFAULT_SHEET_NAME_PREFIX = 'Sheet';

    /** @var string Name of the sheet */
    private $name;

    /** @var bool Visibility of the sheet */
    private $isVisible;

    /**
     * @param int $index Index of the sheet, based on order in the workbook (zero-based)
     * @param string $associatedWorkbookId ID of the sheet's associated workbook
     * @param SheetManager $sheetManager To manage sheets
     */
    public function __construct(private $index, private $associatedWorkbookId, private readonly SheetManager $sheetManager)
    {
        $this->sheetManager->markWorkbookIdAsUsed($this->associatedWorkbookId);

        $this->setName(self::DEFAULT_SHEET_NAME_PREFIX . ($this->index + 1));
        $this->setIsVisible(true);
    }

    /**
     * @return int Index of the sheet, based on order in the workbook (zero-based)
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return string
     */
    public function getAssociatedWorkbookId()
    {
        return $this->associatedWorkbookId;
    }

    /**
     * @return string Name of the sheet
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name of the sheet. Note that Excel has some restrictions on the name:
     *  - it should not be blank
     *  - it should not exceed 31 characters
     *  - it should not contain these characters: \ / ? * : [ or ]
     *  - it should be unique
     *
     * @param string $name Name of the sheet
     * @throws \Box\Spout\Writer\Exception\InvalidSheetNameException If the sheet's name is invalid.
     * @return Sheet
     */
    public function setName($name)
    {
        $this->sheetManager->throwIfNameIsInvalid($name, $this);

        $this->name = $name;

        $this->sheetManager->markSheetNameAsUsed($this);

        return $this;
    }

    /**
     * @return bool isVisible Visibility of the sheet
     */
    public function isVisible()
    {
        return $this->isVisible;
    }

    /**
     * @param bool $isVisible Visibility of the sheet
     * @return Sheet
     */
    public function setIsVisible($isVisible)
    {
        $this->isVisible = $isVisible;

        return $this;
    }
}
