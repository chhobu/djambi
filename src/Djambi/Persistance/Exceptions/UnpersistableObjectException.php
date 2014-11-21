<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 10/04/14
 * Time: 00:18
 */

namespace Djambi\Persistance\Exceptions;


use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiInvalidArgumentException;

class UnpersistableObjectException extends DjambiInvalidArgumentException implements DjambiExceptionInterface {}
