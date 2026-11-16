<?php

namespace App\Payments;

use RuntimeException;

/**
 * The payment provider couldn't be reached or refused the request.
 */
class PaymentException extends RuntimeException {}
