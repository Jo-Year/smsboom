<?php
// +----------------------------------------------------------------------
// | 前台续费（卡密充值）
// +----------------------------------------------------------------------
namespace Home\Controller;

class CodeController extends CommonController {

    public function index() {
        $this->display();
    }

    // 续费提交（ajax）
    public function renew() {
        if (!IS_AJAX) $this->error('非法请求');
        $uid  = $_SESSION['uid'];
        $code = trim($_POST['code']);
        if ($code === '') $this->ajaxReturn(array('code' => -1, 'msg' => '请输入卡密'));

        $codeRow = M('code')->where(array('key' => $code))->find();
        if (!$codeRow) $this->ajaxReturn(array('code' => -1, 'msg' => '卡密不存在'));
        if (!empty($codeRow['use_time'])) $this->ajaxReturn(array('code' => -1, 'msg' => '该卡密已被使用'));

        $user = M('user')->where(array('id' => $uid))->find();
        $newEnd = calc_end_time($user['end_time'], $codeRow['type']);

        $save = array('end_time' => $newEnd);
        // 进程：仅当赠送的进程高于用户当前进程时才升级，否则保持不变
        $newProc = type_to_process($codeRow['type']);
        $curProc = intval($user['process']);
        if ($newProc > $curProc) {
            $save['process'] = $newProc;
        }

        M('user')->where(array('id' => $uid))->save($save);
        M('code')->where(array('id' => $codeRow['id']))->save(array(
            'uid'      => $uid,
            'use_time' => now_dt(),
        ));
        $this->ajaxReturn(array('code' => 0, 'msg' => '续费成功，到期时间：' . $newEnd, 'end_time' => $newEnd));
    }
}
