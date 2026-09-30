<?php
// +----------------------------------------------------------------------
// | 前台首页
// +----------------------------------------------------------------------
namespace Home\Controller;

class IndexController extends CommonController {

    // 首页：展示用户信息 + 最近5条登录记录
    public function index() {
        $uid = $this->loginUser['id'];
        $logs = M('login')->where(array('uid' => $uid))->order('id desc')->limit(5)->select();
        $this->assign('logs', $logs);
        $this->assign('is_member', is_member($this->loginUser['end_time']) ? '是' : '否');
        $this->display();
    }

    // 前端每10秒轮询：校验本地 session 与数据库是否一致
    public function poll() {
        if (!IS_AJAX) $this->error('非法请求');
        $user = M('user')->where(array('id' => $_SESSION['uid']))->find();
        if (!$user || $user['session'] !== $_SESSION['user_token']) {
            $this->ajaxReturn(array('code' => -1, 'kick' => true, 'msg' => '账号已在其他设备登录'));
        }
        $this->ajaxReturn(array('code' => 0, 'msg' => 'ok'));
    }
}
