<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| 邮件发送相关配置
| -------------------------------------------------------------------------
| $config['sleep_time'] = 2; // 发送间隔，单位 秒
| $config['limit'] = 1000; // 每次发送的量
| $config['site_url'] = 'http://domain.com' // 结尾请不要带 / ，问卷访问地址，用于邮件内容中显示问卷地址
| $config['root_path'] = '/api/'; // 结尾请不要带 / ，对应 CI 框架主入口文件, 即 index.php 所在目录
| $config['mail_from'] = 'no-reply@webmaster.com'; // 邮件的发件人
| $config['poster_name'] = '网站管理员'; // 邮件的发件人的名称
| $config['php_path'] = '/Applications/MAMP/bin/php/php7.1.20/bin/php'; // php所在路径，可通过 bash 执行 which php 查看
*/

$config['sleep_time'] = 2;
$config['limit'] = 1000;
$config['site_url'] = 'http://localhost:9090';
$config['root_path'] = '/api/';
$config['mail_from'] = 'contact@52admin.net';
$config['poster_name'] = '网站管理员';
$config['php_path'] = '/Applications/MAMP/bin/php/php7.1.20/bin/php';


/*
| -------------------------------------------------------------------------
| 用户相关配置
| -------------------------------------------------------------------------
| $config['not_active_time'] = 2592000000; // 30天，单位 毫秒， 0 则关闭该功能
*/
$config['not_active_time'] = 2592000000;
