<?php
/**
 * SvnExplorer 的公共函数模块。
 *
 * @author Han Jing Yu <hanjy@han-soft.com>
 * @version 1.0
 * @copyright 2001-2017 Han-soft
 */

/**
 * 在路径末尾添加路径分隔符。
 *
 * 如果路径末尾没有路径分隔符，则添加一个。
 *
 * @param  字符串，路径。
 * @return 字符串，已在末尾添加路径分隔符的路径。
 */
function remove_slash($path) {
    return ((substr($path, -1, 1) == "/") && (trim($path) != "/")) ? substr($path, 0, -1) : $path;
}

/**
 * 删除路径末尾的路径分隔符。
 *
 * 如果路径末尾有路径分隔符，则删除，但不会删除根路径。
 *
 * @param  字符串，路径。
 * @return 字符串，已删除末尾路径分隔符的路径。
 */
function append_slash($path) {
    return substr($path, -1, 1) == "/" ? $path : $path."/";
}

/**
 * 处理路径，如果末尾是 /.. 则转换为正常路径。
 *
 * @param  字符串 $path 待处理路径。
 * @return 字符串       返回转换后的路径。
 */
function pack_path($path) {
    if (substr($path, -3) == "/.") {
        $path = substr($path, 0, -2);
    } else if (substr($path, -3) == "/..") {
        $path = substr($path, 0, -3);
        $pos = strrpos($path, "/");
        if ($pos !== false) { $path = substr($path, 0, $pos); }
    }
    if ($path == "") { $path = "/"; }
    return remove_slash($path);
}

/**
 * 解析 svn 命令输出中的 unicode 字符串。
 *
 * 该函数对 svn 命令输出中已编码的 unicode 字符串进行解码。
 * svn 命令输出中已编码的 unicode 字符串每一个字节表示为 "?\nnn", nnn 为3位十进制数。
 *
 * @param  字符串 $str svn 命令输出的字符串。
 * @return 字符串      解码后的字符串。
 */
function decode_unicode($str) {
    $state = 0;
    $code = "";
    $value = "";
    for ($i = 0; $i < strlen($str); $i++) {
        switch ($state) {
            case 0:
                if ($str[$i] == "?") {
                    $state = 1;
                } else {
                    $code .= $str[$i];
                }
                break;
            case 1:
                if ($str[$i] == "\\") {
                    $state = 2;
                    $value = "";
                } else {
                    $code .= "?" . $str[$i];
                }
                break;
            case 2:
            case 3:
            case 4:
                if ($str[$i] >= "0" && $str[$i] <= "9")  {
                    $value .= $str[$i];
                    $state++;
                    if ($state == 5) {
                        $code .= chr(intval($value));
                        $state = 0;
                    }
                } else {
                    $code .= "?\\" . $value . $str[$i];
                }
                break;
            default:
                $code .= $str[$i];
                break;
        }
    }
    return $code;
}

/**
 * 该函数读取全局配置。
 *
 * 将全局配置文件读入二维数组，配置文件中的每个段将读入一个数组元素，段名用作数组键，段内容则作为数组元素值，该值也是一个数组，段中每个配
 * 置项为其中的一个数组元素，配置项名称作为该数组元素的键，配置项的值作为该数组元素的值。同时还会将页面风格的模版定义文件读入配置中的一个
 * 数组元素。该元素的键是“map”，值是一个二维数组，每个数组项是一个页面，数组键是页面名称，数组值也是一个数组，其中每个数组项是一个模版定
 * 义，键名是模版名称，键值是模版内容或保存模版内容的文件名。部分配置优先使用全局配置中的设置，如全局配置中没有设置，则使用风格设置中模版
 * 定义文件公共部分的设置，包括 encoding、static 和 feed。
 *
 * @param  字符串，配置文件路径。
 * @return 数组，配置。
*/
function read_config($config_file) {
    $config = @parse_ini_file($config_file, true);
    $config['web']['style'] = isset($config['web']['style']) ? trim($config['web']['style']) : "default";
    if (!is_dir("styles/".$config['web']['style'])) { $config['web']['style'] = 'default'; }
    $config['web']['language'] = isset($config['web']['language']) ? trim($config['web']['language']) : "en-us";
    if (!is_dir("styles/".$config['web']['style'].'/'.$config['web']['language'])) {
        $config['web']['language'] = 'en-us';
    }
    $config['web']['encoding'] = isset($config['web']['encoding']) ? trim($config['web']['encoding']) : "";
    $config['web']['feed'] = isset($config['web']['feed']) ? trim($config['web']['feed']) : "";
    return $config;
}

