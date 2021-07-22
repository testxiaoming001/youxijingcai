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
namespace app\plugins\wallet\index;

use app\service\SeoService;
use app\plugins\wallet\index\Common;
use app\plugins\wallet\service\BaseService;
use app\plugins\wallet\service\WalletService;

/**
 * 钱包 - 账户明细
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class Wallet extends Common
{
    /**
     * 构造方法
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-03-15
     * @desc    description
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 钱包明细
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-03-15T23:51:50+0800
     * @param   [array]          $params [输入参数]
     */
    public function index($params = [])
    {
        // 参数
        $params['user'] = $this->user;

        // 分页
        $number = 10;

        // 条件
        $where = BaseService::WalletLogWhere($params);

        // 获取总数
        $total = BaseService::WalletLogTotal($where);

        // 分页
        $page_params = array(
                'number'    =>  $number,
                'total'     =>  $total,
                'where'     =>  $params,
                'page'      =>  isset($params['page']) ? intval($params['page']) : 1,
                'url'       =>  PluginsHomeUrl('wallet', 'wallet', 'index'),
            );
        $page = new \base\Page($page_params);
        $this->assign('page_html', $page->GetPageHtml());

        // 获取列表
        $data_params = array(
            'm'         => $page->GetPageStarNumber(),
            'n'         => $number,
            'where'     => $where,
        );
        $data = BaseService::WalletLogList($data_params);
        $this->assign('data_list', $data['data']);

        // 静态数据
        $this->assign('business_type_list', WalletService::$business_type_list);
        $this->assign('operation_type_list', WalletService::$operation_type_list);
        $this->assign('money_type_list', WalletService::$money_type_list);

        // 浏览器名称
        $this->assign('home_seo_site_title', SeoService::BrowserSeoTitle('我的钱包', 1));

        // 参数
        $this->assign('params', $params);
        return $this->fetch('../../../plugins/view/wallet/index/wallet/index');
    }
}
?>