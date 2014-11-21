<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 22:59
 */

namespace Djambi\Enums;


use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiUnexpectedValueException;

class StatusNotFoundException extends DjambiUnexpectedValueException implements DjambiExceptionInterface {}