<?php
// Restore the provided SQL snapshot into a disposable database to prove it imports.
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/config.php';
$test='angelinas_test_'.bin2hex(random_bytes(6)); $pdo=mysql_connection(false);
try {
    $pdo->exec('CREATE DATABASE `'.$test.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `'.$test.'`');
    $pdo->exec(file_get_contents(dirname(__DIR__).'/database/angelinas_dormitory.sql'));
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if(count($tables)!==20) throw new RuntimeException('Unexpected exported table count.');
    foreach(['users','tenants','rooms','billing','deposits'] as $table) if((int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()===0) throw new RuntimeException('Export is missing '.$table.' records.');
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=?');$q->execute([$test]);
    if((int)$q->fetchColumn()<20) throw new RuntimeException('Missing foreign keys in restored database.');
    echo 'PASS: SQL snapshot restores all 20 MySQL tables, migrated records, and foreign keys.'.PHP_EOL;
} finally { $pdo->exec('DROP DATABASE IF EXISTS `'.$test.'`'); }
