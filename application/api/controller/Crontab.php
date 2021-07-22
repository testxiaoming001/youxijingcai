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
namespace app\api\controller;

use app\service\CrontabService;
use think\Exception;
use think\facade\Log;
use app\service\TgBotService;
use think\Db;

/**
 * 定时任务
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2019-08-18T17:19:33+0800
 */
class Crontab extends Common
{
    /**
     * 订单关闭
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-08-18T17:19:33+0800
     */
    public function OrderClose()
    {
        $ret = CrontabService::OrderClose();
        return 'sucs:'.$ret['data']['sucs'].', fail:'.$ret['data']['fail'];
    }

    /**
     * 订单收货
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-08-18T17:19:33+0800
     */
    public function OrderSuccess()
    {
        $ret = CrontabService::OrderSuccess();
        return 'sucs:'.$ret['data']['sucs'].', fail:'.$ret['data']['fail'];
    }

    /**
     * 支付日志订单关闭
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-08-18T17:19:33+0800
     */
    public function PayLogOrderClose()
    {
        $ret = CrontabService::PayLogOrderClose();
        return 'count:'.$ret['data'];
    }


    public function captureUsdtRate()
    {

        $captureUrl  ="https://otc-api-hk.eiijo.cn/v1/data/trade-market?coinId=2&currency=1&tradeType=sell&currPage=1&payMethod=0&country=37&blockType=block&online=1&range=0&amount=";
        try {
            $result  = json_decode(httpRequest($captureUrl),true);
            if($result['code'] ==200)
            {
                $rate = $result['data'][0]['price'];

            }
            throw new Exception('采集usdt最新汇率失败');
        }catch(\Exception $exception)
        {
            dd($exception->getMessage());
            Log::info($exception->getMessage());
        }
        dd($result);
    }


    /**
     * 每隔五分钟推送一次广告
     * @return string
     */
    public function pushTgBanner()
    {
        $ret = CrontabService::pushTgBanner();
        return 'count:'.$ret['data'];
    }

       /**
     * 拉取远程订单警告推送的tg消息
     */
    public function pullplatzfSendMessageOld()
    {
        $remoteUrl = "http://www.yingqianpay.com/index/tg_message/orderWarning";
        $result = json_decode(httpRequest($remoteUrl), true);
        if ($result['is_play']==false) {
            //发送消息到订单机器人群组
            $groupId = Db::name('Config')->where(['only_tag' => 'tg_order_warning_rebot_in_chat'])->value('value');
            $token = Db::name('Config')->where(['only_tag' => 'tg_order_warning_robot_token'])->value('value');
            $tgBotService = new TgBotService();
            $tgBotService->setBotToken($token)->sendMessage($groupId, $result['send_message']);
        }
    }


       /**
     * 拉取远程订单警告推送的tg消息
     */
    public function pullplatzfSendMessageoldv3()
    {
        $remoteUrl = "http://www.yingqianpay.com/index/tg_message/orderWarning";
        $result = httpRequest($remoteUrl);
        Log::info("拉取到支付系统待播放Tg消息业务类型【订单报警】参数返回".$result);
        $result = json_decode($result, true);
        if ($result['is_play']==false) {
            //发送消息到订单机器人群组
            $groupId = Db::name('Config')->where(['only_tag' => 'tg_order_warning_rebot_in_chat'])->value('value');
            $token = Db::name('Config')->where(['only_tag' => 'tg_order_warning_robot_token'])->value('value');
            $tgBotService = new TgBotService();
            $tgBotService->setBotToken($token)->sendMessage($groupId, $result['send_message']);
        }
    }

       public function pullplatzfSendMessage()
    {
        $remoteUrl = "http://www.yingqianpay.com/index/tg_message/orderWarning";
        $result = httpRequest($remoteUrl);
        Log::info("拉取到支付系统待播放Tg消息业务类型【订单报警】参数返回" . $result);
        $result = json_decode($result, true);
        try {
            if ($result['is_play']) {
                //发送消息到订单机器人群组
#                if (true) {
 
               $groupId = Db::name('Config')->where(['only_tag' => 'tg_order_warning_rebot_in_chat'])->value('value');
                $token = Db::name('Config')->where(['only_tag' => 'tg_order_warning_robot_token'])->value('value');
                $tgBotService = new TgBotService();
               # $tgBotService->setBotToken($token)->sendMessage($groupId, $result['send_message']);
                         $result =   $tgBotService->setBotToken($token)->sendMessage($groupId, $result['send_message']);
#               dd($result);  
          }
        } catch (\Exception $exception) {
                     dd($exception->getMessage());
            Log::info("拉取到支付系统待播放Tg消息业务类型【订单报警】shopxo处理异常" . $exception->getMessage());
        }

    }


}
?>
