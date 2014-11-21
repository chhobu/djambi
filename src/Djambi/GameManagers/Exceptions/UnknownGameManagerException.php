<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 01:27
 */

namespace Djambi\GameManagers\Exceptions;


use Djambi\Exceptions\DjambiBadFunctionCallException;
use Djambi\Exceptions\DjambiExceptionInterface;

class UnknownGameManagerException extends DjambiBadFunctionCallException implements DjambiExceptionInterface {}