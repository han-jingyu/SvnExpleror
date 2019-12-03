<?php
/**
 * SvnExplorer 的 Subversion 接口模块。
 *
 * @author Han Jing Yu <hanjy@han-soft.com>
 * @version 1.0
 * @copyright 2001-2017 Han-soft
 */

/**
 * 这个类用来存取 Subversion 仓库。
 *
 * @todo 添加 zip 方法。
 */
class Svn {

    /**
     * @var 字符串 $version_no  保存系统中 svn 的版本号。
     * @var 字符串 $revision_no 保存系统中 svn 的代码库版本。
     */
    public $version_no = '';
    public $revision_no = '';

    /**
     * @var 字符串 $repo_url   保存仓库根路径，不要直接使用，请通过属性 url 来访问。
     * @var 数组   $parsed_url 保存仓库根路径解析后的各个部分，包括 <schema>, [host], [port], [username], [password], <path>。
     */
    private $repo_url = '';
    private $parsed_url;

    /**
     * @var 字符串 $path 保存系统中 svn 命令所在目录的路径。
     */
    public static $path = '';

    /**
     * @var 逻辑值 $diff_ignore_eol        指定比较文件时是否忽略行尾格式。
     * @var 逻辑值 $diff_ignore_spaces     指定比较文件时是否忽略空白字符。
     * @var 逻辑值 $diff_ignore_all_spaces 指定比较文件时是否忽略所有空白字符。
     */
    public $diff_ignore_eol = false;
    public $diff_ignore_spaces = false;
    public $diff_ignore_all_spaces = false;

    /**
     * @var 整数值 $archive_max_size 保存 zip 压缩路径时所处理文件的大小上限，单位是字节，文件大小大于该值的文件不会压缩到压缩包中，
     *                              为 0 则不作限制。
     */
    public $archive_max_size = 0;

    /**
     * 实现属性 url 的设置方法。
     *
     * @param 字符串 $property 属性名称。
     * @param 字符串 $value    属性值。
     */
    function __set($property, $value) {
        if ($property == 'url') {
            if ($this->parse_repo_url($value)) {
                $this->repo_url = remove_slash($value);
            }
        }
    }

    /**
     * 实现属性 url 的读取方法。
     *
     * @param  字符串 $property 属性名称。
     * @return 字符串           属性值。
     */
    function __get($property) {
        if ($property == 'url') {
            return $this->repo_url;
        } else {
            return false;
        }
    }

    /**
     * 删除路径末尾的路径分隔符。
     *
     * 如果路径末尾有路径分隔符，则删除，但不会删除根路径。
     *
     * @param  字符串 $path 待删除尾部分隔符的路径。
     * @return 字符串       已删除尾部分隔符的路径。
     */
    function remove_slash($path) {
        return ((substr($path, -1, 1) == '/') && (trim($path) != '/')) ? substr($path, 0, -1) : $path;
    }

    /**
     * 在路径末尾添加路径分隔符。
     *
     * 如果路径末尾没有路径分隔符，则添加一个。
     *
     * @param  字符串 $path 待添加分隔符的路径。
     * @return 字符串       已在末尾添加路径分隔符的路径。
     */
    function append_slash($path) {
        return substr($path, -1, 1) == '/' ? $path : $path.'/';
    }

    /**
     * 处理仓库中项目路径，如果末尾是 /.. 则转换为正常路径。
     *
     * @param  字符串 $path 仓库中项目路径。
     * @return 字符串       返回转换后的 URL 路径。
     */
    private function pack_path($path) {
        if (substr($path, -3) == "/.") {
            $path = substr($path, 0, -2);
        } else if (substr($path, -3) == "/..") {
            $path = substr($path, 0, -3);
            $pos = strrpos($path, "/");
            if ($pos !== false) { $path = substr($path, 0, $pos); }
        }
        if ($path == "") { $path = "/"; }
        return trim($this->remove_slash($path));
    }

    /**
     * 解析仓库路径。
     *
     * 将仓库路径解析为多个部分，并保存到 parsed_url 属性中。
     *
     * @param  字符串 $repo_url 待解析仓库路径。
     * @return 字符串           解析成功返回 true 值，失败则返回 false 值。
     */
    private function parse_repo_url($repo_url) {
        $repo_url = trim($repo_url);
        if ($schema_end = strpos($repo_url, '://')) {
            $this->parsed_url['schema'] = strtolower(substr($repo_url, 0, $schema_end + 3));
            $repo_url = substr($repo_url, $schema_end + 3);
            switch ($this->parsed_url['schema']) {
                case 'https://':
                case 'http://':
                case 'svn://':
                case 'svn+ssh://':
                    if ($user_end = strpos($repo_url, '@')) {
                        $user = substr($repo_url, 0, $user_end);
                        $user_items = explode(':', $user, 2);
                        if (strpos($user_items[0], '/') === false) {
                            $repo_url = substr($repo_url, $user_end + 1);
                            $this->parsed_url['username'] = $user_items[0];
                            if (count($user_items) == 2) {
                                $this->parsed_url['password'] = $user_items[1];
                            }
                        }
                    }
                    if ($host_end = strpos($repo_url, '/')) {
                        $host = substr($repo_url, 0, $host_end);
                        $repo_url = substr($repo_url, $host_end);
                        $host_items = explode(':', $host, 2);
                        if (!preg_match("/[0-9a-z-_~]+(.[0-9a-z-]+)+/", strtolower($host_items[0]))) { return false; }
                        $this->parsed_url['host'] = strtolower($host_items[0]);
                        if (count($host_items) == 2) {
                            if (!preg_match("/[0-9]+/", $host_items[1])) return false;
                            $this->parsed_url['port'] = $host_items[1];
                        }
                        $this->parsed_url['path'] = $this->remove_slash($repo_url);
                    } else {
                        return false;
                    }
                    break;
                case 'file://':
                    $this->parsed_url['path'] = $this->remove_slash($repo_url);
                    break;
                default:
                    return false;
                    break;
            }
            return true;
        }
        return false;
    }

