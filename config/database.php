<?php
/**
 * Database Connection Setup using PDO
 * Auto-creates MySQL database & tables if missing, with SQLite fallback
 * Multi-Day Fishing Boat Catch, Sorting & Dispatch Logistics Management System
 */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'fishing_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            // Step 1: Connect to MySQL server without dbname to ensure DB exists
            $rootDsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
            $tempPdo = new PDO($rootDsn, DB_USER, DB_PASS, $options);
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tempPdo = null;

            // Step 2: Connect to target database
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Step 3: Auto-create tables & default data if missing
            initializeMySqlSchema($pdo);
            initializeDefaultUsers($pdo);

        } catch (PDOException $e) {
            // Fallback to SQLite if MySQL service is unreachable or access denied
            try {
                $sqlitePath = __DIR__ . '/../database/fishing_db.sqlite';
                $sqliteDir = dirname($sqlitePath);
                if (!is_dir($sqliteDir)) {
                    mkdir($sqliteDir, 0777, true);
                }
                $sqliteOptions = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                $pdo = new PDO("sqlite:" . $sqlitePath, null, null, $sqliteOptions);
                initializeSqliteDb($pdo);
            } catch (Exception $fallbackErr) {
                die("<div class='container mt-5'><div class='alert alert-danger shadow-sm'>
                        <h4 class='alert-heading'><i class='fa-solid fa-triangle-exclamation me-2'></i>Database Connection Failed</h4>
                        <p class='mb-0'><strong>Error:</strong> " . htmlspecialchars($fallbackErr->getMessage()) . "</p>
                        <pre>" . htmlspecialchars($fallbackErr->getTraceAsString()) . "</pre>
                    </div></div>");
            }
        }
    }
    
    return $pdo;
}

/**
 * Auto-creates MySQL tables from schema.sql if database is fresh
 */
function initializeMySqlSchema(PDO $pdo) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($check->rowCount() == 0) {
            $sqlFile = __DIR__ . '/../database/schema.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        }
    } catch (Exception $e) {
        // Table created via schema migration
    }
}

/**
 * Ensures default admin & staff seed accounts exist and performs column migrations
 */
function initializeDefaultUsers(PDO $pdo) {
    // Safe column migrations & table creations for existing databases
    $migrations = [
        "ALTER TABLE trips ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE trips ADD COLUMN created_by INT NULL",
        "ALTER TABLE trip_expenses ADD COLUMN bait_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE dispatches ADD COLUMN driver_phone VARCHAR(30) NULL",
        "ALTER TABLE dispatches ADD COLUMN transit_allowance DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "CREATE TABLE IF NOT EXISTS trip_bills (
            bill_id INT AUTO_INCREMENT PRIMARY KEY,
            trip_id INT NOT NULL,
            bill_title VARCHAR(150) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(20) NOT NULL,
            file_size_kb INT NOT NULL DEFAULT 0,
            uploaded_by INT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS direct_buyers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            trip_id INT NOT NULL,
            buyer_name VARCHAR(100) NOT NULL,
            contact_number VARCHAR(30) NULL,
            vehicle_no VARCHAR(30) NULL,
            payment_type VARCHAR(30) NOT NULL DEFAULT 'CASH',
            total_weight_kg DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            total_boxes INT NOT NULL DEFAULT 0,
            total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS direct_buyer_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            buyer_id INT NOT NULL,
            trip_id INT NOT NULL,
            quality_grade VARCHAR(20) NOT NULL,
            fish_species VARCHAR(50) NOT NULL,
            size_category VARCHAR(10) NOT NULL DEFAULT 'L',
            net_weight_kg DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            box_count INT NOT NULL DEFAULT 1,
            rate_per_kg DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (buyer_id) REFERENCES direct_buyers(id) ON DELETE CASCADE,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($migrations as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Ignore if column/table already exists
        }
    }

    try {
        $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
        $staffHash = password_hash('staff123', PASSWORD_BCRYPT);

        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $insert->execute(['admin', 'admin@sealogix.com', $adminHash, 'Admin']);
            $insert->execute(['staff', 'staff@sealogix.com', $staffHash, 'Staff']);
        } else {
            // Ensure seed users have valid password hashes
            $uP = $pdo->prepare("UPDATE users SET password_hash = ?, is_active = 1 WHERE username = ?");
            $uP->execute([$adminHash, 'admin']);
            $uP->execute([$staffHash, 'staff']);
        }
    } catch (Exception $e) {
        // Table schema handled
    }
}


/**
 * Initializes SQLite schema fallback for quick offline local testing
 */