/**
 * 判断一个本地路径是否是 Subversion 代码库。
 *
 * 如果本地路径是 Subversion 代码库则返回真，否则返回假。
 *
 * @param  字符串 $path 指定待判断的本地路径。
 * @return 布尔值       本地路径是否 Subversion 代码库。
 */
function is_repo($path) {
    return (is_dir($path."/conf") && is_dir($path."/db") && is_file($path."/format") &&
        is_file($path."/conf/svnserve.conf"));
}

/**
 * 提取给定本地路径下所有的 Subversion 代码库。
 *
 * 该函数判断本地路径下的每一个目录项，如果是一个 Subversion 代码库，则将其加入第二个参数给出的数组中，数组项的键是每个代码库的标签名称，
 * 由代码库标签名称的前缀 $prefix 参数值和目录项的名称组成，其中目录名称首字母会转换为大写。数组项的值是则该代码库完整的本地路径，格式为
 * file://....。
 *
 * @param $path：字符串，用来搜索代码库的本地路径。
 * @param $repos：数组，本地路径下所有的代码库会追加至该数组，数组项的键是代码库标签名称，值是代码库完整的本地路径。
 * @param $prefix：字符串，用来指定代码库标签名称的前缀。
 */
function fetch_repos_urls($path, &$repos, $prefix = "") {
    $path_coms = explode(",", $path, 2);
    switch (count($path_coms)) {
        case 0: continue;
        case 1: $path_coms = Array(remove_slash(trim($path_coms[0])), remove_slash(trim($path_coms[0])));
        case 2: $path_coms = Array(remove_slash(trim($path_coms[0])), remove_slash(trim($path_coms[1])));
    }
    if ($handle = opendir($path_coms[0])) {
        while($item = readdir($handle)) {
            if (($item != ".") && ($item != "..")) {
                $path_full = $path_coms[0] . "/" . $item;
                if (is_dir($path_full) && is_repo($path_full)) {
                    $repos[$prefix.ucfirst($item)] = Array("file://".$path_full, $path_coms[1] . "/" . $item);
                }
            }
        }
        closedir($handle);
    }
}

/**
 * 提取给全局配置中所有的 Subversion 代码库。
 *
 * 该函数根据全局配置文件中代码库配置部分，获取所有的代码库及其 url 路径，结果位于第二个参数给出的数组中，数组项的键是每个代码库的标签名
 * 称，键名是代码库标签，键值是代码库的 url 路径。
 *
 * @param  数组 $repos_config 全局配置文件中的代码库配置部分。
 * @return 数组               返回所有代码库及其 url 路径，每个元素是一个数组，包含两个元素，第一个是代码库 url 路径，第
 *                            二个是远程访问路径（用来显示）。
 */
function fetch_all_urls($repos_config) {
    $repo_urls = array();
    foreach ($repos_config as $repo_id => $repo_url) {
        $repo_url = trim($repo_url);
        if (substr($repo_url, 0, 1) == "@") {
            fetch_repos_urls(substr($repo_url, 1), $repo_urls, (substr($repo_id, 0, 1) == "*") ? "" : $repo_id." / ");
        } else {
            $url_coms = explode(",", $repo_url, 2);
            switch (count($url_coms)) {
                case 0: continue;
                case 1: $repo_urls[$repo_id] = Array(trim($url_coms[0]), trim($url_coms[0]));
                case 2: $repo_urls[$repo_id] = Array(trim($url_coms[0]), trim($url_coms[1]));
            }
        }
    }
    return $repo_urls;
}

/**
 * 根据文件后缀和 mime 类型判断文件内容如何显示。
 *
 * @param  字符串 $filename 文件名。
 * @param  字符串 $mimetype 文件 mime 类型。
 * @return 字符串           返回文件应该如何显示，none: 不显示内容，source: 显示为文字（源代码），image：显示为图像，注意结果可能组
 *                         合 source 和 image。
 */
