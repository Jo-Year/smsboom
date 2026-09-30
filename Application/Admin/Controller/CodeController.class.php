<?php
// +----------------------------------------------------------------------
// | 后台卡密管理：add 生成 / list 列表 / delete 删除
// +----------------------------------------------------------------------
namespace Admin\Controller;

class CodeController extends CommonController {

    // 默认入口：跳卡密列表
    public function index() {
        $this->redirect('Admin/Code/lst');
    }

    // 添加卡密页
    public function add() {
        $this->display();
    }

    // 批量生成卡密（ajax）
    public function generate() {
        if (!IS_AJAX) $this->error('非法请求');
        $type   = intval($_POST['type']);
        $num    = intval($_POST['num']);
        if (!in_array($type, array(1, 2, 3))) $this->ajaxReturn(array('code' => -1, 'msg' => '卡密类型错误'));
        if ($num <= 0 || $num > 1000) $this->ajaxReturn(array('code' => -1, 'msg' => '生成数量须在1-1000之间'));

        $M = M('code');
        $codes = array();
        $now = now_dt();
        $batch = array();
        for ($i = 0; $i < $num; $i++) {
            // 保证唯一
            do {
                $key = gen_code(16);
            } while ($M->where(array('key' => $key))->find());
            $batch[] = array('key' => $key, 'type' => $type, 'add_time' => $now, 'uid' => 0, 'use_time' => null);
            $codes[] = $key;
        }
        // 批量插入
        foreach ($batch as $row) {
            $M->add($row);
        }
        $this->ajaxReturn(array('code' => 0, 'msg' => "成功生成 {$num} 个卡密", 'data' => $codes));
    }

    // 卡密列表页
    public function lst() {
        $this->display();
    }

    // 卡密列表数据接口
    public function getList() {
        if (!IS_AJAX) $this->error('非法请求');
        $p     = intval($_POST['page']);
        $limit = intval($_POST['limit']);
        if ($p <= 0) $p = 1;
        if ($limit <= 0) $limit = 50;

        $tab       = isset($_POST['tab']) ? $_POST['tab'] : 'unused';
        $searchF   = isset($_POST['searchField']) ? $_POST['searchField'] : '';
        $searchV   = trim($_POST['searchVal']);
        $sortField = isset($_POST['sortField']) ? $_POST['sortField'] : 'id';
        $sortType  = isset($_POST['sortType']) && $_POST['sortType'] == 'asc' ? 'asc' : 'desc';

        $allowSort = array('id', 'type', 'use_time');
        if (!in_array($sortField, $allowSort)) $sortField = 'id';

        $M = M('code');
        $map = array();

        if ($tab == 'used') {
            $map['use_time'] = array('exp', 'IS NOT NULL');
        } elseif ($tab == 'unused') {
            $map['use_time'] = array('exp', 'IS NULL');
        }

        if ($searchF && $searchV !== '') {
            if ($searchF == 'uid') {
                $map['uid'] = intval($searchV);
            } elseif ($searchF == 'type') {
                $map['type'] = intval($searchV);
            } elseif ($searchF == 'key') {
                $map['key'] = array('like', '%' . $searchV . '%');
            }
        }

        $typeMap = array(1 => '10分钟卡', 2 => '月卡', 3 => '年卡');
        $count = $M->where($map)->count();
        $list  = $M->where($map)->order("{$sortField} {$sortType}")->page($p, $limit)->select();
        foreach ($list as &$row) {
            $row['type_show']   = $typeMap[$row['type']];
            $row['use_time_show'] = $row['use_time'] ? $row['use_time'] : '未使用';
        }
        unset($row);

        $this->ajaxReturn(array('code' => 0, 'msg' => '', 'count' => $count, 'data' => $list));
    }

    // 删除卡密（ajax，支持单个 id 或批量 ids 数组）
    public function del() {
        if (!IS_AJAX) $this->error('非法请求');
        // 批量删除
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
            $ids = array_filter($ids);
            if (empty($ids)) $this->ajaxReturn(array('code' => -1, 'msg' => '请选择要删除的卡密'));
            M('code')->where(array('id' => array('in', $ids)))->delete();
            $this->ajaxReturn(array('code' => 0, 'msg' => '成功删除 ' . count($ids) . ' 个卡密'));
        }
        // 单个删除
        $id = intval($_POST['id']);
        if ($id <= 0) $this->ajaxReturn(array('code' => -1, 'msg' => '参数错误'));
        M('code')->where(array('id' => $id))->delete();
        $this->ajaxReturn(array('code' => 0, 'msg' => '删除成功'));
    }
}
