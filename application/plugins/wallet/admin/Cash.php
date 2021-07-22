<?php
// +----------------------------------------------------------------------
// | ShopXO 国内领先企业级B2C免费开源电商系统
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2019 http://shopxo.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Devil
// +----------------------------------------------------------------------
namespace app\plugins\wallet\admin;

use think\Controller;
use app\plugins\wallet\service\CashService;
use app\plugins\wallet\service\BaseService;
use app\plugins\wallet\service\WalletService;

/**
 * 钱包插件 - 提现管理
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class Cash extends Controller
{
    /**
     * 首页
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-02-07T08:21:54+0800
     * @param    [array]          $params [输入参数]
     */
    public function index($params = [])
    {
        // 分页
        $number = MyC('admin_page_number', 10, true);

        // 条件
        $where = BaseService::CashWhere($params);

        // 获取总数
        $total = BaseService::CashTotal($where);

        // 分页
        $page_params = array(
                'number'    =>  $number,
                'total'     =>  $total,
                'where'     =>  $params,
                'page'      =>  isset($params['page']) ? intval($params['page']) : 1,
                'url'       =>  PluginsAdminUrl('wallet', 'cash', 'index'),
            );
        $page = new \base\Page($page_params);
        $this->assign('page_html', $page->GetPageHtml());

        // 获取列表
        if($total > 0)
        {
            $data_params = array(
                'm'         => $page->GetPageStarNumber(),
                'n'         => $number,
                'where'     => $where,
            );
            $data = BaseService::CashList($data_params);
            $this->assign('data_list', $data['data']);
        } else {
            $this->assign('data_list', []);
        }

        // 静态数据
        $this->assign('cash_status_list', CashService::$cash_status_list);

        // 参数
        $this->assign('params', $params);
        return $this->fetch('../../../plugins/view/wallet/admin/cash/index');
    }

    /**
     * 审核页面
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-05
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function auditinfo($params = [])
    {
        $data = [];
        if(!empty($params['id']))
        {
            $data_params = array(
                'm'         => 0,
                'n'         => 1,
                'where'     => ['id'=>intval($params['id'])],
            );
            $ret = BaseService::CashList($data_params);
            if(!empty($ret['data'][0]))
            {
                // 用户钱包
                $user_wallet = WalletService::UserWallet($ret['data'][0]['user_id']);
                if($user_wallet['code'] == 0)
                {
                    $data = $ret['data'][0];
                    $this->assign('user_wallet', $user_wallet['data']);
                } else {
                    $this->assign('msg', $user_wallet['msg']);
                }
            } else {
                $this->assign('msg', '数据不存在或已删除');
            }
        } else {
            $this->assign('msg', '参数id有误');
        }

        $this->assign('data', $data);
        return $this->fetch('../../../plugins/view/wallet/admin/cash/auditinfo');
    }

    /**
     * 审核
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-06
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function audit($params = [])
    {
        return CashService::CashAudit($params);
    }
}
?>