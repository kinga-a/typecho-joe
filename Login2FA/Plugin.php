<?php

namespace TypechoPlugin\Login2FA;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Plugin\Exception;
use Typecho\Plugin\PluginInterface;
use Typecho\Request;
use Typecho\Widget;
use Typecho\Widget\Helper\Form;
use Utils\Helper;
use Widget\Notice;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 多用户 OTP 二次认证插件 <a href="https://www.dbkuaizi.com/archives/login2fa.html">使用帮助</a>
 *
 * @package Login2FA
 * @author 两双筷子
 * @version 1.0.0
 * @link https://www.dbkuaizi.com
 */
class Plugin implements PluginInterface
{
    // 插件名称
    private static $pluginName = 'Login2FA';
    // 窗口期选项
    private static $windowTimes = [
        '1' => '30&emsp;秒&emsp;(半分钟)',
        '2' => '60&emsp;秒&emsp;(一分钟)',
        '3' => '90&emsp;秒&emsp;(一分半)',
        '4' => '120&ensp;秒&emsp;(两分钟)',
        '5' => '150&ensp;秒&emsp;(两分半)',
        '6' => '180&ensp;秒&emsp;(三分钟)',
        '7' => '210&ensp;秒&emsp;(三分半)',
        '8' => '240&ensp;秒&emsp;(四分钟)',
        '9' => '270&ensp;秒&emsp;(四分半)',
        '10' => '300&ensp;秒&emsp;(五分钟)',
    ];

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        // 校验版本是否为 1.2.0+
        if (version_compare(Common::VERSION, '1.2.0', '<')) {
            throw new Exception('此插件仅支持 1.2.0 及以上版本的 Typecho 程序');
        }

        // 注册挂载
        \Typecho\Plugin::factory('admin/footer.php')->end = __CLASS__ . '::loginInput';
        \Typecho\Plugin::factory('Widget_Login')->loginSucceed = __CLASS__ . '::LoginVerify';