    /**
     * 将仓库中项目路径转换为完整的 URL 路径。
     *
     * @param  字符串 $path 仓库中项目路径。
     * @return 字符串       返回转换后的 URL 路径，失败则返回假。
     */
    private function path_to_url($path) {
        $path = $this->pack_path($path);
        switch($this->parsed_url['schema']) {
            case "file://":
                return $this->parsed_url['schema'].$this->parsed_url['path'].$path;
                break;
            default:
                return false;
        }
    }

    /**
     * 执行 svn 命令。
     *
     * @param  字符串     $command   要执行的 svn 子命令。
     * @param  字符串     $parameter 命令行参数。
     * @param  字符串数组  &$return   返回命令输出结果，每个数组项是一行，不含行尾的换行符和。
     * @param  整数       &$code     返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值                执行成功返回 true 值，否则返回 false 值。
     */
    private function svn_exec($command, $parameter, &$return, &$code) {
        $set_env = "export LANG=en_US.UTF-8; ";
        exec( $set_env.self::$path."svn ".$command." ".$parameter." 2>&1", $return, $code);
        if ($code != 0) {
            echo "svn ".$command." ".$parameter."<br/>";
            foreach ($return as $index => $line) {
                echo "$line<br />";
            }
        }
        return $code == 0;
    }

    /**
     * 将 svn 命令输出中的日期解析为 UNIX 时间戳值。
     *
     * @param  字符串 $date 日期字符串，格式如: 2017-04-09 21:03:05 +0800 (Tue, 09 Apr 2017)。
     * @return 整数         返回 UNIX 时间戳值。
     */
    protected function parse_svn_date($date) {
        $year = substr($date, 0, 4);
        $month = substr($date, 5, 2);
        $day = substr($date, 8, 2);
        $hour = substr($date, 11, 2);
        $minute = substr($date, 14, 2);
        $second = substr($date, 17, 2);
        $zone_hour = substr($date, 21, 2);
        $zone_minute = substr($date, 23, 2);
        $zone = ($zone_hour * 60 + $zone_minute) * 60;
        $timestamp = mktime($hour, $minute, $second, $month, $day, $year);
        $timestamp = substr($date, 20, 1) == "+" ? $timestamp - $zone : $timestamp + $zone;
        return $timestamp;
    }

    /**
     * 将 svn 命令输出的 xml 中的日期解析为 UNIX 时间戳值。
     *
     * @param  字符串 $date 日期字符串，格式如: 2017-04-09T21:03:05.100888Z。
     * @return 整数         返回 UNIX 时间戳值。
     */
    protected function parse_xml_date($date) {
        $year = substr($date, 0, 4);
        $month = substr($date, 5, 2);
        $day = substr($date, 8, 2);
        $hour = substr($date, 11, 2);
        $minute = substr($date, 14, 2);
        $second = substr($date, 17, 2);
        $timestamp = mktime($hour, $minute, $second, $month, $day, $year);
        return $timestamp;
    }

    /**
     * 分解日志搜索的待搜索字符串。
     *
     * 待搜索字符串以空白分隔，各个词之间是默认逻辑或的关系，词前如果有 + 号则为与关系。每个词都可以使用 " 符号括起来，如果词中包含空白或
     * + 号则必须使用 " 括起来，词中的 " 符号使用 \" 代替，符号 \ 使用 \\ 代替。词中可以使用 * 和 ? 通配符。
     *
     * @param  字符串 $find 待搜索字符串。
     * @return 数组         返回分解的各个待搜索词数组。
     */
    protected function parse_find_words($find) {
        $words = array();
        $word = '';
        $isand = false;
        $quot = false;
        $len = strlen($find);
        $i = 0;
        while ($i < $len) {
            $c = substr($find, $i, 1);
            if ($c == "\\") {
                $i++;
                if ($i < $len) {
                    $c = substr($find, $i, 1);
                    switch ($c) {
                        case "\\":
                            $word .= '\\';
                            break;
                        case '"':
                            $word .= '\"';
                            break;
                        default:
                            $word .= '\\'.$c;
                            break;
                    }
                } else {
                    $word .= '\\';
                }
            } else if ($c == '"') {
                $quot = !$quot;
                if ($word != '') { $words[] = array('and' => $isand, 'word' => $word); }
                $isand = false;
                $word = '';
            } else if ($c == ' ') {
                if ($quot) {
                    $word .= ' ';
                } else {
                    if ($word != '') { $words[] = array('and' => $isand, 'word' => $word); }
                    $isand = false;
                    $word = '';
                }
            } else if ($c == '+') {
                if ($quot) {
                    $word .= '+';
                } else {
                    if ($word != '') { $words[] = array('and' => $isand, 'word' => $word); }
                    $isand = true;
                    $word = '';
                }
            } else {
                $word .= $c;
            }
            $i++;
        }
        if ($word != '') { $words[] = array('and' => $isand, 'word' => $word); }
        return $words;
    }

    /**
     * 类构造函数，同时可设置 path 属性和默认仓库路径 url 属性的值，并取得 parsed_url、version_no 和 revision_no 属性的值。
     *
     * @param 字符串 $svn_path 系统中 svn 命令所在目录的路径，用来设置 path、version_no、revision_no 属性的值。
     * @param 字符串 $repo_url 默认仓库路径，用来设置 url 和 parsed_url 属性的值。
     */
    public function __construct($svn_path = "", $repo_url = "") {
        if ($svn_path != "") {
            self::$path = $this->append_slash($svn_path);
            $this->version($version, $code);
        }
        if ($repo_url != "") { $this->url = $repo_url; }
    }

