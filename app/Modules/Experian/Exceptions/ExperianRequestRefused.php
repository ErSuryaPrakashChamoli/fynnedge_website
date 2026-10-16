<?php

namespace App\Modules\Experian\Exceptions;

use RuntimeException;

/**
 * An Experian request or workflow step was refused before anything was sent to
 * Surepass. The message is safe to show the caller: it never contains the PAN,
 * mobile number or provider text.
 */
class ExperianRequestRefused extends RuntimeException {}
