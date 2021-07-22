<?php


namespace app\message\controller;

use app\plugins\wallet\service\WalletService;
use app\service\MessageBotService;
use app\service\TelegramBotService;
use app\service\UserService;
use think\Cache;
use think\cache\driver\Redis;
use think\Controller;
use think\Db;
use think\Log;
use think\Request;


class Index extends Controller
{


    public function demo()
    {

        $redis = new \Redis();
        $redis->connect('dnmp-redis', 6379);
        $task = [
            'task' => 'send_email',
            'data' => '你好，隔壁老王',
        ];
        $redis->publish('task_queue', serialize($task));
    }


    public function test()
    {

        $json = '{
	"update_id": 968000210,
	"message": {
		"message_id": 72083,
		"from": {
			"id": 688505200,
			"is_bot": false,
			"first_name": "cc",
			"last_name": "bbb",
			"username": "nxihei"
		},
		"chat": {
			"id": -1001285837375,
			"title": "97AAA",
			"username": "danb",
			"type": "supergroup"
		},
		"date": 1608024449,
		"text": "找手机支付",
		"entities": [{
			"offset": 0,
			"length": 11,
			"type": "mention"
		}]
	}
}';

        $data = json_decode($json, true);

        //测试游戏竞猜
        if (strpos($data['message']['text'], '支付') !== false) {

            MessageBotService::payPayGame($data['message']);
            return;
        }
        exit;


        //测试说话三次触发提醒’
        MessageBotService::sendMessageWhenSpeckSameWords($data['message'], 3);
        exit;


        //自动注册测试
        UserService::autoRegisterWhenSpeak($data['message']);

        exit;


