<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 24/12/14
 * Time: 11:47
 */

namespace Djambi\PieceDescriptions\Exceptions;


use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiOutOfBoundsException;

class BadContainerReference extends DjambiOutOfBoundsException implements DjambiExceptionInterface {}