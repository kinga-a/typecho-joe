<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * WangStyle是一款即插即用的typecho后台美化插件
 *
 * @package WangStyle
 * @author 小王先森
 * @version 6.4.1
 * @link https://xwsir.cn
 * @dependence 1.3.0
 */
class WangStyle_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 旧版本插件替换后台首页时写入的特征字符串，用于识别被替换的 admin/index.php。
     * 该字符串来自旧版自带首页里独有的“快捷方式”卡片标题（Typecho 1.3 核心首页没有）。
     */
    const MARKER = '快捷方式';

    /**
     * 激活插件方法
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 通过 header 钩子注入美化资源，不再替换任何核心文件
        Typecho_Plugin::factory('admin/header.php')->header = array('WangStyle_Plugin', 'render');

        // 自愈旧版本（≤6.3）残留的 admin/index.php 替换
        self::repairCoreDashboard();
    }

    /**
     * 禁用插件方法
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        // 禁用时同样清理旧版本残留，避免旧 index.bak 影响核心后台
        self::repairCoreDashboard();
    }

    /**
     * 自愈旧版本对 admin/index.php 的替换。
     *
     * 旧版（≤6.3）激活时会把核心 admin/index.php 改名为 admin/index.bak，
     * 再把插件自带的首页复制为 admin/index.php。升级到 Typecho 1.3 后，
     * 核心 index.php 已被新版覆盖，但 index.bak 仍是旧版备份；若此时按旧逻辑
     * 禁用插件，会用旧 index.bak 覆盖新版首页，导致后台打不开。
     *
     * 这里统一处理：
     *  - 核心 index.php 缺失而 bak 存在  -> 用 bak 救回；
     *  - 当前 index.php 是插件旧版     -> 用 bak 还原核心；
     *  - 核心 index.php 已是新版        -> 仅删除残留 bak。
     */
    private static function repairCoreDashboard()
    {
        $core = __TYPECHO_ROOT_DIR__ . '/admin/index.php';
        $bak  = __TYPECHO_ROOT_DIR__ . '/admin/index.bak';

        if (!file_exists($bak)) {
            return;
        }

        if (!file_exists($core)) {
            @rename($bak, $core);
        } elseif (false !== strpos((string) file_get_contents($core), self::MARKER)) {
            @unlink($core);
            @rename($bak, $core);
        } else {
            @unlink($bak);
        }
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function render($head)
    {
        $url = Helper::options()->pluginUrl . '/WangStyle/assets/';

        if (Typecho_Widget::widget('Widget_User')->hasLogin()) {
            //gravatar 头像源
            define('__TYPECHO_GRAVATAR_PREFIX__', '//' . 'dn-qiniu-avatar.qbox.me/avatar' . '/');
            $user = Typecho_Widget::widget('Widget_User');
            $menu = Typecho_Widget::widget('Widget_Menu')->to($menu);
            $email = $user->mail;
            if ($email) {
                $lowercase = strtolower($email);
                $format = str_replace('@qq.com', '', $lowercase);
                if (strstr($lowercase, "qq.com") && is_numeric($format) && strlen($format) < 11 && strlen($format) > 4) {
                    $qqImage = '//q1.qlogo.cn/g?b=qq&nk=' . $format . '&';
                } else {
                    $decode = md5($lowercase);
                    $qqImage = '//' . 'dn-qiniu-avatar.qbox.me/avatar' . '/' . $decode . '?';
                }
            } else {
                $qqImage = $url . 'img/user.png';
            }
            $head = $head . '<link rel="stylesheet" href="' . $url . 'css/admin.css?v=6.4.1">
                <link rel="stylesheet" href="//at.alicdn.com/t/font_1159885_m16xrs3st3k.css">
                <script src="' . $url . 'js/admin.js?v=6.4.1"></script>
                <script>
                    var UserLink="' . Helper::options()->adminUrl . '/profile.php";
                    var UserPic="' . $qqImage . '";
                    var AdminLink="' . Helper::options()->adminUrl . '";
                    var SiteLink="' . Helper::options()->siteUrl . '";
                    var UserName="' . $user->screenName . '";
                    var UserGroup="' . $user->group . '";
                    var SiteName="' . Helper::options()->title . '";
                    var MenuTitle="' . strip_tags($menu->title) . '";
                </script>';
        } else {
            $head = $head . '<link rel="stylesheet" href="' . $url . 'css/login.css">';
        }
        return $head;
    }
}
