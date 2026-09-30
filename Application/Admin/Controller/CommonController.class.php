<?php
// +----------------------------------------------------------------------
// | 后台公共控制器：登录校验 + 全局配置 + layout 公共变量
// +----------------------------------------------------------------------
namespace Admin\Controller;
use Think\Controller;

class CommonController extends Controller {

    protected function _initialize() {
        // 加载系统配置
        $opt = get_all_options();
        $this->assign('web_name', isset($opt['web_name']) ? $opt['web_name'] : '发卡网');
        $this->assign('admin_name', isset($opt['admin_name']) ? $opt['admin_name'] : 'admin');

        // 登录白名单：登录页、登录提交、验证码、退出不需要校验
        $allow = array('Login' => array('index', 'doLogin', 'verify', 'logout'));
        $controller = CONTROLLER_NAME;
        $action     = ACTION_NAME;

        if (!isset($allow[$controller]) || !in_array($action, $allow[$controller])) {
            if (empty($_SESSION['admin_token'])) {
                if (IS_AJAX) {
                    $this->ajaxReturn(array('code' => -1, 'msg' => '登录已失效，请重新登录', 'login' => true));
                }
                $this->redirect('Admin/Login/index');
            }
            // 校验 token 与当前 config 是否一致
            $expect = md5(get_option('admin_name') . get_option('admin_pwd'));
            if ($_SESSION['admin_token'] !== $expect) {
                unset($_SESSION['admin_token']);
                $this->redirect('Admin/Login/index');
            }
        }
    }
}
