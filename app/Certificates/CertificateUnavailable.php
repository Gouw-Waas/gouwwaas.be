<?php

namespace App\Certificates;

use RuntimeException;

/**
 * Certificates can't be made right now; the message is shown to the organiser.
 */
class CertificateUnavailable extends RuntimeException {}
