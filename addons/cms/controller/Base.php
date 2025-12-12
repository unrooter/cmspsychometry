<?php

namespace addons\cms\controller;

use addons\cms\library\IntCode;
use addons\cms\library\Service;
use addons\cms\model\SpiderLog;
use think\Cache;
use think\Config;
use think\Lang;
use think\Request;

/**
 * CMS控制器基类
 */
class Base extends \think\addons\Controller
{

    // 初始化
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $config = get_addon_config('cms');
        // 设定主题模板目录
        $this->view->engine->config('view_path', $this->view->engine->config('view_path') . $config['theme'] . DS);
        // 加载自定义标签库
        //$this->view->engine->config('taglib_pre_load', 'addons\cms\taglib\Cms');
        // 默认渲染栏目为空
        $this->view->assign('__CHANNEL__', null);
        $this->view->assign('isWechat', strpos($this->request->server('HTTP_USER_AGENT'), 'MicroMessenger') !== false);

        $this->lang = 'zh-cn';//zh-cn
        /*设置语言*/
        $lang = cookie('frontend_language')?cookie('frontend_language'):'zh-cn';
        if ($this->request->param('lg')) {
            /*判断语言是否存在*/
            $get_lang = request()->param('lg');
//            if($lang != $get_lang){
//                Cache::clear();
//            }
            $lang = request()->param('lg');
            cookie('frontend_language', $lang);
        }
        $this->lang = $lang;
        /*加载语言包*/
        $this->loadlangall();
        $this->view->assign('__LANG__', $this->lang);

        // 定义CMS首页的URL
        Config::set('cms.indexurl', addon_url('cms/index/index', [], false));
        // 定义分页类
        Config::set('paginate.type', '\\addons\\cms\\library\\Bootstrap');

        //判断站点状态
        if (isset($config['openedsite']) && !in_array('pc', explode(',', $config['openedsite']))) {
            if ($this->controller != 'order' && $this->action != 'epay') {
                $this->error('站点已关闭');
            }
        }
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('token')));
        $this->view->assign("token", $token);
    }

    /**
     * 加载语言文件
     * @param string $name
     */
    protected function loadlangall()
    {
        $lang = $this->lang;
        /*加载语言包*/
        $lang = preg_match("/^([a-zA-Z\-_]{2,10})\$/i", $this->lang) ? $this->lang : 'zh-cn';
        Lang::load(ADDON_PATH . $this->addon . '/lang/' . $lang . '.php');
    }

    public function _initialize()
    {
        parent::_initialize();
        // 如果请求参数action的值为一个方法名,则直接调用
        $action = $this->request->post("action");
        if ($action && $this->request->isPost()) {
            return $this->$action();
        }
    }

    /**
     * 是否加密ID处理
     */
    protected function hashids($name = 'id')
    {
        $config = get_addon_config('cms');
        $getValue = $this->request->get($name);
        $postValue = $this->request->post($name);
        if ($config['archiveshashids'] && ($getValue || $postValue)) {
            if ($getValue) {
                $getValue = (int)IntCode::decode($getValue);
                $this->request->get([$name => $getValue]);
            }
            if ($postValue) {
                $postValue = (int)IntCode::decode($postValue);
                $this->request->post([$name => $postValue]);
            }
            $this->request->param('');
        }
    }

}
