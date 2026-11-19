<?php

/*
| Pin the test environment before Laravel boots (pattern from ReportFlow).
|
| Laravel's dotenv repository reads $_SERVER before $_ENV, so values from docker-compose or .env would
| otherwise win over phpunit.xml. Every setting that points at a shared or external service is pinned
| here; hosts under .invalid never resolve, so a test that reaches for dev Redis, Gotenberg or a
| provider fails loudly. A new external setting MUST be added here and to TestIsolationTest.
*/

require __DIR__.'/../vendor/autoload.php';

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'agensi_test',
    'DB_USERNAME' => 'agensi_tester',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'log',
    'BCRYPT_ROUNDS' => '4',
    'LOG_CHANNEL' => 'null',
    'LOG_STACK' => 'null',
    'REDIS_HOST' => 'redis.invalid',
    'REDIS_PORT' => '1',
    'REDIS_PASSWORD' => '',
    'GOTENBERG_URL' => 'http://gotenberg.invalid:3000',
    'ASSETS_DISK_ROOT' => sys_get_temp_dir().'/agensi-test-assets',
    'LLM_PROVIDER' => 'fake',
    'TELEGRAM_CLIENT' => 'fake',
    'PUBLISHER' => 'fake',
    'RENDERER' => 'fake',
    'ANTHROPIC_API_KEY' => '',
    'ANTHROPIC_AUTH_TOKEN' => '',
    'ANTHROPIC_BASE_URL' => '',
    'OPENAI_API_KEY' => '',
    'AWS_ACCESS_KEY_ID' => '',
    'AWS_SECRET_ACCESS_KEY' => '',
] as $name => $value) {
    $_ENV[$name] = $_SERVER[$name] = $value;
    putenv("{$name}={$value}");
}
