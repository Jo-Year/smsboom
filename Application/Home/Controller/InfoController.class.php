<?php
// +----------------------------------------------------------------------
// | 前台用户信息修改
// +----------------------------------------------------------------------
namespace Home\Controller;

class InfoController extends CommonController {

    public function index() {
        $this->display();
    }

    // 保存修改（ajax）
    public function save() {
        if (!IS_AJAX) $this->error('非法请求');
        $uid   = $_SESSION['uid'];
        $email = trim($_POST['email']);
        $pwd   = $_POST['pwd'];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '邮箱格式不正确'));
        }
        // 邮箱查重（排除自己）
        $dup = M('user')->where(array('email' => $email, 'id' => array('neq' => $uid)))->find();
        if ($dup) $this->ajaxReturn(array('code' => -1, 'msg' => '该邮箱已被占用'));

        $save = array('email' => $email);
        if ($pwd !== '') {
            if (!preg_match('/^[A-Za-z0-9!@#$%^&*._-]{8,16}$/', $pwd) || preg_match('/[\x80-\xff\s]/', $pwd)) {
                $this->ajaxReturn(array('code' => -1, 'msg' => '密码须为8-16位，不含中文和空格'));
            }
            $save['pwd'] = hash_pwd($pwd);
        }
        M('user')->where(array('id' => $uid))->save($save);
        // 修改完强制重新登录
        M('user')->where(array('id' => $uid))->save(array('session' => ''));
        session_unset();
        session_destroy();
        $this->ajaxReturn(array('code' => 0, 'msg' => '修改成功，请重新登录', 'url' => U('Home/Login/index')));
    }
}
