<?php

namespace Box\Spout\Reader\XLSX\Manager;

use Box\Spout\Common\Exception\IOException;
use Box\Spout\Reader\Exception\XMLProcessingException;
use Box\Spout\Reader\Wrapper\XMLReader;
use Box\Spout\Reader\XLSX\Creator\HelperFactory;
use Box\Spout\Reader\XLSX\Creator\InternalEntityFactory;
use Box\Spout\Reader\XLSX\Manager\SharedStringsCaching\CachingStrategyFactory;
use Box\Spout\Reader\XLSX\Manager\SharedStringsCaching\CachingStrategyInterface;

/**
 * Class SharedStringsManager
 * This class manages the shared strings defined in the associated XML file
 */
class SharedStringsManager
{
    /** Definition of XML nodes names used to parse data */
    public const XML_NODE_SST = 'sst';
    public const XML_NODE_SI = 'si';
    public const XML_NODE_R = 'r';
    public const XML_NODE_T = 't';

    /** Definition of XML attributes used to parse data */
    public const XML_ATTRIBUTE_COUNT = 'count';
    public const XML_ATTRIBUTE_UNIQUE_COUNT = 'uniqueCount';
    public const XML_ATTRIBUTE_XML_SPACE = 'xml:space';
    public const XML_ATTRIBUTE_VALUE_PRESERVE = 'preserve';

    /** @var CachingStrategyInterface The best caching strategy for storing shared strings */
    protected $cachingStrategy;

    /**
     * @param string $filePath Path of the XLSX file being read
     * @param string $tempFolder Temporary folder where the temporary files to store shared strings will be stored
     * @param WorkbookRelationshipsManager $workbookRelationshipsManager Helps retrieving workbook relationships
     * @param InternalEntityFactory $entityFactory Factory to create entities
     * @param HelperFactory $helperFactory Factory to create helpers
     * @param CachingStrategyFactory $cachingStrategyFactory Factory to create shared strings caching strategies
     */
    public function __construct(protected $filePath, protected $tempFolder, protected $workbookRelationshipsManager, protected $entityFactory, protected $helperFactory, protected $cachingStrategyFactory)
    {
    }

    /**
     * Returns whether the XLSX file contains a shared strings XML file
     *
     * @return bool
     */
    public function hasSharedStrings()
    {
        return $this->workbookRelationshipsManager->hasSharedStringsXMLFile();
    }

    /**
     * Builds an in-memory array containing all the shared strings of the sheet.
     * All the strings are stored in a XML file, located at 'xl/sharedStrings.xml'.
     * It is then accessed by the sheet data, via the string index in the built table.
     *
     * More documentation available here: http://msdn.microsoft.com/en-us/library/office/gg278314.aspx
     *
     * The XML file can be really big with sheets containing a lot of data. That is why
     * we need to use a XML reader that provides streaming like the XMLReader library.
     *
     * @throws IOException If shared strings XML file can't be read
     * @return void
     */
    public function extractSharedStrings()
    {
        $sharedStringsXMLFilePath = $this->workbookRelationshipsManager->getSharedStringsXMLFilePath();
        $xmlReader = $this->entityFactory->createXMLReader();
        $sharedStringIndex = 0;

        if ($xmlReader->openFileInZip($this->filePath, $sharedStringsXMLFilePath) === false) {
            throw new IOException('Could not open "' . $sharedStringsXMLFilePath . '".');
        }

        try {
            $sharedStringsUniqueCount = $this->getSharedStringsUniqueCount($xmlReader);
            $this->cachingStrategy = $this->getBestSharedStringsCachingStrategy($sharedStringsUniqueCount);

            $xmlReader->readUntilNodeFound(self::XML_NODE_SI);

            while ($xmlReader->getCurrentNodeName() === self::XML_NODE_SI) {
                $this->processSharedStringsItem($xmlReader, $sharedStringIndex);
                $sharedStringIndex++;

                // jump to the next '<si>' tag
                $xmlReader->next(self::XML_NODE_SI);
            }

            $this->cachingStrategy->closeCache();
        } catch (XMLProcessingException $exception) {
            throw new IOException("The sharedStrings.xml file is invalid and cannot be read. [{$exception->getMessage()}]");
        }

        $xmlReader->close();
    }

