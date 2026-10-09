<?php
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
require dirname(__DIR__).'/app/config.php';
$name=database_config()['database'];
if(!preg_match('/^angelinas_test_[a-f0-9]{12}$/',$name)) throw new RuntimeException('Tests may only access their own disposable database.');
if(in_array('--drop-test-database',$argv,true)) { mysql_connection(false)->exec('DROP DATABASE IF EXISTS `'.$name.'`'); exit; }
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$q=mysql_connection()->prepare($input['sql']); $q->execute($input['params']??[]);
echo json_encode($q->columnCount()?$q->fetchAll(PDO::FETCH_NUM):[],JSON_THROW_ON_ERROR);
