<?php
// +----------------------------------------------------------------------
// | 后台首页 - 系统信息修改
// +----------------------------------------------------------------------
namespace Admin\Controller;

class IndexController extends CommonController {

    // 系统信息页
    public function index() {
        $this->assign('web_name',   get_option('web_name'));
        $this->assign('admin_name', get_option('admin_name'));
        $this->assign('admin_email',get_option('admin_email'));
        $this->display();
    }

    // 保存系统信息（ajax）
    public function save() {
        if (!IS_AJAX) $this->error('非法请求');
        $data = array(
            'web_name'    => trim($_POST['web_name']),
            'admin_name'  => trim($_POST['admin_name']),
            'admin_email' => trim($_POST['admin_email']),
        );
        if ($data['web_name'] === '' || $data['admin_name'] === '') {
            $this->ajaxReturn(array('code' => -1, 'msg' => '网站名称和管理员账号不能为空'));
        }
        // 密码：留空表示不修改
        $pwd = trim($_POST['admin_pwd']);
        foreach ($data as $k => $v) {
            set_option($k, $v);
        }
        $needRelogin = false;
        if ($pwd !== '') {
            if (strlen($pwd) < 6) {
                $this->ajaxReturn(array('code' => -1, 'msg' => '新密码至少6位'));
            }
            set_option('admin_pwd', md5($pwd));
            $needRelogin = true;
        }
        if ($needRelogin) {
            unset($_SESSION['admin_token']);
            $this->ajaxReturn(array('code' => 0, 'msg' => '保存成功，密码已修改，请重新登录', 'relogin' => true, 'url' => U('Admin/Login/index')));
        }
        $this->ajaxReturn(array('code' => 0, 'msg' => '保存成功'));
    }
}
