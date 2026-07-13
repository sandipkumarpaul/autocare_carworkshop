<?php
/**
 * Database and System Diagnostics Tool
 * Car Workshop Appointment System
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AutoCare Workshop — Diagnostics Tool</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0e17;
            color: #f1f5f9;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 { color: #3b82f6; border-bottom: 2px solid #1e293b; padding-bottom: 10px; }
        .card {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.85rem;
        }
        .status-ok { background: #064e3b; color: #34d399; }
        .status-fail { background: #7f1d1d; color: #f87171; }
        .status-warn { background: #78350f; color: #fbbf24; }
        pre {
            background: #0f172a;
            padding: 1rem;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #1e293b;
            color: #94a3b8;
        }
        .step-title { font-weight: bold; font-size: 1.1rem; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .hint { margin-top: 10px; color: #38bdf8; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>🔧 System Diagnostics Tool</h1>
    <p>Use this page to check if your PHP environment, MySQL connection, and database tables are configured properly.</p>

    <!-- STEP 1: PHP extensions -->
    <div class="card">
        <div class="step-title">
            <span>Step 1: PHP Environment & Extensions</span>
            <?php
            $pdo_loaded = extension_loaded('pdo');
            $pdo_mysql_loaded = extension_loaded('pdo_mysql');
            if ($pdo_loaded && $pdo_mysql_loaded) {
                echo '<span class="status status-ok">PASS</span>';
            } else {
                echo '<span class="status status-fail">FAIL</span>';
            }
            ?>
        </div>
        <p>PHP Version: <strong><?php echo PHP_VERSION; ?></strong></p>
        <p>PDO Extension: <?php echo $pdo_loaded ? '✅ Loaded' : '❌ NOT LOADED'; ?></p>
        <p>PDO MySQL Extension: <?php echo $pdo_mysql_loaded ? '✅ Loaded' : '❌ NOT LOADED'; ?></p>
        <?php if (!$pdo_mysql_loaded): ?>
            <div class="hint">💡 <strong>Hint:</strong> You need to enable the <code>pdo_mysql</code> extension in your <code>php.ini</code> configuration file inside XAMPP.</div>
        <?php endif; ?>
    </div>

    <!-- STEP 2: Database Connection Config & Connection test -->
    <div class="card">
        <div class="step-title">
            <span>Step 2: MySQL Server Connection</span>
            <?php
            require_once 'config.php';
            
            $db_connected = false;
            $conn_error = '';
            
            try {
                // Try connecting to mysql server without selecting db first to see if MySQL is alive
                $dsn_no_db = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
                $pdo_test = new PDO($dsn_no_db, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3 // 3 seconds timeout
                ]);
                $db_connected = true;
                echo '<span class="status status-ok">PASS</span>';
            } catch (PDOException $e) {
                $conn_error = $e->getMessage();
                echo '<span class="status status-fail">FAIL</span>';
            }
            ?>
        </div>
        <p>Config Host: <code><?php echo DB_HOST; ?></code></p>
        <p>Config User: <code><?php echo DB_USER; ?></code></p>
        <p>Config Password: <code><?php echo empty(DB_PASS) ? '(empty)' : '********'; ?></code></p>
        
        <?php if ($db_connected): ?>
            <p style="color: #34d399;">✅ Connected to MySQL server successfully.</p>
        <?php else: ?>
            <p style="color: #f87171;">❌ Connection to MySQL failed.</p>
            <p><strong>Error Message:</strong></p>
            <pre><?php echo htmlspecialchars($conn_error); ?></pre>
            <div class="hint">💡 <strong>Hint:</strong> Make sure MySQL is running in your XAMPP Control Panel. If you changed the MySQL password or port, update it in <code>config.php</code>.</div>
        <?php endif; ?>
    </div>

    <!-- STEP 3: Database Verification -->
    <div class="card">
        <div class="step-title">
            <span>Step 3: Database existence check ('<?php echo DB_NAME; ?>')</span>
            <?php
            $db_exists = false;
            $db_error = '';
            if ($db_connected) {
                try {
                    $pdo_test->query("USE `" . DB_NAME . "`");
                    $db_exists = true;
                    echo '<span class="status status-ok">PASS</span>';
                } catch (PDOException $e) {
                    $db_error = $e->getMessage();
                    echo '<span class="status status-fail">FAIL</span>';
                }
            } else {
                echo '<span class="status status-fail">FAIL (No MySQL connection)</span>';
            }
            ?>
        </div>
        <?php if ($db_exists): ?>
            <p style="color: #34d399;">✅ Database <strong><?php echo DB_NAME; ?></strong> exists and is accessible.</p>
        <?php else: ?>
            <p style="color: #f87171;">❌ Database could not be accessed.</p>
            <?php if (!empty($db_error)): ?>
                <p><strong>Error Message:</strong></p>
                <pre><?php echo htmlspecialchars($db_error); ?></pre>
                <div class="hint">💡 <strong>Hint:</strong> You might not have imported <code>db.sql</code> yet. Go to phpMyAdmin (<code>http://localhost/phpmyadmin/</code>), create a database named <code>car_workshop</code>, and import <code>db.sql</code>.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- STEP 4: Tables and Seed Data Verification -->
    <div class="card">
        <div class="step-title">
            <span>Step 4: Table Schema & Seed Data Check</span>
            <?php
            $tables_ok = false;
            $table_details = [];
            if ($db_exists) {
                try {
                    $required_tables = ['mechanics', 'appointments', 'users'];
                    $existing_tables = [];
                    
                    $stmt = $pdo_test->query("SHOW TABLES");
                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                        $existing_tables[] = $row[0];
                    }
                    
                    $all_present = true;
                    foreach ($required_tables as $table) {
                        $present = in_array($table, $existing_tables);
                        $count = 0;
                        if ($present) {
                            $count = $pdo_test->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                        } else {
                            $all_present = false;
                        }
                        $table_details[$table] = [
                            'present' => $present,
                            'count' => $count
                        ];
                    }
                    
                    if ($all_present) {
                        $tables_ok = true;
                        echo '<span class="status status-ok">PASS</span>';
                    } else {
                        echo '<span class="status status-fail">FAIL</span>';
                    }
                } catch (PDOException $e) {
                    echo '<span class="status status-fail">ERROR: ' . htmlspecialchars($e->getMessage()) . '</span>';
                }
            } else {
                echo '<span class="status status-fail">FAIL (No DB)</span>';
            }
            ?>
        </div>
        <?php if ($db_exists): ?>
            <ul>
                <?php foreach ($table_details as $tbl => $info): ?>
                    <li>
                        <strong><?php echo $tbl; ?></strong>: 
                        <?php echo $info['present'] ? '✅ Present' : '❌ MISSING'; ?> 
                        (<?php echo $info['count']; ?> rows)
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$tables_ok): ?>
                <div class="hint">💡 <strong>Hint:</strong> Please run/import the SQL statements inside <code>db.sql</code> in your database to create all the required tables and insert seeded data.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
