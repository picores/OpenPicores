<?php
/*
 * OpenPicores 核心入口文件
 * @author  Hario Ban<bewill@foxmail.com>
 * @version Pic1.0
 */

if (!defined('SITE_PATH')) exit();

ini_set('magic_quotes_runtime', 0);
date_default_timezone_set('UTC');

$time_include_start = microtime(TRUE);
$mem_include_start = memory_get_usage();

//设置全局变量pic
$pic['_debug']	=	false;		//调试模式
$pic['_define']	=	array();	//全局常量
$pic['_config']	=	array();	//全局配置
$pic['_access']	=	array();	//访问配置
$pic['_router']	=	array();	//路由配置

picdefine('IS_CGI',substr(PHP_SAPI, 0, 3)=='cgi' ? 1 : 0 );
picdefine('IS_WIN',strstr(PHP_OS, 'WIN') ? 1 : 0 );
picdefine('IS_HTTPS',0);

// 当前文件名
if(!defined('_PHP_FILE_')) {
	if(IS_CGI) {
		// CGI/FASTCGI模式下
		$_temp  = explode('.php',$_SERVER["PHP_SELF"]);
		define('_PHP_FILE_', rtrim(str_replace($_SERVER["HTTP_HOST"],'',$_temp[0].'.php'),'/'));
	}else {
		define('_PHP_FILE_', rtrim($_SERVER["SCRIPT_NAME"],'/'));
	}
}

// 网站URL根目录
if(!defined('__ROOT__')) {	
	$_root = dirname(_PHP_FILE_);
	define('__ROOT__',  (($_root=='/' || $_root=='\\')?'':rtrim($_root,'/')));
}

//基本常量定义
picdefine('CORE_PATH'	,	dirname(__FILE__));
picdefine('THINK_PATH'	,	CORE_PATH.'/ThinkPHP');

picdefine('SITE_DOMAIN'	,	strip_tags($_SERVER['HTTP_HOST']));
picdefine('SITE_URL'		,	(IS_HTTPS?'https:':'http:').'//'.SITE_DOMAIN.__ROOT__);

picdefine('CONF_PATH'	,	SITE_PATH.'/config');

picdefine('APPS_PATH'	,	SITE_PATH.'/apps');
picdefine('APPS_URL'		,	SITE_URL.'/apps');	# 应用内部图标 等元素

picdefine('ADDON_PATH'	,	SITE_PATH.'/addons');
picdefine('ADDON_URL'	,	SITE_URL.'/addons');

picdefine('DATA_PATH'	,	SITE_PATH.'/data');
picdefine('DATA_URL'		,	SITE_URL.'/data');

picdefine('UPLOAD_PATH'	,	DATA_PATH.'/upload');
picdefine('UPLOAD_URL'	,	SITE_URL.'/data/upload');

picdefine('PUBLIC_PATH'	,	SITE_PATH.'/public');
picdefine('PUBLIC_URL'	,	SITE_URL.'/public');

//载入核心模式: 默认是OpenPicores. 也支持ThinkPHP
if(!defined('CORE_MODE'))	define('CORE_MODE','OpenPicores');

picdefine('CORE_LIB_PATH'	,	CORE_PATH.'/'.CORE_MODE);
picdefine('CORE_RUN_PATH'	,	SITE_PATH.'/_runtime');
picdefine('LOG_PATH'			,	CORE_RUN_PATH.'/logs/');

//注册AUTOLOAD方法
if ( function_exists('spl_autoload_register') )
	spl_autoload_register('picautoload');

//载入核心运行时文件
if(file_exists(CORE_PATH.'/'.CORE_MODE.'Runtime.php') && !$pic['_debug']){
	include CORE_PATH.'/'.CORE_MODE.'Runtime.php';
}else{
	include CORE_LIB_PATH.'/'.CORE_MODE.'.php';
}

/* 核心方法 */

/**
 * 载入文件 去重\缓存.
 * @param string $filename 载入的文件名
 * @return boolean
 */
function picload($filename) {
	
	static $_importFiles = array();	//已载入的文件列表缓存
	
	$key = strtolower($filename);
	
	if (!isset($_importFiles[$key])) {
		
		if (is_file($filename)) {
			
			require_once $filename;
			$_importFiles[$key] = true;
		} elseif(file_exists(CORE_LIB_PATH.'/'.$filename.'.class.php')) {
			
			require_once CORE_LIB_PATH.'/'.$filename.'.class.php';
			$_importFiles[$key] = true;
		} else {
			
			$_importFiles[$key] = false;
		}
	}
	return $_importFiles[$key];
}

/**
 * 系统自动加载函数
 * @param string $classname 对象类名
 * @return void
 */
