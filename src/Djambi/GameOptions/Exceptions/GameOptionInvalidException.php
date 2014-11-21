<?php

namespace Djambi\GameOptions\Exceptions;

use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiOutOfBoundsException;

class GameOptionInvalidException extends DjambiOutOfBoundsException implements DjambiExceptionInterface {}
