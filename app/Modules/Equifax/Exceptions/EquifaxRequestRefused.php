<?php

namespace App\Modules\Equifax\Exceptions;

use RuntimeException;

/**
 * An Equifax request or workflow step was refused before anything was sent to
 * Surepass. The message is safe to show the caller: it never contains the PAN,
 * mobile number or provider text.
 */
class EquifaxRequestRefused extends RuntimeException {}
