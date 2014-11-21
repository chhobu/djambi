<?php

namespace Djambi\GameManagers\Exceptions;

use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiInvalidArgumentException;

class GameNotFoundException extends DjambiInvalidArgumentException implements DjambiExceptionInterface {}