function get_display($filename, $mimetype) {
    $images_ext = "bmp, gif, jpg, jpeg, jpe, jpz, tif, tiff, wbmp, ico, png";
    $source_ext = "txt, text, html, htm, wml, xml, css, js, asp, sh, csh, bcsh, bat, inc, php, py, pl, c, c++, cpp, ".
                  "h, hpp, pas, dpr, dpk, rc, iss, isl, nsis, swift, bas, java, tex, latex, tcl, dtd, pem, crt, cer, ".
                  "der, key, ini, inf, htaccess, ps, pdf, p7b, p7c, p7r, p7m, spc, vcf, sql, xsl, xslt, reg, md, ".
                  "rst, csv";
    $none_ext = "wmv, avi, pfx, rtf, zip, tar, tgz, bz2, xsl, doc, mp3, mp4, dat, mpg, psd";

    $mime_type = strtolower(dirname($mimetype));
    $mime_sub = strtolower(basename($mimetype));
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    switch ($mime_type) {
        case 'audio':
        case 'video':
        case 'x-world':
            return 'none';
        case 'message':
        case 'text':
            if (($file_ext == "md")||($mime_sub =="markdown")) { return "source markdown"; }
            if ($file_ext == "svg") { return "image source svg"; }
            return 'source';
            break;
        case 'multipart':
            return 'none';
        case 'image':
            switch ($mime_sub) {
                case 'png':
                case 'bmp':
                case 'bitmap':
                case 'vnd.wap.wbmp':
                case 'jpeg':
                case 'gif':
                case 'tiff':
                case 'x-icon':
                    return "image";
                case 'svg+xml':
                    return "image source svg";
                default:
                    return "none";
            }
        case 'application':
            switch ($mime_sub) {
                case 'xml':
                    if ($file_ext == 'svg') { return "image source svg"; }
                case 'xhtml+xml':
                case 'xml-dtd':
                case 'x-javascript':
                case 'x-perl':
                case 'postscript':
                case 'x-sh':
                case 'x-csh':
                case 'x-tex':
                case 'x-latex':
                case 'x-texinfo':
                case 'x-tcl':
                case 'x-x509-ca-cert':
                case 'x-pkcs7-certificates':
                case 'x-pkcs7-certreqresp':
                case 'pkcs10':
                case 'pdf':
                    return 'source';
                case 'octet-stream':
                    break;
                default:
                    return 'none';
            }
        default:
            if ($file_ext == "svg") { return "image source svg"; }
            if ($file_ext == "md") { return "source markdown"; }
            if (strpos("+, $images_ext, ", ", $file_ext, ") > 0) { return "image"; }
            if (strpos("+, $source_ext, ", ", $file_ext, ") > 0) { return "source"; }
            if (strpos("+, $none_ext, ", ", $file_ext, ") > 0) { return "none"; }
            if ($mime_sub == 'octet-stream') return 'none';
            return "source";
    }
}

/**
 * 根据文件后缀获取其 mime 类型以便下载。
 *
 * @param  字符串 $filename 文件名。
 * @return 字符串           返回文件 mime 类型。
 */
