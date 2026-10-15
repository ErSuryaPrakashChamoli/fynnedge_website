<?php

namespace App\Modules\CreditBureau\Exceptions;

use RuntimeException;

/**
 * A CRIF request or workflow step was refused before anything was sent to
 * Surepass. The message is safe to show the employee: it never contains the
 * PAN, mobile number or provider text.
 */
class CrifRequestRefused extends RuntimeException {}
