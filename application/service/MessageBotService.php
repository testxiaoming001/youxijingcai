<?php


namespace app\service;


use app\plugins\wallet\service\RechargeService;
use app\plugins\wallet\service\WalletService;
use think\Db;
use think\Exception;
use think\facade\Log;
use think\route\Rule;

class MessageBotService
{

    public function complainRemind($chat_id, $from_id, $first_name)
    {

        $complainList = cache('botComplainList');
        if (!$complainList) {
            $complainList = [];
            //获取投诉列表
            $exposure = Db::name('Exposure')->select();
            foreach ($exposure as $k => $v) {
                $complainList['complain_' . $v['tg_uid']] = $v;
            }
            cache('botComplainList', $complainList, 3600);
        }
        if (isset($complainList['complain_' . $from_id])) {
            $TelegramBotService = new TelegramBotService();
            //投诉记录
            $records = $this->getComplainRecordByTgUid($from_id);
            $complaint_usernames = implode(',', array_column($records, 'complaint_username'));
            $complaint_reasons = implode(',', array_column($records, 'complaint_reason'));
            $sendText = "此用户{$first_name} TG id:{$from_id}, 被 用户{$complaint_usernames} 投诉过,投诉原因({$complaint_reasons})";
            //$TelegramBotService->sendGroupMessage($chat_id,'⬆️此人('.'@'.$first_name.')有可能是骗子，被用户 @'.$complainList['complain_'.$from_id]['complaint_username'].' 投诉过');
            $TelegramBotService->sendGroupMessage($chat_id, "⬆ {$sendText}");
            return false;
        }
        return true;
    }


    /**
     * 获取用户被投诉的记录
     * @param $tg_uid
     */
    public function getComplainRecordByTgUid($tg_uid)
    {
        $records = Db::name('Exposure')->where('tg_uid', $tg_uid)->select();
        return $records;
    }


    /**
     * 注册
     */
    public function botRegister($data)
    {
        $TelegramBotService = new TelegramBotService();
        //判断用户是否存在
        $user = Db::name('User')->where(['user_telegram_id' => $data['from']['id']])->find();
        if ($user) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 账号已存在');
            return;
        }