        MessageBotService::payPayGame($data['message']);

//        $text = 'XXX支付      1000';
//        try {
//            $text = str_replace(' ', '', $text);
//            $index = strpos($text,'支付');
//            if($index === false)
//            {
//                throw  new \Exception('输入格式有误,不含有【支付】字样');
//            }
//            if($index == 0)
//            {
//                throw  new \Exception('输入格式有误,请输入具体的支付名称');
//            }
//            //查询支付
//            $pay = substr($text,0,$index).'支付';
//            $number = substr($text,strlen($pay));
//
//            $payGameParam = cache('pay_game_params');
//            //参与用户入库 ['user_id'=>1,'play_result'=>2,'number'=>100,'user_name'=>'shopxo']
//            $join['user_id']  = $userId;
//            $join['play_result']  = $payId;
//            $join['number']  = $number;
//            $join['user_name']  = $userName;
//
//
//
//
//
//
//
//
//            dd($number);
//
//
//
//
//
//
//
//            dd($text);
//
//
//
//            dd($play);
//        } catch (\Exception $exception) {
//            dd($exception->getMessage());
//        }
//
//        dd(333);


    }


    /*
     * 测试新成员入群
     */
    public function testNewMember()
    {

        $json = '{"update_id":135468915,
"message":{"message_id":324,"from":{"id":1458266103,"is_bot":false,"first_name":"jianyun","last_name":"li"},"chat":{"id":-1001366870194,"title":"test","type":"supergroup"},"date":1614616984,"new_chat_participant":{"id":1494186771,"is_bot":false,"first_name":"meisha","last_name":"qiao","language_code":"zh-hans"},"new_chat_member":{"id":1494186771,"is_bot":false,"first_name":"meisha","last_name":"qiao","language_code":"zh-hans"},"new_chat_members":[{"id":1494186771,"is_bot":false,"first_name":"meisha","last_name":"qiao","language_code":"zh-hans"}]}}';
        $data = json_decode($json, true);
        $message = $data['message'];

        if (array_key_exists('new_chat_members', $message) && $message['new_chat_members']) {
            MessageBotService::hookNewMemberEevent($message);
        }


        dd($data);
    }

    /**
     * 获取消息
     */
    public function message()
    {
        $json = file_get_contents("php://input");
        file_put_contents('./test.log', $json, FILE_APPEND);
        \think\facade\Log::INFO('telegramNotifyPost1:' . $json);
        $data = json_decode($json, true);
        //parse各种事件  消息事件&行为事件
        if (array_key_exists('callback_query', $data)) {
            MessageBotService::hookCallbackEvent($data['callback_query']);
            return;
        }

        //有可能是发送消息 有可能是编辑消息
        $pushMsg = isset($data['message']) ? $data['message'] : $data['edited_message'];

        //超时不候
        if (($pushMsg['date'] + 20) < time()) {
            return;
        }

        //只要是来自于机器人的消息10s后自动删除
        if ($pushMsg['from']['is_bot']) {
            MessageBotService::HookBootMessage($pushMsg);
        }


        //新用户入群事件
        if (array_key_exists('new_chat_members', $pushMsg) && $pushMsg['new_chat_members'] && $pushMsg['new_chat_participant']) {
            MessageBotService::hookNewMemberEevent($pushMsg);
            return;
        }


        //监听新用户推送事件
        if (array_key_exists('new_chat_members', $pushMsg) && $pushMsg['new_chat_members']) {
            MessageBotService::hookNewMemberEvent($pushMsg);
            return;
        }

        //监听用户从其他地方抓转发过来的事件
        if (array_key_exists('forward_from', $pushMsg) && $pushMsg['forward_from']) {
            MessageBotService::hookForwardEvent($pushMsg);
            return;
        }

        //监听转发消息
        if (array_key_exists('reply_to_message', $pushMsg) && $pushMsg['reply_to_message']) {
            MessageBotService::hookReplyEvent($pushMsg);
            return;
        }
        //######################发送基本文本事件处理########################
        //回调文本校验
        if (!isset($pushMsg['text']) || empty($pushMsg['text'])) {
            return;
        }

        //用户说话投诉提醒
        $MessageBotService = new MessageBotService();

        $MessageBotService->complainRemind($pushMsg['chat']['id'], $pushMsg['from']['id'], $pushMsg['from']['first_name']);


        //连续三次相同进行禁言提醒
        $times = 3;
        MessageBotService::sendMessageWhenSpeckSameWords($pushMsg, $times);

        //用户说话自动注册
        UserService::autoRegisterWhenSpeak($pushMsg);

        //有消息【用书说话】进来派送
        WalletService::sendUsdtWhenSpeak($pushMsg);

        //更新用户说话次数
        UserService::updateSayTimes($pushMsg['from']['id']);

        //处理特殊文本
        $text = $pushMsg['text'];
        //①支付曝光游戏竞猜处理
//        if (strpos($text, '支付') !== false) {
//            MessageBotService::payPayGame($pushMsg);
//            return;
//        }
        //②禁言通知  禁言1000分钟
        if (strpos($text, '禁言') !== false) {
            $limitMinus = str_replace('分钟', '', str_replace('禁言', '', $pushMsg['text']));
            $endTime = time() + (int)$limitMinus * 60;
            $telgram = new TelegramBotService();
            $telgram->strictChatMember($pushMsg['chat']['id'], $pushMsg['from']['id'], $endTime);
            return;
        }
        //③注册
        if ($text == '注册') {
            $MessageBotService->botRegister($pushMsg);
            return;
        }
        //④查余额
        if ($text == '查余额') {
            $MessageBotService->queryBalance($pushMsg);
            return;
        }
        //⑤曝光
        if (strstr($text, '我要曝光')) {
            $MessageBotService->exposurePay($pushMsg);
            return;
        }
        //⑥充值
        if (strstr($text, '充值') && strstr($text, '@')) {
            if (is_numeric(trim(mb_substr(strstr($text, "充值"), 2)))) {
                $MessageBotService->adminToUserRecharge($pushMsg);
            }
            return;
        }

        //⑦找商品
        if (strstr($text, '找') && strpos($text, '找') == 0) {
            $text = mb_substr(strstr($text, '找'), 1);
            //调用消息发送
            $TelegramBotService = new TelegramBotService();
            $TelegramBotService->reply(trim($text));
            return;
        }
        //⑧查询个人信息

        if ($text == '查询信息') {
            $MessageBotService->queryUserinfo($pushMsg);
            return;
        }

        //⑨当前发送文本的用户有投诉操作
        if (cache('complain_' . $pushMsg['from']['id'])) {
            cache('trigger_callback_complain_btn_' . $pushMsg['from']['id'], null);
            MessageBotService::hookMemberComplainEvent($pushMsg);
            return;
        }

        //10 解析command 文本
        if (strstr($pushMsg['text'], '/start')) {
            $TelegramBotService = new TelegramBotService();
            $TelegramBotService->parseCommand($pushMsg, 'start');
            return;
        }
        //用户触发command中的按钮选项tg回调过来处理逻辑


        //其他文本处理todo

    }


    public function setUrl()
    {
        $a = new TelegramBotService();
        $request = \think\facade\Request::instance();
        $url = $request->domain() . '/index.php/message/index/message';
//        $url =  'https://www.baidu.com/';

        $b = $a->setWebHook($url);

        var_dump($b);
    }
                    
  /*********************20210701新增****************************************************/





























}
