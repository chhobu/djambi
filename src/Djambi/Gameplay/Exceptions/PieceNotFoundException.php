<?php

namespace Djambi\Gameplay\Exceptions;

use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiOutOfBoundsException;

class PieceNotFoundException extends DjambiOutOfBoundsException implements DjambiExceptionInterface {}