    /**
     * Returns the shared strings unique count, as specified in <sst> tag.
     *
     * @param XMLReader $xmlReader XMLReader instance
     * @throws IOException If sharedStrings.xml is invalid and can't be read
     * @return int|null Number of unique shared strings in the sharedStrings.xml file
     */
    protected function getSharedStringsUniqueCount($xmlReader)
    {
        $xmlReader->next(self::XML_NODE_SST);

        // Iterate over the "sst" elements to get the actual "sst ELEMENT" (skips any DOCTYPE)
        while ($xmlReader->getCurrentNodeName() === self::XML_NODE_SST && $xmlReader->nodeType !== XMLReader::ELEMENT) {
            $xmlReader->read();
        }

        $uniqueCount = $xmlReader->getAttribute(self::XML_ATTRIBUTE_UNIQUE_COUNT);

        // some software do not add the "uniqueCount" attribute but only use the "count" one
        // @see https://github.com/box/spout/issues/254
        if ($uniqueCount === null) {
            $uniqueCount = $xmlReader->getAttribute(self::XML_ATTRIBUTE_COUNT);
        }

        return ($uniqueCount !== null) ? (int) $uniqueCount : null;
    }

    /**
     * Returns the best shared strings caching strategy.
     *
     * @param int|null $sharedStringsUniqueCount Number of unique shared strings (NULL if unknown)
     * @return CachingStrategyInterface
     */
    protected function getBestSharedStringsCachingStrategy($sharedStringsUniqueCount)
    {
        return $this->cachingStrategyFactory
                ->createBestCachingStrategy($sharedStringsUniqueCount, $this->tempFolder, $this->helperFactory);
    }

    /**
     * Processes the shared strings item XML node which the given XML reader is positioned on.
     *
     * @param XMLReader $xmlReader XML Reader positioned on a "<si>" node
     * @param int $sharedStringIndex Index of the processed shared strings item
     * @return void
     */
    protected function processSharedStringsItem($xmlReader, $sharedStringIndex)
    {
        $sharedStringValue = '';

        // NOTE: expand() will automatically decode all XML entities of the child nodes
        /** @var \DOMElement $siNode */
        $siNode = $xmlReader->expand();
        $textNodes = $siNode->getElementsByTagName(self::XML_NODE_T);

        foreach ($textNodes as $textNode) {
            if ($this->shouldExtractTextNodeValue($textNode)) {
                $textNodeValue = $textNode->nodeValue;
                $shouldPreserveWhitespace = $this->shouldPreserveWhitespace($textNode);

                $sharedStringValue .= ($shouldPreserveWhitespace) ? $textNodeValue : \trim((string) $textNodeValue);
            }
        }

        $this->cachingStrategy->addStringForIndex($sharedStringValue, $sharedStringIndex);
    }

    /**
     * Not all text nodes' values must be extracted.
     * Some text nodes are part of a node describing the pronunciation for instance.
     * We'll only consider the nodes whose parents are "<si>" or "<r>".
     *
     * @param \DOMElement $textNode Text node to check
     * @return bool Whether the given text node's value must be extracted
     */
    protected function shouldExtractTextNodeValue($textNode)
    {
        $parentTagName = $textNode->parentNode->localName;

        return ($parentTagName === self::XML_NODE_SI || $parentTagName === self::XML_NODE_R);
    }

    /**
     * If the text node has the attribute 'xml:space="preserve"', then preserve whitespace.
     *
     * @param \DOMElement $textNode The text node element (<t>) whose whitespace may be preserved
     * @return bool Whether whitespace should be preserved
     */
    protected function shouldPreserveWhitespace($textNode)
    {
        $spaceValue = $textNode->getAttribute(self::XML_ATTRIBUTE_XML_SPACE);

        return ($spaceValue === self::XML_ATTRIBUTE_VALUE_PRESERVE);
    }

    /**
     * Returns the shared string at the given index, using the previously chosen caching strategy.
     *
     * @param int $sharedStringIndex Index of the shared string in the sharedStrings.xml file
     * @throws \Box\Spout\Reader\Exception\SharedStringNotFoundException If no shared string found for the given index
     * @return string The shared string at the given index
     */
    public function getStringAtIndex($sharedStringIndex)
    {
        return $this->cachingStrategy->getStringAtIndex($sharedStringIndex);
    }

    /**
     * Destroys the cache, freeing memory and removing any created artifacts
     *
     * @return void
     */
    public function cleanup()
    {
        if ($this->cachingStrategy !== null) {
            $this->cachingStrategy->clearCache();
        }
    }
}
