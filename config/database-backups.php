<?php

return [
    'path' => storage_path('app/database-backups'),
    'connection' => env('DATABASE_BACKUP_CONNECTION') ?: env('DB_CONNECTION', 'mysql'),
    'queue_connection' => env('DATABASE_BACKUP_QUEUE_CONNECTION') ?: 'database-backups',
    'queue' => 'database-backups',
    'dump_binary' => env('DATABASE_BACKUP_DUMP_BINARY') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump'),
    'restore_binary' => env('DATABASE_BACKUP_RESTORE_BINARY') ?: (PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql'),
    'process_timeout' => 900,
    'max_upload_kb' => (int) (env('DATABASE_BACKUP_MAX_UPLOAD_KB') ?: 262144),
    'max_sql_bytes' => (int) (env('DATABASE_BACKUP_MAX_SQL_BYTES') ?: 1073741824),
];
