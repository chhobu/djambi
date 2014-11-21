<?php

namespace Djambi\Players\Exceptions;


use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\Exceptions\DjambiInvalidArgumentException;

class PlayerNotFoundException extends DjambiInvalidArgumentException implements DjambiExceptionInterface {}
