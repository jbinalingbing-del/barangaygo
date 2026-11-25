<?php
// =====================================================
//  db.php — BarangayGo Database Connection (Local at Hosting)
//  Gumagana sa parehong MySQLi ($conn) at PDO ($pdo)
// =====================================================

// I-check kung tumatakbo sa InfinityFree hosting o sa Localhost (WAMP)
$is_live = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'barangaygo.gt.tc') !== false) 
        || (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'infinityfree') !== false);

if ($is_live) {
    // InfinityFree Live Hosting Credentials
    $dbHost = 'sql307.infinityfree.com';
    $dbUser = 'if0_43533714';
    $dbPass = 'o2vlukWJYPYQD';
    $dbName = 'if0_43533714_baranbarangaygo';
    $dbPort = 3306;
} else {
    // WAMP / Localhost Credentials
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'webdev2';
    $dbPort = 3306;
}

// 1. MySQLi Connection ($conn)
$conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
if (!$conn && !$is_live) {
    // Subukan ang port 3308 kung local WAMP MariaDB
    $conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, 3308);
}

if ($conn) {
    mysqli_set_charset($conn, "utf8mb4");
}

// 2. PDO Connection ($pdo)
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    if (!$is_live) {
        try {
            $pdo = new PDO(
                "mysql:host=$dbHost;port=3308;dbname=$dbName;charset=utf8mb4",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e2) {
            error_log("Database connection error: " . $e2->getMessage());
        }
    } else {
        error_log("Database connection error: " . $e->getMessage());
    }
}

