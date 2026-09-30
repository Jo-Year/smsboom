<?php
// +----------------------------------------------------------------------
// | 后台用户列表
// +----------------------------------------------------------------------
namespace Admin\Controller;

class UserController extends CommonController {

    // 用户列表页
    public function index() {
        $this->display();
    }

    // layui table 数据接口
    public function getList() {
        if (!IS_AJAX) $this->error('非法请求');
        $p        = intval($_POST['page']);
        $limit    = intval($_POST['limit']);
        if ($p <= 0) $p = 1;
        if ($limit <= 0) $limit = 50;

        $tab       = isset($_POST['tab']) ? $_POST['tab'] : 'all';
        $searchF   = isset($_POST['searchField']) ? $_POST['searchField'] : '';
        $searchV   = trim($_POST['searchVal']);
        $sortField = isset($_POST['sortField']) ? $_POST['sortField'] : 'id';
        $sortType  = isset($_POST['sortType']) && $_POST['sortType'] == 'asc' ? 'asc' : 'desc';

        // 合法字段白名单
        $allowSort = array('id', 'add_time', 'end_time');
        if (!in_array($sortField, $allowSort)) $sortField = 'id';

        $M = M('user');
        $map = array();

        // tab 过滤
        $now = now_dt();
        if ($tab == 'member') {
            $map['end_time'] = array('exp', "> '{$now}'");
        } elseif ($tab == 'nomember') {
            // 非会员：到期时间为空 或 已过期
            $map['end_time'] = array('exp', "<= '{$now}' OR end_time IS NULL");
        }

        // 搜索
        if ($searchF && $searchV !== '') {
            if ($searchF == 'id') {
                $map['id'] = intval($searchV);
            } elseif ($searchF == 'email') {
                $map['email'] = array('like', '%' . $searchV . '%');
            }
        }

        $count = $M->where($map)->count();
        $list  = $M->where($map)->order("{$sortField} {$sortType}")
                   ->page($p, $limit)->select();

        foreach ($list as &$row) {
            $row['is_member'] = is_member($row['end_time']) ? '会员' : '非会员';
            $row['end_time_show'] = $row['end_time'] ? $row['end_time'] : '未开通';
            $p = intval($row['process']);
            $row['process_show'] = $p == 5 ? '进程5' : ($p == 3 ? '进程3' : ($p == 1 ? '进程1' : '未开通'));
        }
        unset($row);

        $this->ajaxReturn(array(
            'code'  => 0,
            'msg'   => '',
            'count' => $count,
            'data'  => $list,
        ));
    }

    // 修改用户（ajax 弹窗提交）
    public function edit() {
        if (!IS_AJAX) $this->error('非法请求');
        $uid   = intval($_POST['id']);
        $email = trim($_POST['email']);
        $pwd   = $_POST['pwd'];
        $end   = trim($_POST['end_time']);
        $process = intval($_POST['process']);

        if ($uid <= 0) $this->ajaxReturn(array('code' => -1, 'msg' => '参数错误'));
        if (!in_array($process, array(0, 1, 3, 5))) $process = 0;
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->ajaxReturn(array('code' => -1, 'msg' => '邮箱格式不正确'));
        }
        // 邮箱查重（排除自己）
        $dup = M('user')->where(array('email' => $email, 'id' => array('neq' => $uid)))->find();
        if ($dup) $this->ajaxReturn(array('code' => -1, 'msg' => '该邮箱已被占用'));

        $save = array('email' => $email, 'end_time' => $end ? $end : null, 'process' => $process);
        if ($pwd !== '') {
            if (!preg_match('/^[A-Za-z0-9!@#$%^&*._-]{8,16}$/', $pwd) || preg_match('/[\x80-\xff\s]/', $pwd)) {
                $this->ajaxReturn(array('code' => -1, 'msg' => '密码须为8-16位，不含中文和空格'));
            }
            $save['pwd'] = hash_pwd($pwd);
        }
        $res = M('user')->where(array('id' => $uid))->save($save);
        $this->ajaxReturn(array('code' => 0, 'msg' => '修改成功'));
    }
}
