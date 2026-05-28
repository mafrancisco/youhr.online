<?php

class Installer
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function getStepData(string $step): array
    {
        return match ($step) {
            'requirements' => $this->checkRequirements(),
            'database'     => [],
            'config'       => [],
            'admin'        => [],
            'install'      => [],
            'complete'     => [],
            default        => [],
        };
    }

    public function handleAction(string $action, array $data): array
    {
        return match ($action) {
            'check_requirements' => $this->checkRequirements(),
            'test_database'      => $this->testDatabase($data),
            'run_install'        => $this->runInstall($data),
            'import_sql'         => $this->importSql(),
            default              => ['success' => false, 'message' => 'Unknown action'],
        };
    }

    public function checkRequirements(): array
    {
        $checks = [];

        // PHP Version
        $checks['php_version'] = [
            'label'    => 'PHP Version (≥ 8.2)',
            'status'   => version_compare(PHP_VERSION, '8.2.0', '>='),
            'current'  => PHP_VERSION,
        ];

        // Required extensions
        $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'gd', 'curl'];
        foreach ($extensions as $ext) {
            $checks["ext_{$ext}"] = [
                'label'  => "PHP Extension: {$ext}",
                'status' => extension_loaded($ext),
                'current' => extension_loaded($ext) ? 'Installed' : 'Missing',
            ];
        }

        // Writable directories
        $writableDirs = ['storage', 'storage/logs', 'storage/framework', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'];
        foreach ($writableDirs as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            $writable = is_dir($fullPath) && is_writable($fullPath);
            $checks["writable_{$dir}"] = [
                'label'   => "Writable: {$dir}",
                'status'  => $writable,
                'current' => $writable ? 'Writable' : 'Not writable',
            ];
        }

        // Disk space (need at least 100MB)
        $freeSpace = disk_free_space($this->basePath);
        $checks['disk_space'] = [
            'label'   => 'Disk Space (≥ 100MB)',
            'status'  => $freeSpace > 100 * 1024 * 1024,
            'current' => round($freeSpace / 1024 / 1024) . ' MB free',
        ];

        // Check if composer dependencies are installed
        $checks['vendor'] = [
            'label'   => 'Composer Dependencies',
            'status'  => is_dir($this->basePath . '/vendor'),
            'current' => is_dir($this->basePath . '/vendor') ? 'Installed' : 'Run: composer install',
        ];

        // Check if npm build exists
        $checks['build'] = [
            'label'   => 'Frontend Build (public/build)',
            'status'  => is_dir($this->basePath . '/public/build'),
            'current' => is_dir($this->basePath . '/public/build') ? 'Built' : 'Run: npm run build',
        ];

        $allPassed = !in_array(false, array_column($checks, 'status'));

        return ['checks' => $checks, 'allPassed' => $allPassed];
    }

    public function testDatabase(array $data): array
    {
        $host = $data['db_host'] ?? '127.0.0.1';
        $port = $data['db_port'] ?? '3306';
        $user = $data['db_username'] ?? 'root';
        $pass = $data['db_password'] ?? '';
        $name = $data['db_database'] ?? 'ironone';

        try {
            $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Try to create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Test connection to the database
            $pdo->exec("USE `{$name}`");

            return ['success' => true, 'message' => "Connected successfully. Database '{$name}' is ready."];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    public function runInstall(array $data): array
    {
        $steps = [];

        try {
            // Step 1: Generate .env file
            $steps[] = $this->generateEnv($data);

            // Step 2: Generate app key
            $result = $this->runArtisan('key:generate --force');
            $steps[] = $result;
            if (!$result['success']) {
                return ['success' => false, 'steps' => $steps, 'error' => 'Failed to generate app key.'];
            }

            // Step 3: Run migrations
            $result = $this->runArtisan('migrate --force');
            $steps[] = $result;
            
            // If shell-based migration failed, try running directly via PHP
            if (!$result['success'] || $result['output'] === 'Done') {
                $directResult = $this->runMigrationsDirect($data);
                $steps[] = $directResult;
                if (!$directResult['success']) {
                    return ['success' => false, 'steps' => $steps, 'error' => 'Migration failed: ' . $directResult['output']];
                }
            }

            // Step 4: Import SQL backup if provided (before creating admin, as it may contain user data)
            if (!empty($_SESSION['sql_file'])) {
                $steps[] = $this->importSqlFile($_SESSION['sql_file'], $data);
            }

            // Step 5: Create admin user (after migrations and SQL import)
            if (!empty($data['admin_username'])) {
                $steps[] = $this->createAdmin($data);
            }

            // Step 6: Create storage link
            $steps[] = $this->runArtisan('storage:link --force');

            // Step 7: Clear caches
            $steps[] = $this->runArtisan('config:clear');
            $steps[] = $this->runArtisan('cache:clear');

            // Step 8: Create installed lock file
            file_put_contents($this->basePath . '/storage/installed.lock', date('Y-m-d H:i:s'));
            $steps[] = ['step' => 'Lock File', 'success' => true, 'output' => 'Installation locked.'];

            return ['success' => true, 'steps' => $steps];
        } catch (\Exception $e) {
            $steps[] = ['step' => 'Error', 'success' => false, 'output' => $e->getMessage()];
            return ['success' => false, 'steps' => $steps, 'error' => $e->getMessage()];
        }
    }

    private function generateEnv(array $data): array
    {
        $appName  = $data['app_name'] ?? 'DTR System';
        $appUrl   = $data['app_url'] ?? 'http://localhost';
        $timezone = $data['timezone'] ?? 'Asia/Manila';
        $dbHost   = $data['db_host'] ?? '127.0.0.1';
        $dbPort   = $data['db_port'] ?? '3306';
        $dbName   = $data['db_database'] ?? 'dtr_system';
        $dbUser   = $data['db_username'] ?? 'root';
        $dbPass   = $data['db_password'] ?? '';

        $env = <<<ENV
APP_NAME="{$appName}"
APP_ENV=production
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE={$timezone}
APP_URL={$appUrl}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPass}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file
CACHE_PREFIX=

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@localhost"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
ENV;

        file_put_contents($this->basePath . '/.env', $env);

        return ['step' => 'Generate .env', 'success' => true, 'output' => '.env file created.'];
    }

    private function runArtisan(string $command): array
    {
        $php = $this->findPhp();
        $artisan = $this->basePath . DIRECTORY_SEPARATOR . 'artisan';
        
        if (!file_exists($artisan)) {
            return ['step' => "artisan {$command}", 'success' => false, 'output' => "artisan file not found at: {$artisan}"];
        }

        $fullCmd = "\"" . $php . "\" \"" . $artisan . "\" " . $command . " 2>&1";
        $output = @shell_exec($fullCmd);

        if ($output === null) {
            // shell_exec might be disabled, try exec instead
            $output = '';
            @exec($fullCmd, $outputLines, $returnCode);
            $output = implode("\n", $outputLines ?? []);
            
            if (empty($output) && ($returnCode ?? 1) !== 0) {
                return ['step' => "artisan {$command}", 'success' => false, 'output' => "Command execution failed. shell_exec/exec may be disabled in php.ini"];
            }
        }

        $success = stripos($output, 'ERROR') === false 
                && stripos($output, 'Exception') === false
                && stripos($output, 'Fatal') === false;

        return [
            'step'    => "artisan {$command}",
            'success' => $success,
            'output'  => trim($output ?: 'Done'),
        ];
    }

    private function createAdmin(array $data): array
    {
        $username = $data['admin_username'] ?? 'admin';
        $password = password_hash($data['admin_password'] ?? 'admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        $fullname = $data['admin_fullname'] ?? 'System Administrator';
        $email    = $data['admin_email'] ?? 'admin@localhost';

        try {
            $pdo = new PDO(
                "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']}",
                $data['db_username'], $data['db_password']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Verify users table exists
            $check = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($check->rowCount() === 0) {
                return ['step' => 'Create Admin', 'success' => false, 'output' => 'Users table does not exist. Migrations may have failed.'];
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, type, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE password=VALUES(password), fullname=VALUES(fullname)");
            $stmt->execute([$username, $password, $fullname, $email]);

            return ['step' => 'Create Admin', 'success' => true, 'output' => "Admin user '{$username}' created."];
        } catch (PDOException $e) {
            return ['step' => 'Create Admin', 'success' => false, 'output' => 'Error: ' . $e->getMessage()];
        }
    }

    private function importSqlFile(string $filePath, array $data): array
    {
        if (!file_exists($filePath)) {
            return ['step' => 'Import SQL', 'success' => false, 'output' => 'SQL file not found.'];
        }

        try {
            $pdo = new PDO(
                "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']}",
                $data['db_username'], $data['db_password']
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            $sql = file_get_contents($filePath);
            $pdo->exec($sql);

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

            // Clean up uploaded file
            unlink($filePath);
            unset($_SESSION['sql_file']);

            return ['step' => 'Import SQL', 'success' => true, 'output' => 'Database backup imported successfully.'];
        } catch (PDOException $e) {
            return ['step' => 'Import SQL', 'success' => false, 'output' => 'SQL import error: ' . $e->getMessage()];
        }
    }

    public function importSql(): array
    {
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error.'];
        }

        $ext = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            return ['success' => false, 'message' => 'Only .sql files are accepted.'];
        }

        $dest = $this->basePath . '/storage/app/install_backup.sql';
        move_uploaded_file($_FILES['sql_file']['tmp_name'], $dest);
        $_SESSION['sql_file'] = $dest;

        return ['success' => true, 'message' => 'SQL file uploaded: ' . $_FILES['sql_file']['name']];
    }

    private function runMigrationsDirect(array $data): array
    {
        $host = $data['db_host'] ?? '127.0.0.1';
        $port = $data['db_port'] ?? '3306';
        $name = $data['db_database'] ?? 'dtr_system';
        $user = $data['db_username'] ?? 'root';
        $pass = $data['db_password'] ?? '';

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create users table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `username` varchar(255) NOT NULL,
                `fullname` varchar(255) NOT NULL DEFAULT '',
                `email` varchar(255) NOT NULL DEFAULT '',
                `password` varchar(255) NOT NULL,
                `type` int NOT NULL DEFAULT 2,
                `remember_token` varchar(100) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                UNIQUE KEY `users_username_unique` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create password_reset_tokens table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                `email` varchar(255) NOT NULL PRIMARY KEY,
                `token` varchar(255) NOT NULL,
                `created_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create sessions table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `sessions` (
                `id` varchar(255) NOT NULL PRIMARY KEY,
                `user_id` bigint unsigned DEFAULT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text,
                `payload` longtext NOT NULL,
                `last_activity` int NOT NULL,
                KEY `sessions_user_id_index` (`user_id`),
                KEY `sessions_last_activity_index` (`last_activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create cache table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `cache` (
                `key` varchar(255) NOT NULL PRIMARY KEY,
                `value` mediumtext NOT NULL,
                `expiration` int NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `cache_locks` (
                `key` varchar(255) NOT NULL PRIMARY KEY,
                `owner` varchar(255) NOT NULL,
                `expiration` int NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create jobs table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `jobs` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `queue` varchar(255) NOT NULL,
                `payload` longtext NOT NULL,
                `attempts` tinyint unsigned NOT NULL,
                `reserved_at` int unsigned DEFAULT NULL,
                `available_at` int unsigned NOT NULL,
                `created_at` int unsigned NOT NULL,
                KEY `jobs_queue_index` (`queue`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create employees table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `employees` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `badgeID` varchar(50) NOT NULL,
                `empName` varchar(100) NOT NULL DEFAULT '',
                `email` varchar(100) NOT NULL DEFAULT '',
                `empStatus` int NOT NULL DEFAULT 1,
                `empDesig` varchar(100) NOT NULL DEFAULT '',
                `empHead` varchar(100) NOT NULL DEFAULT '',
                `schedule` bigint unsigned DEFAULT NULL,
                `status1` varchar(20) NOT NULL DEFAULT 'Active',
                `date_deact` varchar(20) NOT NULL DEFAULT '',
                `date_encoded` varchar(20) NOT NULL DEFAULT '',
                `division_id` bigint unsigned DEFAULT NULL,
                `unit_id` bigint unsigned DEFAULT NULL,
                KEY `employees_badgeid_index` (`badgeID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create schedule table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `schedule` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `schedulename` varchar(100) NOT NULL,
                `m_timein` varchar(10) NOT NULL DEFAULT '', `m_breakout` varchar(10) NOT NULL DEFAULT '', `m_breakin` varchar(10) NOT NULL DEFAULT '', `m_timeout` varchar(10) NOT NULL DEFAULT '', `m_crossday` tinyint(1) NOT NULL DEFAULT 0,
                `t_timein` varchar(10) NOT NULL DEFAULT '', `t_breakout` varchar(10) NOT NULL DEFAULT '', `t_breakin` varchar(10) NOT NULL DEFAULT '', `t_timeout` varchar(10) NOT NULL DEFAULT '', `t_crossday` tinyint(1) NOT NULL DEFAULT 0,
                `w_timein` varchar(10) NOT NULL DEFAULT '', `w_breakout` varchar(10) NOT NULL DEFAULT '', `w_breakin` varchar(10) NOT NULL DEFAULT '', `w_timeout` varchar(10) NOT NULL DEFAULT '', `w_crossday` tinyint(1) NOT NULL DEFAULT 0,
                `th_timein` varchar(10) NOT NULL DEFAULT '', `th_breakout` varchar(10) NOT NULL DEFAULT '', `th_breakin` varchar(10) NOT NULL DEFAULT '', `th_timeout` varchar(10) NOT NULL DEFAULT '', `th_crossday` tinyint(1) NOT NULL DEFAULT 0,
                `f_timein` varchar(10) NOT NULL DEFAULT '', `f_breakout` varchar(10) NOT NULL DEFAULT '', `f_breakin` varchar(10) NOT NULL DEFAULT '', `f_timeout` varchar(10) NOT NULL DEFAULT '', `f_crossday` tinyint(1) NOT NULL DEFAULT 0,
                `sat_timein` varchar(10) NOT NULL DEFAULT '', `sat_breakout` varchar(10) NOT NULL DEFAULT '', `sat_breakin` varchar(10) NOT NULL DEFAULT '', `sat_timeout` varchar(10) NOT NULL DEFAULT '', `sat_crossday` tinyint(1) NOT NULL DEFAULT 0,
                `sun_timein` varchar(10) NOT NULL DEFAULT '', `sun_breakout` varchar(10) NOT NULL DEFAULT '', `sun_breakin` varchar(10) NOT NULL DEFAULT '', `sun_timeout` varchar(10) NOT NULL DEFAULT '', `sun_crossday` tinyint(1) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create attendance tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `BadgeNumber` varchar(50) NOT NULL,
                `attDate` varchar(20) NOT NULL,
                `attTime` varchar(10) NOT NULL DEFAULT '',
                `attType` int NOT NULL DEFAULT 0,
                KEY `attendance_badgenumber_index` (`BadgeNumber`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance_clean` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `BadgeNumber` varchar(50) NOT NULL,
                `AttDate` varchar(20) NOT NULL,
                `StartTime1` varchar(20) NOT NULL DEFAULT '', `StartTime2` varchar(20) NOT NULL DEFAULT '',
                `StartTime3` varchar(20) NOT NULL DEFAULT '', `StartTime4` varchar(20) NOT NULL DEFAULT '',
                `OTIn` varchar(10) NOT NULL DEFAULT '', `OTOut` varchar(10) NOT NULL DEFAULT '',
                `OT` int NOT NULL DEFAULT 0, `Tardiness` int NOT NULL DEFAULT 0, `undertime` int NOT NULL DEFAULT 0,
                `amlate` int NOT NULL DEFAULT 0, `pmlate` int NOT NULL DEFAULT 0,
                `amundertime` int NOT NULL DEFAULT 0, `pmuntertime` int NOT NULL DEFAULT 0,
                `remarks` varchar(255) NOT NULL DEFAULT '', `obtime` varchar(50) NOT NULL DEFAULT '',
                KEY `attendance_clean_badgenumber_index` (`BadgeNumber`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create request table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `request` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `attID` int DEFAULT NULL,
                `BadgeNumber` varchar(50) NOT NULL,
                `AttDate` varchar(20) NOT NULL,
                `StartTime1` varchar(10) NOT NULL DEFAULT '', `StartTime2` varchar(10) NOT NULL DEFAULT '',
                `StartTime3` varchar(10) NOT NULL DEFAULT '', `StartTime4` varchar(10) NOT NULL DEFAULT '',
                `dateReq` varchar(20) NOT NULL DEFAULT '',
                `log1` varchar(10) NOT NULL DEFAULT '', `log2` varchar(10) NOT NULL DEFAULT '',
                `log3` varchar(10) NOT NULL DEFAULT '', `log4` varchar(10) NOT NULL DEFAULT '',
                `remarks` varchar(255) NOT NULL DEFAULT '',
                KEY `request_badgenumber_index` (`BadgeNumber`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create leaves table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `leaves` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `controlno` varchar(50) NOT NULL, `badgeID` varchar(50) NOT NULL,
                `leave_type` int NOT NULL DEFAULT 1, `date_start` varchar(255) NOT NULL DEFAULT '',
                `date_end` varchar(255) NOT NULL DEFAULT '', `leave_details` text,
                `date_filed` varchar(20) NOT NULL DEFAULT '', `noofdays` decimal(5,2) NOT NULL DEFAULT 0,
                `status` varchar(20) NOT NULL DEFAULT 'Pending',
                `credits_vl` decimal(5,2) NOT NULL DEFAULT 0, `credits_sl` decimal(5,2) NOT NULL DEFAULT 0,
                `ot_credits` decimal(5,2) NOT NULL DEFAULT 0, `service_credits` decimal(5,2) NOT NULL DEFAULT 0,
                `dateUpdated` varchar(20) NOT NULL DEFAULT '',
                KEY `leaves_badgeid_index` (`badgeID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create leave_type table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `leave_type` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `leave_type` varchar(100) NOT NULL, `description` varchar(255) NOT NULL DEFAULT '', `acronym` varchar(10) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create lcredits table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `lcredits` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `badgeID` varchar(50) NOT NULL, `vl` decimal(5,2) NOT NULL DEFAULT 0, `sl` decimal(5,2) NOT NULL DEFAULT 0,
                `maternity` decimal(6,2) NOT NULL DEFAULT 0, `paternity` decimal(6,2) NOT NULL DEFAULT 0,
                `spl` decimal(6,2) NOT NULL DEFAULT 0, `forced` decimal(6,2) NOT NULL DEFAULT 0,
                `wellness` decimal(6,2) NOT NULL DEFAULT 0,
                `ot` decimal(5,2) NOT NULL DEFAULT 0, `service` decimal(5,2) NOT NULL DEFAULT 0,
                `dateupdated` varchar(20) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create submissions table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `submissions` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `badgeID` varchar(50) NOT NULL, `attRange` varchar(20) NOT NULL,
                `date_submitted` varchar(20) NOT NULL, `time_submitted` varchar(20) NOT NULL,
                KEY `submissions_badgeid_index` (`badgeID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create gatepass table
            $pdo->exec("CREATE TABLE IF NOT EXISTS `gatepass` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `controlno` varchar(50) NOT NULL, `badgeID` varchar(50) NOT NULL,
                `gatepass_type` varchar(50) NOT NULL DEFAULT '', `gatepass_date` varchar(20) NOT NULL DEFAULT '',
                `gatepass_timeout` varchar(10) NOT NULL DEFAULT '', `gatepass_timein` varchar(10) NOT NULL DEFAULT '',
                `purpose` text, `destination` varchar(255) NOT NULL DEFAULT '',
                `gatepass_datefiled` varchar(20) NOT NULL DEFAULT '',
                `actual_timeout` varchar(10) NOT NULL DEFAULT '', `actual_timein` varchar(10) NOT NULL DEFAULT '',
                `date_time_approved` varchar(30) NOT NULL DEFAULT '',
                `time_consumed` varchar(20) NOT NULL DEFAULT '', `status` varchar(20) NOT NULL DEFAULT 'Pending',
                KEY `gatepass_badgeid_index` (`badgeID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create other supporting tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS `empstatus` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `description` varchar(50) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `heads` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `headname` varchar(100) NOT NULL, `headposition` varchar(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `divisions` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `division_name` varchar(100) NOT NULL, `division_chief` varchar(50) DEFAULT NULL,
                `created_at` timestamp NULL, `updated_at` timestamp NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `units` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `unit_name` varchar(100) NOT NULL, `division_id` bigint unsigned NOT NULL,
                `unit_head` varchar(50) DEFAULT NULL, `created_at` timestamp NULL, `updated_at` timestamp NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `holtype` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `type` varchar(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `dateparameters` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `actualDate` varchar(20) NOT NULL, `type` varchar(100) NOT NULL DEFAULT '',
                `description` varchar(255) NOT NULL DEFAULT '',
                `timein` varchar(10) NOT NULL DEFAULT '', `breakout` varchar(10) NOT NULL DEFAULT '',
                `breakin` varchar(10) NOT NULL DEFAULT '', `timeout` varchar(10) NOT NULL DEFAULT '',
                KEY `dateparameters_actualdate_index` (`actualDate`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `system_name` varchar(100) NOT NULL DEFAULT 'DTR System',
                `company_address` varchar(255) NOT NULL DEFAULT '',
                `authorized_signatory` varchar(100) NOT NULL DEFAULT '',
                `authorized_signatory_position` varchar(100) NOT NULL DEFAULT '',
                `logo_path` varchar(255) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Insert default settings row
            $check = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
            if ($check == 0) {
                $appName = $data['app_name'] ?? 'DTR System';
                $pdo->exec("INSERT INTO settings (id, system_name) VALUES (1, '{$appName}')");
            }

            // Create personal_access_tokens for API
            $pdo->exec("CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `tokenable_type` varchar(255) NOT NULL, `tokenable_id` bigint unsigned NOT NULL,
                `name` varchar(255) NOT NULL, `token` varchar(64) NOT NULL,
                `abilities` text, `last_used_at` timestamp NULL, `expires_at` timestamp NULL,
                `created_at` timestamp NULL, `updated_at` timestamp NULL,
                UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
                KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Create migrations table so Laravel knows tables exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
                `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `migration` varchar(255) NOT NULL,
                `batch` int NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            return ['step' => 'Direct Migration', 'success' => true, 'output' => 'All tables created successfully via direct SQL.'];
        } catch (PDOException $e) {
            return ['step' => 'Direct Migration', 'success' => false, 'output' => 'SQL Error: ' . $e->getMessage()];
        }
    }

    private function findPhp(): string
    {
        // On Windows/XAMPP, use the same PHP that's running this script
        if (PHP_BINARY && file_exists(PHP_BINARY)) {
            return PHP_BINARY;
        }

        // Try common XAMPP paths on Windows
        $paths = [
            'C:\\xampp\\php\\php.exe',
            'D:\\xampp\\php\\php.exe',
            'E:\\xampp\\php\\php.exe',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return 'php';
    }
}
