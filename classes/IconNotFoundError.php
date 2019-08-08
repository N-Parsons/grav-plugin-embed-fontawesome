<?php
namespace Grav\Plugin\EmbedFontAwesomePlugin;

use Exception;

/**
 * Basic exception class to provide a more meaningful error message
 */
class IconNotFoundError extends Exception
{
  public function __construct($message, $code = 0, Exception $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}
