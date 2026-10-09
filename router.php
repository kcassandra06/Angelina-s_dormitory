<?php
// Router for PHP's development server. Apache uses .htaccess instead.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(preg_match('#(?:^|/)(?:storage|app|database|tests|\.git|\.qodo|config[^/]*|README\.md|start\.ps1|router\.php)(?:/|$)#i',$path)||str_contains($path,'..')) { http_response_code(403); exit('Forbidden'); }
if($path==='/' || $path==='/index.php') { require __DIR__.'/index.php'; return true; }
if(str_starts_with($path,'/assets/') && is_file(__DIR__.$path) && preg_match('/\.(css|js|svg|png|jpg|jpeg|webp|avif|woff|woff2)$/',$path)) return false;
http_response_code(404); echo 'Not found';
