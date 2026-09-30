<?php
// +----------------------------------------------------------------------
// | 后台登录
// +----------------------------------------------------------------------
namespace Admin\Controller;

class LoginController extends CommonController {

    // 登录页
    public function index() {
        if (!empty($_SESSION['admin_token'])) {
            $this->redirect('Admin/Index/index');
        }
        $this->display();
    }

    // 验证码
    public function verify() {
        $cfg = array(
            'useNoise' => false,
            'useCurve' => false,
            'useImgBg' => false,
            'fontSize' => 16,
            'length'   => 4,
            'imageH'   => 38,
            'imageW'   => 130,
        );
        $Verify = new \Think\Verify($cfg);
        $Verify->entry();
    }

    // 登录提交（ajax）
    public function doLogin() {
        if (!IS_AJAX) $this->error('非法请求');
        $name  = trim($_POST['admin_name']);
        $pwd   = $_POST['admin_pwd'];
        $verify= isset($_POST['verify']) ? trim($_POST['verify']) : '';

        // 验证码校验
        $v = new \Think\Verify();
        if (!$v->check($verify)) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '验证码错误'));
        }
        if ($name === '' || $pwd === '') {
            $this->ajaxReturn(array('code' => -1, 'msg' => '请输入账号和密码'));
        }
        // 比对 config 表
        if ($name !== get_option('admin_name')) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '管理员账号不存在'));
        }
        if (md5($pwd) !== get_option('admin_pwd')) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '管理员密码错误'));
        }
        // 加密登录 session
        $_SESSION['admin_token'] = md5(get_option('admin_name') . get_option('admin_pwd'));
        $this->ajaxReturn(array('code' => 0, 'msg' => '登录成功', 'url' => U('Admin/Index/index')));
    }

    // 退出
    public function logout() {
        unset($_SESSION['admin_token']);
        $this->redirect('Admin/Login/index');
    }

    // ===== 登录记录管理（复用 Login 控制器，避免与登录功能冲突） =====

    // 登录记录列表页
    public function log() {
        $this->display();
    }

    // 登录记录数据接口（ajax）
    public function getLogList() {
        if (!IS_AJAX) $this->error('非法请求');
        $p     = intval($_POST['page']);
        $limit = intval($_POST['limit']);
        if ($p <= 0) $p = 1;
        if ($limit <= 0) $limit = 50;

        $searchV   = trim($_POST['searchVal']);
        $sortField = isset($_POST['sortField']) ? $_POST['sortField'] : 'add_time';
        $sortType  = isset($_POST['sortType']) && $_POST['sortType'] == 'asc' ? 'asc' : 'desc';

        if (!in_array($sortField, array('id', 'add_time'))) $sortField = 'add_time';

        $M = M('login');
        $map = array();
        if ($searchV !== '') {
            $map['uid'] = intval($searchV);
        }

        $count = $M->where($map)->count();
        $list  = $M->where($map)->order("{$sortField} {$sortType}")->page($p, $limit)->select();
        $this->ajaxReturn(array('code' => 0, 'msg' => '', 'count' => $count, 'data' => $list));
    }

    // 删除单条登录记录（ajax）
    public function delLog() {
        if (!IS_AJAX) $this->error('非法请求');
        $id = intval($_POST['id']);
        if ($id <= 0) $this->ajaxReturn(array('code' => -1, 'msg' => '参数错误'));
        M('login')->where(array('id' => $id))->delete();
        $this->ajaxReturn(array('code' => 0, 'msg' => '删除成功'));
    }
}
