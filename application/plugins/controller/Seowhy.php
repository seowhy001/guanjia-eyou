<?php

namespace app\plugins\controller;
use think\Db;
use think\Session;
define('D_APP_PATH', dirname(dirname(__DIR__)));

/**
 * 插件的控制器
 */
class Seowhy extends Base
{
    function __construct()
    {
        parent::__construct();
       require(D_APP_PATH.'/admin/common.php');

        $guanjia_token = Db::name('weapp_seowhy')->where("key", "token")->value('content');
        $guanjia_time = intval($_REQUEST['guanjia_time']);
        if (!$guanjia_time) {
            $this->failRsp(1408, "password error", "time不存在");
        }

        if (time() - $guanjia_time > 600) {
            $this->failRsp(1409, "password error", "该token已超时！");
        }
        //token校验
        if (empty($_REQUEST['guanjia_token']) || md5($guanjia_time . $guanjia_token) != $_REQUEST['guanjia_token']) {
            $this->failRsp(1403, "password error");
        }
    }

    function articleAdd()
    {
        if (isset($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on") {
            $http = "https://";
        } else {
            $http = "http://";
        }
        $domain = $http . str_replace('\\', '/', $_SERVER['HTTP_HOST']);


//检查标题
        $title = isset($_REQUEST['title']) ? addslashes($_REQUEST['title']) : '';//标题
        if (empty($title)) {
            $this->failRsp(1404, "title is empty", "标题不能为空");
        }
//检查内容
        $content = isset($_REQUEST['content']) ? $_REQUEST['content'] : '';
        if (empty($content)) {
            $this->failRsp(1404, "content is empty", "内容不能为空");
        }
//检查栏目
        $typeid = isset($_REQUEST['category_id']) ? intval($_REQUEST['category_id']) : '';
        if (empty($typeid)) {
            $this->failRsp(1404, "typeid is empty", "栏目ID不能为空");
        }

//检查频道模型
        $channel = isset($_REQUEST['channel']) ? intval($_REQUEST['channel']) : 1;
        if (empty($channel)) {
            $this->failRsp(1404, "channel is empty", "频道模型不能为空");
        }

//    if ($titleUnique) {
//        $archivesList = Db::name('archives')->where('title', $title)->select();
//        $existAid = $archivesList[0]['aid'];
//        if ($existAid > 0) {
//            return $this->successRsp(array("url" => $domain . "/index.php?m=home&c=View&a=index&aid=" . $existAid), '标题已存在');
//        }
//    }

        $seo_keywords = $_REQUEST['seo_keywords'];
        if (!empty($seo_keywords)) {
            $seo_keywords = str_replace('，', ',', $seo_keywords);
        }

        $litpic = $this->getThumb($_REQUEST);
//是否有封面图
        if (empty($litpic)) {
            $is_litpic = 0; // 无封面图
        } else {
            $is_litpic = 1; // 有封面图
        }
        if (empty($litpic) && $_REQUEST['__guanjia_download_imgs_flag'] != true) {
            $litpic = get_html_first_imgurl($content) ?: '';

            if (empty($litpic)) {
                $is_litpic = 0; // 无封面图
            } else {
                $is_litpic = 1; // 有封面图
            }
        }
// 描述
        $seo_description = '';
        if (empty($_REQUEST['seo_description']) && !empty($content)) {
            $seo_description = @msubstr(checkStrHtml($content), 0, config('global.arc_seo_description_length'), false);
        } else {
            $seo_description = $_REQUEST['seo_description'];
        }

        if (!empty($_REQUEST['add_time'])) {
            $add_time = $_REQUEST['add_time'];
        } else {
            $add_time = time();
        }
// --存储数据
        $newData = array(
            'typeid'          => $typeid,
            'channel'         => $channel,
            'is_b'            => empty($_REQUEST['is_b']) ? 0 : $_REQUEST['is_b'],
            'title'           => $title,
            'litpic'          => $litpic,
            'is_head'         => empty($_REQUEST['is_head']) ? 0 : $_REQUEST['is_head'],//头条（0=否，1=是）
            'is_special'      => empty($_REQUEST['is_special']) ? 0 : $_REQUEST['is_special'],//特荐（0=否，1=是）
            'is_top'          => empty($_REQUEST['is_top']) ? 0 : $_REQUEST['is_top'],//置顶（0=否，1=是）
            'is_recom'        => empty($_REQUEST['is_recom']) ? 0 : $_REQUEST['is_recom'],//推荐（0=否，1=是）
            'is_litpic'       => $is_litpic,
            'author'          => $_REQUEST['author'],
            'click'           => mt_rand(100, 300),
            'arcrank'         => empty($_REQUEST['arcrank']) ? 0 : $_REQUEST['arcrank'],//阅读权限：0=开放浏览，-1=待审核稿件
            'seo_title'       => $_REQUEST['seo_title'],
            'seo_keywords'    => $seo_keywords,
            'seo_description' => $seo_description,
            'tempview'        => '',
            'status'          => 1,
            'admin_id'        => 1,
            'lang'            => $this->admin_lang,
            'sort_order'      => 100,
            'add_time'        => $add_time,
            'update_time'     => $add_time,
            'content' => $content,
            'tags'    => $_REQUEST['tags'],
        );
        $aid = M('archives')->insertGetId($newData);


        if ($aid) {
            //添加文章内容
            $this->afterSave($aid, $newData, 'add');

            $fields = "b.*, a.*, a.aid as aid";
            $row = Db::name('archives')

                ->field($fields)

                ->alias('a')

                ->join('__ARCTYPE__ b', 'a.typeid = b.id', 'LEFT')

                ->where('a.aid', 'in', $aid)

                ->getAllWithIndex('aid');

            $docFinalUrl = $this->get_arcurl($row[$aid],false);
            $this->downloadImages($_REQUEST);
            $this->successRsp(array("url" => $docFinalUrl));
        } else {
            $this->failRsp(1403, "insert archives,article_content error", "文章发布错误");
        }
    }

    function categoryLists()
    {
        $_REQUEST['model_hash'] = $_REQUEST['model_hash'] ? $_REQUEST['model_hash'] : 1;
        $lists = Db::name('arctype')->where('is_del', 0)->where('current_channel', $_REQUEST['model_hash'])->field('id,typename as title')->select();
        $this->successRsp($lists);
    }

    function saveAfter(){

        Session::pause(); // 暂停session，防止session阻塞机制
        sitemap_auto();
        $this->successRsp([],'更新sitemap成功！');

    }

    function version()
    {
        $content = file_get_contents('./config.php');
        $this->successRsp($content);
    }


    /**
     * 后置操作方法
     * 自定义的一个函数 用于数据保存后做的相应处理操作, 使用时手动调用
     * @param int $aid 产品id
     * @param        $data
     * @param string $opt 操作
     * @return void
     */
    private function afterSave($aid, $data, $opt)
    {
        //写入文章内容

        Db::name('article_content')->data(array(
                                              'aid'         => $aid,
                                              'content'     => $data['content'],
                                              'add_time'    => $data['add_time'],
                                              'update_time' => $data['update_time']
                                          ))->insert();

        // --处理TAG标签
        model('Taglist')->savetags($aid, $data['typeid'], $data['tags']);
    }

    /**
     * 获取文件完整路径
     * @return string
     */

    function getFilePath()
    {
        $rootUrl = dirname(dirname(dirname(dirname(__FILE__))));
        return $rootUrl . '/uploads/ueditor';
    }

    /**
     * 查找文件夹，如不存在就创建并授权
     * @return string
     */
    function createFolders($dir)
    {
        return is_dir($dir) or ($this->createFolders(dirname($dir)) and mkdir($dir, 0777));
    }

    function getThumb($post)
    {
        try {
            $downloadFlag = isset($post['__guanjia_download_imgs_flag']) ? $post['__guanjia_download_imgs_flag'] : '';
            if (!empty($downloadFlag) && $downloadFlag == "true") {
                $docImgsStr = isset($post['__guanjia_docImgs']) ? $post['__guanjia_docImgs'] : '';
                if (!empty($docImgsStr)) {
                    $docImgs = explode(',', $docImgsStr);
                    if (is_array($docImgs)) {
                        $i = 0;
                        foreach ($docImgs as $imgUrl) {
                            $i = $i + 1;
                            $urlItemArr = explode('/', $imgUrl);
                            $itemLen = count($urlItemArr);
                            if ($itemLen >= 3) {
                                //
                                $fileRelaPath = $urlItemArr[$itemLen - 3] . '/' . $urlItemArr[$itemLen - 2];
                                $imgName = $urlItemArr[$itemLen - 1];
                                $finalPath = '/uploads/ueditor' . '/' . $fileRelaPath . '/' . $imgName;
                                if ($i == 1) {
                                    return $finalPath;
                                }
                            }
                        }//.for
                    }//..is_array
                }
            }
        }
        catch (Exception $ex) {

        }
    }

    function downloadImages($post)
    {
        try {

            $downloadFlag = isset($post['__guanjia_download_imgs_flag']) ? $post['__guanjia_download_imgs_flag'] : '';
            if (!empty($downloadFlag) && $downloadFlag == "true") {
                $docImgsStr = isset($post['__guanjia_docImgs']) ? $post['__guanjia_docImgs'] : '';

                if (!empty($docImgsStr)) {
                    $docImgs = explode(',', $docImgsStr);
                    if (is_array($docImgs)) {
                        $uploadDir = $this->getFilePath();
                        foreach ($docImgs as $imgUrl) {
                            $urlItemArr = explode('/', $imgUrl);
                            $itemLen = count($urlItemArr);
                            if ($itemLen >= 3) {
                                //
                                $fileRelaPath = $urlItemArr[$itemLen - 3] . '/' . $urlItemArr[$itemLen - 2];
                                $imgName = $urlItemArr[$itemLen - 1];
                                $finalPath = $uploadDir . '/' . $fileRelaPath;
                                if ($this->createFolders($finalPath)) {
                                    $file = $finalPath . '/' . $imgName;
                                    if (!file_exists($file)) {
                                        $doc_image_data = file_get_contents($imgUrl);
                                        file_put_contents($file, $doc_image_data);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        catch (Exception $ex) {
        }
    }

    function successRsp($data = "", $msg = "")
    {
        $this->rsp(1, $data, $msg);
    }

    function failRsp($code = 0, $data = "", $msg = "")
    {
        $this->rsp($code, $data, $msg);
    }

    function rsp($code = 0, $data = "", $msg = "")
    {
        die(json_encode(array("code" => $code, "data" => $data, "msg" => urlencode($msg))));
    }

    /**
     * 获取文档链接
     *
     * @param array $arctype_info 栏目信息
     * @param boolean $admin 后台访问链接，还是前台链接
     * @param string  $domain_type   mobile：手机端
     */
    function get_arcurl($arcview_info = array(), $admin = true,$domain_type = '')
    {
        if ($domain_type == 'mobile'){
            $domain = tpCache('web.web_mobile_domain');
        }else{
            static $domain = null;
            null === $domain && $domain = request()->domain();
        }
        /*兼容采集没有归属栏目的文档*/
        if (empty($arcview_info['channel'])) {
            $channelRow = \think\Db::name('channeltype')->field('id as channel')
                ->where('id',1)
                ->find();
            $arcview_info = array_merge($arcview_info, $channelRow);
        }
        /*--end*/

        static $result = null;
        null === $result && $result = model('Channeltype')->getAll('id, ctl_name', array(), 'id');
        $ctl_name = '';
        if ($result) {
            $ctl_name = $result[$arcview_info['channel']]['ctl_name'];
        }

        static $seo_pseudo = null;
        static $seo_dynamic_format = null;
        if (null === $seo_pseudo || null === $seo_dynamic_format) {
            $seoConfig = tpCache('seo');
            $seo_pseudo = !empty($seoConfig['seo_pseudo']) ? $seoConfig['seo_pseudo'] : config('ey_config.seo_pseudo');
            $seo_dynamic_format = !empty($seoConfig['seo_dynamic_format']) ? $seoConfig['seo_dynamic_format'] : config('ey_config.seo_dynamic_format');
        }

        if ($admin) {
            if (2 == $seo_pseudo) {
                static $lang = null;
                null === $lang && $lang = input('param.lang/s', 'cn');
                $arcurl = ROOT_DIR."/index.php?m=home&c=View&a=index&aid={$arcview_info['aid']}&lang={$lang}&admin_id=".session('admin_id');
            } else {
                $arcurl = arcurl("home/{$ctl_name}/view", $arcview_info, true, $domain, $seo_pseudo, $seo_dynamic_format);
                if (config('city_switch_on')) {
                    $url_path = parse_url($arcurl, PHP_URL_PATH);
                    $url_path = str_replace('.html', '', $url_path);
                    $url_path = '/'.trim($url_path, '/').'/';
                    preg_match_all('/\/site\/([^\/]+)\//', $url_path, $matches);
                    $site_domain = !empty($matches[1][0]) ? $matches[1][0] : '';
                    if (!empty($site_domain)) {
                        $url_path_new = str_replace("/site/{$site_domain}/", '', $url_path);
                        $root_dir_str = str_replace('/', '\/', ROOT_DIR);
                        $url_path_new = preg_replace("/^{$root_dir_str}\//", ROOT_DIR."/{$site_domain}/", $url_path_new);
                        $arcurl = str_replace(rtrim($url_path, '/'), $url_path_new, $arcurl);
                    }
                }
                // 自动隐藏index.php入口文件
                $arcurl = auto_hide_index($arcurl);
                if (stristr($arcurl, '?')) {
                    $arcurl .= '&admin_id='.session('admin_id');
                } else {
                    $arcurl .= '?admin_id='.session('admin_id');
                }
            }
        } else {
            $arcurl = arcurl("home/{$ctl_name}/view", $arcview_info, true, $domain, $seo_pseudo, $seo_dynamic_format);
            // 自动隐藏index.php入口文件
            $arcurl = auto_hide_index($arcurl);
        }

        return $arcurl;
    }

}