function picautoload($classname) {
	
	// 检查是否存在别名定义
	if(picload($classname)) return ;
	
	// 自动加载当前项目的Actioon类和Model类
	if(substr($classname,-5)=="Model") {
		if(!picload(ADDON_PATH.'/model/'.$classname.'.class.php'))
			picload(APP_LIB_PATH.'/Model/'.$classname.'.class.php');

	}elseif(substr($classname,-6)=="Action"){
		picload(APP_LIB_PATH.'/Action/'.$classname.'.class.php');

	}elseif(substr($classname,-6)=="Widget"){
		if(!picload(ADDON_PATH.'/widget/'.$classname.'.class.php'))
			picload(APP_LIB_PATH.'/Widget/'.$classname.'.class.php');

	}elseif(substr($classname,-6)=="Addons"){
		if(!picload(ADDON_PATH.'/plugin/'.$classname.'.class.php'))
			picload(APP_LIB_PATH.'/Plugin/'.$classname.'.class.php');

	}else{
		$paths = array(ADDON_PATH.'/library');
		foreach ($paths as $path){
			if(picload($path.'/'.$classname.'.class.php'))
				// 如果加载类成功则返回
				return ;
		}
	}
	return ;
}

/**
 * 定义常量,判断是否未定义.
 *
 * @param string $name 常量名
 * @param string $value 常量值
 * @return string $str 返回常量的值
 */
function picdefine($name,$value) {
	global $pic;
	//定义未定义的常量
	if(!defined($name)){
		//定义新常量
		define($name,$value);
	}else{
		//返回已定义的值
		$value = constant($name);
	}
	//缓存已定义常量列表
	$pic['_define'][$name] = $value;
	return $value;
}

/**
 * 返回16位md5值
 *
 * @param string $str 字符串
 * @return string $str 返回16位的字符串
 */
function picmd5($str) {
	return substr(md5($str),8,16);
}

/**
 * 载入配置 修改自ThinkPHP:C函数 为了不与TP冲突
 *
 * @param string $name 配置名/文件名.
 * @param string|array|object $value 配置赋值
 * @return void|null
 */
function picconfig($name=null,$value=null) {
    global $pic;
    // 无参数时获取所有
    if(empty($name)) return $pic['_config'];
    // 优先执行设置获取或赋值
    if (is_string($name))
    {
        if (!strpos($name,'.')) {
            $name = strtolower($name);
            if (is_null($value))
                return isset($pic['_config'][$name])? $pic['_config'][$name] : null;
            $pic['_config'][$name] = $value;
            return;
        }
        // 二维数组设置和获取支持
        $name = explode('.',$name);
        $name[0]   = strtolower($name[0]);
        if (is_null($value))
            return isset($pic['_config'][$name[0]][$name[1]]) ? $pic['_config'][$name[0]][$name[1]] : null;
        $pic['_config'][$name[0]][$name[1]] = $value;
        return;
    }
    // 批量设置
    if(is_array($name))
        return $pic['_config'] = array_merge((array)$pic['_config'],array_change_key_case($name));
    return null;// 避免非法参数
}

/**
 * 执行钩子方法
 *
 * @param string $name 钩子方法名.
 * @param array $params 钩子参数数组.
 * @return array|string Stripped array (or string in the callback).
 */
function pichook($name,$params=array()) {
	global $pic;
    $hooks	=	$pic['_config']['hooks'][$name];
    if($hooks) {
        foreach ($hooks as $call){
            if(is_callable($call))
                $result = call_user_func_array($call,$params);
        }
        return $result;
    }
    return false;
}

/**
 * Navigates through an array and removes slashes from the values.
 *
 * If an array is passed, the array_map() function causes a callback to pass the
 * value back to the function. The slashes from this value will removed.
 * @param array|string $value The array or string to be striped.
 * @return array|string Stripped array (or string in the callback).
 */
function stripslashes_deep($value) {
	if ( is_array($value) ) {
		$value = array_map('stripslashes_deep', $value);
	} elseif ( is_object($value) ) {
		$vars = get_object_vars( $value );
		foreach ($vars as $key=>$data) {
			$value->{$key} = stripslashes_deep( $data );
		}
	} else {
		$value = stripslashes($value);
	}
	return $value;
}

/**
 * GPC参数过滤
 * @param array|string $value The array or string to be striped.
 * @return array|string Stripped array (or string in the callback).
 */
function check_gpc($value=array()) {
	if(!is_array($value)) return;
	foreach ($value as $key=>$data) {
		//对get、post的key值做限制,只允许数字、字母、及部分符号_-[]#@~
		if(!preg_match('/^[a-zA-Z0-9,_\-\/\*\.#!@~\[\]]+$/i',$key)){
			die('wrong_parameter: not safe get/post/cookie key.');
		}
		//如果key值为app\mod\act,value只允许数字、字母下划线
		if( ($key=='app' || $key=='mod' || $key=='act') && !empty($data) ){
			if(!preg_match('/^[a-zA-Z0-9_]+$/i',$data)){
				die('wrong_parameter: not safe app/mod/act value.');				
			}
		}
	}
}

//全站静态缓存,替换之前每个model类中使用的静态缓存
//类似于s和f函数的使用
function static_cache($cache_id,$value=null,$clean = false){
    static $cacheHash = array();
    if($clean){ //清空缓存 其实是清不了的 程序执行结束才会自动清理
        unset($cacheHash);
        $cacheHash = array(0);
        return $cacheHash;
    }
    if(empty($cache_id)){
        return false;
    }
    if($value === null){
        //获取缓存数据
        return isset($cacheHash[$cache_id]) ? $cacheHash[$cache_id] : false;
    }else{
        //设置缓存数据
        $cacheHash[$cache_id] = $value;
        return $cacheHash[$cache_id];
    }
}
?>