        // 返回提示信息
        return '插件安装成功';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
        $pluginConfig = \Widget\Options::alloc()->plugin(self::$pluginName);
        if (!$pluginConfig->uninstall) {
            throw new Exception('请先修改插件配置，允许卸载插件后重试。');
        }
    }


    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {

        $windowTime = new Form\Element\Select('windowTime',
            self::$windowTimes, '0', _t('令牌容错窗口期'), _t('当验证 OTP 令牌时，服务器和客户端的时间可能不完全一致。为了解决这个问题，通过设置“容错窗口”，允许在 N 秒内存在时间误差，仍然能够成功验证。'));
        $form->addInput($windowTime);

        // 防呆设计
        $uninstall = new Form\Element\Radio('uninstall',
            array(1 => _t('确认卸载'), 0 => _t('禁止卸载')), '0', _t('是否允许卸载插件'), _t('卸载插件会导致所有用户的二次认证数据清空，且无法恢复，请慎重选择。'));
        $form->addInput($uninstall);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
        $config = self::getUserConfig();
        if (isset($config['secret'])) {
            self::genUnBindForm($form);
        } else {
            self::genBindForm($form);
        }
    }

    // 自助保存
    public static function personalConfigHandle($settings, $isSetup)
    {
        $name = '_plugin:' . self::$pluginName;
        $db = Db::get();
        // 如果是插件初始化 就只写一个空的序列化
        if ($isSetup) {
            Options::alloc()->insert([
                'name' => $name,
                'value' => serialize([]),
                'user' => 0
            ]);
            return;
        }

        // 如果是绑定 接受前端提交的 密钥 否则 使用后端提供的
        if ($settings['action'] == 'bind') {
            $secret = $settings['secret'];
        } else {
            $secret = self::getUserConfig('secret');
        }

        $pluginConfig = Options::alloc()->plugin(self::$pluginName);

        // 不管是绑定还是解绑 都先校验令牌
        if (!(new GoogleAuthenticator())->verifyCode($secret, $settings['code'], $pluginConfig->windowTime)) {
            Notice::alloc()->set(_t('数字令牌校验失败'), 'error');
            Helper::options()->response->goBack();
        }
        // 获取用户 id
        $uid = Widget::widget('Widget_User')->uid;

        // 判断用户是绑定 2fa 还是解绑
        if ($settings['action'] == 'bind') {
            Options::alloc()->insert([
                'name' => $name,
                'value' => serialize(['secret' => $settings['secret']]),
                'user' => $uid
            ]);
            Notice::alloc()->set(_t('两步认证开启成功'), 'success');
        } else {
            Options::alloc()->delete(
                $db->sql()->where('name = ? AND user = ?', $name, $uid)
            );
            Notice::alloc()->set(_t('两步认证关闭成功'), 'success');
        }

        Helper::options()->response->goBack();
    }

    /**
     * 登录页面添加 otp 输入框
     * @return void
     */
    public static function loginInput()
    {
        if (Widget::widget('Widget_User')->hasLogin()) return;
        $inputHTML = <<<HTML
<p><label for="otp" class="sr-only"><?php _e('OTP令牌'); ?></label><input type="number" name="otp-code" class="text-l w-100" style="border: 1px solid #D9D9D6;box-sizing: border-box" placeholder="OTP令牌" /></p>
HTML;
        echo '<script> $(".submit").before("' . addslashes($inputHTML) . '"); </script>';
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function LoginVerify()
    {
        // 判断当前用户是否设置了 2FA
        if ($secret = self::getUserConfig('secret')) {
            $code = Request::getInstance()->get('otp-code');
            $pluginConfig = Options::alloc()->plugin(self::$pluginName);
            if (!(new GoogleAuthenticator())->verifyCode($secret, $code, $pluginConfig->windowTime)) {
                Cookie::delete('__typecho_uid');
                Cookie::delete('__typecho_authCode');
                Notice::alloc()->set(_t('OTP 令牌校验失败'), 'error');
                header('location: ' . __TYPECHO_ADMIN_DIR__);
                Helper::options()->response->goBack();
            }
        }
    }

    /**
     * 生成绑定表单
     */
    private static function genBindForm(Form $form)
    {
        // 生成随机密钥
        $secret = (new GoogleAuthenticator())->createSecret();
        $optauth = sprintf('otpauth://totp/%s:%s?secret=%s&issuer=%s',
            Widget::widget('Widget_Options')->title,
            Widget::widget('Widget_User')->name,
            $secret,
            Widget::widget('Widget_Options')->title);
        $qrcodeScript = sprintf('%s/%s/jquery.qrcode.min.js',
            Helper::options()->pluginUrl,
            pathinfo(__DIR__, PATHINFO_BASENAME));
        $QRcodeScript = <<<SCRIPT
    <div>
    <div id="totp-qrcode" class="w-60" style="display:flex;justify-content: center;margin-top: 10px;">二维码加载中，请稍后</div>
        <p class="w-60" style="text-align: center">密钥：<b>{$secret}</b><br>使用身份验证器扫描上方二维码或手动绑定</p>
</div>
<script>
    window.onload = function() {
      $.getScript("{$qrcodeScript}",function() {
          document.getElementById("totp-qrcode").innerHTML = '';
          $('#totp-qrcode').qrcode({width:200,height:200,text:"{$optauth}"})
          $('#Login2FA').find('button[type=submit]').text('启用两步验证')
      })
    }
</script>
SCRIPT;
        $code = new Form\Element\Text('code', ['class' => 'w-60'], null, _t('输入 6 位数字令牌'), $QRcodeScript);
        $code->input->setAttribute('class', 'w-60');
        $form->addInput($code);
        $hiddenSecret = new Form\Element\Hidden('secret');
        $form->addInput($hiddenSecret->value($secret));

        $hiddenState = new Form\Element\Hidden('action');
        $form->addInput($hiddenState->value('bind'));
    }

    private static function genUnBindForm(Form $form)
    {
        $QRcodeScript = <<<SCRIPT
<script>
    window.onload = function() {
      $('#Login2FA').find('button[type=submit]').text('关闭两步验证')
    }
</script>
SCRIPT;
        $code = new Form\Element\Text('code', ['class' => 'w-60'], null, _t('输入 6 位数字令牌'), $QRcodeScript);
        $code->input->setAttribute('class', 'w-60');
        $form->addInput($code);

        // 没有会报错 必须有 但解绑校验不能用这个不安全
        $hiddenSecret = new Form\Element\Hidden('secret');
        $form->addInput($hiddenSecret);

        $hiddenState = new Form\Element\Hidden('action');
        $form->addInput($hiddenState->value('unbind'));
    }

    /**
     * 获取当前登录用户的插件配置
     * @return array|mixed
     * @throws Db\Exception
     */
    private static function getUserConfig($key = null)
    {
        $Query = Options::alloc()->select()->where('name = ? and user = ?',
            '_plugin:' . self::$pluginName,
            Widget::widget('Widget_User')->uid
        );

        $result = Db::get()->fetchObject($Query);
        if (is_null($result)) return [];
        $config = unserialize($result->value);
        if (is_null($key)) {
            return $config;
        } else {
            return $config[$key] ?? '';
        }

    }
}
