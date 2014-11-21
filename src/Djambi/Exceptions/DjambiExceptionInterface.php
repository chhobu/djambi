<?php

namespace Djambi\Exceptions;

/**
 * Class Exception
 *
 * - LogicException
 *   - BadFunctionCallException (dynamic)
 *     - BadMethodCallException
 *   - DomainException
 *   - InvalidArgumentException
 *   - LengthException
 *   - OutOfRangeException
 * - RuntimeException
 *   - OutOfBoundsException
 *   - OverflowException
 *   - RangeException
 *   - UnderflowException
 *   - UnexpectedValueException
 */
interface DjambiExceptionInterface {}