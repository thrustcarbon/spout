<?php

namespace Box\Spout\Reader\ODS\Helper;

use Box\Spout\Reader\Exception\InvalidValueException;

/**
 * Class CellValueFormatter
 * This class provides helper functions to format cell values
 */
class CellValueFormatter
{
    /** Definition of all possible cell types */
    public const CELL_TYPE_STRING = 'string';
    public const CELL_TYPE_FLOAT = 'float';
    public const CELL_TYPE_BOOLEAN = 'boolean';
    public const CELL_TYPE_DATE = 'date';
    public const CELL_TYPE_TIME = 'time';
    public const CELL_TYPE_CURRENCY = 'currency';
    public const CELL_TYPE_PERCENTAGE = 'percentage';
    public const CELL_TYPE_VOID = 'void';

    /** Definition of XML nodes names used to parse data */
    public const XML_NODE_P = 'p';
    public const XML_NODE_TEXT_A = 'text:a';
    public const XML_NODE_TEXT_SPAN = 'text:span';
    public const XML_NODE_TEXT_S = 'text:s';
    public const XML_NODE_TEXT_TAB = 'text:tab';
    public const XML_NODE_TEXT_LINE_BREAK = 'text:line-break';

    /** Definition of XML attributes used to parse data */
    public const XML_ATTRIBUTE_TYPE = 'office:value-type';
    public const XML_ATTRIBUTE_VALUE = 'office:value';
    public const XML_ATTRIBUTE_BOOLEAN_VALUE = 'office:boolean-value';
    public const XML_ATTRIBUTE_DATE_VALUE = 'office:date-value';
    public const XML_ATTRIBUTE_TIME_VALUE = 'office:time-value';
    public const XML_ATTRIBUTE_CURRENCY = 'office:currency';
    public const XML_ATTRIBUTE_C = 'text:c';

    /** @var array List of XML nodes representing whitespaces and their corresponding value */
    private static $WHITESPACE_XML_NODES = [
        self::XML_NODE_TEXT_S => ' ',
        self::XML_NODE_TEXT_TAB => "\t",
        self::XML_NODE_TEXT_LINE_BREAK => "\n",
    ];

    /**
     * @param bool $shouldFormatDates Whether date/time values should be returned as PHP objects or be formatted as strings
     * @param \Box\Spout\Common\Helper\Escaper\ODS $escaper Used to unescape XML data
     */
    public function __construct(protected $shouldFormatDates, protected $escaper)
    {
    }

    /**
     * Returns the (unescaped) correctly marshalled, cell value associated to the given XML node.
     * @see http://docs.oasis-open.org/office/v1.2/os/OpenDocument-v1.2-os-part1.html#refTable13
     *
     * @param \DOMElement $node
     * @throws InvalidValueException If the node value is not valid
     * @return string|int|float|bool|\DateTime|\DateInterval The value associated with the cell, empty string if cell's type is void/undefined
     */
    public function extractAndFormatNodeValue($node)
    {
        $cellType = $node->getAttribute(self::XML_ATTRIBUTE_TYPE);

        return match ($cellType) {
            self::CELL_TYPE_STRING => $this->formatStringCellValue($node),
            self::CELL_TYPE_FLOAT => $this->formatFloatCellValue($node),
            self::CELL_TYPE_BOOLEAN => $this->formatBooleanCellValue($node),
            self::CELL_TYPE_DATE => $this->formatDateCellValue($node),
            self::CELL_TYPE_TIME => $this->formatTimeCellValue($node),
            self::CELL_TYPE_CURRENCY => $this->formatCurrencyCellValue($node),
            self::CELL_TYPE_PERCENTAGE => $this->formatPercentageCellValue($node),
            default => '',
        };
    }

    /**
     * Returns the cell String value.
     *
     * @param \DOMElement $node
     * @return string The value associated with the cell
     */
    protected function formatStringCellValue($node)
    {
        $pNodeValues = [];
        $pNodes = $node->getElementsByTagName(self::XML_NODE_P);

        foreach ($pNodes as $pNode) {
            $pNodeValues[] = $this->extractTextValueFromNode($pNode);
        }

        $escapedCellValue = \implode("\n", $pNodeValues);
        $cellValue = $this->escaper->unescape($escapedCellValue);

        return $cellValue;
    }

    /**
     * @param \DOMNode $pNode
     * @return string
     */
    private function extractTextValueFromNode($pNode)
    {
        $textValue = '';

        foreach ($pNode->childNodes as $childNode) {
            if ($childNode instanceof \DOMText) {
                $textValue .= $childNode->nodeValue;
            } elseif ($this->isWhitespaceNode($childNode->nodeName)) {
                $textValue .= $this->transformWhitespaceNode($childNode);
            } elseif ($childNode->nodeName === self::XML_NODE_TEXT_A || $childNode->nodeName === self::XML_NODE_TEXT_SPAN) {
                $textValue .= $this->extractTextValueFromNode($childNode);
            }
        }

        return $textValue;
    }

