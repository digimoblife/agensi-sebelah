<?php

namespace App\Services\Artifacts;

use RuntimeException;

/** A generator cannot run yet; the message is shown to the admin as-is (Indonesian). */
final class PrerequisiteMissing extends RuntimeException {}