function get_mime_type($filename) {
    $mimes = array(
        // Compress
        'gz' => 'application/x-gzip', 'tgz' => 'application/x-gzip', '7z' => 'application/x-7z-compressed',
        'bz2' => 'application/x-bzip2', 'bz' => 'application/x-bzip2', 'tbz' => 'application/x-bzip2',
        'zip' => 'application/zip', 'tar' => 'application/x-tar', 'rar' => 'application/x-rar',
        'cab' => 'application/vnd.cab-com-archive', 'iso' => 'application/x-iso9660-image',

        // Image
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpe' => 'image/jpeg', 'jpz' => 'image/jpeg',
        'gif' => 'image/gif', 'png' => 'image/png', 'pnz' => 'image/png', 'bmp' => 'image/bitmap',
        'ico' => 'image/x-icon', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'svg' => 'image/svg+xml',
        'wbmp' => 'image/vnd.wap.wbmp', 'tga' => 'image/x-targa', 'psd' => 'image/vnd.adobe.photoshop',

        // Audio
        'mp3' => 'audio/mpeg', 'wav' => 'audio/x-wav', 'snd' => 'audio/basic', 'au' => 'audio/basic',
        'mid' => 'audio/mid', 'rmi' => 'audio/mid', 'm3u' => 'audio/x-mpegurl', 'ra' => 'audio/x-pn-realaudio',
        'ram' => 'audio/x-pn-realaudio', 'wma' => 'audio/x-ms-wma', 'ogg' => 'audio/ogg', 'mp4a' => 'audio/mp4',
        'mpga' => 'audio/mpeg',

        // Video
        'avi' => 'video/x-msvideo', 'mp4' => 'video/mp4', 'mpg4' => 'video/mp4', 'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg', 'qt' => 'video/quicktime', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
        'wmv' => 'video/x-ms-wmv', 'wmp' => 'video/x-ms-wmp', 'flv' => 'video/x-flv', 'f4v' => 'video/mp4',
        'ogv' => 'video/ogg', 'swf' => 'application/x-shockwave-flash', '3gp' => 'video/3gpp',
        'rm' => 'video/vnd.rn-realmedia', 'rvmb' => 'video/vnd.rn-realvideo',

        // Message
        'mht' => 'message/rfc822', 'mhtml' => 'message/rfc822', 'nws' => 'message/rfc822',

        // Document
        'rtf' => 'application/rtf', 'xla' => 'application/vnd.ms-excel', 'xls' => 'application/vnd.ms-excel',
        'xlm' => 'application/vnd.ms-excel', 'xlc' => 'application/vnd.ms-excel',
        'xlt' => 'application/vnd.ms-excel', 'xlw' => 'application/vnd.ms-excel',
        'pps' => 'application/vnd.ms-powerpoint', 'ppt' => 'application/vnd.ms-powerpoint',
        'doc' => 'application/msword', 'dot' => 'application/msword', 'mdb' => 'application/x-msaccess',
        'wmf' => 'application/x-msmetafile', 'chm' => 'application/vnd.ms-htmlhelp', 'pdf' =>'application/pdf',
        'ai' => 'application/postscript', 'eps' => 'application/postscript', 'ps' => 'application/postscript',

        // Crypto
        'cer' => 'application/x-x509-ca-cert', 'crt' => 'application/x-x509-ca-cert', 'p10' => 'application/pkcs10',
        'der' => 'application/x-x509-ca-cert', 'p12' => 'application/x-pkcs12', 'pfx' => 'application/x-pkcs12',
        'p7b' => 'application/x-pkcs7-certificates', 'p7c' => 'application/x-pkcs7-mime',
        'p7r' => 'application/x-pkcs7-certreqresp', 'p7s' => 'application/x-pkcs7-signature',
        'spc' => 'application/x-pkcs7-certificates', 'p7m' => 'application/x-pkcs7-mime',

        // Text
        'csv' => 'text/plain', 'txt' => 'text/plain', 'css' => 'text/plain', 'js' => 'text/plain',
        'sh' => 'text/plain', 'asp' => 'text/plain', 'csh' => 'text/plain', 'bcsh' => 'text/plain',
        'bat' => 'text/plain', 'inc' => 'text/plain', 'php' => 'text/plain', 'py' => 'text/plain',
        'pl' => 'text/plain', 'c' => 'text/plain', 'c++' => 'text/plain', 'cpp' => 'text/plain',
        'h' => 'text/plain', 'hpp' => 'text/plain', 'pas' => 'text/plain', 'dpr' => 'text/plain',
        'dpk' => 'text/plain', 'rc' => 'text/plain', 'iss' => 'text/plain', 'isl' => 'text/plain',
        'nsis' => 'text/plain', 'swift' => 'text/plain', 'bas' => 'text/plain', 'tex' => 'text/plain',
        'java' => 'text/plain', 'latex' => 'text/plain', 'tcl' => 'text/plain', 'ini' => 'text/plain',
        'inf' => 'text/plain', 'htaccess' => 'text/plain', 'sql' => 'text/plain', 'reg' => 'text/plain',
        'md' => 'text/markdown', 'rst' => 'text/plain', 'dlm' => 'text/plain',

        // Html
        'htm' => 'text/html', 'html' => 'text/html', 'atom' => 'application/atom+xml', 'rss' => 'application/rss+xml',
        'vcf' => 'text/x-vcard', 'xml' => 'text/xml', 'xsl' => 'text/xml', 'xslt' => 'text/xml',
        'dtd' => 'application/xml-dtd'
    );
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return array_key_exists($file_ext, $mimes) ? $mimes[$file_ext] : "application/octet-stream";
}

?>