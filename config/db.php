<?php
declare(strict_types=1);

$pdo = null;

function db_has_table(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS c
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :t
    ');
    $stmt->execute([':t' => $table]);
    return (int)($stmt->fetchColumn() ?: 0) > 0;
}

function db_has_column(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS c
        FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
    ');
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int)($stmt->fetchColumn() ?: 0) > 0;
}

function db_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    if (!db_has_table($pdo, 'users')) {
        $pdo->exec("
            CREATE TABLE users (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              username VARCHAR(32) NOT NULL,
              pass_hash VARCHAR(255) NOT NULL,
              role VARCHAR(16) NOT NULL DEFAULT 'GUEST',
              status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              approved_at TIMESTAMP NULL DEFAULT NULL,
              approved_by BIGINT UNSIGNED NULL DEFAULT NULL,
              last_login_at TIMESTAMP NULL DEFAULT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_users_username (username),
              KEY idx_users_role (role),
              KEY idx_users_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } else {
        // Ensure modern columns exist and relax enums to VARCHAR if needed.
        if (!db_has_column($pdo, 'users', 'approved_by')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN approved_by BIGINT UNSIGNED NULL DEFAULT NULL AFTER approved_at");
        }
        try { $pdo->exec("ALTER TABLE users MODIFY role VARCHAR(16) NOT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE users MODIFY status VARCHAR(16) NOT NULL"); } catch (Throwable $e) {}
    }

    if (!db_has_table($pdo, 'approvals_log')) {
        $pdo->exec("
            CREATE TABLE approvals_log (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              admin_user_id BIGINT UNSIGNED NOT NULL,
              target_user_id BIGINT UNSIGNED NOT NULL,
              action VARCHAR(32) NOT NULL,
              new_role VARCHAR(16) NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_approvals_admin (admin_user_id),
              KEY idx_approvals_target (target_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!db_has_table($pdo, 'user_permissions')) {
        $pdo->exec("
            CREATE TABLE user_permissions (
              user_id BIGINT UNSIGNED NOT NULL,
              perm_key VARCHAR(48) NOT NULL,
              can_view TINYINT(1) NOT NULL DEFAULT 0,
              can_edit TINYINT(1) NOT NULL DEFAULT 0,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (user_id, perm_key),
              KEY idx_perm_key (perm_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!db_has_table($pdo, 'site_settings')) {
        $pdo->exec("
            CREATE TABLE site_settings (
              setting_key VARCHAR(64) NOT NULL,
              setting_value TEXT NOT NULL,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!db_has_table($pdo, 'telemetry_fields')) {
        $pdo->exec("
            CREATE TABLE telemetry_fields (
              field_key VARCHAR(64) NOT NULL,
              category VARCHAR(32) NOT NULL,
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (field_key),
              KEY idx_tf_cat (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!db_has_table($pdo, 'telemetry_log')) {
        $pdo->exec("
            CREATE TABLE telemetry_log (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              source VARCHAR(16) NOT NULL,
              payload JSON NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_tl_source (source),
              KEY idx_tl_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if (!db_has_table($pdo, 'weather_cache')) {
        $pdo->exec("
            CREATE TABLE weather_cache (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              payload JSON NULL,
              fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_wc_time (fetched_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

function db_seed_default_admin(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $username = 'Admin';
    $password = 'Admin-01';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // If an ADMIN exists but is not ACTIVE, activate it (initial bootstrap).
        $stmtA = $pdo->query("SELECT id, username, status FROM users WHERE role='ADMIN' LIMIT 1");
        $admin = $stmtA ? $stmtA->fetch() : null;
        if ($admin) {
            $status = strtoupper((string)($admin['status'] ?? ''));
            if ($status !== 'ACTIVE') {
                $pdo->prepare("
                    UPDATE users
                    SET pass_hash=:p, status='ACTIVE', approved_at=NOW(), approved_by=NULL
                    WHERE id=:id
                ")->execute([':p' => $hash, ':id' => (int)$admin['id']]);
            }
            return;
        }

        $stmtU = $pdo->prepare("SELECT id FROM users WHERE username=:u LIMIT 1");
        $stmtU->execute([':u' => $username]);
        $row = $stmtU->fetch();

        if ($row) {
            $pdo->prepare("
                UPDATE users
                SET pass_hash=:p, role='ADMIN', status='ACTIVE', approved_at=NOW(), approved_by=NULL
                WHERE id=:id
            ")->execute([':p' => $hash, ':id' => (int)$row['id']]);
            return;
        }

        $pdo->prepare("
            INSERT INTO users (username, pass_hash, role, status, approved_at, approved_by)
            VALUES (:u, :p, 'ADMIN', 'ACTIVE', NOW(), NULL)
        ")->execute([':u' => $username, ':p' => $hash]);
    } catch (Throwable $e) {
        // Don't fail the whole app, but log so it's visible.
        error_log('Default admin seed failed: ' . $e->getMessage());
    }
}

function db_seed_telemetry_fields(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $fields = [
        // weather
        ['weather_temp', 'weather'],
        ['weather_humidity', 'weather'],
        ['weather_pressure', 'weather'],
        ['weather_wind_speed', 'weather'],
        ['weather_wind_gust', 'weather'],
        ['weather_wind_dir', 'weather'],
        ['weather_visibility', 'weather'],
        ['weather_clouds', 'weather'],
        ['weather_precip', 'weather'],

        // sensors
        ['gas_co', 'sensors'],
        ['gas_ch4', 'sensors'],
        ['gas_lpg', 'sensors'],
        ['gas_h2', 'sensors'],
        ['smoke_temp', 'sensors'],

        // platform
        ['link_status', 'platform'],
        ['last_seen', 'platform'],
        ['age_sec', 'platform'],
        ['uav_id', 'platform'],
        ['device', 'platform'],
        ['ts', 'platform'],
        ['coords', 'platform'],
        ['flight_vector', 'platform'],
        ['battery', 'platform'],
        ['nav_state', 'platform'],
        ['link_rate', 'platform'],
        ['motors_rpm', 'platform'],
        ['body_temp', 'platform'],
    ];

    foreach ($fields as [$key, $cat]) {
        try {
            $pdo->prepare("
                INSERT INTO telemetry_fields (field_key, category, enabled)
                VALUES (:k, :c, 1)
                ON DUPLICATE KEY UPDATE category=VALUES(category)
            ")->execute([':k' => $key, ':c' => $cat]);
        } catch (Throwable $e) {
            // ignore seeding errors
        }
    }
}

function db_seed_default_settings(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $defaults = [
        'telemetry_log_enabled' => '1',
        'telemetry_log_interval_sec' => '30',
        'weather_enabled' => '0',
        'weather_api_key' => '',
        'weather_lat' => '59.9342',
        'weather_lon' => '30.3351',
        'weather_city' => '',
        'weather_units' => 'metric',
        'weather_lang' => 'ru',
        'weather_interval_sec' => '300',
    ];

    foreach ($defaults as $k => $v) {
        try {
            $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value)
                VALUES (:k, :v)
                ON DUPLICATE KEY UPDATE setting_key=setting_key
            ")->execute([':k' => $k, ':v' => $v]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

function db(): PDO {
    global $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = defined('DB_HOST') ? (string)DB_HOST : 'localhost';
    $dbname = defined('DB_NAME') ? (string)DB_NAME : 'u3052693_default';
    $charset = defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4';
    $user = defined('DB_USER') ? (string)DB_USER : 'u3052693_default';
    $password = defined('DB_PASS') ? (string)DB_PASS : '28RwFk3TptuOFpo5';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        db_ensure_schema($pdo);
        db_seed_default_admin($pdo);
        db_seed_telemetry_fields($pdo);
        db_seed_default_settings($pdo);
        return $pdo;
    } catch (PDOException $e) {
        error_log('DB connect error: ' . $e->getMessage());
        http_response_code(500);
        exit('DB connection failed');
    }
}

// Legacy compatibility: many files expect $pdo to exist.
db();
?>
