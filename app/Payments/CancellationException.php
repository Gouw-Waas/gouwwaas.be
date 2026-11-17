<?php

namespace App\Payments;

use RuntimeException;

/**
 * A registration can't be cancelled right now; the message is shown to the organiser.
 */
class CancellationException extends RuntimeException {}
