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
namespace app\admin\controller;

use app\service\ConfigService;
use app\service\MessageBotService;
use app\service\TelegramBotService;

/**
 * tg机器人相关管理
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2016-12-01T21:51:08+0800
 */
class Tgbot extends Common
{
    /**
     * 构造方法
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  0.0.1
     * @datetime 2016-12-03T12:39:08+0800
     */
    public function __construct()
    {
        // 调用父类前置方法
        parent::__construct();

        // 登录校验
        $this->IsLogin();

        // 权限校验
        $this->IsPower();
    }

    /**
     * 配置列表
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-16
     * @desc    description
     */
    public function Index()
    {
        // 配置信息
        $this->assign('data', ConfigService::ConfigList());

        // 编辑器文件存放地址
        $this->assign('editor_path_type', 'agreement');

        // 导航/视图
        $nav_type = input('nav_type', 'index');
        $this->assign('nav_type', $nav_type);

        return $this->fetch($nav_type);
    }

    /**
     * 配置数据保存
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-16
     * @desc    description
     */
    public function sendMessage()
    {
        // 参数校验
        $message = input('message');
        if (empty($message)) {
            return DataReturn('请输入发送内容', -1);
        }
        $TelegramBotService = new TelegramBotService();
        $TelegramBotService->sedDefaultGroupMessage($message);
        return DataReturn('请输入发送内容', -1);
    }
}

?>