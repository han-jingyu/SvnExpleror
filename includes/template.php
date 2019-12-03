<?php
/**
 * SvnExplorer 模版模块。
 *
 * @author Han Jing Yu <hanjy@han-soft.com>
 * @version 1.0
 * @copyright 2001-2017 Han-soft
 */

/**
 * 这个类用来将模版转换为页面。
 */
class Template {

    /**
     * @var $path 字符串，模版路径。
     */
    private $path = "";

    /**
     * @var $common_map  数组，公共模版定义。
     * @var $page_map    数组，当前页面模版定义。
     * @var $nofount_map 数组，代码库不存在错误的页面模版定义。
     * @var $error_map   数组，代码库内容读取错误的页面模版定义。
     * @var $error_msg   数组，保存错误消息。
     */
    private $common_map = array("page"=>"@");
    protected $page_map = array("page"=>"@");
    private $nofound_map = array("page"=>"@");
    private $error_map = array("page"=>"@");
    public $error_msg = array();

    /**
     * @var $encoding 字符串，用来保存当前模版页面的名称。
     */
    public $command = "";


    /**
     * @var $map_loaded 逻辑值，指出页面 map 是否已加载。
     */
    public $map_loaded = false;

    /**
     * @var $language 字符串，用来保存语言，为指定的模版文件夹下语言文件夹名称。
     */
    public $language = "en-us";

    /**
     * @var $encoding 字符串，用来保存模版使用的编码。
     */
    public $encoding = "utf-8";

    /**
     * @var $static 字符串，用来保存所用的静态文件夹名称，模版共用静态文件夹（位于模版文件夹下）和某个语言自用静态文件夹（位于该语言文
     *                     件夹下）均使用该名称。
     */
    public $static = "static";

    /**
     * @var $feed 字符串，用来保存模版使用的 Feed 种类，默认 atom。
     */
    public $feed = "atom";

    /**
     * 删除路径末尾的路径分隔符。
     *
     * @param  字符串 $path 待删除尾部分隔符的路径。
     * @return 字符串       已删除尾部分隔符的路径。
     */
    private function remove_slash($path) {
        return ((substr($path, -1, 1) == "/") && (trim($path) != "/")) ? substr($path, 0, -1) : $path;
    }

    /**
     * 取得页面模版项的内容。
     *
     * @param  字符串 $template 模版名称。
     * @return 字符串           模版内容，不成功则返回 false 值。
     */
    private function get_template_value($template) {
        $template_value = "";
        if (array_key_exists($template, $this->page_map)) {
            $template_value = trim($this->page_map[$template]);
        } else if (array_key_exists($template, $this->common_map)) {
            $template_value = trim($this->common_map[$template]);
        }
        if ($template_value == "") return false;
        if (substr($template_value, 0, 1) != "@") return $template_value;
        $template_file = $this->remove_slash($this->path).'/'.$this->language.'/'.substr($template_value, 1);
        if (!file_exists($template_file)) return false;
        if ($template_value = @file_get_contents($template_file)) return $template_value;
        return false;
    }

