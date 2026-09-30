<?php
// +----------------------------------------------------------------------
// | 卡密系统 在线安装向导（独立入口，不依赖 ThinkPHP 框架）
// +----------------------------------------------------------------------
// | 步骤1：填写数据库连接信息 -> ajax 测试连接并导入数据表
// | 步骤2：设置管理员账号密码 -> 写入 config 表 -> 写 install.lock -> 跳后台
// +----------------------------------------------------------------------

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
session_start();

define('LOCK_FILE', __DIR__ . '/Application/install.lock');
define('CONF_FILE',  __DIR__ . '/Application/Common/Conf/config.php');

// 已安装则禁止再次访问安装页
if (file_exists(LOCK_FILE)) {
    header('Location: ./index.php');
    exit;
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

/* ---------------- ajax 处理 ---------------- */
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'dbtest') {
        // 接收数据库信息
        $host = trim($_POST['host']);
        $port = intval($_POST['port']);
        $user = trim($_POST['user']);
        $pass = $_POST['pass'];
        $dbname = trim($_POST['dbname']);
        $prefix = trim($_POST['prefix']);

        if ($host === '' || $dbname === '' || $user === '') {
            echo json_encode(['code' => -1, 'msg' => '数据库地址/库名/用户不能为空']);
            exit;
        }

        $mysqli = @new mysqli($host, $user, $pass, '', $port);
        if ($mysqli->connect_errno) {
            echo json_encode(['code' => -1, 'msg' => '数据库连接失败：' . $mysqli->connect_error]);
            exit;
        }
        $mysqli->set_charset('utf8');

        // 选择或创建数据库
        if (!$mysqli->select_db($dbname)) {
            if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8")) {
                echo json_encode(['code' => -1, 'msg' => '创建数据库失败：' . $mysqli->error]);
                exit;
            }
            $mysqli->select_db($dbname);
        }

        // 建表 SQL（注意表前缀动态替换）
        $t_config    = $prefix . 'config';
        $t_code      = $prefix . 'code';
        $t_login     = $prefix . 'login';
        $t_user      = $prefix . 'user';

        $sql = "
        CREATE TABLE IF NOT EXISTS `{$t_config}` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `vkey` varchar(50) NOT NULL DEFAULT '',
          `value` varchar(255) NOT NULL DEFAULT '',
          PRIMARY KEY (`id`),
          UNIQUE KEY `vkey` (`vkey`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `{$t_code}` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `uid` int(11) NOT NULL DEFAULT '0' COMMENT '使用用户编号',
          `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型1=10分钟 2=月 3=年',
          `key` varchar(32) NOT NULL DEFAULT '' COMMENT '卡密',
          `add_time` datetime DEFAULT NULL COMMENT '生成时间',
          `use_time` datetime DEFAULT NULL COMMENT '使用时间',
          PRIMARY KEY (`id`),
          UNIQUE KEY `key` (`key`),
          KEY `uid` (`uid`),
          KEY `type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `{$t_login}` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `uid` int(11) NOT NULL DEFAULT '0',
          `ip` varchar(45) NOT NULL DEFAULT '',
          `add_time` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `uid` (`uid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `{$t_user}` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(100) NOT NULL DEFAULT '',
          `pwd` varchar(64) NOT NULL DEFAULT '',
          `session` varchar(64) NOT NULL DEFAULT '',
          `add_time` datetime DEFAULT NULL,
          `end_time` datetime DEFAULT NULL,
          `process` int(11) NOT NULL DEFAULT '0' COMMENT '进程',
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

        if (!$mysqli->multi_query($sql)) {
            echo json_encode(['code' => -1, 'msg' => '建表失败：' . $mysqli->error]);
            exit;
        }
        // 消耗多查询结果集
        while ($mysqli->more_results() && $mysqli->next_result()) { ; }

        // 写入默认 config 数据
        $defaults = [
            ['web_name',   'smsbb.top短信测试promax版'],
            ['admin_name', 'admin'],
            ['admin_pwd',  md5('123456')],
            ['admin_email',''],
        ];
        $stmt = $mysqli->prepare("INSERT INTO `{$t_config}` (`vkey`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `vkey`=`vkey`");
        foreach ($defaults as $d) {
            $stmt->bind_param('ss', $d[0], $d[1]);
            $stmt->execute();
        }
        $stmt->close();

        // 生成 TP 数据库配置文件
        $confStr = "<?php\nreturn array(\n";
        $confStr .= "    'DB_TYPE'   => 'mysql',\n";
        $confStr .= "    'DB_HOST'   => '" . addslashes($host) . "',\n";
        $confStr .= "    'DB_PORT'   => '" . intval($port) . "',\n";
        $confStr .= "    'DB_NAME'   => '" . addslashes($dbname) . "',\n";
        $confStr .= "    'DB_USER'   => '" . addslashes($user) . "',\n";
        $confStr .= "    'DB_PWD'    => '" . addslashes($pass) . "',\n";
        $confStr .= "    'DB_PREFIX' => '" . addslashes($prefix) . "',\n";
        $confStr .= "    'DB_CHARSET'=> 'utf8',\n";
        // 全局运行时配置
        $confStr .= "    'URL_MODEL'          => 2, // REWRITE 重写模式，配合 .htaccess 去掉 index.php\n";
        $confStr .= "    'URL_HTML_SUFFIX'     => '', // 去掉 .html 后缀\n";
        $confStr .= "    'URL_PATHINFO_DEPR'  => '/',\n";
        $confStr .= "    'DEFAULT_MODULE'     => 'Home',\n";
        $confStr .= "    'MODULE_ALLOW_LIST' => array('Home','Admin'),\n";
        $confStr .= "    'DEFAULT_AJAX_RETURN'=> 'JSON',\n";
        $confStr .= "    'TMPL_L_DELIM'       => '<{',\n";
        $confStr .= "    'TMPL_R_DELIM'       => '}>',\n";
        $confStr .= "    'URL_ROUTER_ON'     => true,\n";
        $confStr .= "    'URL_ROUTE_RULES'   => array(\n";
        $confStr .= "        'admin/code/list$' => 'Admin/Code/lst',\n";
        $confStr .= "        'admin/code/add$'  => 'Admin/Code/add',\n";
        $confStr .= "    ),\n";
        $confStr .= ");\n";

        if (!is_dir(dirname(CONF_FILE))) {
            @mkdir(dirname(CONF_FILE), 0755, true);
        }
        if (file_put_contents(CONF_FILE, $confStr) === false) {
            echo json_encode(['code' => -1, 'msg' => '配置文件写入失败，请检查 Application/Common/Conf 目录权限']);
            exit;
        }

        // 暂存连接信息到 session，供下一步用
        $_SESSION['install_db'] = compact('host','port','user','pass','dbname','prefix');
        $mysqli->close();

        echo json_encode(['code' => 0, 'msg' => '数据库连接成功，数据表已导入', 'next' => true]);
        exit;
    }

    if ($action === 'setupadmin') {
        if (empty($_SESSION['install_db'])) {
            echo json_encode(['code' => -1, 'msg' => '会话已失效，请从第一步重新开始']);
            exit;
        }
        $adminName = trim($_POST['admin_name']);
        $adminPwd  = $_POST['admin_pwd'];
        if ($adminName === '' || $adminPwd === '') {
            echo json_encode(['code' => -1, 'msg' => '管理员账号和密码不能为空']);
            exit;
        }
        $db = $_SESSION['install_db'];
        $mysqli = @new mysqli($db['host'], $db['user'], $db['pass'], $db['dbname'], $db['port']);
        if ($mysqli->connect_errno) {
            echo json_encode(['code' => -1, 'msg' => '数据库连接失败：' . $mysqli->connect_error]);
            exit;
        }
        $mysqli->set_charset('utf8');
        $tc = $db['prefix'] . 'config';
        $stmt = $mysqli->prepare("INSERT INTO `{$tc}` (`vkey`,`value`) VALUES ('admin_name',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $stmt->bind_param('s', $adminName);
        $stmt->execute();
        $stmt->close();
        $pwdHash = md5($adminPwd);
        $stmt = $mysqli->prepare("INSERT INTO `{$tc}` (`vkey`,`value`) VALUES ('admin_pwd',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $stmt->bind_param('s', $pwdHash);
        $stmt->execute();
        $stmt->close();
        $mysqli->close();

        // 写安装锁
        @file_put_contents(LOCK_FILE, 'install ok at ' . date('Y-m-d H:i:s'));
        unset($_SESSION['install_db']);
        echo json_encode(['code' => 0, 'msg' => '安装完成，正在进入后台...', 'url' => './index.php/Admin/Login/index']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>卡密系统 - 在线安装</title>
<link rel="stylesheet" href="https://cdn.staticfile.org/layui/2.9.17/css/layui.css">
<style>
  body{background:#f2f3f5;}
  .install-box{max-width:560px;margin:60px auto;background:#fff;border-radius:6px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:30px 40px;}
  .install-title{text-align:center;font-size:22px;font-weight:600;margin-bottom:25px;color:#333;}
  .steps{text-align:center;margin-bottom:25px;}
</style>
</head>
<body>
<div class="install-box">
  <div class="install-title">卡密系统安装向导</div>
  <div class="steps layui-text">
    <?php if($step==1){ echo '<span class="layui-badge layui-bg-blue">1 数据库配置</span>　→　<span class="layui-badge">2 管理员设置</span>'; } else { echo '<span class="layui-badge">1 数据库配置</span>　→　<span class="layui-badge layui-bg-blue">2 管理员设置</span>'; } ?>
  </div>

  <?php if($step==1): ?>
  <form class="layui-form" id="dbForm">
    <div class="layui-form-item"><input class="layui-input" name="host" placeholder="数据库地址" value="localhost"></div>
    <div class="layui-form-item"><input class="layui-input" name="dbname" placeholder="数据库库名" value=""></div>
    <div class="layui-form-item"><input class="layui-input" name="user" placeholder="数据库用户" value="root"></div>
    <div class="layui-form-item"><input class="layui-input" type="password" name="pass" placeholder="数据库密码" value=""></div>
    <div class="layui-form-item"><input class="layui-input" name="port" placeholder="端口" value="3306"></div>
    <div class="layui-form-item"><input class="layui-input" name="prefix" placeholder="数据库表前缀" value="wp_"></div>
    <div class="layui-form-item">
      <button class="layui-btn layui-btn-fluid" lay-submit id="btn1">测试连接并导入数据库</button>
    </div>
  </form>
  <?php else: ?>
  <form class="layui-form" id="adminForm">
    <div class="layui-form-item"><input class="layui-input" name="admin_name" placeholder="管理员账号" value="admin"></div>
    <div class="layui-form-item"><input class="layui-input" type="password" name="admin_pwd" placeholder="管理员密码" value=""></div>
    <div class="layui-form-item">
      <button class="layui-btn layui-btn-fluid" lay-submit id="btn2">完成安装并进入后台</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<script src="https://cdn.staticfile.org/layui/2.9.17/layui.js"></script>
<script>
layui.use(['layer','form'], function(){
  var $ = layui.$, layer = layui.layer;
  $('#btn1').on('click', function(e){
    e.preventDefault();
    var load = layer.load(2);
    $.post('?step=1', $('#dbForm').serialize()+'&action=dbtest', function(res){
      layer.close(load);
      if(res.code === 0){ layer.msg(res.msg, {icon:1, time:1000}, function(){ location.href='?step=2'; }); }
      else { layer.msg(res.msg, {icon:2}); }
    }, 'json');
  });
  $('#btn2').on('click', function(e){
    e.preventDefault();
    var load = layer.load(2);
    $.post('?step=2', $('#adminForm').serialize()+'&action=setupadmin', function(res){
      layer.close(load);
      if(res.code === 0){ layer.msg(res.msg, {icon:1, time:1000}, function(){ location.href=res.url; }); }
      else { layer.msg(res.msg, {icon:2}); }
    }, 'json');
  });
});
</script>
</body>
</html>
