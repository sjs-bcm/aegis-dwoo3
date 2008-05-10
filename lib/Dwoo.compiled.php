<?php
 set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__)); define('DWOO_DIRECTORY', dirname(__FILE__).DIRECTORY_SEPARATOR); if(defined('DWOO_CACHE_DIRECTORY') === false) define('DWOO_CACHE_DIRECTORY', DWOO_DIRECTORY.'cache'.DIRECTORY_SEPARATOR); if(defined('DWOO_COMPILE_DIRECTORY') === false) define('DWOO_COMPILE_DIRECTORY', DWOO_DIRECTORY.'compiled'.DIRECTORY_SEPARATOR); if(defined('DWOO_CHMOD') === false) define('DWOO_CHMOD', 0777); if(is_writable(DWOO_CACHE_DIRECTORY) === false) throw new Dwoo_Exception('Dwoo cache directory must be writable, either chmod "'.DWOO_CACHE_DIRECTORY.'" to make it writable or define DWOO_CACHE_DIRECTORY to a writable directory before including Dwoo.php'); if(is_writable(DWOO_COMPILE_DIRECTORY) === false) throw new Dwoo_Exception('Dwoo compile directory must be writable, either chmod "'.DWOO_COMPILE_DIRECTORY.'" to make it writable or define DWOO_COMPILE_DIRECTORY to a writable directory before including Dwoo.php'); if((file_exists(DWOO_COMPILE_DIRECTORY.DIRECTORY_SEPARATOR.'classpath.cache.php') && include DWOO_COMPILE_DIRECTORY.DIRECTORY_SEPARATOR.'classpath.cache.php') === false) Dwoo_Loader::rebuildClassPathCache(DWOO_DIRECTORY.'plugins', DWOO_COMPILE_DIRECTORY.DIRECTORY_SEPARATOR.'classpath.cache.php'); class Dwoo { const VERSION = "0.9.0"; const RELEASE_TAG = 9; const CLASS_PLUGIN = 1; const FUNC_PLUGIN = 2; const NATIVE_PLUGIN = 4; const BLOCK_PLUGIN = 8; const COMPILABLE_PLUGIN = 16; const CUSTOM_PLUGIN = 32; const SMARTY_MODIFIER = 64; const SMARTY_BLOCK = 128; const SMARTY_FUNCTION = 256; protected $charset = 'utf-8'; protected $globals; protected $compileDir; protected $cacheDir; protected $cacheTime = 0; protected $securityPolicy = null; protected $plugins = array(); protected $filters = array(); protected $resources = array ( 'file' => array ( 'class' => 'Dwoo_Template_File', 'compiler' => null ), 'string' => array ( 'class' => 'Dwoo_Template_String', 'compiler' => null ) ); protected $template = null; protected $runtimePlugins; protected $data; protected $scope; protected $scopeTree; protected $stack; protected $curBlock; protected $buffer; public function __construct() { $this->cacheDir = DWOO_CACHE_DIRECTORY.DIRECTORY_SEPARATOR; $this->compileDir = DWOO_COMPILE_DIRECTORY.DIRECTORY_SEPARATOR; } public function __clone() { $this->template = null; } public function output($tpl, $data = array(), Dwoo_ICompiler $compiler = null) { return $this->get($tpl, $data, $compiler, true); } public function get($_tpl, $data = array(), $_compiler = null, $_output = false) { if($this->template instanceof Dwoo_ITemplate) { $proxy = clone $this; return $proxy->get($_tpl, $data, $_compiler, $_output); } if($_tpl instanceof Dwoo_ITemplate) {} elseif(is_string($_tpl) && file_exists($_tpl)) $_tpl = new Dwoo_Template_File($_tpl); elseif(is_string($_tpl)) $_tpl = new Dwoo_Template_String($_tpl); else throw new Dwoo_Exception('Dwoo->get/Dwoo->output\'s first argument must be a Dwoo_ITemplate (i.e. Dwoo_Template_File) or a valid path to a template file', E_USER_NOTICE); $this->template = $_tpl; if($data instanceof Dwoo_IDataProvider) $this->data = $data->getData(); elseif(is_array($data)) $this->data = $data; else throw new Dwoo_Exception('Dwoo->get/Dwoo->output\'s data argument must be a Dwoo_IDataProvider object (i.e. Dwoo_Data) or an associative array', E_USER_NOTICE); $this->initGlobals($_tpl); $this->initRuntimeVars($_tpl); $file = $_tpl->getCachedTemplate($this); $doCache = $file === true; $cacheLoaded = is_string($file); if($cacheLoaded === true) { if($_output === true) { readfile($file); $this->template = null; } else { $this->template = null; return file_get_contents($file); } } else { $out = include $_tpl->getCompiledTemplate($this, $_compiler); if($out === false) { $_tpl->forceCompilation(); $out = include $_tpl->getCompiledTemplate($this, $_compiler); } foreach($this->filters as $filter) { if(is_array($filter) && $filter[0] instanceof Dwoo_Filter) $out = call_user_func($filter, $out); else $out = call_user_func($filter, $this, $out); } $this->template = null; if($doCache === true) { $_tpl->cache($this, $out); if($_output === true) echo $out; else return $out; } else { if($_output === true) echo $out; else return $out; } } } protected function initGlobals(Dwoo_ITemplate $tpl) { $this->globals = array ( 'version' => self::VERSION, 'ad' => '<a href="http://dwoo.org/">Powered by Dwoo</a>', 'now' => $_SERVER['REQUEST_TIME'], 'template' => $tpl->getName(), 'charset' => $this->charset, ); } protected function initRuntimeVars(Dwoo_ITemplate $tpl) { $this->runtimePlugins = array(); $this->scope =& $this->data; $this->scopeTree = array(); $this->stack = array(); $this->curBlock = null; $this->buffer = ''; } public function addPlugin($name, $callback) { if(is_array($callback)) { if(is_subclass_of(is_object($callback[0]) ? get_class($callback[0]) : $callback[0], 'Dwoo_Block_Plugin')) $this->plugins[$name] = array('type'=>self::BLOCK_PLUGIN, 'callback'=>$callback, 'class'=>(is_object($callback[0]) ? get_class($callback[0]) : $callback[0])); else $this->plugins[$name] = array('type'=>self::CLASS_PLUGIN, 'callback'=>$callback, 'class'=>(is_object($callback[0]) ? get_class($callback[0]) : $callback[0]), 'function'=>$callback[1]); } elseif(class_exists($callback, false)) { if(is_subclass_of($callback, 'Dwoo_Block_Plugin')) $this->plugins[$name] = array('type'=>self::BLOCK_PLUGIN, 'callback'=>$callback, 'class'=>$callback); else $this->plugins[$name] = array('type'=>self::CLASS_PLUGIN, 'callback'=>$callback, 'class'=>$callback, 'function'=>'process'); } elseif(function_exists($callback)) { $this->plugins[$name] = array('type'=>self::FUNC_PLUGIN, 'callback'=>$callback); } else { throw new Dwoo_Exception('Callback could not be processed correctly, please check that the function/class you used exists'); } } public function removePlugin($name) { if(isset($this->plugins[$name])) unset($this->plugins[$name]); } public function addFilter($callback, $autoload = false) { if($autoload) { $name = str_replace('Dwoo_Filter_', '', $callback); $class = 'Dwoo_Filter_'.$name; if(!class_exists($class, false) && !function_exists($class)) Dwoo_Loader::loadPlugin($name); if(class_exists($class, false)) $callback = array(new $class($this), 'process'); elseif(function_exists($class)) $callback = $class; else throw new Dwoo_Exception('Wrong filter name, when using autoload the filter must be in one of your plugin dir as "name.php" containg a class or function named "Dwoo_Filter_name"'); $this->filters[] = $callback; } else { $this->filters[] = $callback; } } public function removeFilter($callback) { if(($index = array_search($callback, $this->filters, true)) !== false) unset($this->filters[$index]); elseif(($index = array_search('Dwoo_Filter_'.str_replace('Dwoo_Filter_', '', $callback), $this->filters, true)) !== false) unset($this->filters[$index]); else { $class = 'Dwoo_Filter_' . str_replace('Dwoo_Filter_', '', $callback); foreach($this->filters as $index=>$filter) { if(is_array($filter) && $filter[0] instanceof $class) { unset($this->filters[$index]); break; } } } } public function addResource($name, $class, $compilerFactory = null) { if(strlen($name) < 2) throw new Dwoo_Exception('Resource names must be at least two-character long to avoid conflicts with Windows paths'); $interfaces = class_implements($class, false); if(in_array('Dwoo_ITemplate', $interfaces) === false) throw new Dwoo_Exception('Resource class must implement Dwoo_ITemplate'); $this->resources[$name] = array('class'=>$class, 'compiler'=>$compilerFactory); } public function removeResource($name) { unset($this->resources[$name]); if($name==='file') $this->resources['file'] = array('class'=>'Dwoo_Template_File', 'compiler'=>null); } public function getCustomPlugins() { return $this->plugins; } public function getCacheDir() { return $this->cacheDir; } public function setCacheDir($dir) { $this->cacheDir = rtrim($dir, '/\\').DIRECTORY_SEPARATOR; } public function getCompileDir() { return $this->compileDir; } public function setCompileDir($dir) { $this->compileDir = rtrim($dir, '/\\').DIRECTORY_SEPARATOR; } public function getCacheTime() { return $this->cacheTime; } public function setCacheTime($seconds) { $this->cacheTime = (int) $seconds; } public function getCharset() { return $this->charset; } public function setCharset($charset) { $this->charset = strtolower((string) $charset); } public function getTemplate() { return $this->template; } public function setDefaultCompilerFactory($resourceName, $compilerFactory) { $this->resources[$resourceName]['compiler'] = $compilerFactory; } public function getDefaultCompilerFactory($resourceName) { return $this->resources[$resourceName]['compiler']; } public function setSecurityPolicy(Dwoo_Security_Policy $policy = null) { $this->securityPolicy = $policy; } public function getSecurityPolicy() { return $this->securityPolicy; } public function isCached(Dwoo_ITemplate $tpl) { return is_string($tpl->getCachedTemplate($this)); } public function clearCache($olderThan=-1) { $cacheDirs = new RecursiveDirectoryIterator($this->cacheDir); $cache = new RecursiveIteratorIterator($cacheDirs); $expired = time() - $olderThan; $count = 0; foreach($cache as $file) { if($cache->isDot() || $cache->isDir() || substr($file, -5) !== '.html') continue; if($cache->getCTime() < $expired) $count += unlink((string) $file) ? 1 : 0; } return $count; } public function templateFactory($resourceName, $resourceId, $cacheTime = null, $cacheId = null, $compileId = null) { if(isset($this->resources[$resourceName])) return call_user_func(array($this->resources[$resourceName]['class'], 'templateFactory'), $this, $resourceId, $cacheTime, $cacheId, $compileId); else throw new Dwoo_Exception('Unknown resource type : '.$resourceName); } public function isArray($value, $checkIsEmpty=false, $allowNonCountable=false) { if(is_array($value) === true) { if($checkIsEmpty === false) return true; else return count($value) > 0; } elseif($value instanceof Iterator) { if($checkIsEmpty === false) { return true; } else { if($allowNonCountable === false) { return count($value) > 0; } else { if($value instanceof Countable) return count($value) > 0; else { $value->rewind(); return $value->valid(); } } } } return false; } public function triggerError($message, $level=E_USER_NOTICE) { trigger_error('Dwoo error (in '.$this->template->getResourceIdentifier().') : '.$message, $level); } public function addStack($blockName, array $args=array()) { if(isset($this->plugins[$blockName])) $class = $this->plugins[$blockName]['class']; else $class = 'Dwoo_Plugin_'.$blockName; if($this->curBlock !== null) { $this->curBlock->buffer(ob_get_contents()); ob_clean(); } else { $this->buffer .= ob_get_contents(); ob_clean(); } $block = new $class($this); $cnt = count($args); if($cnt===0) $block->init(); elseif($cnt===1) $block->init($args[0]); elseif($cnt===2) $block->init($args[0], $args[1]); elseif($cnt===3) $block->init($args[0], $args[1], $args[2]); elseif($cnt===4) $block->init($args[0], $args[1], $args[2], $args[3]); else call_user_func_array(array($block,'init'), $args); $this->stack[] = $this->curBlock = $block; return $block; } public function delStack() { $args = func_get_args(); $this->curBlock->buffer(ob_get_contents()); ob_clean(); $cnt = count($args); if($cnt===0) $this->curBlock->end(); elseif($cnt===1) $this->curBlock->end($args[0]); elseif($cnt===2) $this->curBlock->end($args[0], $args[1]); elseif($cnt===3) $this->curBlock->end($args[0], $args[1], $args[2]); elseif($cnt===4) $this->curBlock->end($args[0], $args[1], $args[2], $args[3]); else call_user_func_array(array($this->curBlock, 'end'), $args); $tmp = array_pop($this->stack); if(count($this->stack) > 0) { $this->curBlock = end($this->stack); $this->curBlock->buffer($tmp->process()); } else { echo $tmp->process(); } unset($tmp); } public function getParentBlock(Dwoo_Block_Plugin $block) { $index = array_search($block, $this->stack, true); if($index !== false && $index > 0) { return $this->stack[$index-1]; } return false; } public function findBlock($type) { if(isset($this->plugins[$type])) $type = $this->plugins[$type]['class']; else $type = 'Dwoo_Plugin_'.str_replace('Dwoo_Plugin_','',$type); $keys = array_keys($this->stack); while(($key = array_pop($keys)) !== false) if($this->stack[$key] instanceof $type) return $this->stack[$key]; return false; } protected function getObjectPlugin($class) { if(isset($this->runtimePlugins[$class])) return $this->runtimePlugins[$class]; return $this->runtimePlugins[$class] = new $class($this); } public function classCall($plugName, array $params = array()) { $class = 'Dwoo_Plugin_'.$plugName; $plugin = $this->getObjectPlugin($class); $cnt = count($params); if($cnt===0) return $plugin->process(); elseif($cnt===1) return $plugin->process($params[0]); elseif($cnt===2) return $plugin->process($params[0], $params[1]); elseif($cnt===3) return $plugin->process($params[0], $params[1], $params[2]); elseif($cnt===4) return $plugin->process($params[0], $params[1], $params[2], $params[3]); else return call_user_func_array(array($plugin, 'process'), $params); } public function arrayMap($callback, array $params) { if($params[0] === $this) { $addThis = true; array_shift($params); } if((is_array($params[0]) || ($params[0] instanceof Iterator && $params[0] instanceof ArrayAccess))) { if(empty($params[0])) return $params[0]; $out = array(); $cnt = count($params); if(isset($addThis)) { array_unshift($params, $this); $items = $params[1]; $keys = array_keys($items); if(is_string($callback) === false) while(($i = array_shift($keys)) !== null) $out[] = call_user_func_array($callback, array(1=>$items[$i]) + $params); elseif($cnt===1) while(($i = array_shift($keys)) !== null) $out[] = $callback($this, $items[$i]); elseif($cnt===2) while(($i = array_shift($keys)) !== null) $out[] = $callback($this, $items[$i], $params[2]); elseif($cnt===3) while(($i = array_shift($keys)) !== null) $out[] = $callback($this, $items[$i], $params[2], $params[3]); else while(($i = array_shift($keys)) !== null) $out[] = call_user_func_array($callback, array(1=>$items[$i]) + $params); } else { $items = $params[0]; $keys = array_keys($items); if(is_string($callback) === false) while(($i = array_shift($keys)) !== null) $out[] = call_user_func_array($callback, array($items[$i]) + $params); elseif($cnt===1) while(($i = array_shift($keys)) !== null) $out[] = $callback($items[$i]); elseif($cnt===2) while(($i = array_shift($keys)) !== null) $out[] = $callback($items[$i], $params[1]); elseif($cnt===3) while(($i = array_shift($keys)) !== null) $out[] = $callback($items[$i], $params[1], $params[2]); elseif($cnt===4) while(($i = array_shift($keys)) !== null) $out[] = $callback($items[$i], $params[1], $params[2], $params[3]); else while(($i = array_shift($keys)) !== null) $out[] = call_user_func_array($callback, array($items[$i]) + $params); } return $out; } else { return $params[0]; } } public function readVarInto($varstr, $data) { if($data === null) return null; if(is_array($varstr) === false) preg_match_all('#(\[|->|\.)?([a-z0-9_]+)\]?#i', $varstr, $m); else $m = $varstr; unset($varstr); while(list($k, $sep) = each($m[1])) { if($sep === '.' || $sep === '[' || $sep === '') { if((is_array($data) || $data instanceof ArrayAccess) && isset($data[$m[2][$k]])) $data = $data[$m[2][$k]]; else return null; } else { if(is_object($data) && property_exists($data, $m[2][$k])) $data = $data->$m[2][$k]; else return null; } } return $data; } public function readParentVar($parentLevels, $varstr = null) { $tree = $this->scopeTree; $cur = $this->data; while($parentLevels--!==0) { array_pop($tree); } while(($i = array_shift($tree)) !== null) { if(is_object($cur)) $cur = $cur->$i; else $cur = $cur[$i]; } if($varstr!==null) return $this->readVarInto($varstr, $cur); else return $cur; } public function readVar($varstr) { if(is_array($varstr)===true) { $m = $varstr; unset($varstr); } else { if(strstr($varstr, '.') === false && strstr($varstr, '[') === false && strstr($varstr, '->') === false) { if($varstr === 'dwoo') { return $this->globals; } elseif($varstr === '_root' || $varstr === '__') { return $this->data; $varstr = substr($varstr, 6); } elseif($varstr === '_parent' || $varstr === '_') { $varstr = '.'.$varstr; $tree = $this->scopeTree; $cur = $this->data; array_pop($tree); while(($i = array_shift($tree)) !== null) { if(is_object($cur)) $cur = $cur->$i; else $cur = $cur[$i]; } return $cur; } $cur = $this->scope; if(isset($cur[$varstr])) return $cur[$varstr]; else return null; } if(substr($varstr, 0, 1) === '.') $varstr = 'dwoo'.$varstr; preg_match_all('#(\[|->|\.)?([a-z0-9_]+)\]?#i', $varstr, $m); } $i = $m[2][0]; if($i === 'dwoo') { $cur = $this->globals; array_shift($m[2]); array_shift($m[1]); switch($m[2][0]) { case 'get': $cur = $_GET; break; case 'post': $cur = $_POST; break; case 'session': $cur = $_SESSION; break; case 'cookies': case 'cookie': $cur = $_COOKIE; break; case 'server': $cur = $_SERVER; break; case 'env': $cur = $_ENV; break; case 'request': $cur = $_REQUEST; break; case 'const': array_shift($m[2]); if(defined($m[2][0])) return constant($m[2][0]); else return null; } if($cur !== $this->globals) { array_shift($m[2]); array_shift($m[1]); } } elseif($i === '_root' || $i === '__') { $cur = $this->data; array_shift($m[2]); array_shift($m[1]); } elseif($i === '_parent' || $i === '_') { $tree = $this->scopeTree; $cur = $this->data; while(true) { array_pop($tree); array_shift($m[2]); array_shift($m[1]); if(current($m[2]) === '_parent' || current($m[2]) === '_') continue; while(($i = array_shift($tree)) !== null) { if(is_object($cur)) $cur = $cur->$i; else $cur = $cur[$i]; } break; } } else $cur = $this->scope; while(list($k, $sep) = each($m[1])) { if($sep === '.' || $sep === '[' || $sep === '') { if((is_array($cur) || $cur instanceof ArrayAccess) && isset($cur[$m[2][$k]])) $cur = $cur[$m[2][$k]]; else return null; } elseif($sep === '->') { if(is_object($cur) && property_exists($cur, $m[2][$k])) $cur = $cur->$m[2][$k]; else return null; } else return null; } return $cur; } public function assignInScope($value, $scope) { $tree =& $this->scopeTree; $data =& $this->data; if(strstr($scope, '.') === false && strstr($scope, '->') === false) { $this->scope[$scope] = $value; } else { preg_match_all('#(\[|->|\.)?([a-z0-9_]+)\]?#i', $scope, $m); $cur =& $this->scope; $last = array(array_pop($m[1]), array_pop($m[2])); while(list($k, $sep) = each($m[1])) { if($sep === '.' || $sep === '[' || $sep === '') { if(is_array($cur) === false) $cur = array(); $cur =& $cur[$m[2][$k]]; } elseif($sep === '->') { if(is_object($cur) === false) $cur = new stdClass; $cur =& $cur->$m[2][$k]; } else return false; } if($last[0] === '.' || $last[0] === '[' || $last[0] === '') { if(is_array($cur) === false) $cur = array(); $cur[$last[1]] = $value; } elseif($last[0] === '->') { if(is_object($cur) === false) $cur = new stdClass; $cur->$last[1] = $value; } else return false; } } public function setScope($scope) { $old = $this->scopeTree; if(empty($scope)) return $old; if(is_array($scope)===false) $scope = explode('.', $scope); while(($bit = array_shift($scope)) !== null) { if($bit === '_parent' || $bit === '_') { array_pop($this->scopeTree); reset($this->scopeTree); $this->scope =& $this->data; $cnt = count($this->scopeTree); for($i=0;$i<$cnt;$i++) $this->scope =& $this->scope[$this->scopeTree[$i]]; } elseif($bit === '_root' || $bit === '__') { $this->scope =& $this->data; $this->scopeTree = array(); } elseif(isset($this->scope[$bit])) { $this->scope =& $this->scope[$bit]; $this->scopeTree[] = $bit; } else { unset($this->scope); $this->scope = null; } } return $old; } public function getData() { return $this->data; } public function &getScope() { return $this->scope; } public function forceScope($scope) { $prev = $this->setScope(array('_root')); $this->setScope($scope); return $prev; } } class Dwoo_Loader { protected static $paths = array(); public static $classpath = array(); public static function rebuildClassPathCache($path, $cacheFile) { if($cacheFile!==false) { $tmp = self::$classpath; self::$classpath = array(); } $list = glob($path.DIRECTORY_SEPARATOR.'*'); if(is_array($list)) foreach($list as $f) { if(is_dir($f)) self::rebuildClassPathCache($f, false); else self::$classpath[str_replace(array('function.','block.','modifier.','outputfilter.','filter.','prefilter.','postfilter.','pre.','post.','output.','shared.','helper.'), '', basename($f,'.php'))] = $f; } if($cacheFile!==false) { if(!file_put_contents($cacheFile, '<?php Dwoo_Loader::$classpath = '.var_export(self::$classpath, true).' + Dwoo_Loader::$classpath; ?>')) throw new Dwoo_Exception('Could not write into '.$cacheFile.', either because the folder is not there (create it) or because of the chmod configuration (please ensure this directory is writable by php)'); self::$classpath += $tmp; } } public static function loadPlugin($class, $forceRehash = true) { if(!isset(self::$classpath[$class]) || !include self::$classpath[$class]) { if($forceRehash) { self::rebuildClassPathCache(DWOO_DIRECTORY . 'plugins', DWOO_COMPILE_DIRECTORY . DIRECTORY_SEPARATOR . 'classpath.cache.php'); foreach(self::$paths as $path=>$file) self::rebuildClassPathCache($path, $file); if(isset(self::$classpath[$class])) include self::$classpath[$class]; else throw new Dwoo_Exception('Plugin <em>'.$class.'</em> can not be found, maybe you forgot to bind it if it\'s a custom plugin ?', E_USER_NOTICE); } else throw new Dwoo_Exception('Plugin <em>'.$class.'</em> can not be found, maybe you forgot to bind it if it\'s a custom plugin ?', E_USER_NOTICE); } } public static function addDirectory($pluginDir) { if(!isset(self::$paths[$pluginDir])) { $cacheFile = DWOO_COMPILE_DIRECTORY . DIRECTORY_SEPARATOR . 'classpath-'.substr(strtr($pluginDir, ':/\\.', '----'), strlen($pluginDir) > 80 ? -80 : 0).'.cache.php'; self::$paths[$pluginDir] = $cacheFile; if(file_exists($cacheFile)) include $cacheFile; else Dwoo_Loader::rebuildClassPathCache($pluginDir, $cacheFile); } } } class Dwoo_Exception extends Exception { } class Dwoo_Security_Policy { const PHP_ENCODE = 1; const PHP_REMOVE = 2; const PHP_ALLOW = 3; const CONST_DISALLOW = false; const CONST_ALLOW = true; protected $allowedPhpFunctions = array ( 'str_repeat', 'count', 'number_format', 'htmlentities', 'htmlspecialchars', 'long2ip', 'strlen', 'list', 'empty', 'count', 'sizeof', 'in_array', 'is_array', ); protected $allowedDirectories = array(); protected $phpHandling = self::PHP_REMOVE; protected $constHandling = self::CONST_DISALLOW; public function allowPhpFunction($func) { if(is_array($func)) foreach($func as $fname) $this->allowedPhpFunctions[strtolower($fname)] = true; else $this->allowedPhpFunctions[strtolower($func)] = true; } public function disallowPhpFunction($func) { if(is_array($func)) foreach($func as $fname) unset($this->allowedPhpFunctions[strtolower($fname)]); else unset($this->allowedPhpFunctions[strtolower($func)]); } public function getAllowedPhpFunctions() { return $this->allowedPhpFunctions; } public function allowDirectory($path) { if(is_array($path)) foreach($path as $dir) $this->allowedDirectories[realpath($dir)] = true; else $this->allowedDirectories[realpath($path)] = true; } public function disallowDirectory($path) { if(is_array($path)) foreach($path as $dir) unset($this->allowedDirectories[realpath($dir)]); else unset($this->allowedDirectories[realpath($path)]); } public function getAllowedDirectories() { return $this->allowedDirectories; } public function setPhpHandling($level = self::PHP_REMOVE) { $this->phpHandling = $level; } public function getPhpHandling() { return $this->phpHandling; } public function setConstantHandling($level = self::CONST_DISALLOW) { $this->constHandling = $level; } public function getConstantHandling() { return $this->constHandling; } } class Dwoo_Security_Exception extends Dwoo_Exception { } interface Dwoo_ICompilable { } interface Dwoo_ICompiler { public function compile(Dwoo $dwoo, Dwoo_ITemplate $template); public function setCustomPlugins(array $customPlugins); public function setSecurityPolicy(Dwoo_Security_Policy $policy = null); } interface Dwoo_IDataProvider { public function getData(); } interface Dwoo_ITemplate { public function getCacheTime(); public function setCacheTime($seconds = null); public function getCachedTemplate(Dwoo $dwoo); public function cache(Dwoo $dwoo, $output); public function clearCache(Dwoo $dwoo, $olderThan = -1); public function getCompiledTemplate(Dwoo $dwoo, Dwoo_ICompiler $compiler = null); public function getName(); public function getResourceName(); public function getResourceIdentifier(); public function getSource(); public function getUid(); public function getCompiler(); public static function templateFactory(Dwoo $dwoo, $resourceId, $cacheTime = null, $cacheId = null, $compileId = null); } interface Dwoo_ICompilable_Block { } abstract class Dwoo_Plugin { protected $dwoo; public function __construct(Dwoo $dwoo) { $this->dwoo = $dwoo; } } abstract class Dwoo_Block_Plugin extends Dwoo_Plugin { protected $buffer = ''; public function buffer($input) { $this->buffer .= $input; } public function end() { } public function process() { return $this->buffer; } public static function preProcessing(Dwoo_Compiler $compiler, array $params, $prepend='', $append='', $type) { return Dwoo_Compiler::PHP_OPEN.$prepend.'$this->addStack("'.$type.'", array('.implode(', ', $compiler->getCompiledParams($params)).'));'.$append.Dwoo_Compiler::PHP_CLOSE; } public static function postProcessing(Dwoo_Compiler $compiler, array $params, $prepend='', $append='') { return Dwoo_Compiler::PHP_OPEN.$prepend.'$this->delStack();'.$append.Dwoo_Compiler::PHP_CLOSE; } } abstract class Dwoo_Filter { protected $dwoo; public function __construct(Dwoo $dwoo) { $this->dwoo = $dwoo; } abstract public function process($input); } abstract class Dwoo_Processor { protected $compiler; public function __construct(Dwoo_Compiler $compiler) { $this->compiler = $compiler; } abstract public function process($input); } class Dwoo_Template_String implements Dwoo_ITemplate { protected $name; protected $compileId; protected $cacheId; protected $cacheTime; protected $compilationEnforced; protected static $cache = array('cached'=>array(), 'compiled'=>array()); protected $compiler; public function __construct($templateString, $cacheTime = null, $cacheId = null, $compileId = null) { $this->template = $templateString; $this->name = hash('md4', $templateString); $this->cacheTime = $cacheTime; if($compileId !== null) { $this->compileId = strtr($compileId, '\\%?=!:;'.PATH_SEPARATOR, '/-------'); } if($cacheId !== null) { $this->cacheId = strtr($cacheId, '\\%?=!:;'.PATH_SEPARATOR, '/-------'); } } public function getCacheTime() { return $this->cacheTime; } public function setCacheTime($seconds = null) { $this->cacheTime = $seconds; } public function getName() { return $this->name; } public function getResourceName() { return 'string'; } public function getResourceIdentifier() { return false; } public function getSource() { return $this->template; } public function getUid() { return $this->name; } public function getCompiler() { return $this->compiler; } public function forceCompilation() { $this->compilationEnforced = true; } public function getCachedTemplate(Dwoo $dwoo) { $cachedFile = $this->getCacheFilename($dwoo); if($this->cacheTime !== null) $cacheLength = $this->cacheTime; else $cacheLength = $dwoo->getCacheTime(); if($cacheLength === 0) { return false; } if(isset(self::$cache['cached'][$this->cacheId]) === true && file_exists($cachedFile)) { return $cachedFile; } elseif($this->compilationEnforced !== true && file_exists($cachedFile) && ($cacheLength === -1 || filemtime($cachedFile) > ($_SERVER['REQUEST_TIME'] - $cacheLength))) { self::$cache['cached'][$this->cacheId] = true; return $cachedFile; } else { return true; } } public function cache(Dwoo $dwoo, $output) { $cacheDir = $dwoo->getCacheDir(); $cachedFile = $this->getCacheFilename($dwoo); $temp = tempnam($cacheDir, 'temp'); if(!($file = @fopen($temp, 'wb'))) { $temp = $cacheDir . DIRECTORY_SEPARATOR . uniqid('temp'); if(!($file = @fopen($temp, 'wb'))) { trigger_error('Error writing temporary file \''.$temp.'\'', E_USER_WARNING); return false; } } fwrite($file, $output); fclose($file); $this->makeDirectory(dirname($cachedFile)); if(!@rename($temp, $cachedFile)) { @unlink($cachedFile); @rename($temp, $cachedFile); } chmod($cachedFile, DWOO_CHMOD); self::$cache['cached'][$this->cacheId] = true; return true; } public function clearCache(Dwoo $dwoo, $olderThan = -1) { $cachedFile = $this->getCacheFilename($dwoo); return !file_exists($cachedFile) || (filectime($cachedFile) < (time() - $olderThan) && unlink($cachedFile)); } public function getCompiledTemplate(Dwoo $dwoo, Dwoo_ICompiler $compiler = null) { $compiledFile = $this->getCompiledFilename($dwoo); if($this->compilationEnforced !== true && isset(self::$cache['compiled'][$this->compileId]) === true) { } elseif($this->compilationEnforced !== true && file_exists($compiledFile)===true) { self::$cache['compiled'][$this->compileId] = true; } else { $this->compilationEnforced = false; if($compiler === null) { $compiler = $dwoo->getDefaultCompilerFactory('string'); if($compiler === null || $compiler === array('Dwoo_Compiler', 'compilerFactory')) { if(class_exists('Dwoo_Compiler', false) === false) include 'Dwoo/Compiler.php'; $compiler = Dwoo_Compiler::compilerFactory(); } else $compiler = call_user_func($compiler); } $this->compiler = $compiler; $compiler->setCustomPlugins($dwoo->getCustomPlugins()); $compiler->setSecurityPolicy($dwoo->getSecurityPolicy()); $this->makeDirectory(dirname($compiledFile)); file_put_contents($compiledFile, $compiler->compile($dwoo, $this)); chmod($compiledFile, DWOO_CHMOD); self::$cache['compiled'][$this->compileId] = true; } return $compiledFile; } public static function templateFactory(Dwoo $dwoo, $resourceId, $cacheTime = null, $cacheId = null, $compileId = null) { return false; } protected function getCompiledFilename(Dwoo $dwoo) { if($this->compileId===null) { $this->compileId = $this->name; } return $dwoo->getCompileDir() . $this->compileId.'.d'.Dwoo::RELEASE_TAG.'.php'; } protected function getCacheFilename(Dwoo $dwoo) { if($this->cacheId === null) { if(isset($_SERVER['REQUEST_URI']) === true) $cacheId = $_SERVER['REQUEST_URI']; elseif(isset($_SERVER['SCRIPT_FILENAME']) && isset($_SERVER['argv'])) $cacheId = $_SERVER['SCRIPT_FILENAME'].'-'.implode('-', $_SERVER['argv']); $this->cacheId = strtr($cacheId, '\\%?=!:;'.PATH_SEPARATOR, '/-------'); } return $dwoo->getCacheDir() . $this->cacheId.'.html'; } protected function makeDirectory($path) { if(is_dir($path) === true) return; mkdir($path, DWOO_CHMOD, true); } } class Dwoo_Template_File extends Dwoo_Template_String { protected $file; public function __construct($file, $cacheTime = null, $cacheId = null, $compileId = null) { $this->file = $file; $this->name = basename($file); $this->cacheTime = $cacheTime; if($compileId !== null) { $this->compileId = strtr($compileId, '\\%?=!:;'.PATH_SEPARATOR, '/-------'); } if($cacheId !== null) { $this->cacheId = strtr($cacheId, '\\%?=!:;'.PATH_SEPARATOR, '/-------'); } } public function getCompiledTemplate(Dwoo $dwoo, Dwoo_ICompiler $compiler = null) { $compiledFile = $this->getCompiledFilename($dwoo); if($this->compilationEnforced !== true && isset(self::$cache['compiled'][$this->compileId]) === true) { } elseif($this->compilationEnforced !== true && file_exists($compiledFile)===true && filemtime($this->file) <= filemtime($compiledFile)) { self::$cache['compiled'][$this->compileId] = true; } else { $this->compilationEnforced = false; if($compiler === null) { $compiler = $dwoo->getDefaultCompilerFactory('string'); if($compiler === null || $compiler === array('Dwoo_Compiler', 'compilerFactory')) { if(class_exists('Dwoo_Compiler', false) === false) include 'Dwoo/Compiler.php'; $compiler = Dwoo_Compiler::compilerFactory(); } else $compiler = call_user_func($compiler); } $this->compiler = $compiler; $compiler->setCustomPlugins($dwoo->getCustomPlugins()); $compiler->setSecurityPolicy($dwoo->getSecurityPolicy()); $this->makeDirectory(dirname($compiledFile)); file_put_contents($compiledFile, $compiler->compile($dwoo, $this)); chmod($compiledFile, DWOO_CHMOD); self::$cache['compiled'][$this->compileId] = true; } return $compiledFile; } public function getSource() { return file_get_contents($this->file); } public function getResourceName() { return 'file'; } public function getResourceIdentifier() { return $this->file; } public function getUid() { return (string) filemtime($this->file); } public static function templateFactory(Dwoo $dwoo, $resourceId, $cacheTime = null, $cacheId = null, $compileId = null) { $resourceId = str_replace(array("\t", "\n", "\r"), array('\\t', '\\n', '\\r'), $resourceId); if(file_exists($resourceId) === false) { $tpl = $dwoo->getTemplate(); if($tpl instanceof Dwoo_Template_File) { $resourceId = dirname($tpl->getResourceIdentifier()).DIRECTORY_SEPARATOR.$resourceId; if(file_exists($resourceId) === false) return null; } else return null; } if($policy = $dwoo->getSecurityPolicy()) { $tpl = $dwoo->getTemplate(); if($tpl instanceof Dwoo_Template_File && $resourceId === $tpl->getResourceIdentifier()) return $dwoo->triggerError('You can not include a template into itself', E_USER_WARNING); } return new Dwoo_Template_File($resourceId, $cacheTime, $cacheId, $compileId); } protected function getCompiledFilename(Dwoo $dwoo) { if($this->compileId===null) { $this->compileId = implode('/', array_slice(explode('/', strtr($this->file, '\\', '/')), -3)); } return $dwoo->getCompileDir() . $this->compileId.'.d'.Dwoo::RELEASE_TAG.'.php'; } } class Dwoo_Data implements Dwoo_IDataProvider { protected $data = array(); public function getData() { return $this->data; } public function clear($name = null) { if($name === null) { $this->data = array(); } elseif(is_array($name)) { foreach($name as $index) unset($this->data[$index]); } else unset($this->data[$name]); } public function setData(array $data) { $this->data = $data; } public function mergeData(array $data) { $args = func_get_args(); while(list(,$v) = each($args)) if(is_array($v)) $this->data = array_merge($this->data, $v); } public function assign($name, $val = null) { if(is_array($name)) { reset($name); while(list($k,$v) = each($name)) $this->data[$k] = $v; } else $this->data[$name] = $val; } public function assignByRef($name, &$val) { $this->data[$name] =& $val; } public function append($name, $val = null, $merge = false) { if(is_array($name)) { foreach($name as $key=>$val) { if(isset($this->data[$key]) && !is_array($this->data[$key])) settype($this->data[$key], 'array'); if($merge === true && is_array($val)) $this->data[$key] = $val + $this->data[$key]; else $this->data[$key][] = $val; } } elseif($val !== null) { if(isset($this->data[$name]) && !is_array($this->data[$name])) settype($this->data[$name], 'array'); if($merge === true && is_array($val)) $this->data[$name] = $val + $this->data[$name]; else $this->data[$name][] = $val; } } public function appendByRef($name, &$val, $merge = false) { if(isset($this->data[$name]) && !is_array($this->data[$name])) settype($this->data[$name], 'array'); if($merge === true && is_array($val)) { foreach($val as $key => &$val) $this->data[$name][$key] =& $val; } else $this->data[$name][] =& $val; } } 