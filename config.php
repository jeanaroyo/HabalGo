<?php
// Database settings. Override with environment variables, or edit the defaults below.
// Never commit a real password.
return [
  'host'   => getenv('DB_HOST') ?: 'localhost',
  'port'   => getenv('DB_PORT') ?: '5432',
  'dbname' => getenv('DB_NAME') ?: 'habalgo',
  'user'   => getenv('DB_USER') ?: 'postgres',
  'pass'   => getenv('DB_PASS') ?: '',
];