function initializeSqliteDb(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'Staff',
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS trips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            boat_name TEXT NOT NULL,
            reg_number TEXT NOT NULL,
            skipper_name TEXT NOT NULL,
            crew_count INTEGER DEFAULT 1,
            crew_members TEXT NULL,
            departure_date TEXT NOT NULL,
            arrival_date TEXT NULL,
            status TEXT DEFAULT 'DEPARTED',
            is_locked INTEGER DEFAULT 0,
            created_by INTEGER NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS trip_expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            diesel_cost REAL DEFAULT 0,
            ice_cost REAL DEFAULT 0,
            ration_cost REAL DEFAULT 0,
            gas_cost REAL DEFAULT 0,
            maintenance_cost REAL DEFAULT 0,
            bait_cost REAL DEFAULT 0,
            other_cost REAL DEFAULT 0,
            total_expenses REAL DEFAULT 0,
            notes TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS harbour_unloadings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            rate_type TEXT DEFAULT 'FIXED',
            rate_amount REAL DEFAULT 0,
            total_unloaders INTEGER DEFAULT 1,
            total_boxes INTEGER DEFAULT 0,
            total_labour_fee REAL DEFAULT 0,
            notes TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS dispatches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            dispatch_no TEXT NOT NULL UNIQUE,
            lorry_number TEXT NOT NULL,
            driver_name TEXT NOT NULL,
            driver_phone TEXT NULL,
            helper_name TEXT NULL,
            lorry_hire_fee REAL DEFAULT 0,
            helper_fee REAL DEFAULT 0,
            transit_allowance REAL DEFAULT 0,
            total_transport_cost REAL DEFAULT 0,
            dispatch_date TEXT DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS dispatch_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dispatch_id INTEGER NOT NULL,
            trip_id INTEGER NOT NULL,
            quality_grade TEXT NOT NULL,
            fish_species TEXT NOT NULL,
            size_category TEXT DEFAULT 'L',
            box_count INTEGER DEFAULT 1,
            net_weight_kg REAL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS trip_bills (
            bill_id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            bill_title TEXT NOT NULL,
            file_path TEXT NOT NULL,
            file_type TEXT NOT NULL,
            file_size_kb INTEGER DEFAULT 0,
            uploaded_by INTEGER NULL,
            uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS direct_buyers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            buyer_name TEXT NOT NULL,
            contact_number TEXT NULL,
            vehicle_no TEXT NULL,
            payment_type TEXT DEFAULT 'CASH',
            total_weight_kg REAL DEFAULT 0,
            total_boxes INTEGER DEFAULT 0,
            total_amount REAL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS direct_buyer_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            buyer_id INTEGER NOT NULL,
            trip_id INTEGER NOT NULL,
            quality_grade TEXT NOT NULL,
            fish_species TEXT NOT NULL,
            size_category TEXT DEFAULT 'L',
            net_weight_kg REAL DEFAULT 0,
            box_count INTEGER DEFAULT 1,
            rate_per_kg REAL DEFAULT 0,
            total_price REAL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (buyer_id) REFERENCES direct_buyers(id) ON DELETE CASCADE,
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
        );
    ");


    $sqliteMigrations = [
        "ALTER TABLE trips ADD COLUMN is_locked INTEGER DEFAULT 0",
        "ALTER TABLE trips ADD COLUMN created_by INTEGER NULL",
        "ALTER TABLE trip_expenses ADD COLUMN bait_cost REAL DEFAULT 0",
        "ALTER TABLE dispatches ADD COLUMN driver_phone TEXT NULL",
        "ALTER TABLE dispatches ADD COLUMN transit_allowance REAL DEFAULT 0"
    ];
    foreach ($sqliteMigrations as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Ignore if column already exists
        }
    }

    initializeDefaultUsers($pdo);

    $stmt = $pdo->query("SELECT COUNT(*) FROM trips");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO trips (id, boat_name, reg_number, skipper_name, crew_count, crew_members, departure_date, arrival_date, status, is_locked, created_by) VALUES
            (1, 'Ocean Queen', 'IMUL-A-0482-TRINCO', 'Sunil Perera', 5, 'K. Silva, M. Fernando, P. Kumara, S. Bandara, R. Gamage', '2026-08-01', '2026-08-12', 'DISPATCHED', 0, 1),
            (2, 'Blue Dolphin', 'IMUL-A-0911-GALLE', 'Kamal Samarawickrama', 4, 'N. Perera, A. Mendis, W. Dasun, C. Ruwan', '2026-08-08', NULL, 'DEPARTED', 0, 1);

            INSERT INTO trip_expenses (id, trip_id, diesel_cost, ice_cost, ration_cost, gas_cost, maintenance_cost, bait_cost, other_cost, total_expenses, notes) VALUES
            (1, 1, 450000.00, 120000.00, 85000.00, 18000.00, 25000.00, 30000.00, 12000.00, 740000.00, '12-Day Deep Sea Trip');

            INSERT INTO harbour_unloadings (id, trip_id, rate_type, rate_amount, total_unloaders, total_boxes, total_labour_fee, notes) VALUES
            (1, 1, 'PER_BOX', 250.00, 6, 85, 21250.00, 'Dikowita Pier Unloading');

            INSERT INTO dispatches (id, trip_id, dispatch_no, lorry_number, driver_name, driver_phone, helper_name, lorry_hire_fee, helper_fee, transit_allowance, total_transport_cost, dispatch_date, notes) VALUES
            (1, 1, 'DISP-20260812-01', 'WP LE-4892', 'Dhammika Bandara', '0771234567', 'Saman Kumara', 35000.00, 5000.00, 2500.00, 42500.00, '2026-08-12 14:30:00', 'Transport Logistics Waybill');

            INSERT INTO dispatch_items (id, dispatch_id, trip_id, quality_grade, fish_species, size_category, box_count, net_weight_kg) VALUES
            (1, 1, 1, 'Grade 1 (①)', 'Yellowfin Tuna (Kelawalla)', 'L', 35, 1250.50),
            (2, 1, 1, 'Grade 2 (②)', 'Skipjack (Balaya)', 'P', 25, 820.00),
            (3, 1, 1, 'Grade 1 (①)', 'Sailfish (Thalapatha)', 'L', 15, 480.00),
            (4, 1, 1, 'Grade 3 (③)', 'Marlin (Koppara)', 'L', 10, 310.00);
        ");
    }
}
