<?php

namespace App\Services\Publish;

use RuntimeException;

/** Code only (e.g. `http_400_9004`); provider messages are never stored or logged. */
final class PublishFailed extends RuntimeException {}
