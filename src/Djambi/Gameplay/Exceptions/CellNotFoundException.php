<?php

namespace Djambi\Gameplay\Exceptions;

use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiOutOfBoundsException;

class CellNotFoundException extends DjambiOutOfBoundsException implements DjambiExceptionInterface {}
