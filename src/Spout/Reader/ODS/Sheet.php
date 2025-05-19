<?php

namespace Box\Spout\Reader\ODS;

use Box\Spout\Reader\SheetInterface;

/**
 * Class Sheet
 * Represents a sheet within a ODS file
 */
class Sheet implements SheetInterface
{
    /** @var int ID of the sheet */
    protected $id;

    /**
     * @param RowIterator $rowIterator The corresponding row iterator
     * @param int $index Index of the sheet, based on order in the workbook (zero-based)
     * @param string $name Name of the sheet
     * @param bool $isActive Whether the sheet was defined as active
     * @param bool $isVisible Whether the sheet is visible
     */
    public function __construct(protected $rowIterator, protected $index, protected $name, protected $isActive, protected $isVisible)
    {
    }

    /**
     * @return RowIterator
     */
    public function getRowIterator()
    {
        return $this->rowIterator;
    }

    /**
     * @return int Index of the sheet, based on order in the workbook (zero-based)
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return string Name of the sheet
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return bool Whether the sheet was defined as active
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * @return bool Whether the sheet is visible
     */
    public function isVisible()
    {
        return $this->isVisible;
    }
}
