<?php
// +----------------------------------------------------------------------
// | 全局公共函数
// +----------------------------------------------------------------------

// 强制 URL 配置：干净地址 /Home/Login，不带 index.php、不带 ?s=、不带 .html
// 放在 common.php 每次请求都会加载，覆盖 config 缓存，确保生效
C('URL_MODEL', 2);         // 2=REWRITE 重写模式（地址栏不带 index.php）
C('URL_HTML_SUFFIX', '');  // 去掉 .html
C('URL_PATHINFO_DEPR', '/');

/**
 * 读取 config 系统信息表（vkey => value），带静态缓存
 * @return array
 */
function get_all_options() {
    static $opt = null;
    if ($opt === null) {
        $opt = array();
        $rows = M('config')->select();
        if ($rows) {
            foreach ($rows as $r) {
                $opt[$r['vkey']] = $r['value'];
            }
        }
    }
    return $opt;
}

/**
 * 读取单个配置项
 */
function get_option($key) {
    $opt = get_all_options();
    return isset($opt[$key]) ? $opt[$key] : '';
}

/**
 * 写入单个配置项
 */
function set_option($key, $value) {
    $map = array('vkey' => $key);
    $exists = M('config')->where($map)->find();
    if ($exists) {
        return M('config')->where($map)->save(array('value' => $value));
    } else {
        return M('config')->add(array('vkey' => $key, 'value' => $value));
    }
}

/**
 * 生成16位数字字母混合卡密（去除易混淆字符 0/O 1/l/I）
 */
function gen_code($len = 16) {
    $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $len; $i++) {
        $code .= $chars[mt_rand(0, $max)];
    }
    return $code;
}

/**
 * 密码哈希
 */
function hash_pwd($pwd) {
    return md5(md5($pwd) . 'km_salt_2024');
}

/**
 * 生成随机会话 token
 */
function gen_session_token() {
    return md5(uniqid(mt_rand(), true) . microtime(true));
}

/**
 * 当前时间字符串 Y-m-d H:i:s
 */
function now_dt() {
    return date('Y-m-d H:i:s');
}

/**
 * 根据卡密类型计算到期时间
 * @param string $base 基准时间（用户当前 end_time，若已过期则取 now）
 * @param int $type 1=10分钟 2=1月 3=1年
 * @return string
 */
function calc_end_time($base, $type) {
    $now = time();
    $baseTs = $base ? strtotime($base) : $now;
    if ($baseTs < $now) $baseTs = $now; // 已过期则从现在起算
    if ($type == 1) {
        $add = 600; // 10分钟
        return date('Y-m-d H:i:s', $baseTs + $add);
    } elseif ($type == 2) {
        return date('Y-m-d H:i:s', strtotime('+1 month', $baseTs));
    } elseif ($type == 3) {
        return date('Y-m-d H:i:s', strtotime('+1 year', $baseTs));
    }
    return date('Y-m-d H:i:s', $baseTs);
}

/**
 * 根据卡密类型返回对应的进程值
 * type=1(10分钟) -> 进程1；type=2(月卡)/3(年卡) -> 进程3
 */
function type_to_process($type) {
    $map = array(1 => 1, 2 => 3, 3 => 5);
    return isset($map[$type]) ? $map[$type] : 0;
}

/**
 * 判断某用户是否为会员（end_time 大于当前时间）
 */
function is_member($endTime) {
    return $endTime && strtotime($endTime) > time();
}

/**
 * 写入登录记录
 */
function write_login_log($uid) {
    M('login')->add(array(
        'uid'      => intval($uid),
        'ip'       => get_client_ip(),
        'add_time' => now_dt(),
    ));
}
