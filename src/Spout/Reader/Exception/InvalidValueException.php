<?php

namespace Box\Spout\Reader\Exception;

use Throwable;

/**
 * Class InvalidValueException
 */
class InvalidValueException extends ReaderException
{
    /**
     * @param mixed $invalidValue
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(private $invalidValue, $message = '', $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return mixed
     */
    public function getInvalidValue()
    {
        return $this->invalidValue;
    }
}
