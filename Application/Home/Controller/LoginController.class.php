<?php
// +----------------------------------------------------------------------
// | 前台登录 / 注册
// +----------------------------------------------------------------------
namespace Home\Controller;

class LoginController extends CommonController {

    // 登录/注册页
    public function index() {
        if (!empty($_SESSION['uid']) && !empty($_SESSION['user_token'])) {
            $this->redirect('Home/Index/index');
        }
        $this->assign('show', intval($_GET['show'])); // 是否显示"其他设备登录"弹窗
        $this->display();
    }

    // 登录（ajax）
    public function doLogin() {
        if (!IS_AJAX) $this->error('非法请求');
        $email = trim($_POST['email']);
        $pwd   = $_POST['pwd'];
        if ($email === '' || $pwd === '') $this->ajaxReturn(array('code' => -1, 'msg' => '请输入邮箱和密码'));

        $user = M('user')->where(array('email' => $email))->find();
        if (!$user || $user['pwd'] !== hash_pwd($pwd)) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '邮箱或密码错误'));
        }
        // 生成本地 session 并写入数据库
        $token = gen_session_token();
        M('user')->where(array('id' => $user['id']))->save(array('session' => $token));
        $_SESSION['uid']         = $user['id'];
        $_SESSION['user_token']  = $token;
        write_login_log($user['id']);
        $this->ajaxReturn(array('code' => 0, 'msg' => '登录成功', 'url' => U('Home/Index/index')));
    }

    // 注册（ajax）
    public function doRegister() {
        if (!IS_AJAX) $this->error('非法请求');
        $email    = trim($_POST['email']);
        $pwd      = $_POST['pwd'];
        $pwd2     = $_POST['pwd2'];
        $code     = trim($_POST['code']);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '邮箱格式不正确'));
        }
        // 邮箱查重
        if (M('user')->where(array('email' => $email))->find()) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '该邮箱已被注册'));
        }
        // 密码 8-16 位，不含中文空格
        if (!preg_match('/^[A-Za-z0-9!@#$%^&*._-]{8,16}$/', $pwd) || preg_match('/[\x80-\xff\s]/', $pwd)) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '密码须为8-16位，不含中文和空格'));
        }
        if ($pwd !== $pwd2) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '两次输入的密码不一致'));
        }
        // 卡密校验
        if ($code === '') $this->ajaxReturn(array('code' => -1, 'msg' => '请输入卡密'));
        $codeRow = M('code')->where(array('key' => $code))->find();
        if (!$codeRow) $this->ajaxReturn(array('code' => -1, 'msg' => '卡密不存在'));
        if (!empty($codeRow['use_time'])) $this->ajaxReturn(array('code' => -1, 'msg' => '该卡密已被使用'));

        // 计算到期时间
        $endTime = calc_end_time(null, $codeRow['type']);

        // 事务：建用户 + 绑定卡密
        $uid = M('user')->add(array(
            'email'    => $email,
            'pwd'      => hash_pwd($pwd),
            'session'  => '',
            'add_time' => now_dt(),
            'end_time' => $endTime,
            'process'  => type_to_process($codeRow['type']),
        ));
        if (!$uid) $this->ajaxReturn(array('code' => -1, 'msg' => '注册失败，请重试'));

        $token = gen_session_token();
        M('user')->where(array('id' => $uid))->save(array('session' => $token));
        M('code')->where(array('id' => $codeRow['id']))->save(array(
            'uid'      => $uid,
            'use_time' => now_dt(),
        ));
        $_SESSION['uid']        = $uid;
        $_SESSION['user_token'] = $token;
        write_login_log($uid);

        $this->ajaxReturn(array('code' => 0, 'msg' => '注册成功，已自动登录', 'url' => U('Home/Index/index')));
    }

    // 退出
    public function logout() {
        if (!empty($_SESSION['uid'])) {
            M('user')->where(array('id' => $_SESSION['uid']))->save(array('session' => ''));
        }
        session_unset();
        session_destroy();
        $this->redirect('Home/Login/index');
    }
}
