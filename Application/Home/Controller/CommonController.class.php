<?php
// +----------------------------------------------------------------------
// | 前台公共控制器：用户登录校验
// +----------------------------------------------------------------------
namespace Home\Controller;
use Think\Controller;

class CommonController extends Controller {

    protected function _initialize() {
        $opt = get_all_options();
        $this->assign('web_name', isset($opt['web_name']) ? $opt['web_name'] : '发卡网');

        $allow = array('Login' => array('index', 'doLogin', 'doRegister', 'logout'));
        $controller = CONTROLLER_NAME;
        $action     = ACTION_NAME;

        if (!isset($allow[$controller]) || !in_array($action, $allow[$controller])) {
            if (empty($_SESSION['uid']) || empty($_SESSION['user_token'])) {
                if (IS_AJAX) {
                    $this->ajaxReturn(array('code' => -1, 'msg' => '未登录', 'login' => true));
                }
                $this->redirect('Home/Login/index');
            }
            // 校验 session 是否与数据库一致（被其他设备登录则失效）
            $user = M('user')->where(array('id' => $_SESSION['uid']))->find();
            if (!$user || $user['session'] !== $_SESSION['user_token']) {
                // 被挤下线：先清空本地 session，避免登录页又凭残留 session 弹回首页造成死循环
                unset($_SESSION['uid'], $_SESSION['user_token']);
                if (IS_AJAX) {
                    $this->ajaxReturn(array('code' => -1, 'msg' => '账号已在其他设备登录', 'login' => true, 'kick' => true));
                }
                $this->redirect('Home/Login/index', array('show' => 1));
            }
            // 把当前用户 assign 到所有前台页面
            $this->loginUser = $user;
            $this->assign('loginUser', $user);
        }
    }
}
