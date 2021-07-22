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
namespace app\plugins\wallet\service;

use think\Db;
use app\service\PaymentService;
use app\plugins\wallet\service\PayService;

/**
 * 钱包服务层 - 头条定制化服务层
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class ToutiaoService
{
    /**
     * 支付
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-10-29
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function Pay($params = [])
    {    
        // 获取支付信息
        $ret =  PayService::Pay($params);
        if($ret['code'] != 0)
        {
            return $ret;
        }
  
        // 配置信息
        $merchant_id = MyC('common_app_mini_toutiao_pay_merchant_id');
        $app_id = MyC('common_app_mini_toutiao_pay_appid');
        $pay_secret = MyC('common_app_mini_toutiao_pay_secret');
        if(empty($merchant_id) || empty($app_id) || empty($pay_secret))
        {
            return DataReturn('小程序未配置', -1);
        }

        // 支付配置
        $order_info = [];
        $service = 0;
    
        // 获取订单信息
        $where = ['id'=>$ret['data']['order_id'], 'user_id'=>$params['user']['id']];
        $pay_log = Db::name('PayLog')->where($where)->find();
        if(empty($pay_log))
        {
            return DataReturn('订单支付日志有误', -1);
        }

        // 头条需要的订单信息
        $time = (string) time();
        $valid_time = intval(MyC('common_order_close_limit_time', 30, true))*60;
        $order_info = [
            'merchant_id'       => $merchant_id,
            'app_id'            => $app_id,
            'sign_type'         => 'MD5',
            'timestamp'         => $time,
            'version'           => '2.0',
            'trade_type'        => 'H5',
            'product_code'      => 'pay',
            'payment_type'      => 'direct',
            'out_order_no'      => $pay_log['log_no'],
            'uid'               => $app_id,
            'total_amount'      => $pay_log['total_price']*100,
            'currency'          => 'CNY',
            'subject'           => '订单支付',
            'body'              => $pay_log['log_no'],
            'trade_time'        => $time,
            'valid_time'        => (string) $valid_time,
            'notify_url'        => __MY_URL__,
            'risk_info'         => json_encode(['ip'=>GetClientIP()]),
        ];

        // 支付方式
        $service = 1;
        switch($pay_log['payment'])
        {
            // 微信
            case 'Weixin' :
                $service = 3;
                $order_info['wx_url'] = $ret['data']['data'];
                $order_info['wx_type'] = 'MWEB';
                break;

            // 支付宝
            case 'Alipay' :
                $service = 4;
                $order_info['alipay_url'] = $ret['data']['data'];
                break;
        }

        // 签名
        $order_info['sign'] = (new \base\Toutiao())->PaySignCreated($order_info, $pay_secret);

        // 返回数据
        $result = [
            'order_info'    => $order_info,
            'service'       => $service,
        ];
        return DataReturn('success', 0, $result);
    }
}
?>