    /**
     * 取得 svn blame 命令的执行结果。
     *
     * @param  字符串 $path  待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev   待操作仓库版本，默认为 “head” 版本。
     * @param  数组值 $lines 返回命令执行结果，每个元素包括 lineno(行号)、date(时间戳)、author(作者)、rev(版本)、source(代码行)。
     * @param  整数值 &$code 返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值        执行成功返回 true 值，否则返回 false 值。
     */
    public function anno($path, $rev, &$lines, &$code) {
        $lines = array();
        $parameter = $this->path_to_url($path)."@$rev -r $rev -v";
        if ($this->svn_exec("blame", $parameter, $output, $code)) {
            foreach ($output as $index => $text) {
                $line = array('lineno' => $index + 1);
                $rev_patt = "/[0-9]+/";
                if (preg_match($rev_patt, $text, $rev, PREG_OFFSET_CAPTURE, 0) == 0) {
                    $lines = array();
                    return false;
                }
                $line['rev'] = $rev[0][0];
                $author_start = $rev[0][1] + strlen($rev[0][0]);
                $date_patt = "/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} [+|-][0-9]{4} ".
                    "\((Sun|Mon|Tue|Wed|Thu|Fri|Sat), ".
                    "[0-9]{2} (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) [0-9]{4}\)/";
                if (preg_match($date_patt, $text, $date, PREG_OFFSET_CAPTURE, $author_start) == 0) {
                    $lines = array();
                    return false;
                }
                $line['date'] = $this->parse_svn_date($date[0][0]);
                $author_length = $date[0][1] - $author_start;
                $line['author'] = trim(substr($text, $author_start, $author_length));
                $line['source'] = substr($text, $date[0][1] + 45);
                $lines[] = $line;
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn cat 命令的执行结果。
     *
     * @param  字符串 $path   待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev    待操作仓库版本，默认为 “head” 版本。
     * @param  数组值 $lines  返回命令执行结果，每个元素包括 lineno（行号）和 source（代码行）。
     * @param  整数值 &$code  返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值         执行成功返回 true 值，否则返回 false 值。
     */
    public function cat($path, $rev, &$lines, &$code) {
        $lines = array();
        $parameter = $this->path_to_url($path)."@$rev -r $rev";
        if ($this->svn_exec("cat", $parameter, $output, $code)) {
            foreach ($output as $index => $source) {
                $line['lineno'] = $index + 1;
                $line['source'] = $source;
                $lines[] = $line;
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn diff 命令的执行结果。
     *
     * @param  字符串 $path    待比较首个仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev     待比较首个仓库版本（旧文件），默认为 “head” 版本。
     * @param  数组值 $diff    返回内容比较结果，每个文件一个元素，键名为文件路径，键值包括：
     *                         - old: 首个文件名及版本
     *                         - old-file: 首个文件名
     *                         - old-rev: 首个版本
     *                         - new: 第二个文件名及版本
     *                         - new-file: 第二个文件名
     *                         - new-rev: 第二个版本
     *                         - lines: 该文件内容的比较输出（补丁），每行一个元素，每个元素键值包括：
     *                           + lineno: 补丁行号
     *                           + line: 补丁行内容
     *                           + kind: 补丁行类型: header, context, insert, delete
     *                         - props: 属性比较结果，每个属性一行，每个元素键值包括：
     *                           + op: 操作：added, deleted, modified
     *                           + name: 属性名
     *                           + lines: 该属性内容的比较输出，每行一个元素，每个元素键值包括行号
     *                             * lineno: 行号
     *                             * line: 行内容
     *                             * kind: 行类型: header, context, insert, delete
     * @param  整数值 $code    返回命令输出代码，执行成功则为 0 值。
     * @param  字符串 $path1   待比较第二个仓库路径，默认与首个仓库路径一致。
     * @param  字符串 $rev1    待比较第二个仓库版本（新文件），默认为 “head” 版本。
     * @param  逻辑值 $reverse 指定是否交换首个和第二个比较对象。
     * @return 逻辑值          执行成功返回 true 值，否则返回 false 值。
     */
    public function diff($path, $rev, &$diff, &$code, $path1 = "", $rev1 = 0, $reverse = false) {
        $diff = array();
        $prop = array();

        $path = trim($path);
        $path1 = trim($path1);
        if ($path == $path1) { $path1 = ""; }
        $rev = (($rev == 0)||($rev == "")) ? "head" : intval($rev);
        $rev1 = (($rev1 == 0)||($rev1 == "")) ? "head" : intval($rev1);

        $parameter = $reverse ? "-r $rev1:$rev " : "-r $rev:$rev1 --force ";
        if ($this->diff_ignore_eol) {  $parameter .= "-x -ignore-eol-style "; }
        if ($this->diff_ignore_spaces) {  $parameter .= "-x -b "; }
        if ($this->diff_ignore_all_spaces) {  $parameter .= "-x -w "; }
        if ($path1 == "") {
            $parameter .= $this->path_to_url($path)."@$rev";
        } else {
            $parameter .= $reverse ?
                "--old=".$this->path_to_url($path1)."@$rev1 --new=".$this->path_to_url($path)."@$rev" :
                "--old=".$this->path_to_url($path)."@$rev --new=".$this->path_to_url($path1)."@$rev1";
        }
        if ($this->svn_exec("diff", $parameter, $output, $code)) {
            $is_prop = false;
            $prop_name = "";
            foreach ($output as $line) {
                if (substr($line, 0, 7) == "Index: ") {
                    $is_prop = false;
                    $name = "/".substr($line, 7);
                    $diff[$name]['lines'] = array();
                    continue;
                } else if ($line == "===================================================================") {
                    continue;
                } else if (substr($line, 0, 4) == "--- ") {
                    $entry["kind"] = "new";
                    $entry["line"] = $line;
                    $diff[$name]['old'] = substr($line, 4);  // old_rev
                    $rev_pos = strrpos($line, "(revision ");
                    $diff[$name]['old-file'] = trim(substr($line, 4, $rev_pos - 4));
                    $diff[$name]['old-rev'] = substr($line, $rev_pos + 10, -1);
                } else if (substr($line, 0, 4) == "+++ ") {
                    $entry["kind"] = "old";
                    $entry["line"] = $line;
                    $diff[$name]['new'] = substr($line, 4);  // new_rev
                    $rev_pos = strrpos($line, "(revision ");
                    $diff[$name]['new-file'] = trim(substr($line, 4, $rev_pos - 4));
                    $diff[$name]['new-rev'] = substr($line, $rev_pos + 10, -1);
                } else if ((substr($line, 0, 3) == "@@ ") && (substr($line, -3) == " @@") && !$is_prop) {
                    $entry["kind"] = "header";
                    $entry["line"] = $line;
                } else if ((substr($line, 0, 3) == "## ") && (substr($line, -3) == " ##") && $is_prop) {
                    $entry["kind"] = "header";
                    $entry["line"] = $line;
                } else if ((substr($line, 0, 1) == " ")||($line == "")) {
                    $entry["kind"] = "context";
                    $entry["line"] = $line;
                } else if (substr($line, 0, 1) == "+") {
                    $entry["kind"] = "insert";
                    $entry["line"] = $line;
                } else if (substr($line, 0, 1) == "-") {
                    $entry["kind"] = "delete";
                    $entry["line"] = $line;
                } else if (substr($line, 0, 1) == "\\") {
                    $entry["kind"] = "other";
                    $entry["line"] = $line;
                } else if (substr($line, 0, 21) == "Property changes on: ") {
                    $last = count($diff[$name]['lines']) - 1;
                    if (($diff[$name]['lines'][$last]['kind'] == "context") &&
                        ($diff[$name]['lines'][$last]['line'] == "")) {
                        unset($diff[$name]['lines'][$last]);
                    }
                    $is_prop = true;
                    $name = "/".substr($line, 21);
                    continue;
                } else if ($line == "___________________________________________________________________") {
                    continue;
                } else if (substr($line, 0, 7) == "Added: ") {
                    $prop_name = substr($line, 7);
                    $prop[$name][$prop_name]['name'] = $prop_name;
                    $prop[$name][$prop_name]['op'] = "added";
                    $prop[$name][$prop_name]['lines'] = array();
                    continue;
                } else if (substr($line, 0, 9) == "Deleted: ") {
                    $prop_name = substr($line, 9);
                    $prop[$name][$prop_name]['name'] = $prop_name;
                    $prop[$name][$prop_name]['op'] = "deleted";
                    $prop[$name][$prop_name]['lines'] = array();
                    continue;
                } else if (substr($line, 0, 10) == "Modified: ") {
                    $prop_name = substr($line, 10);
                    $prop[$name][$prop_name]['name'] = $prop_name;
                    $prop[$name][$prop_name]['op'] = "modified";
                    $prop[$name][$prop_name]['lines'] = array();
                    continue;
                } else {
                    $entry["kind"] = "unknown";
                    $entry["line"] = $line;
                }

                if ($is_prop) {
                    $entry['lineno'] = count($prop[$name][$prop_name]['lines']) + 1;
                    $prop[$name][$prop_name]['lines'][] = $entry;
                } else {
                    $entry['lineno'] = count($diff[$name]['lines']) + 1;
                    $diff[$name]['lines'][] = $entry;
                }
            }
            foreach ($diff as $path => $value) {
                $diff[$path]['path'] = substr($path, 1);
                $diff[$path]['props'] = isset($prop[$path]) ? $prop[$path] : array();
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn diff2 命令的执行结果。
     *
     * @param  字符串 $path    待比较首个仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev     待比较首个仓库版本（旧文件），默认为 “head” 版本。
     * @param  字符串 $kind    待比较路径的种类（file 表示文件，dir 表示目录）。
     * @param  数组值 $diff    返回内容比较结果，每个文件一个元素，键名为文件路径，键值包括：
     *                         - old: 首个文件名及版本
     *                         - old-file: 首个文件名
     *                         - old-rev: 首个版本
     *                         - new: 第二个文件名及版本
     *                         - new-file: 第二个文件名
     *                         - new-rev: 第二个版本
     *                         - lines-all: 该文件内容的比较输出，每行一个元素，每个元素键值包括：
     *                           + lineno-o: 在首个文件中的行号（不存在则显示为 + 表示添加）
     *                           + lineno-n: 在第二个文件中的行号（不存在则显示为 - 表示删除）
     *                           + line: 行内容
     *                           + kind: 行种类: insert, delete, equal
     *                         - lines: 该文件内容的比较输出（补丁），每行一个元素，每个元素键值包括：
     *                           + lineno: 补丁行号
     *                           + line: 补丁行内容
     *                           + kind: 补丁行类型: header, context, insert, delete
     *                         - props: 属性比较结果，每个属性一行，每个元素键值包括：
     *                           + op: 操作：added, deleted, modified
     *                           + name: 属性名
     *                           + lines: 该属性内容的比较输出，每行一个元素，每个元素键值包括：
     *                             * lineno: 行号
     *                             * line: 行内容
     *                             * kind: 行类型: header, context, insert, delete
     * @param  整数值 $code    返回命令输出代码，执行成功则为 0 值。
     * @param  字符串 $path1   待比较第二个仓库路径，默认与首个仓库路径一致。
     * @param  字符串 $rev1    待比较第二个仓库版本（新文件），默认为 “head” 版本。
     * @param  逻辑值 $reverse 指定是否交换首个和第二个比较对象。
     * @return 逻辑值          执行成功返回 true 值，否则返回 false 值。
     */
    public function diff2($path, $rev, $kind, &$diffs, &$code, $path1 = "", $rev1 = 0, $reverse = false) {
        if ($this->diff($path, $rev, $diffs, $code, $path1, $rev1, $reverse)) {
            foreach ($diffs as $file => $diff) {
                $oldfile = $kind == 'file' ? $path : $path."/".$diff['old-file'];
                if ($this->cat($oldfile, $diff['old-rev'], $lines0, $code)) {
                    $no0 = 1;
                    $no1 = 1;
                    foreach ($diff['lines'] as $index => $line) {
                        if ($line['kind'] == "header") {
                            $headers = explode(" ", trim($line['line']));
                            $pos0 = explode(",", $headers[1]);
                            for ($no = $no0; $no < intval(substr($pos0[0], 1)); $no++) {
                                $line_cur['lineno-o'] = "$no0";
                                $line_cur['lineno-n'] = "$no1";
                                $line_cur['kind'] = "equal";
                                $line_cur['line'] = $lines0[$no - 1]['source'];
                                $diffs[$file]['lines-all'][] = $line_cur;
                                $no0++;
                                $no1++;
                            }
                        } else if ($line['kind'] == "context") {
                            $line_cur['lineno-o'] = "$no0";
                            $line_cur['lineno-n'] = "$no1";
                            $line_cur['kind'] = "equal";
                            $line_cur['line'] = substr($line['line'], 1);
                            $diffs[$file]['lines-all'][] = $line_cur;
                            $no0++;
                            $no1++;
                        } else if ($line['kind'] == "delete") {
                            $line_cur['lineno-o'] = "$no0";
                            $line_cur['lineno-n'] = '-';
                            $line_cur['kind'] = "delete";
                            $line_cur['line'] = substr($line['line'], 1);
                            $diffs[$file]['lines-all'][] = $line_cur;
                            $no0++;
                        } else if ($line['kind'] == "insert") {
                            $line_cur['lineno-o'] = '+';
                            $line_cur['lineno-n'] = "$no1";
                            $line_cur['kind'] = "insert";
                            $line_cur['line'] = substr($line['line'], 1);
                            $diffs[$file]['lines-all'][] = $line_cur;
                            $no1++;
                        }
                    }
                    for ($i = $no0; $i <= count($lines0); $i++) {
                        $line_cur['lineno-o'] = $no0;
                        $line_cur['lineno-n'] = $no1;
                        $line_cur['line'] = $lines0[$i - 1]['source'];
                        $line_cur['kind'] = "equal";
                        $diffs[$file]['lines-all'][] = $line_cur;
                        $no0++;
                        $no1++;
                    }
                } else {
                    $diffs[$file]['line-all'] = array();
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn list 命令的执行结果，列出指定版本指定路径下的所有文件，对子目录，包括 “..” 目录项。
     *
     * @param  字符串 $path   待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev    待操作仓库版本，默认为 “head” 版本。
     * @param  数组值 $files  返回命令执行结果。每个元素包括：
     *                       kind(类型：parent, dir, file)、name(名称)、size(大小)、last-rev(版本)、last-author(作者)、
     *                       last-date(日期时间戳)。
     * @param  整数值 &$code  返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值         执行成功返回 true 值，否则返回 false 值。
     */
    public function files($path, $rev, &$files, &$code) {
        $files = array();
        if ($path != "/") {
            if ($this->info($this->append_slash($path)."..", $rev, $info, $code)) {
                $files[] = array('name' => '..', 'kind' => 'parent', 'size' => 0, 'last-date' => $info['last-date'],
                   'last-author' => $info['last-author'], 'last-rev' => $info['last-rev']);
            } else {
                $files[] = array('name' => '..', 'kind' => 'parent', 'size' => 0, 'last-date' => 0,
                    'last-author' => '', 'last-rev' => 0);
            }
        }
        $parameter = $this->path_to_url($path)."@$rev --xml -r $rev";
        if ($this->svn_exec("list", $parameter, $output, $code)) {
            $xml = "";
            foreach ($output as $line) { $xml .= " ".$line; }
            $files_dom = simplexml_load_string(trim($xml));
            foreach ($files_dom->list->entry as $key => $value) {
                $file_entry['kind'] = (string)$value->attributes()['kind'];
                $file_entry['name'] = (string)$value->name;
                $file_entry['size'] = intval($value->size);
                $file_entry['last-rev'] = intval($value->commit->attributes()['revision']);
                $file_entry['last-author'] = (string)($value->commit->author);
                $file_entry['last-date'] = $this->parse_xml_date((string)($value->commit->date));
                $files[] = $file_entry;
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn info 命令的执行结果，取得代码库指定版本下指定路径的信息。
     *
     * @param  字符串 $path   待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev    待操作仓库版本，默认为 “head” 版本。
     * @param  数组值 $info   返回命令执行结果。每个元素包括：
     *                        kind(类型：dir, file)、last-rev(最后修改版本)、last-author(作者)、last-date(时间戳)、
     *                        root(代码库根 url)、uuid(代码库唯一标识)、rev(代码库版本)、path(代码库内路径)、url(完整 url)。
     * @param  整数值 $code   返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值         执行成功返回 true 值，否则返回 false 值。
     */
    public function info($path, $rev, &$info, &$code) {
        $info = array();
        $items = array('path' => 'Relative URL: ^',
                       'uuid' => 'Repository UUID: ',
                       'rev' => 'Revision: ',
                       'kind' => 'Node Kind: ',
                       'last-author' => 'Last Changed Author: ',
                       'last-rev' => 'Last Changed Rev: ',
                       'last-date' => 'Last Changed Date: ',
                       'url' => 'URL: ',
                       'root' => 'Repository Root: ');
        $parameter = $this->path_to_url($path)."@$rev -r $rev";
        if ($this->svn_exec("info", $parameter, $output, $code)) {
            foreach ($output as $line) {
                foreach ($items as $item => $label) {
                    if (substr($line, 0, strlen($label)) == $label) {
                        $info[$item] = substr($line, strlen($label));
                        break;
                    }
                }
            }
            if (!isset($info['path'])) {
                $info['path'] = substr($info['url'], strlen($info['root']));
            }
            $info['last-date'] = $this->parse_svn_date($info['last-date']);
            if ($info['kind'] == 'directory') { $info['kind'] = 'dir'; }
            if ($info['path'] != "") {
                $info['basename'] = basename($info['path']);
                $info['parent'] = $this->append_slash(dirname($info['path']));
            } else {
                $info['basename'] = "/";
                $info['parent'] = "";
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn log 命令的执行结果，取得代码库指定路径的日志信息。
     *
     * @todo  输出使用 xml 模式，以确定修改路径的种类。
     * @todo  输出是否修改属性，是否修改内容，所进行的操作（添加、删除、修改、复制、移动）。
     *
     * @param  字符串 $path    待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev     开始（最大）版本号，默认为 “head” 版本。
     * @param  整数值 $count   要取得的最大日志行数。
     * @param  数组值 $info    返回命令执行结果。每个元素包括：
     *                         rev(版本)、author(作者)、date(时间戳)、lines(提交消息行数)、messages(提交消息)、
     *                         paths(变动路径，每个元素包括操作 op、路径 path、kind 路径种类)、head(是否头版本，始终是 no，需自
     *                        己设置)。
     * @param  整数值 $code    返回命令输出代码，执行成功则为 0 值。
     * @param  整数值 $min_rev 返回结果中最小版本号。
     * @param  整数值 $max_rev 返回结果中最大版本号。
     * @param  字符串 $find    指定搜索字符串，以空白分隔，各个词之间是逻辑与的关系。每个词都可以使用 " 符号括起来，如果词中包含空白
     *                         则必须使用 " 括起来，词中的 " 符号使用 \" 代替，符号 \ 使用 \\ 代替。词中可以使用 * 和 ? 通配符。
     * @return 逻辑值          执行成功返回 true 值，否则返回 false 值。
     */
    public function log($path, $rev, $count, &$logs, &$code, &$min_rev, &$max_rev, $find = "") {
        $logs = array();
        $parameter = $this->path_to_url($path)."@$rev -v -l $count -r $rev:0 --xml ";
        if (($find != "") && ($this->version_no >= "1.8")) {
            if ($words = $this->parse_find_words($find)) {
                foreach ($words as $word) {
                    $parameter .= ($word['and'] ? '--search-and "' : '--search "').$word['word'].'" ';
                }
            } else {
                $parameter .= '--search "$find"';
            }
        }
        if ($this->svn_exec("log", $parameter, $output, $code)) {
            $xml = "";
            foreach ($output as $line) { $xml .= " ".$line; }
            $min_rev = 99999999;
            $max_rev = 0;
            $logs_dom = simplexml_load_string(trim($xml));
            foreach ($logs_dom->logentry as $log_dom) {
                $log['rev'] = (int)$log_dom->attributes()['revision'];
                $min_rev = $log['rev'] < $min_rev ? $log['rev'] : $min_rev;
                $max_rev = $log['rev'] > $max_rev ? $log['rev'] : $max_rev;
                $log['head'] = 'no';
                $log['author'] = (string)$log_dom->author;
                $log['date'] = $this->parse_xml_date((string)($log_dom->date));
                $log['messages'] = (string)$log_dom->msg;
                $log['lines'] = substr_count($log['messages'], "\n");
                $log['add'] = 'no';
                $log['delete'] = 'no';
                $log['modify'] = 'no';
                $log['replace'] = 'no';
                $log['text-mods'] = 'no';
                $log['prop-mods'] = 'no';
                $log['file'] = 'no';
                $log['dir'] = 'no';
                $log['paths'] = array();
                foreach ($log_dom->paths->path as $path_dom) {
                    $path_item['op'] = (string)$path_dom->attributes()['action'];
                    switch ($path_item['op']) {
                        case 'A': $log['add'] = 'yes'; break;
                        case 'D': $log['delete'] = 'yes'; break;
                        case 'M': $log['modify'] = 'yes'; break;
                        case 'R': $log['replace'] = 'yes'; break;
                    }
                    $path_item['path'] = (string)$path_dom;
                    $path_item['kind'] = (string)$path_dom->attributes()['kind'];
                    if ($path_item['kind'] == 'file') $log['file'] = 'yes';
                    if ($path_item['kind'] == 'dir') $log['dir'] = 'yes';
                    if ($path_item['kind'] == "") $path_item['kind'] = 'file';
                    $path_item['text'] = ((string)$path_dom->attributes()['text-mods'] == 'true') ? 'yes' : 'no';
                    $path_item['prop'] = ((string)$path_dom->attributes()['prop-mods'] == 'true') ? 'yes' : 'no';
                    if ($path_item['text'] == 'yes') $log['text-mods'] = 'yes';
                    if ($path_item['prop'] == 'yes') $log['prop-mods'] = 'yes';
                    $log['paths'][] = $path_item;
                }
                $logs[] = $log;
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn propget 命令的执行结果，获取指定属性的值。
     *
     * @param  字符串 $path       待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev        待操作仓库版本，默认为 “head” 版本。
     * @param  字符串 $prop_name  待操作属性名称。
     * @param  字符串 $prop_value 返回的属性值。
     * @param  整数值 $code       返回命令输出代码，执行成功则为 0 值。
     * @param  逻辑值 $revprop    指定取回版本属性，此时参数 $path 无意义。
     * @return 逻辑值             执行成功返回 true 值，否则返回 false 值。
     */
    public function propget($path, $rev, $prop_name, &$prop_value, &$code, $revprop = false) {
        $parameter = "$prop_name ".$this->path_to_url($path) . "@$rev -r $rev";
        if ($revprop) $parameter .= " --revprop";
        if ($this->svn_exec("propget", $parameter, $output, $code)) {
            $prop_value = implode("\n", $output);
            return true;
        }
        return false;
    }

    /**
     * 取得 svn proplist 命令的执行结果，获取属性列表。
     *
     * @param  字符串 $path    待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev     待操作仓库版本，默认为 “head” 版本。
     * @param  数组值 $props   返回命令执行结果，每个元素包含两项：name(属性名称) 和 value(属性值)。
     * @param  整数值 $code    返回命令输出代码，执行成功则为 0 值。
     * @param  字符串 $mime    返回文件的 mimi 类型，未设置则返回空字符。
     * @param  逻辑值 $revprop 指定取回版本属性，此时参数 $path 无意义。
     * @return 逻辑值          执行成功返回 true 值，否则返回 false 值。
     */
    public function proplist($path, $rev, &$props, &$code, &$mime, $revprop = false) {
        $mime = "";  // "text/plain"
        $props = array();
        $parameter = $this->path_to_url($path);
        if ($revprop) {
            $parameter .= " -r $rev -v --revprop";
        } else {
            $parameter .= "@$rev -r $rev -v";
        }
        if ($this->svn_exec("proplist", $parameter, $output, $code)) {
            foreach ($output as $line) {
                if (substr($line, 0, 4) == '    ') {
                    $prop['value'] .= "\n".substr($line, 4);
                } else if (substr($line, 0, 2) == '  ') {
                    if (isset($prop['name'])) {
                        $prop['value'] = substr($prop['value'], 1);
                        $props[$prop['name']] = $prop;
                    }
                    $prop['name'] = substr($line, 2);
                    $prop['value'] = "";
                }
            }
            if (isset($prop['name'])) {
                $prop['value'] = substr($prop['value'], 1);
                $props[$prop['name']] = $prop;
            }
            foreach ($props as $prop) {
                if (strtolower($prop['name']) == 'svn:mime-type') {
                    $mime = $prop['value'];
                    break;
                }
            }
            if ($revprop) {
                foreach ($props as $id => $prop) {
                    if (substr($prop['name'], 0, 4) == "svn:") { unset($props[$id]); }
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 取得 svn --version 命令的执行结果，返回系统所安装 Subversion 的版本。
     *
     * @param  数组值 $version 返回命令执行结果。每个元素包括：
     *                         version(版本号)、revision(Subversion 代码库版本号)、label(版本字符串)。
     * @param  整数值 &$code  返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值         执行成功返回 true 值，否则返回 false 值。
     */
    public function version(&$version, &$code) {
        if ($this->svn_exec("", "--version", $output, $code)) {
            $version['label'] = trim(substr($output[0], 13));
            if ($p = strpos($version['label'], " (r")) {
                $version['version'] = trim(substr($version['label'], 0, $p));
                $version['revision'] =  trim(substr($version['label'], $p + 3, -1));
                $this->version_no = $version['version'];
                $this->revision_no = $version['revision'];
            } else {
                $this->version_no = trim($version['label']);
                $this->revision_no = "";
            }
            return true;
        }
        return false;
    }

    /**
     * 取得代码库信息。
     *
     * @param  数组值 $repo 返回命令执行结果。每个元素包括：
     *                      root(代码库根 url)、uuid(代码库唯一标识)、rev(代码库版本)，last-author(最后修改作者)、
     *                      last-rev(最后修改版本)、last-date(最后修改时间戳)，对于 file:// 代码库，还包括：
     *                      realm(认证名称)、auth-access(授权用户访问权限)、anao-access(匿名用户访问权限)、
     *                      password-db(密码文件路径)、authz-db(用户认证文件路径)。
     * @param  整数值 $code 返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值       执行成功返回 true 值，否则返回 false 值。
     */
    public function repository(&$repo, &$code) {
        if (!$this->info("/", "head", $info, $code)) { return false; }
        $repo['root'] = $info['root'];
        $repo['rev'] = $info['rev'];
        $repo['uuid'] = $info['uuid'];
        $repo['last-author'] = $info['last-author'];
        $repo['last-date'] = $info['last-date'];
        $repo['last-rev'] = $info['last-rev'];
        if ($this->parsed_url["schema"] == "file://") {
            if (!$conf = @parse_ini_file($this->parsed_url["path"]."/conf/svnserve.conf", true)) { return false; }
            if (array_key_exists("general", $conf)) {
                $repo["realm"] = array_key_exists("realm", $conf["general"]) ? trim($conf["general"]["realm"]) : "";
                $repo["auth-access"] = array_key_exists("auth-access", $conf["general"]) ?
                    (strtolower(trim($conf["general"]["auth-access"])) == "" ? "none" :
                        strtolower(trim($conf["general"]["auth-access"]))) : "";
                $repo["anon-access"] = array_key_exists("anon-access", $conf["general"]) ?
                    (strtolower(trim($conf["general"]["anon-access"])) == "" ? "none" :
                        strtolower(trim($conf["general"]["anon-access"]))) : "";
                $repo["password-db"] = array_key_exists("password-db", $conf["general"]) ?
                    $this->parsed_url["path"]."/".trim($conf["general"]["password-db"]) : "";
                $repo["authz-db"] = array_key_exists("authz-db", $conf["general"]) ?
                    $this->parsed_url["path"]."/".trim($conf["general"]["authz-db"]) : "";
            }
            return true;
        }
        return true;
    }

    /**
     * 输出文件原始内容。
     *
     * @param  字符串 $path    待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev     待操作仓库版本，默认为 “head” 版本。
     * @param  整数值 $code    返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值          执行成功返回 true 值，否则返回 false 值。
     */
    public function raw($path, $rev, &$code) {
        $set_env = "export LANG=en_US.UTF-8; ";
        $result = "";
        if ($fp = popen($set_env.self::$path."svn cat ".$this->path_to_url($path)."@$rev -r $rev", "r")) {
            while (!feof($fp)) {
                $result .= fgets($fp, 4096);
            }
            fclose($fp);
            return $result;
        }
        return false;
    }

    /**
     * 获取指定版本指定路径下的所有文件和目录，以便压缩为 zip 文件。
     *
     * @param  字符串 $path    待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev     待操作仓库版本，默认为 “head” 版本。
     * @param  数组值 $files   用来返回该路径下的所有文件路径，包括该路径自身。
     * @param  整数值 $code    返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值          执行成功返回 true 值，否则返回 false 值。
     */
    private function get_zip_files($path, $rev, &$files, &$code) {
        $date_patt = "/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) [0-9]{2} (([0-9]{4})|([0-9]{2}:[0-9]{2}))/";
        $base_path = $this->append_slash($this->pack_path($path));
        $files = array(0 => $base_path);
        $parameter = $this->path_to_url($path)."@$rev -R -r $rev -v";
        if ($this->svn_exec("list", $parameter, $output, $code)) {
            foreach ($output as $line) {
                if (preg_match($date_patt, $line, $date, PREG_OFFSET_CAPTURE, 0) == 0) {
                    // $files = array();
                    // return false;
                    continue;
                }
                $line_path = substr($line, $date[0][1] + 13);
                if ($line_path == './') { continue; }
                if (substr($line_path, -1, 1) == '/') {
                    $size = 0;
                } else {
                    $line_size = substr($line, 0, $date[0][1] - 1);
                    $size = substr($line_size, strrpos($line_size, ' ') + 1);
                }
                if (($size < $this->archive_max_size) || ($this->archive_max_size == 0)) {
                    $files[] = $base_path.substr($line, $date[0][1] + 13);
                }
            }
            return true;
        }
        return false;
    }


    /**
     * 将指定版本指定路径下的所有文件和目录（包括空目录）压缩为 zip 文件。
     *
     * @param  字符串 $path     待操作仓库路径，默认为 “/” 路径/。
     * @param  字符串 $rev      待操作仓库版本，默认为 “head” 版本。
     * @param  字符串 $zip_file 压缩文件路径及文件名。
     * @param  整数值 $code     返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值           执行成功返回 true 值，否则返回 false 值。
     */
    public function zip($path, $rev, $zip_file, &$code) {
        $path = $this->pack_path($path);
        $base = $this->append_slash(dirname($path));
        if ($this->get_zip_files($path, $rev, $files, $code)) {
            $zip = new ZipArchive();
            if(@$zip->open($zip_file, ZIPARCHIVE::CREATE)) {
                foreach ($files as $file) {
                    if ($file == "/") { continue; }
                    $path_inzip = substr($file, strlen($base));
                    if (substr($file, -1, 1) == "/") {
                        if (!@$zip->addEmptyDir(substr($path_inzip, 0, -1))) {
                            return false;
                        }
                    } else {
                        if ($content = $this->raw($file, $rev, $code)) {
                            @$zip->addFromString($path_inzip, $content);
                        } else {
                            return false;
                        }
                    }
                }
                @$zip->setArchiveComment('Repository: '.$this->url.
                    "\nRevision: $rev\nPath: $path\nCreator: SvnExplorer 1.0; Subversion ".$this->version_no.' ('.
                    $this->revision_no.')');
                if (@$zip->close()) {
                    $code = 0;
                    return true;
                } else {
                    $code = 2;
                    return false;
                }
            } else {
                $code = 999;
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 对于由 zip 方法创建的 zip 文件，解析其注释中包含的代码库、路径和版本信息。
     *
     * @param  字符串 $zip_path 待解析 zip 文件路径。
     * @param  字符串 $info     返回解析结果，包括 Repository、Revision、Path 和 Creator。
     * @param  整数值 $code     返回命令输出代码，执行成功则为 0 值。
     * @return 逻辑值           执行成功返回 true 值，否则返回 false 值。
     */
    public function fetch_zip_info($zip_path, &$info, &$code) {
        $info = array('Repository' => '', 'Revision' => '', 'Path' => '', 'Creator' => '');
        $code = 1;
        $zip = new ZipArchive();
        if(@$zip->open($zip_path)) {
            $code = 2;
            if ($comment = @$zip->getArchiveComment(ZipArchive::FL_UNCHANGED)) {
                $code = 0;
                $comments = explode("\n", $comment);
                foreach ($comments as $value) {
                    $item = explode(": ", $value, 2);
                    if (count($item) == 2) {
                        $info[trim($item[0])] = trim($item[1]);
                    }
                }
            }
            @$zip->close();
        }
        return $code == 0;
    }

    /**
     * 获取指定版本指定文件的大小。
     *
     * @param  字符串 $path     待操作仓库路径。
     * @param  字符串 $rev      待操作仓库版本，默认为 “head” 版本。
     * @param  整数值 $code     返回命令输出代码，执行成功则为 0 值。
     * @return 字符串           返回文件大小，失败则返回空字符串。
     */
    public function filesize($path, $rev, &$code) {
        if ($this->files($path, $rev, $files, $code)) {
            $files_count = count($files);
            if ($files_count > 0) {
                return $files[$files_count - 1]['size'];
            }
        }
        return "";
    }

    public function create($path, $realm, $format, &$code) {
        $format = strtolower($format);
        if (($format == 'fsx') && ($this->version_no < '1.8')) $format = 'fsfs';
        $set_env = "export LANG=en_US.UTF-8; ";
        exec( $set_env.self::$path."svnadmin create $path --fs-type $format", $return, $code);
        if ($code == 0) {
            if ($conf = fopen("$path/conf/svnserve.conf", "w")) {
                fwrite($conf, "[general]\n");
                fwrite($conf, "anon-access = none\n");
                fwrite($conf, "auth-access = write\n");
                fwrite($conf, "password-db = passwd\n");
                fwrite($conf, "authz-db = authz\n");
                fwrite($conf, "groups-db = groups\n");
                fwrite($conf, "realm = $realm\n");
                fwrite($conf, "force-username-case = none\n");
                fwrite($conf, "hooks-env = hooks-env\n");
                fwrite($conf, "[sasl]\n");
                fwrite($conf, "use-sasl = false\n");
                fwrite($conf, "min-encryption = 0\n");
                fwrite($conf, "max-encryption = 256\n");
                fclose($conf);
            } else {
                $code = '1000';
            }
        }
        return $code == 0;
    }

}

?>