    /**
     * Returns whether the given node is a whitespace node. It must be one of these:
     *  - <text:s />
     *  - <text:tab />
     *  - <text:line-break />
     *
     * @param string $nodeName
     * @return bool
     */
    private function isWhitespaceNode($nodeName)
    {
        return isset(self::$WHITESPACE_XML_NODES[$nodeName]);
    }

    /**
     * The "<text:p>" node can contain the string value directly
     * or contain child elements. In this case, whitespaces contain in
     * the child elements should be replaced by their XML equivalent:
     *  - space => <text:s />
     *  - tab => <text:tab />
     *  - line break => <text:line-break />
     *
     * @see https://docs.oasis-open.org/office/v1.2/os/OpenDocument-v1.2-os-part1.html#__RefHeading__1415200_253892949
     *
     * @param \DOMElement $node The XML node representing a whitespace
     * @return string The corresponding whitespace value
     */
    private function transformWhitespaceNode($node)
    {
        $countAttribute = $node->getAttribute(self::XML_ATTRIBUTE_C); // only defined for "<text:s>"
        $numWhitespaces = (!empty($countAttribute)) ? (int) $countAttribute : 1;

        return \str_repeat((string) self::$WHITESPACE_XML_NODES[$node->nodeName], $numWhitespaces);
    }

    /**
     * Returns the cell Numeric value from the given node.
     *
     * @param \DOMElement $node
     * @return int|float The value associated with the cell
     */
    protected function formatFloatCellValue($node)
    {
        $nodeValue = $node->getAttribute(self::XML_ATTRIBUTE_VALUE);

        $nodeIntValue = (int) $nodeValue;
        $nodeFloatValue = (float) $nodeValue;
        $cellValue = ((float) $nodeIntValue === $nodeFloatValue) ? $nodeIntValue : $nodeFloatValue;

        return $cellValue;
    }

    /**
     * Returns the cell Boolean value from the given node.
     *
     * @param \DOMElement $node
     * @return bool The value associated with the cell
     */
    protected function formatBooleanCellValue($node)
    {
        $nodeValue = $node->getAttribute(self::XML_ATTRIBUTE_BOOLEAN_VALUE);

        return (bool) $nodeValue;
    }

    /**
     * Returns the cell Date value from the given node.
     *
     * @param \DOMElement $node
     * @throws InvalidValueException If the value is not a valid date
     * @return \DateTime|string The value associated with the cell
     */
    protected function formatDateCellValue($node)
    {
        // The XML node looks like this:
        // <table:table-cell calcext:value-type="date" office:date-value="2016-05-19T16:39:00" office:value-type="date">
        //   <text:p>05/19/16 04:39 PM</text:p>
        // </table:table-cell>

        if ($this->shouldFormatDates) {
            // The date is already formatted in the "p" tag
            $nodeWithValueAlreadyFormatted = $node->getElementsByTagName(self::XML_NODE_P)->item(0);
            $cellValue = $nodeWithValueAlreadyFormatted->nodeValue;
        } else {
            // otherwise, get it from the "date-value" attribute
            $nodeValue = $node->getAttribute(self::XML_ATTRIBUTE_DATE_VALUE);
            try {
                $cellValue = new \DateTime($nodeValue);
            } catch (\Exception) {
                throw new InvalidValueException($nodeValue);
            }
        }

        return $cellValue;
    }

    /**
     * Returns the cell Time value from the given node.
     *
     * @param \DOMElement $node
     * @throws InvalidValueException If the value is not a valid time
     * @return \DateInterval|string The value associated with the cell
     */
    protected function formatTimeCellValue($node)
    {
        // The XML node looks like this:
        // <table:table-cell calcext:value-type="time" office:time-value="PT13H24M00S" office:value-type="time">
        //   <text:p>01:24:00 PM</text:p>
        // </table:table-cell>

        if ($this->shouldFormatDates) {
            // The date is already formatted in the "p" tag
            $nodeWithValueAlreadyFormatted = $node->getElementsByTagName(self::XML_NODE_P)->item(0);
            $cellValue = $nodeWithValueAlreadyFormatted->nodeValue;
        } else {
            // otherwise, get it from the "time-value" attribute
            $nodeValue = $node->getAttribute(self::XML_ATTRIBUTE_TIME_VALUE);
            try {
                $cellValue = new \DateInterval($nodeValue);
            } catch (\Exception) {
                throw new InvalidValueException($nodeValue);
            }
        }

        return $cellValue;
    }

    /**
     * Returns the cell Currency value from the given node.
     *
     * @param \DOMElement $node
     * @return string The value associated with the cell (e.g. "100 USD" or "9.99 EUR")
     */
    protected function formatCurrencyCellValue($node)
    {
        $value = $node->getAttribute(self::XML_ATTRIBUTE_VALUE);
        $currency = $node->getAttribute(self::XML_ATTRIBUTE_CURRENCY);

        return "$value $currency";
    }

    /**
     * Returns the cell Percentage value from the given node.
     *
     * @param \DOMElement $node
     * @return int|float The value associated with the cell
     */
    protected function formatPercentageCellValue($node)
    {
        // percentages are formatted like floats
        return $this->formatFloatCellValue($node);
    }
}