    /**
     * 替换模版内容中的“内容块”。
     *
     * 在模版中可以通过内容块导入另一个模版的内容，格式是 “{<内容块名称>%<模版名称>}”。 该函数会对模版内容中的每个内容块，在当前风格的模
     * 版定义中，根据其 <模版名称> 找到该内容块模版的内容，并替换该内容块。模版定义中，内容块模版的内容即可以直接定义，也可以通过引用一个
     * 模版文件来间接定义，引用格式为 “@<模版文件名>”。
     *
     * 内容块模版中依然可以包含别的内容块，注意不要形成直接或间接循环。
     *
     * @param  字符串 $content 待处理的模版内容。
     * @return 字符串          已处理的模版内容。
     */
    private function replace_content_blocks($content) {
        while (true) {
            preg_match_all("/{([a-z-_]+)%([a-z-_]+)}/", $content, $blocks, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
            if (count($blocks) == 0) { break; }
            $blocks = array_reverse($blocks);
            foreach ($blocks as $block) {
                $define = $block[0][0];
                $offset = $block[0][1];
                $length = strlen($define);
                $name = $block[1][0];
                $template = $block[2][0];
                if ($template_value = $this->get_template_value($template)) {
                    $content = substr($content, 0, $offset).$template_value.substr($content, $offset + $length);
                    continue;
                }
                $content = substr($content, 0, $offset).substr($content, $offset + $length);
            }
        };
        return $content;
    }

    /**
     * 构造函数，同时指定当前模版的定义，以及模版文件所在的路径。
     *
     * @param  字符串 $command 当前命令（页面）名称。
     * @param  字符串 $path    当前模版风格模版文件所在的路径。
     * @param  字符串 $language 当前模版风格模版文件的语言。
     */
    public function __construct($command, $path, $language) {
        $this->path = $this->remove_slash($path);
        $this->language = is_dir($this->path.'/'.$language) ? $language : 'en-us';
        $map_file = $this->path."/".$this->language."/map.ini";
        $map_file = is_file($map_file) ? $map_file : $this->path."/en-us/map.ini";
        if ($map = @parse_ini_file($map_file, true)) {
            $this->error_msg = $map['error-msg'];
            $this->common_map = $map['common'];
            if (array_key_exists($command, $map)) {
                $this->page_map = $map[$command];
                $this->map_loaded = true;
                $this->command = $command;
            }
            $this->nofound_map = $map['nofound'];
            $this->error_map = $map['error'];
            $this->encoding = isset($map['config']['encoding']) ? $map['config']['encoding'] : 'utf-8';
            $this->static = isset($map['config']['static']) ? $map['config']['static'] : 'static';
            $this->feed = isset($map['config']['feed']) ? $map['config']['feed'] : 'atom';
            if (!array_key_exists($this->feed, $map)) $this->feed = "atom";
        }
    }

    /**
     * 加载指定命令的 map 定义。
     *
     * @param  字符串 $command  命令（页面）名称。
     * @param  字符串 $language 当前模版风格模版文件的语言。
     * @return 逻辑值           加载成功返回 true，否则返回 false。
     */
    public function load_map($command) {
        $map_file = $this->path.'/'.$this->language.'/map.ini';
        $map_file = is_file($map_file) ? $map_file : $this->path.'/en-us/map.ini';
        if ($map = @parse_ini_file($map_file, true)) {
            if (array_key_exists($command, $map)) {
                $this->page_map = $map[$command];
                $this->map_loaded = true;
                $this->command = $command;
                return true;
            }
        }
        return false;
    }

    /**
     * 取得风格所支持的语言列表。
     *
     * @return 数组 返回风格所支持的所有语言，键名为语言标识（风格目录下语言相应文件夹名称），键值为关联数组，包括 id(语言标识)、
     *              name(语言名称，在 map.ini  文件的 config 段使用 languange-name 定义)。
     */
    public function fetch_languages() {
        $languages = array();
        if ($handle = opendir($this->path)) {
            while (false !== ($item = readdir($handle))) {
                if ($item != "." && $item != ".." && is_dir($this->path."/".$item)) {
                    if (is_file($this->path.'/'.$item.'/map.ini')) {
                        if ($map = @parse_ini_file($this->path.'/'.$item.'/map.ini', true)) {
                            $languages[$item]['id'] = $item;
                            $languages[$item]['name'] = $map['config']['language-name'];
                            $languages[$item]['selected'] = ($item == $this->language) ? 'selected="selected"' : "";
                        }
                    }
                }
            }
            closedir($handle);
        }
        return $languages;
    }

    /**
     * 取得当前页面的模版内容，其中所有的 “内容块” 都将被替换。
     *
     * @param  字符串 $page_name 页面级模版名称，默认为当前页面定义中的 page 项。
     * @return 字符串            当前页面模版内容，失败则返回 false 值。
     */
    public function get_page_content($page_name = 'page') {
        if ($page = $this->get_template_value($page_name)) {
           return $this->replace_content_blocks($page);
        }
        return false;
    }

    /**
     * 替换模版内容中的 “条目块”。
     *
     * 在模版中可以通过条目块导入一系列条目数据，其中每一个条目会通过另一个模版的内容进行格式化，条目块的格式是
     *   “{<条目块名称>#<模版名称>}”。
     * 该函数会在当前风格的模版定义中，根据其 <模版名称> 找到该条目模版的内容，并将其应用于条目数据形成条目内容，最后将所有条目的条目内
     * 容合在一起替换该条目块。模版定义中，条目块模版的内容即可以直接定义，也可以通过引用一个模版文件来间接定义，引用格式为
     *   “@<模版文件名>”。
     *
     * @param  字符串   $content 待处理的模版内容。
     * @param  字符串   $block   条目块名称。
     * @param  二维数组 $entries 条目数据。每个数组元素是一个关联数组，表示一个条目。
     * @return 字符串            已处理的模版内容。
     */
    public function replace_entries_block($content, $block, $entries) {
        while ($start = strpos($content, "{".$block."#")) {
            if ($end = strpos($content, "}", $start)) {
                $end++;
                $define = substr($content, $start, $end - $start);
                $template = substr($define, strpos($define, "#") + 1, -1);
                if (($template_value = $this->get_template_value($template)) && (count($entries) > 0)) {
                    $block_content = "";
                    foreach ($entries as $index => $entry) {
                        $entry["index"] = $index;
                        $entry["parity"] = $index % 2;
                        $block_content .= $this->replace_elements($template_value, $entry, true);
                    }
                    $content = substr($content, 0, $start).$block_content.substr($content, $end);
                    continue;
                }
                $content = substr($content, 0, $start).substr($content, $end);
            } else {
                break;
            }
        }
        return $content;
    }

    /**
     * 替换模版内容中的 “元素”。
     *
     * 将模版内容中的每个元素，替换为对应的元素值。
     * 元素在模版中的格式为 “{元素名称:}”，也可以使用一个或多个过滤器，格式为 “{元素名称:过滤器}”，“{元素名称:过滤器|过滤器|过滤器}”。
     *
     * @param  字符串   $content 待处理模版内容。
     * @param  关联数组 $data    元素数据。每个数组元素的键是元素名称，值是元素值。
     * @param  逻辑值   $retain  该参数指定是否将关联数组中不存在的元素保留在模版中，为真则保留，否则将删除（替换为空字符串）。
     * @return 字符串            经替换的模版内容。
     */
    public function replace_elements($content, $data, $retain) {
        preg_match_all("/{([a-z-_]+):([a-z0-9-_^|]+)*}/", $content, $elements, PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
        $elements = array_reverse($elements);
        foreach ($elements as $element) {
            $define = $element[0][0];
            $offset = $element[0][1];
            $name = $element[1][0];
            $filters = array();
            if (count($element) == 3) { $filters = explode("|", $element[2][0]); }
            if (array_key_exists($name, $data)) {
                $value = $data[$name];
                foreach($filters as $filter) {
                    switch ($filter) {
                        case 'basename':
                            $value = basename($value);
                            break;
                        case 'addslash':
                            $value = substr($value, -1, 1) == "/" ? $value : $value."/";
                            break;
                        case 'delslash':
                            $value = ((substr($value, -1, 1) == "/") && (trim($value) != "/")) ?
                                substr($value, 0, -1) : $value;
                            break;
                        case 'stripdir':
                            $value = ((substr($value, -1, 1) == "/") && (trim($value) != "/")) ?
                                substr($value, 0, -1) : $value;
                            $value = ($pos = strrpos($value, "/")) ? substr($value, 0, $pos) : $value;
                            break;
                        case 'urlescape':
                            $value = urlencode($value);
                            break;
                        case 'escape':
                            $value = htmlspecialchars($value);
                            break;
                        case 'unicode':
                            $value = decode_unicode($value);
                            break;
                        case 'firstline':
                            $value = str_replace(array("\r\n", "\r"), array("\n", "\n"), $value);
                            $value = rtrim(($pos = strpos($value, "\n")) ? substr($value, 0, $pos) : $value, "\n");
                            break;
                        case 'lower':
                            $value = strtolower($value);
                            break;
                        case 'upper':
                            $value = strtoupper($value);
                            break;
                        case 'addbreaks':
                            $value =
                                str_replace(array("\r\n", "\r", "\n"), array("<br />", "<br />", "<br />"), $value);
                            break;
                        case 'nonempty':
                            $value = $value == "" ? "&nbsp;" : $value;
                            break;
                        case 'fill76':
                            $eol = strpos($value, "\r\n") ? "\r\n" : strpos($value, "\r") ? "\r" : "\n";
                            $value = chunk_split($value, 76, $eol);
                            break;
                        case 'fill68':
                            $eol = strpos($value, "\r\n") ? "\r\n" : strpos($value, "\r") ? "\r" : "\n";
                            $value = chunk_split($value, 68, $eol);
                            break;
                        case 'trim':
                            $value = trim($value);
                            break;
                        case 'sizefmt':
                            if ($value > 1073741824) {
                                $value = sprintf('%.2f GB', $value / 1073741824);
                            } else if ($value > 1048576) {
                                $value = sprintf('%.2f MB', $value / 1048576);
                            } else if ($value > 1024) {
                                $value = sprintf('%.2f KB', $value / 1024);
                            }
                            break;
                        case 'localdate':
                            $value = $value + date("Z");
                            break;
                        default:
                            if (substr($filter, 0, 5) == "date^") {
                                $format_id = substr($filter, 5);
                                if ($format = $this->get_template_value($format_id)) {
                                    $value = date($format, $value);
                                } else {
                                    $value = "";
                                }
                            }
                            break;
                    }
                }
                $content = substr($content, 0, $offset).$value.substr($content, $offset + strlen($define));
            } else {
                if (!$retain) {
                    $content = substr($content, 0, $offset).substr($content, $offset + strlen($define));
                }
            }
        }
        return $content;
    }

    public function error($elements, $error, $sub_code = 0) {
        $elements['err'] = $error;
        $elements['sub'] = $sub_code;
        if (isset($this->error_msg['e-'.$error])) {
            $elements['msg'] = $this->error_msg["e-$error"];
        } else {
            $elements['msg'] = $this->error_msg["e-0000"];
        }
        $elements['msg'] = $this->replace_elements($elements['msg'], $elements, false);
        if (($this->command == 'rss') || ($this->command == 'atom')) {
            if ($content = $this->get_page_content('error')) {
                $elements['date'] = time();
                    // err, msg, sub, date
                $content = $this->replace_elements($content, $elements, false);
            }
            header('Content-Type: application/'.$this->command.'+xml; charset='.$elements['encoding']);
            header('Content-Length: '.strlen($content));
            header('Content-Transfer-Encoding: binary');
            echo $content;
        } else {
            if (isset($elements['repo-rev'])) {
                $this->page_map = $this->error_map;
            } else {
                $this->page_map = $this->nofound_map;
            }
            if ($content = $this->get_page_content()) {
            } else if ($content = @file_get_contents("error.tmpl")) {
            } else {
                $content = "<h3 style=\"color: red;\">Error: </h3><p style=\"color: red;\">".
                           "{err:escape}-{sub:escape}: {msg:escape}</p>";
            }
            $languages = $this->fetch_languages();
            $content = $this->replace_entries_block($content, 'languages-list', $languages);
            echo $this->replace_elements($content, $elements, false);
        }
        exit;
    }

}

?>