        if (!isset($data['from']['username'])) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 账号无法注册，Telgram请先设置username');
            return;
        }


        $save = [
            'admin' => ['id' => '1'],
            'username' => $data['from']['username'],
            'nickname' => $data['from']['first_name'],
            'user_telegram' => $data['from']['username'],
            'pwd' => '123456',
            'gender' => '0',
            'status' => '0',
            'integral' => 0,
            'user_telegram_id' => $data['from']['id']
        ];
        //执行注册
        $result = UserService::UserSave($save);
        $str = '@' . $data['from']['first_name'] . ' 注册失败';
        if ($result['code'] == 0) {
            //读取站点名称配置
            $home_site_name = Db::name('Config')->where(['only_tag' => 'home_site_name'])->find();
            $str = '恭喜 ' . '@' . $data['from']['first_name'] . ' 成功注册(' . $home_site_name['value'] . ')';
        }


        //派送注册奖励start
        try {
            $user_wallet = \app\plugins\wallet\service\WalletService::UserWallet($user['id']);

            if ($user_wallet['code'] != 0) {
                //钱包状态异常
                throw  new \Exception($user_wallet['msg']);
            }

            $sendUsdt = config('shopxo.regitser_send_usdt');
            $res = WalletService::UserWalletMoneyUpdate($user['id'], $sendUsdt, 1, 'normal_money');
            if ($res['code'] != 0) {
                //钱包状态异常
                throw  new \Exception($res['msg']);
            }
        } catch (\Exception $exception) {
            Log::info("为用户派送注册奖励失败,失败原因{$exception->getMessage()}");
        }
        //派送注册奖励end

        $TelegramBotService->sendGroupMessage($data['chat']['id'], $str);

    }

    /**
     * 查询余额
     */
    public function queryBalance($data)
    {
        //查找用户
        $user = Db::name('User')->where(['user_telegram_id' => $data['from']['id']])->find();
        if (!$user) {
            $TelegramBotService = new TelegramBotService();
            $TelegramBotService->sendGroupMessage($data['chat']['id'], ' 账号不存在');
            return;
        }
        // 用户钱包
        $str = '余额查询错误';
        $user_wallet = \app\plugins\wallet\service\WalletService::UserWallet($user['id']);
        if ($user_wallet['code'] == 0) {
            //读取单位配置
            $home_site_wallet_units = Db::name('Config')->where(['only_tag' => 'home_site_wallet_units'])->find();
            $str = '@' . $data['from']['first_name'] . ' 您还有' . $user_wallet['data']['normal_money'] . 'usdt';//.$home_site_wallet_units['value'];
        }
        $TelegramBotService = new TelegramBotService();
        $TelegramBotService->sendGroupMessage($data['chat']['id'], $str);
    }

    /**
     * 是否是管理员消息
     */
    public function isAdmin($from_id)
    {
        $adminList = cache('botAdminList');
        if (!$adminList) {
            $adminList = [];
            //获取管理员列表
            $admins = Db::name('Admin')->select();
            foreach ($admins as $k => $v) {
                if ($v['user_telegram_id']) {
                    $adminList['admin_' . $v['user_telegram_id']] = $v;
                }
            }
            cache('botAdminList', $adminList, 3600);
        }
        if (isset($adminList['admin_' . $from_id])) {
            return true;
        }
        return false;
    }

    public function cut($begin, $end, $str)
    {
        $b = mb_strpos($str, $begin) + mb_strlen($begin);
        $e = mb_strpos($str, $end) - $b;
        return mb_substr($str, $b, $e);
    }


    /**
     * 取消投诉
     */
    public function adminCancelComplaint($data)
    {
        $TelegramBotService = new TelegramBotService();
        //先判断是否是管理员操作  取消管理员限制
//        if(!$this->isAdmin($data['from']['id'])){
//            return ;
//        }

        //判断投诉是否存在
//        $complain['tg_uid'] = $data['message']['reply_to_message']['from']['id']
        $exposure = Db::name('Exposure')->where(['tg_uid' => $data['reply_to_message']['from']['id']])->find();
        if (!$exposure) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 用户没有被投诉');
            return;
        }
        Db::name('Exposure')->where(['tg_uid' => $data['reply_to_message']['from']['id']])->delete();
        //清理缓存
        cache('botComplainList', null);
        $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 投诉取消成功');
    }


    /**
     * 管理员充值
     */
    public function adminToUserRecharge($data)
    {
        $TelegramBotService = new TelegramBotService();
        //先判断是否是管理员操作
        if (!$this->isAdmin($data['from']['id'])) {
            return;
        }


        //判断用户是否存在
        $username = trim($this->cut('@', '充值', $data['text']));
        $user = Db::name('User')->field('id,username')->where(['username' => $username])->find();
        if (!$user) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 账号不存在');
            return;
        }

        $user_wallet = \app\plugins\wallet\service\WalletService::UserWallet($user['id']);
        if ($user_wallet['code'] != 0) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 钱包信息不存在');
            return;
        }
        $recharge = [
            'user' => $user,
            'user_wallet' => $user_wallet['data'],
            'money' => trim(mb_substr(strstr($data['text'], "充值"), 2)),
            'reacharge_usdt_address' => 'bot管理员充值',
            'by_reacharge_usdt_address' => 'bot管理员充值',
        ];


        $create = RechargeService::RechargeCreate($recharge);
        if ($create['code'] != 0) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 订单创建失败');
            return;
        }

        //充值成功
        $res = $this->rechargeSuccess($create['data']);
        if (!$res) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 充值失败');
            return;
        }
        $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 充值成功');
    }


    /**
     * 支付成功
     */
    public function rechargeSuccess($item)
    {
        //开启事务
        Db::startTrans();
        try {
            $update = [
                'pay_money' => $item['money'],
                'status' => '1',
            ];
            //设置为成功
            $res = Db::name('plugins_wallet_recharge')->where(['id' => $item['recharge_id']])->update($update);
            if (!$res) {
                throw new Exception('操作失败');
            }

            $recharge = Db::name('plugins_wallet_recharge')->where(['id' => $item['recharge_id']])->find();

            //用户增加余额
            $result = Db::name('plugins_wallet')->where(['user_id' => $recharge['user_id']])->setInc('normal_money', $item['money']);
            if (!$result) {
                throw new Exception('操作失败');
            }
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            return false;
        }

    }

    /**
     * 用户充值
     */
    public function userRecharge()
    {

    }


    /**
     * 曝光支付
     */
    public function exposurePay($data)
    {
        $TelegramBotService = new TelegramBotService();

        //解析 名称 域名  原因

        $save = [];
        $complaint_username = isset($data['from']['username']) ? $data['from']['username'] : $data['from']['first_name'];
        $save['exposurepay_name'] = trim($this->cut('名称', '域名', $data['text']));
        $save['exposurepay_gateway'] = trim($this->cut('域名', '原因', $data['text']));
        $save['add_time'] = time();
        $save['complaint_reason'] = trim(mb_substr(strstr($data['text'], "原因"), 2));
        $save['complaint_uid'] = $data['from']['id'];
        $save['complaint_username'] = $complaint_username;
        //执行添加

        $result = Db::name('Exposurepay')->insertGetId($save);
        if (!$result) {
            $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 曝光失败');
            return;
        }
        $TelegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 曝光成功');
    }


    /**
     * 定时发送曝光支付列表
     */
    public function exposurepayList()
    {
        $result = Db::name('Exposurepay')
            ->whereTime('add_time', '>', strtotime('-7 day'))
            ->where(['is_delete_time' => '0'])->order('id desc')
            ->select();

        $str = '近7日被曝光的支付';

        foreach ($result as $k => $v) {
            $str .= '
' . $v['exposurepay_name'] . ' ' . $v['exposurepay_gateway'] . ' ' . $v['complaint_reason'] . '  ' . date('Y-m-d', $v['add_time']);
        }

        $TelegramBotService = new TelegramBotService();
        $TelegramBotService->sedDefaultGroupMessage($str);
    }


    /**
     * 验证发起投诉人
     *
     * @param $complaintTgid
     */
    public static function validteUserTgId($complaintTgid)
    {
        $user = Db::name('user')->where('user_telegram_id', $complaintTgid)->find();
        if (empty($user)) {
            throw  new \Exception("请先完成注册,再发起投诉");
        }
    }


    /**
     * 玩支付游戏竞猜
     */
    public static function payPayGame($data)
    {
        $telegramBotService = new TelegramBotService();
        try {
            //当前参与者参与入库
            $pay_game_params = cache('pay_game_params');
            if (empty($pay_game_params)) {
                throw  new \Exception('');
                return;
            }
            //$playText = 'demo3支付 1000';
            $playText = $data['text'];

            $joinUserTgId = $data['from']['id'];
            $user = Db::name('User')->where(['user_telegram_id' => $joinUserTgId])->find();
            if (empty($user)) {

                throw  new \Exception('请先完成注册再参与游戏');
            }

            $playText = str_replace(' ', '', $playText);
            $index = strpos($playText, '支付');
            if ($index === false) {
                return;
                // throw  new \Exception('输入格式有误,不含有【支付】字样');
            }
            if ($index == 0) {
                return;
                // throw  new \Exception('输入格式有误,请输入具体的支付名称');
            }

            //参与参数
            $pay = substr($playText, 0, $index) . '支付';
            $number = substr($playText, strlen($pay));
            //参与支付
            $joinPay = Db::name('Exposurepay')
                ->where('exposurepay_name', $pay)
                ->find();
            if (empty($joinPay)) {
                return;
                //   throw  new \Exception('你输入的支付方式不在本轮竞猜中');
            }
            //参与用户入库 ['user_id'=>1,'play_result'=>2,'number'=>100,'user_name'=>'shopxo']
            $join['user_id'] = $user['id'];
            $join['play_result'] = $joinPay['id'];
            $join['number'] = $number;
            $join['user_name'] = $data['from']['first_name'];


            $joinPersons = $pay_game_params['joinPersons'] ? $pay_game_params['joinPersons'] : [];
            array_push($joinPersons, $join);
            $pay_game_params['joinPersons'] = $joinPersons;
            $telegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 竞猜' . $pay . '成功');
        } catch (\Exception $exception) {
            $telegramBotService->sendGroupMessage($data['chat']['id'], '@' . $data['from']['first_name'] . ' 竞猜XX支付失败,失败原因【' . $exception->getMessage() . "】");
            Log::info("tg用户{$data['from']['first_name']}参与游戏竞猜失败,失败原因" . $exception->getMessage());
        }
        return;
    }

    /**
     * 连续多少次发现相同文本进行禁言提醒
     * @param $tgId
     * @param int $allowTimes
     */
    public static function sendMessageWhenSpeckSameWords($message, $allowTimes = 3, $maxTimes = 4)
    {
        $tgId = $message['from']['id'];
        $msgText = trim($message['text']);
        //获取用户上一次说的话
        $sayWords = cache($tgId . "_prev_saywold");
        $sayWordsSameTimes = cache($tgId . "_same_saywold_times");
        $sayWordsSameTimes = $sayWordsSameTimes ? $sayWordsSameTimes : 0;

        $setTimes = ($sayWords == $msgText) ? $sayWordsSameTimes + 1 : 1;
        //累加次数
        cache($tgId . "_same_saywold_times", $setTimes);
        $cTimes = cache($tgId . "_same_saywold_times");
        if ($cTimes >= $allowTimes) {
            $sendText = $cTimes == $allowTimes ? "检查到用户的话，连续相同{$allowTimes}次，机器人提醒用户 @{$message['from']['username']} 连续{$allowTimes}次发言有广告嫌疑，第四次管理员将进行禁言" :
                "该用户连续{$cTimes}次发送相同消息，可以送飞机票了";
            //触发三次相同提醒
            $telegramBotService = new TelegramBotService();
            $telegramBotService->sendGroupMessage($message['chat']['id'], $sendText);
            return;
        }
        cache($tgId . "_prev_saywold", $msgText);
    }


    /**"
     * 新用户进入tg触发事件
     * @param $message
     */
    public static function hookNewMemberEvent($message)
    {

        try {
            //①新用户注册
            UserService::autoRegisterByTg($message);
            //todo
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    /**
     *监听用户消息转发事件
     * @param $message
     */
    public static function hookForwardEvent($message)
    {
        $user = UserService::getUserinfoBykTg($message['forward_from']['id']);
        if (empty($user)) {
            throw  new \Exception("转发用户未录入");
        }

        $telegramBotService = new TelegramBotService();
        try {
            //用户有之前操作举报按钮的操作10min内tg可响应
            if (cache('trigger_callback_complain_btn_' . $message['from']['id'])) {
                //验证投诉人
                MessageBotService::validteUserTgId($message['from']['id']);

                //读取投诉人id和username
                $complain['complaint_uid'] = $message['from']['id'];
                $complain['complaint_username'] = $message['from']['first_name'];
                //被投诉人id和username
                $complain['tg_uid'] = $message['forward_from']['id'];
                $complain['tg_user'] = $message['forward_from']['first_name'];
                //被投诉消息内容
                $complain['exposure_name'] = '';
                $complain['exposure_gateway'] = '';
                $complain['integral'] = '';
                //保存到缓存中
                cache('complain_' . $message['from']['id'], json_encode($complain), 300);
                //返回消息 选择投诉理由
                $text = '请输入举报理由(不超过10个字)';
                $telegramBotService->sendGroupMessage($message['chat']['id'], $text);
                return;
            }

            $res = (new self)->complainRemind($message['chat']['id'], $message['forward_from']['id'], $message['forward_from']['first_name']);
            //如果当前被转发的人被投诉过输出投诉信息
            if ($res == false) {
                return;
            }

            //转发内容
            $message['text'] = '查询信息';//固定死为查询信息
            switch ($message['text']) {
                case '查询信息':
                    //存在
                    $sendText = self::sendTemplateWhenSearchUserInfo($user, $message['forward_from']['first_name']);
                    //发送文本
                    $telegramBotService->sendGroupMessage($message['chat']['id'], $sendText);
                    break;

            }
        } catch (\Exception $exception) {

            $telegramBotService->sendGroupMessage($message['chat']['id'], '@' . $message['from']['first_name'] . ' 操作转发,失败原因【' . $exception->getMessage() . "】");
            Log::error("hook hookForwardEvent error:" . $exception->getMessage());
        }
    }

    /**
     * 查询个人信息
     * @param $message
     */
    public function queryUserinfo($message)
    {
        $telegramBotService = new TelegramBotService();
        try {
            $user = UserService::getUserinfoBykTg($message['from']['id']);

            if (empty($user)) {
                throw  new \Exception("转发用户未录入");
            }
            //存在
            $sendText = self::sendTemplateWhenSearchUserInfo($user, $message['from']['first_name']);
            //发送文本
            $telegramBotService->sendGroupMessage($message['chat']['id'], $sendText);
        } catch (\Exception $exception) {
            $telegramBotService->sendGroupMessage($message['chat']['id'], '@' . $message['from']['first_name'] . ' 操作转发,失败原因【' . $exception->getMessage() . "】");
            Log::error("hook hookForwardEvent error:" . $exception->getMessage());
        }

    }


    /**
     * 监听转发消息
     * @param $message
     */
    public static function hookReplyEvent($message)
    {
        $telegramBotService = new TelegramBotService();
        $MessageBotService = new MessageBotService();

        try {
            //转发内容
            $text = str_replace(' ', '', $message['text']);

            switch ($message['text']) {
                case '投诉':
                    if ($message['reply_to_message']['from']['is_bot'] == false && !$MessageBotService->isAdmin($message['reply_to_message']['from']['id'])) {
                        //验证投诉人
                        MessageBotService::validteUserTgId($message['from']['id']);
                        //读取投诉人id和username
                        $complain['complaint_uid'] = $message['from']['id'];
                        $complain['complaint_username'] = $message['from']['first_name'];
                        //被投诉人id和username
                        $complain['tg_uid'] = $message['reply_to_message']['from']['id'];
                        $complain['tg_user'] = $message['reply_to_message']['from']['first_name'];
                        //被投诉消息内容
                        $complain['exposure_name'] = $message['reply_to_message']['text'];
                        $complain['exposure_gateway'] = '';
                        $complain['integral'] = '';
                        //保存到缓存中
                        cache('complain_' . $message['from']['id'], json_encode($complain), 300);
                        //返回消息 选择投诉理由
                        $text = '请输入举报理由(不超过10个字)';
                        $telegramBotService->sendGroupMessage($message['chat']['id'], $text);
                    }
                    break;
                case '取消投诉':
                    $MessageBotService->adminCancelComplaint($message);
                    break;
                case '查询信息':
                    $user = UserService::getUserinfoBykTg($message['reply_to_message']['from']['id']);
                    $sendText = self::sendTemplateWhenSearchUserInfo($user, $message['reply_to_message']['from']['first_name']);
                    $user && $telegramBotService->sendGroupMessage($message['chat']['id'], $sendText);
                    break;
                case strpos($text, '备注') !== false:
                    if ($message['reply_to_message']['from']['is_bot'] == false && !$MessageBotService->isAdmin($message['reply_to_message']['from']['id'])) {
                        //更有用户备注
                        if ($text == '查询信息') {
                            $user = UserService::getUserinfoBykTg($message['reply_to_message']['from']['id']);
                            $sendText = self::sendTemplateWhenSearchUserInfo($user, $message['reply_to_message']['from']['first_name']);
                            //发送文本
                            $user && $telegramBotService->sendGroupMessage($message['chat']['id'], $sendText);
                            return;
                        }
                        //更新备注
                        $desc = str_replace('备注', '', $text);
                        UserService::updateDesc($message['reply_to_message']['from']['id'], $desc);
                    }

                    break;
            }
        } catch (\Exception $exception) {
            Log::error("hook ReplyEvent error:" . $exception->getMessage());
        }
    }

    /**
     * 查询信息  发送模板
     * @param $userId
     */
    public static function sendTemplateWhenSearchUserInfo($userInfo, $tgName = '')
    {

        $registerTime = date('Y-m-d H:i', $userInfo['add_time']);
        $sendText = "姓  名:{$tgName}
用户名:{$userInfo['username']}
TG_ID:{$userInfo['user_telegram_id']}
发言次数:{$userInfo['say_times']}
注册时间:{$registerTime}
备  注:{$userInfo['desc']}";
        return $sendText;
    }


    /**
     * 执行投诉
     * @param $pushMsg
     */
    public static function hookMemberComplainEvent($pushMsg)
    {
        //判断投诉消息
        $TelegramBotService = new TelegramBotService();
        if (empty($pushMsg['text'])) {
            $TelegramBotService->sendGroupMessage($pushMsg['chat']['id'], '请输入投诉理由');
        }
        $complain = cache('complain_' . $pushMsg['from']['id']);
        if ($complain) {
            //判断是否有该投诉 没有的话添加到记录中
            $complain = json_decode($complain, true);
            $exposure = Db::name('Exposure')->where(['tg_uid' => $complain['tg_uid'], 'complaint_reason' => $pushMsg['text']])->find();
            if (!$exposure) {
                //添加
                $complain['complaint_reason'] = $pushMsg['text'];
                $complain['add_time'] = time();

                $result = Db::name('Exposure')->insert($complain);
            } else {
                $result = true;
            }
            if ($result) {
                cache('complain_' . $pushMsg['from']['id'], null);
                $TelegramBotService = new TelegramBotService();
                $TelegramBotService->sendGroupMessage($pushMsg['chat']['id'], '投诉提交成功');
                //清理缓存
                cache('botComplainList', null);
                return;
            }
        }
    }


    /**
     * 监听机器人推文时间（机器人发送）
     */
    public static function HookBootMessage($pushMsg)
    {
        //十秒后过期自动删除
        $ttlTime = 10;
        $redis = \think\facade\Cache::connect(['type' => 'redis'])->handler();
        $redis->setex('bot_message:' . $pushMsg['message_id'], $ttlTime, 1);
    }


    /**
     * 监听用户和机器人的私信
     * @param $pushMsg }
     */
    public static function HookUserBootMessage($message)
    {
        $telegramBotService = new TelegramBotService();
        $text = $message['text'];
        try {
            switch ($message['text']) {
                case strpos($text, '我要曝光') !== false:
                    $telegramBotService->sendGroupMessage($message['chat']['id'], '请转发一条骗子的消息给我');
                    break;
                default:
                    //默认就是曝光原因
                    $waitComplainKey = 'wait_complain_from_' . $message['from']['id'];

                    //带曝光信息保存一个消息
                    $complainInfo = cache($waitComplainKey);
                    if ($complainInfo) {

                        //有有曝光的用户记录
                        $complaint_username = isset($message['from']['username']) ? $message['from']['username'] : $message['from']['first_name'];
                        $save['add_time'] = time();
                        $save['complaint_reason'] = $message['text'];
                        $save['complaint_uid'] = $message['from']['id'];
                        $save['complaint_username'] = $complaint_username;
                        $save['tg_uid'] = $complainInfo['tg_uid'];
                        $save['tg_user'] = $complainInfo['tg_user'];
                        $result = Db::name('Exposure')->insertGetId($save);
                        cache($waitComplainKey, null);
                        if (!$result) {
                            $telegramBotService->sendGroupMessage($message['chat']['id'], '@' . $message['from']['first_name'] . ' 曝光失败');
                            return;
                        }
                        $telegramBotService->sendGroupMessage($message['chat']['id'], '@' . $message['from']['first_name'] . ' 曝光成功');

                    }
            }
        } catch (\Exception $exception) {
            Log::error("hook HookUserBootMessage error:" . $exception->getMessage());
        }


    }


    /**
     * 监听用户机器人私信的转发事件
     * @param $message
     */
    public static function hookUserBootForwardEvent($message)
    {
        $telegramBotService = new TelegramBotService();
        try {
            //带曝光信息
            $waitComplainKey = 'wait_complain_from_' . $message['from']['id'];
            //带曝光信息保存一个消息
            $val['tg_uid'] = $message['forward_from']['id'];
            $val['tg_user'] = isset($message['forward_from']['user_name']) ? $message['forward_from']['user_name'] :
                $message['forward_from']['first_name'];
            cache($waitComplainKey, $val, 3600);
            $telegramBotService->sendGroupMessage($message['chat']['id'], '请输入你要曝光的原因');
        } catch (\Exception $exception) {
            Log::error("hook hookUserBootForwardEvent error:" . $exception->getMessage());
        }

    }


    /**
     * hook用户操作事件回调
     * @param $message
     */
    public static function hookCallbackEvent($message)
    {
        $telegramBotService = new TelegramBotService();
        $text = $message['data'];//用户触发文本
        switch ($text) {
            case '查询骗子':
                $telegramBotService->sendGroupMessage($message['message']['chat']['id'], '请转发一段骗子的发言给我');
                break;
            case '骗子举报':
                //当前用户触发骗子举报按钮
                cache('trigger_callback_complain_btn_' . $message['from']['id'], 1, 600);
                $telegramBotService->sendGroupMessage($message['message']['chat']['id'], '请转发一段骗子的话给我，我开始记录信息');
                break;
        }
    }


    /**
     * 新人加入群主
     * @param $message
     */
    public static function hookNewMemberEevent($message)
    {

        $text = config('shopxo.new_member_add.text');
        $inline_keyboard = config('shopxo.new_member_add.inline_keyboard');
        $tgService = new  TelegramBotService();
        $keyboard = [
            'inline_keyboard' => $inline_keyboard
        ];
        $option['reply_markup'] = json_encode($keyboard);
        $tgService->sendGroupMessageWithOption($message['chat']['id'], $text, $option);
    }

}
