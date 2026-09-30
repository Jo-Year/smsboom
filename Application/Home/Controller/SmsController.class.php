<?php
// +----------------------------------------------------------------------
// | 短信回访页（前台用户端）
// | 未登录由 CommonController 拦截；这里再判断会员身份
// +----------------------------------------------------------------------
namespace Home\Controller;

class SmsController extends CommonController {

    public function index() {
        $user = $this->loginUser;
        // 会员判定：到期时间晚于当前时间即为会员
        $now = date('Y-m-d H:i:s');
        $isMember = (!empty($user['end_time']) && $user['end_time'] > $now) ? 1 : 0;
        $this->assign('isMember', $isMember);
        $this->display();
    }
}
