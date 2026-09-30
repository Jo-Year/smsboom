<?php
return array(
    'DB_TYPE'   => 'mysql',
    'DB_HOST'   => 'localhost',
    'DB_PORT'   => '3306',
    'DB_NAME'   => 'root',
    'DB_USER'   => '',
    'DB_PWD'    => '',
    'DB_PREFIX' => 'sms_',
    'DB_CHARSET'=> 'utf8',
    'URL_MODEL'          => 2, // REWRITE 重写模式，配合 .htaccess 去掉 index.php
    'URL_HTML_SUFFIX'     => '', // 去掉 .html 后缀
    'URL_PATHINFO_DEPR'  => '/',
    'DEFAULT_MODULE'     => 'Home',
    'MODULE_ALLOW_LIST' => array('Home','Admin'),
    'DEFAULT_AJAX_RETURN'=> 'JSON',
    'TMPL_L_DELIM'       => '<{',
    'TMPL_R_DELIM'       => '}>',
    'URL_ROUTER_ON'     => true,
    'URL_ROUTE_RULES'   => array(
        'admin/code/list$' => 'Admin/Code/lst',
        'admin/code/add$'  => 'Admin/Code/add',
    ),
);