// =====================================================
//  AUTOMATIC DATABASE SCHEMA REPAIR & MIGRATION
//  Tinitiyak na kumpleto ang mga tables at columns
//  para maiwasan ang mga errors tulad ng:
//  "Unknown column 'service' in 'field list'"
// =====================================================
function ensure_barangaygo_database_ready($conn) {
    if (!$conn) return;

    // Kunin ang mga umiiral na tables
    $tables = [];
    $res = @mysqli_query($conn, "SHOW TABLES");
    if ($res) {
        while ($r = mysqli_fetch_row($res)) {
            $tables[] = strtolower($r[0]);
        }
    }

    // 1. TABLE: requests
    if (!in_array('requests', $tables)) {
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `requests` (
            `request_id` INT AUTO_INCREMENT PRIMARY KEY,
            `resident_id` INT NOT NULL DEFAULT 0,
            `service` VARCHAR(100) NOT NULL DEFAULT '',
            `purpose` TEXT NULL,
            `business_name` VARCHAR(255) DEFAULT NULL,
            `business_address` TEXT DEFAULT NULL,
            `business_type` VARCHAR(100) DEFAULT NULL,
            `years_of_residency` INT DEFAULT NULL,
            `id_type` VARCHAR(100) DEFAULT NULL,
            `id_front` VARCHAR(255) DEFAULT NULL,
            `id_back` VARCHAR(255) DEFAULT NULL,
            `selfie` VARCHAR(255) DEFAULT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
            `remarks` TEXT DEFAULT NULL,
            `document_file` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        // Tiyakin ang bawat column sa requests
        $req_cols = [];
        $c_res = @mysqli_query($conn, "SHOW COLUMNS FROM `requests`");
        if ($c_res) {
            while ($c = mysqli_fetch_assoc($c_res)) {
                $req_cols[] = strtolower($c['Field']);
            }
        }

        $expected_req = [
            'resident_id'        => "INT NOT NULL DEFAULT 0",
            'service'            => "VARCHAR(100) NOT NULL DEFAULT ''",
            'purpose'            => "TEXT NULL",
            'business_name'      => "VARCHAR(255) DEFAULT NULL",
            'business_address'   => "TEXT DEFAULT NULL",
            'business_type'      => "VARCHAR(100) DEFAULT NULL",
            'years_of_residency' => "INT DEFAULT NULL",
            'id_type'            => "VARCHAR(100) DEFAULT NULL",
            'id_front'           => "VARCHAR(255) DEFAULT NULL",
            'id_back'            => "VARCHAR(255) DEFAULT NULL",
            'selfie'             => "VARCHAR(255) DEFAULT NULL",
            'status'             => "VARCHAR(50) NOT NULL DEFAULT 'Pending'",
            'remarks'            => "TEXT DEFAULT NULL",
            'document_file'      => "VARCHAR(255) DEFAULT NULL",
            'created_at'         => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at'         => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        ];

        foreach ($expected_req as $col_name => $col_def) {
            if (!in_array(strtolower($col_name), $req_cols)) {
                @mysqli_query($conn, "ALTER TABLE `requests` ADD COLUMN `$col_name` $col_def");
            }
        }

        // Kung may service_type o document_type na lumang data, ilipat sa service
        if (in_array('service_type', $req_cols)) {
            @mysqli_query($conn, "UPDATE `requests` SET `service` = `service_type` WHERE (`service` = '' OR `service` IS NULL) AND `service_type` IS NOT NULL");
        }
        if (in_array('document_type', $req_cols)) {
            @mysqli_query($conn, "UPDATE `requests` SET `service` = `document_type` WHERE (`service` = '' OR `service` IS NULL) AND `document_type` IS NOT NULL");
        }
    }

    // 2. TABLE: residents
    if (!in_array('residents', $tables)) {
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `residents` (
            `resident_id` INT AUTO_INCREMENT PRIMARY KEY,
            `salutation` VARCHAR(20) NOT NULL DEFAULT '',
            `first_name` VARCHAR(100) NOT NULL DEFAULT '',
            `middle_name` VARCHAR(100) NOT NULL DEFAULT '',
            `last_name` VARCHAR(100) NOT NULL DEFAULT '',
            `full_name` VARCHAR(320) NOT NULL DEFAULT '',
            `birthday` DATE NULL,
            `age` INT NOT NULL DEFAULT 0,
            `sex` VARCHAR(20) NOT NULL DEFAULT '',
            `civil_status` VARCHAR(50) NOT NULL DEFAULT '',
            `mobile` VARCHAR(20) NOT NULL DEFAULT '',
            `street` VARCHAR(255) NOT NULL DEFAULT '',
            `region` VARCHAR(100) NOT NULL DEFAULT '',
            `province` VARCHAR(100) NOT NULL DEFAULT '',
            `city` VARCHAR(100) NOT NULL DEFAULT '',
            `barangay` VARCHAR(100) NOT NULL DEFAULT '',
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `profile_pic` VARCHAR(255) DEFAULT NULL,
            `reset_code` VARCHAR(10) DEFAULT NULL,
            `reset_expires` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $res_cols = [];
        $c_res = @mysqli_query($conn, "SHOW COLUMNS FROM `residents`");
        if ($c_res) {
            while ($c = mysqli_fetch_assoc($c_res)) {
                $res_cols[] = strtolower($c['Field']);
            }
        }
        $expected_res = [
            'salutation'   => "VARCHAR(20) NOT NULL DEFAULT ''",
            'first_name'   => "VARCHAR(100) NOT NULL DEFAULT ''",
            'middle_name'  => "VARCHAR(100) NOT NULL DEFAULT ''",
            'last_name'    => "VARCHAR(100) NOT NULL DEFAULT ''",
            'full_name'    => "VARCHAR(320) NOT NULL DEFAULT ''",
            'birthday'     => "DATE NULL",
            'age'          => "INT NOT NULL DEFAULT 0",
            'sex'          => "VARCHAR(20) NOT NULL DEFAULT ''",
            'civil_status' => "VARCHAR(50) NOT NULL DEFAULT ''",
            'mobile'       => "VARCHAR(20) NOT NULL DEFAULT ''",
            'street'       => "VARCHAR(255) NOT NULL DEFAULT ''",
            'region'       => "VARCHAR(100) NOT NULL DEFAULT ''",
            'province'     => "VARCHAR(100) NOT NULL DEFAULT ''",
            'city'         => "VARCHAR(100) NOT NULL DEFAULT ''",
            'barangay'     => "VARCHAR(100) NOT NULL DEFAULT ''",
            'profile_pic'  => "VARCHAR(255) DEFAULT NULL",
            'reset_code'   => "VARCHAR(10) DEFAULT NULL",
            'reset_expires'=> "DATETIME DEFAULT NULL",
        ];
        foreach ($expected_res as $col_name => $col_def) {
            if (!in_array(strtolower($col_name), $res_cols)) {
                @mysqli_query($conn, "ALTER TABLE `residents` ADD COLUMN `$col_name` $col_def");
            }
        }
    }

    // 3. TABLE: admins
    if (!in_array('admins', $tables)) {
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `admins` (
            `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `email` VARCHAR(255) DEFAULT NULL,
            `password` VARCHAR(255) NOT NULL,
            `full_name` VARCHAR(255) NOT NULL DEFAULT 'Administrator',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @mysqli_query($conn, "INSERT IGNORE INTO `admins` (`admin_id`, `username`, `email`, `password`, `full_name`)
            VALUES (1, 'admin', 'admin@barangaygo.local', '\$2y\$10\$wEkgmC/g9yF4wZ5Y2HqLKe8k1K8vS6Yk.7Z1YtYj1bVv6lS8jUqWe', 'Barangay Administrator')");
    } else {
        $adm_cols = [];
        $c_res = @mysqli_query($conn, "SHOW COLUMNS FROM `admins`");
        if ($c_res) {
            while ($c = mysqli_fetch_assoc($c_res)) {
                $adm_cols[] = strtolower($c['Field']);
            }
        }
        if (!in_array('email', $adm_cols)) {
            @mysqli_query($conn, "ALTER TABLE `admins` ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `username`");
        }
        if (!in_array('full_name', $adm_cols)) {
            @mysqli_query($conn, "ALTER TABLE `admins` ADD COLUMN `full_name` VARCHAR(255) NOT NULL DEFAULT 'Administrator'");
        }

        $chk = @mysqli_query($conn, "SELECT COUNT(*) FROM `admins`");
        if ($chk) {
            $cnt = (int)mysqli_fetch_row($chk)[0];
            if ($cnt === 0) {
                @mysqli_query($conn, "INSERT INTO `admins` (`username`, `email`, `password`, `full_name`)
                    VALUES ('admin', 'admin@barangaygo.local', '\$2y\$10\$wEkgmC/g9yF4wZ5Y2HqLKe8k1K8vS6Yk.7Z1YtYj1bVv6lS8jUqWe', 'Barangay Administrator')");
            }
        }
    }

    // 4. TABLE: notifications
    if (!in_array('notifications', $tables)) {
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `notifications` (
            `notif_id` INT AUTO_INCREMENT PRIMARY KEY,
            `resident_id` INT NOT NULL,
            `request_id` INT DEFAULT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

// Patakbuhin ang auto-repair tuwing may koneksyon
if ($conn) {
    ensure_barangaygo_database_ready($conn);
}
?>
