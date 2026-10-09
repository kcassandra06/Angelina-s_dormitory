<?php
function database_config(): array {
    $file=dirname(__DIR__).'/config.database.php'; $cfg=is_file($file)?require $file:[];
    return ['host'=>getenv('DORMITORY_DB_HOST')?:($cfg['host']??'127.0.0.1'), 'port'=>getenv('DORMITORY_DB_PORT')?:($cfg['port']??3306), 'database'=>getenv('DORMITORY_DB_NAME')?:($cfg['database']??'angelinas_dormitory'), 'username'=>getenv('DORMITORY_DB_USER')?:($cfg['username']??'root'), 'password'=>getenv('DORMITORY_DB_PASSWORD')!==false?getenv('DORMITORY_DB_PASSWORD'):($cfg['password']??'')];
}
function mysql_connection(bool $selectDatabase=true): PDO {
    $c=database_config(); if(!preg_match('/^[a-zA-Z0-9_]+$/',$c['database'])) throw new RuntimeException('Invalid database name.');
    $dsn='mysql:host='.$c['host'].';port='.(int)$c['port'].';charset=utf8mb4'.($selectDatabase?';dbname='.$c['database']:'');
    $pdo=new PDO($dsn,$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone='+00:00'"); return $pdo;
}
