<?php

namespace Djambi\Gameplay\Exceptions;

use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiOutOfBoundsException;

class FactionNotFoundException extends DjambiOutOfBoundsException implements DjambiExceptionInterface {}
