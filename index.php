<?php

include("includes/common.php");
include("includes/template.php");
include("includes/svn.php");

/* Configuation */
$config = read_config("config.ini");
$urls = fetch_all_urls($config['repos']);
// unset($config['repos']);
$elements = array();
$elements["style"] = $config['web']['style'];
    // style

/* Request */
$elements["host"] = ((strtolower($SERVER['HTTPS']) == 'on') ? 'https://' : 'http://'). (
    (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && ($_SERVER['HTTP_X_FORWARDED_HOST'] != "")) ?
    $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
$elements["url"] = $_SERVER["PHP_SELF"];
$elements["lang"] = array_key_exists("lang", $_GET) ? trim($_GET["lang"]) :
                    (isset($config['web']['language']) ? $config['web']['language'] : 'en-us');
if (!is_dir("styles/".$config['web']['style'].'/'.$elements['lang'])) $elements["lang"] = $config['web']['language'];
$elements["repo"] = array_key_exists("repo", $_GET) ? trim($_GET["repo"]) : "";
$elements["path"] = array_key_exists("path", $_GET) ? pack_path(trim($_GET["path"])) : "/";
$elements["rev"] = array_key_exists("rev", $_GET) ? trim($_GET["rev"]) : "head";
$elements["count"] = array_key_exists("count", $_GET) ? trim($_GET["count"]) : "50";
$elements["find"] = array_key_exists("find", $_GET) ? trim($_GET["find"]) : "";
$elements["kind"] = array_key_exists("kind", $_GET) ? trim($_GET["kind"]) : "file";
    // host, url, repo, path, rev, count, find, lang, kind

/* Command */
$command = array_key_exists("cmd", $_GET) ? trim($_GET["cmd"]) : "repos";
if (($command == "dir") || ($command == "parent")) { $command = "files"; }
$elements['command'] = $command;

/* Template */
$template = new Template($command, "styles/".$elements['style'], $elements['lang']);
$elements['encoding'] = isset($config['web']['encoding']) ? $config['web']['encoding'] : $template->encoding;
$elements['feed'] = isset($config['web']['feed']) ? $config['web']['feed'] : $template->feed;
$elements['static'] = "styles/".$config['web']['style']."/".$template->static;
$elements['static-lang'] = "styles/".$config['web']['style']."/".$elements['lang']."/".$template->static;
    // encoding, feed, static, static-lang

/* Subversion */
$svn = new Svn($config['svn']['path']);
$elements['svn-version'] = $svn->version_no;
$elements['svn-revision'] = ($svn->version_no != '') ? $svn->revision_no : '?';
if ($svn->version_no == "") { $template->error($elements, 1001, 1); }  // ERR_SVN_NO_FOUND
if (array_key_exists('zip', $config) && array_key_exists('max_size', $config['zip'])) {
    $svn->archive_max_size = intval($config['zip']['max_size']) * 1048576;
}
    // svn-version, svn-revision

/* Build repository and path elements */
if (strpos(": , summary, logs, search, files, file, filedir, rev, anno, diff, diff2, atom, rss, zip, raw, edit, ",
    ", $command,") > 0) {
    if (array_key_exists($elements['repo'], $urls)) {
        $svn->url = $urls[$elements['repo']][0];
        if ($svn->repository($repo, $code)) {
            foreach ($repo as $key => $value) { $elements["repo-".$key] = $value; }
                // repo-label, repo-realm, repo-anno-access, repo-auth-access, repo-password-db, repo-authz-db,
                // repo-uuid, repo-rev, repo-root, repo-last-author, repo-last-rev, repo-last-date
            $elements['repo-remote'] = $urls[$elements['repo']][1];
            if ($elements['rev'] == 'head') { $elements['rev'] = $elements['repo-rev']; }
        } else {
            $template->error($elements, 1002, $code);  // ERR_REPO_READ_FAIL
        }
    } else {
        $template->error($elements, 1003, 1);  // ERR_REPO_NO_FOUND
    }
    if (strpos(": , logs, search, files, file, filedir, rev, anno, diff, diff2, atom, rss, zip, raw, ", ", $command,") > 0) {
        if ($svn->info($elements['path'], $elements['rev'], $info, $code)) {
            foreach ($info as $key => $value) { $elements["path-".$key] = $value; }
            $elements["path-last-head"] = ($elements["path-last-rev"] == $elements['repo-rev']) ? "yes" : "no";
            $elements["path-remote"] = $urls[$elements['repo']][1] . $elements["path-path"];
                // path-kind, path-path, path-uuid, path-rev, path-url, path-root, path-last-author, path-last-rev,
                // path-last-date, path-last-head, path-basename, path-parent
        } else {
            $template->error($elements, 1004, $code);  // ERR_PATH_READ_FAIL
        }
    }
}
if (($command == 'filedir')||($command == 'file')||($command == 'dir')) {
    if ($elements['path-kind'] == 'file') { $command = 'file'; }
    if ($elements['path-kind'] == 'dir') { $command = 'files'; }
}
if (($command == 'zip') && ($elements['path-kind'] == 'file')) $command = 'raw';
if (($command == 'raw') && ($elements['path-kind'] == 'dir')) $command = 'zip';
$elements['command'] = $command;
    // command

/* Template */
if (($command != "zip") && ($command != "raw") && ($command != "static") && ($command != "error")) {
    if (!$template->map_loaded || ($template->command != $command)) {
        if (($command != 'file') && ($command != 'files')) {
            $template->error($elements, 1005, 1);  // ERR_CMD_INVALID
        }
        if (!$template->load_map($elements['command'])) {
            $template->error($elements, 1005, 2);  // ERR_CMD_INVALID
        }
    }
    if (!($content = $template->get_page_content())) { $template->error($elements, 1006, 1); } // ERR_TMP_READ_FAIL
    $languages = $template->fetch_languages();
    $content = $template->replace_entries_block($content, 'languages-list', $languages);

}
// unset($config);

// Output page
switch ($command) {
    case 'repos':
        // cmd=repos&repo=<repo_id>
        $repos = array();
        foreach ($urls as $label => $url) {
            $svn->url = $url[0];
            if ($svn->repository($repo, $code)) { $repo['label'] = $label; $repos[] = $repo; }
        }
        $elements["repos-count"] = count($repos);
            // repos-count
        $content = $template->replace_entries_block($content, 'repos-list', $repos);
            // [ label, uuid, rev, root, last-rev, last-date, last-author, realm, anno-access, auth-access, password-db,
            //   authz-db ]
        $content = $template->replace_elements($content, $elements, false);
        break;
    case 'summary':
        // cmd=summary&repo=<repo_id>
        $logs = array();
        if ($svn->log($elements['path'], 'head', 15, $logs, $code, $min_rev, $max_rev)) {
            if (count($logs) > 0) { $logs[0]['head'] = ($max_rev == $elements['repo-last-rev']) ? 'yes' : 'no'; }
            $elements['logs-count'] = count($logs);
            foreach ($logs as $key => $log) {
                $logs[$key]['paths-count'] = count($log['paths']);
                $logs[$key]['paths-list'] = $template->replace_entries_block($template->get_page_content('paths-list'),
                    'paths-list', $log['paths']);
            }
            $content = $template->replace_entries_block($content, 'logs-list', $logs);
                // [ rev, author, date, lines, messages, paths[op, path], head ]
            $readme_name = array("readme.md", "Readme.md", "ReadMe.md", "README.MD", "readme.txt", "Readme.txt",
                "ReadMe.txt", "README.TXT", "readme", "Readme", "ReadMe", "README");
            $readme_path = array("/", "/trunk");
            $elements['readme'] = "";
            $elements['readme-md'] = "-";
            $elements['has-readme'] = 'no';
            foreach ($readme_path as $path_item) {
                foreach ($readme_name as $name_item) {
                    if ($readme = $svn->raw($path_item.'/'.$name_item, 'head', $code)) {
                        if (strtolower(pathinfo($name_item, PATHINFO_EXTENSION)) == 'md') {
                            $elements['readme-md'] = $readme;
                        } else {
                            $elements['readme'] = $readme;
                        }
                        $elements['has-readme'] = 'yes';
                        break 2;
                    }
                }
            }
            // readme, readme-md, has-readme
            $content = $template->replace_elements($content, $elements, false);
        } else {
            $template->error($elements, 1007, $code);  // ERR_SUM_READ_FAIL
        }
        break;
    case 'search':
    case 'logs':
        // cmd=search&repo=<repo_id>[&rev=head][&path=/][&count=50][&find=]
        // cmd=logs&repo=<repo_id>[&rev=head][&path=/][&count=50][&find=]
        $elements['file-size'] = $svn->filesize($elements["path"], $elements["rev"], $code);
        $elements["old-rev"] = 0;
        $elements["new-rev"] = 0;
        $elements["count-more"] = $elements['count'] * 2;
        if ($elements["count-more"] > 200) { $elements["count-more"] = 200; }
        $elements["count-less"] = intval($elements['count'] / 2);
        if ($elements["count-less"] < 10) { $elements["count-less"] = 10; }
            // old-rev, new-rev, count-more, count-less
        $logs = array();
        if ($svn->log($elements["path"], $elements['rev'], $elements['count'], $logs, $code, $min_rev, $max_rev,
            $elements['find'])) {
            $elements["old-rev"] = $min_rev - 1;
            if ($elements["old-rev"] < 0) $elements["old-rev"] = 0;
            $elements["new-rev"] = $max_rev + $elements['count'];
            $elements["max-rev"] = $max_rev;
            if ($elements["new-rev"] > $elements['repo-last-rev']) $elements["new-rev"] = $elements['repo-last-rev'];
            $elements['is-head'] = ($max_rev == $elements['repo-last-rev']) ? 'yes' : 'no';
            if (count($logs) > 0) { $logs[0]['head'] = ($max_rev == $elements['repo-last-rev']) ? 'yes' : 'no'; }
            $elements["logs-count"] = count($logs);
                // max-rev, is-head, logs-count
            foreach ($logs as $key => $log) {
                $logs[$key]['paths-count'] = count($log['paths']);
                $logs[$key]['paths-list'] = $template->replace_entries_block($template->get_page_content('paths-list'),
                    'paths-list', $log['paths']);
            }
            $content = $template->replace_entries_block($content, 'logs-list', $logs);
                // [ rev, author, date, lines, messages, paths[op, path], head ]
            $content = $template->replace_elements($content, $elements, false);
        } else {
            $template->error($elements, 1008, $code); // ERR_LOG_READ_FAIL
        }
        break;
    case 'files':
        // cmd=files&repo=<repo_id>[&rev=head][&path=/]
        // cmd=parent&repo=<repo_id>[&rev=head][&path=/]
        // cmd=dir&repo=<repo_id>[&rev=head][&path=/]
        $elements['is-head'] = ($elements["rev"] == $elements['repo-rev']) ? 'yes' : 'no';
          // is-head
        if ($svn->files($elements["path"], $elements["rev"], $files, $code)) {
            $elements["files-count"] = count($files);
                // files-count
            $content = $template->replace_entries_block($content, 'files-list', $files);
                // [name, kind, size, last-rev, last-author, last-date]
            $elements['props-count'] = 0;
            if ($svn->proplist($elements["path"], $elements["rev"], $props, $code, $mime, false)) {
                $elements['props-count'] = count($props);
                $content = $template->replace_entries_block($content, 'props-list', $props);
                    // [name, value]
            }
            $content = $template->replace_elements($content, $elements, false);
        } else {
            $template->error($elements, 1009, $code); // ERR_DIR_LIST_FAIL
        }
        break;
    case 'rev':
        // cmd=rev&repo=<repo_id>[&rev=head]
        $logs = array();
        if ($svn->log($elements["path"], $elements['rev'], 1, $logs, $code, $min_rev, $max_rev)) {
            if (count($logs) == 1) {
                $elements['rev-head'] = ($max_rev == $elements['repo-last-rev']) ? 'yes' : 'no';
                $elements['rev-no'] = $logs[0]['rev'];
                $elements['rev-author'] = $logs[0]['author'];
                $elements['rev-date'] = $logs[0]['date'];
                $elements['rev-messages'] = $logs[0]['messages'];
                $elements['paths-count'] = count($logs[0]['paths']);
                $elements['text-mods'] = $logs[0]['text-mods'];
                $elements['prop-mods'] = $logs[0]['prop-mods'];
                $elements['add'] = $logs[0]['add'];
                $elements['delete'] = $logs[0]['delete'];
                $elements['modify'] = $logs[0]['modify'];
                $elements['replace'] = $logs[0]['replace'];
                $elements['file'] = $logs[0]['file'];
                $elements['dir'] = $logs[0]['dir'];
                    // rev-head, rev-no, rev-author, rev-date, rev-messages, paths-count
                $content = $template->replace_entries_block($content, 'paths-list', $logs[0]['paths']);
                    // [op, path, kind]
                $elements['props-count'] = 0;
                if ($svn->proplist($elements["path"], $elements["rev"], $props, $code, $mime, true)) {
                    $elements['props-count'] = count($props);
                    $content = $template->replace_entries_block($content, 'props-list', $props);
                        // [name, value]
                }
                $content = $template->replace_elements($content, $elements, false);
            } else {
                $template->error($elements, 1010, $code);  // ERR_REV_NO_FOUND
            }
        } else {
            $template->error($elements, 1011, $code); // ERR_REV_READ_FAIL
        }
        break;
    case 'file':
        // cmd=file&repo=<repo_id>[&rev=head][&path=/]
        $elements['is-head'] = ($elements["rev"] == $elements['repo-rev']) ? 'yes' : 'no';
          // is-head
        $elements['props-count'] = 0;
        if ($svn->proplist($elements["path"], $elements["rev"], $props, $code, $mime, false)) {
            $elements['props-count'] = count($props);
            $content = $template->replace_entries_block($content, 'props-list', $props);
                // [name, value]
        }
        $display = get_display($elements['path'], $mime);
        $elements["is-source"] = (strpos("+".$display, "source") > 0) ? "yes" : "no";
        $elements["is-image"] = (strpos("+".$display, "image") > 0) ? "yes" : "no";
        $elements["is-svg"] = (strpos("+".$display, "svg") > 0) ? "yes" : "no";
        $elements["is-md"] = (strpos("+".$display, "markdown") > 0) ? "yes" : "no";
        $elements["is-preview"] = ($elements["is-md"] == 'yes')||($elements["is-image"] == 'yes') ? 'yes' : 'no';
        $elements['svg'] = "";
        $elements['md'] = "-"; // For javascript to fetch the text node.
        $elements['file-source'] = "";
        $elements['file-size'] = "";
            // is-source, is-preview, is-image, is-svg, file-source, svg, md
        if ($elements["is-source"] == 'yes') {
            if ($source = $svn->raw($elements["path"], $elements["rev"], $code)) {
                $elements['file-size'] = strlen($source);
                $elements['file-source'] = $source;
                if ($elements["is-md"] == 'yes') { $elements['md'] = $source; }
                if ($elements["is-svg"] == 'yes') { $elements['svg'] = $source; }
            } else {
                $template->error($elements, 1012, $code);  // ERR_FILE_READ_FAIL
            }
        } else {
            $elements['file-size'] = $svn->filesize($elements["path"], $elements["rev"], $code);
        }
            // file-size
        $content = $template->replace_elements($content, $elements, false);
        break;
    case 'raw':
        // cmd=raw&repo=<repo_id>[&rev=head][&path=/]
        $file_ext = strtolower(pathinfo($elements["path"], PATHINFO_EXTENSION));
        if ($content = $svn->raw($elements["path"], $elements["rev"], $code)) {
            header('Content-Type: '.get_mime_type(basename($elements['path'])));
            header('Content-Disposition: attachment; filename="'.basename($elements['path']).'"');
            header('Content-Length: '.strlen($content));
            header('Content-Transfer-Encoding: binary');
        } else {
            @header('HTTP/1.1 500 Internal server error');
            @header("status: 500 Internal server error");
            $template->error($elements, 1013, 500);  // ERR_RAW_READ_FAIL
        }
        break;
    case 'rss':
    case 'atom':
        // cmd=rss&repo=<repo_id>[&path=/]
        // cmd=atom&repo=<repo_id>[&path=/]
        $logs = array();
        if ($svn->log($elements["path"], $elements['rev'], 10, $logs, $code, $min_rev, $max_rev)) {
            $content = $template->replace_entries_block($content, 'feed-entry', $logs);
                // [ rev, author, date, lines, messages, paths[op, path], head ]
            $content = $template->replace_elements($content, $elements, false);
        } else {
            $template->error($elements, 1007);
        }
        break;
    case 'error':
        // cmd=error&repo=<repo_id>&err=<error_code>&sub=<sub_error_code>
        $template->error($elements, $_GET['err'], $_GET['sub']);
        exit;
    case 'static':
        // cmd=static&repo=<repo_id>&path=<static_sub_path>&type=<pub|pri>
        if (substr($elements['path'], 0, 1) != '/') { $elements['path'] = '/'.$elements['path']; }
        $file_path = remove_slash($elements['static']).$elements['path'];
        if (isset($_GET['type'])) {
            if (strtolower($_GET['type']) == 'pri') {
                $file_path = remove_slash($elements['static-lang']).$elements['path'];
            }
        }
        if (file_exists($file_path)) {
            if (is_file($file_path)) {
                header('Location:'.$file_path);
            } else {
                @header("http/1.1 403 Forbidden");
                @header("status: 404 Forbidden");
                $template->error($elements, 1014, 403);  // ERR_STATIC_READ_FAIL
            }
        } else {
            @header("HTTP/1.1 404 Not found.");
            @header("Status: 404 Not found.");
            $template->error($elements, 1015, 404);  // ERR_STATIC_NO_FOUND
        }
        exit;
    case 'zip':
        // cmd=zip&repo=<repo_id>[&rev=head][&path=/]
        $zip_filename = basename($elements['path']);
        $zip_filename = $elements['repo']."-r".$elements['rev'].($zip_filename == '' ? '' : '-'.$zip_filename);
        $zip_local_path = './zip/'.$zip_filename.'_'.md5($elements['path']).'.zip';
        if (file_exists($zip_local_path)) {
            if ($svn->fetch_zip_info($zip_local_path, $info, $code)) {
                if (($info['Repository'] != $svn->url) || ($info['Revision'] != $elements['rev']) ||
                    ($info['Path'] != $elements['path'])) {
                    if (!@unlink($zip_local_path)) {
                        @header('HTTP/1.1 500 Internal server error');
                        @header("status: 500 Internal server error");
                        $template->error($elements, 1016, 500);  // ERR_ZIP_DELETE_FAIL
                    }
                }
            }
        }
        if (!file_exists($zip_local_path)) {
            if (!$svn->zip($elements['path'], $elements['rev'], $zip_local_path, $code)) {
                @header('HTTP/1.1 500 Internal server error');
                @header("status: 500 Internal server error");
                $template->error($elements, 1017, 500);  // ERR_ZIP_CREATE_FAIL
            }
        }
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename="'.$zip_filename.'.zip"');
        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: binary");
        header('Content-Length: '. filesize($zip_local_path));
        @readfile($zip_local_path);
        exit;
    case 'anno':
        // cmd=anno&repo=<repo_id>[&rev=head][&path=/]
        $elements['is-head'] = ($elements["rev"] == $elements['repo-rev']) ? 'yes' : 'no';
        $elements['file-size'] = $svn->filesize($elements["path"], $elements["rev"], $code);
            // is-head, file-size
        $elements['props-count'] = 0;
        if ($svn->proplist($elements["path"], $elements["rev"], $props, $code, $mime, false)) {
            $elements['props-count'] = count($props);
            $content = $template->replace_entries_block($content, 'props-list', $props);
                // [name, value]
        }
        $display = get_display($elements['path'], $mime);
        if (strpos("+".$display, "source") > 0) {
            if ($svn->anno($elements["path"], $elements["rev"], $lines, $code)) {
                $content = $template->replace_entries_block($content, 'anno-list', $lines);
                    // [ lineno, rev, author, date, source ]
                $content = $template->replace_elements($content, $elements, false);
            } else {
                $template->error($elements, 1018, $code);  // ERR_ANNO_READ_FAIL
            }
        } else {
            $content = $template->replace_entries_block($content, 'anno-list', array());
            $content = $template->replace_elements($content, $elements, false);
        }
        break;
    case 'diff':
        // cmd=diff&repo=<repo_id>[&rev=head][&path=/][&rev1=]
        $elements['is-head'] = ($elements["rev"] == $elements['repo-rev']) ? 'yes' : 'no';
        $elements['file-size'] = $svn->filesize($elements["path"], $elements["rev"], $code);
        $elements["old-rev"] = array_key_exists("rev1", $_GET) ? trim($_GET["rev1"]) : $elements['path-last-rev'] - 1;
        if ($svn->diff($elements["path"], $elements["old-rev"], $diffs, $code, "", $elements["rev"])) {
            $elements['diff-count'] = count($diffs);
            foreach ($diffs as $file => $diff) {
                $diffs[$file]['path-id'] = md5($diff['path']);
                $diffs[$file]['text-count'] = count($diff['lines']);
                $diffs[$file]['diff-text'] = $template->replace_entries_block($template->get_page_content('diff-text'),
                    'diff-text', $diff['lines']);
                foreach ($diff['props'] as $prop => $value) {
                    $diff['props'][$prop]['lines-list'] = $template->replace_entries_block(
                        $template->get_page_content('lines-list'), 'lines-list', $value['lines']);
                }
                $diffs[$file]['props-count'] = count($diff['props']);
                $diffs[$file]['diff-prop'] = $template->replace_entries_block($template->get_page_content('diff-prop'),
                    'diff-prop', $diff['props']);
            }
            $content = $template->replace_entries_block($content, 'diffs-list', $diffs);
                // [ path, old, new, old-file, old-rev, new-file, new-rev, lines[], props[] ]
            $content = $template->replace_elements($content, $elements, false);
        } else {
            $template->error($elements, 1019, $code);  // ERR_DIFF_FAILED
        }
        break;
    case 'diff2':
        // cmd=diff&repo=<repo_id>[&rev=head][&path=/][&kind=][&rev1=]
        $elements['is-head'] = ($elements["rev"] == $elements['repo-rev']) ? 'yes' : 'no';
        $elements['file-size'] = $svn->filesize($elements["path"], $elements["rev"], $code);
        $elements["old-rev"] = array_key_exists("rev1", $_GET) ? trim($_GET["rev1"]) : $elements['path-last-rev'] - 1;
        if ($svn->diff2($elements["path"], $elements["old-rev"], $elements["kind"], $diffs, $code, "",
            $elements["rev"])) {
            $elements['diff-count'] = count($diffs);
            foreach ($diffs as $file => $diff) {
                $diffs[$file]['path-id'] = md5($diff['path']);
                $diffs[$file]['text-count'] = count($diff['lines']);
                $diffs[$file]['diff-text'] = $template->replace_entries_block($template->get_page_content('diff-text'),
                    'linenos0', $diff['lines-all']);
                $diffs[$file]['diff-text'] = $template->replace_entries_block($diffs[$file]['diff-text'],
                    'linenos1', $diff['lines-all']);
                $elements['source'] = "";
                foreach ($diff['lines-all'] as $line) { $elements['source'] .= $line['line']."\n"; }
                foreach ($diff['props'] as $prop => $value) {
                    $diff['props'][$prop]['lines-list'] = $template->replace_entries_block(
                        $template->get_page_content('lines-list'), 'lines-list', $value['lines']);
                }
                $diffs[$file]['props-count'] = count($diff['props']);
                $diffs[$file]['diff-prop'] = $template->replace_entries_block($template->get_page_content('diff-prop'),
                    'diff-prop', $diff['props']);
            }
            $content = $template->replace_entries_block($content, 'diffs-list', $diffs);
                // [ path, old, new, old-file, old-rev, new-file, new-rev, lines[], props[] ]
            $content = $template->replace_elements($content, $elements, false);
        } else {
            $template->error($elements, 1019, $code);  // ERR_DIFF_FAILED
        }
        break;
    case 'create':
        $folders = array();
        foreach ($config['repos'] as $name => $value) {
            $value = trim($value);
            if (substr($value, 0, 1) == '@') {
                $value = trim(substr($value, 1));
                $name = trim($name);
                if (substr($name, 0, 1) == "*") $name = trim(substr($name, 1));
                $values = explode(",", $value, 2);
                if (count($values) > 0) {
                    $folder['folder'] = trim($values[0]);
                    $folder['name'] = $name;
                    $folders[$name] = $folder;
                }
            }
        }
        if (isset($_GET['folder'])) {
            $elements['folder'] = trim($_GET['folder']);
            if (!isset($folders[$elements['folder']])) { $elements['folder'] = ''; }
        }
        $elements['realm'] = isset($_GET['realm']) ? trim($_GET['realm']) : '';
        $elements['format'] = isset($_GET['format']) ? trim(strtolower($_GET['format'])) : 'fsfs';
        $elements['error'] = '';

        $field_ok = true;
        if (!preg_match("/^[a-zA-Z0-9\._\-]{3,30}$/", $elements['repo'])) $field_ok = false;
        if (!preg_match("/.{3,60}/", $elements['realm']) || preg_match("/[`&%#?*^{}\\\[\]]/", $elements['realm']))
            $field_ok = false;
        if ($field_ok && ($elements['realm'] != '') && ($elements['repo'] != '') && ($elements['folder'] != '')) {
            if ($svn->create(append_slash($folders[$elements['folder']]['folder']).$elements['repo'],
                $elements['realm'], $elements['format'], $code)) {
                @header('Location: '.$elements['url'].'?lang='.$elements['lang'].'&cmd=repos&repo='.
                    ucfirst($elements['repo']));
                exit;
            } else {
                $elements['err'] = 1200;
                $elements['sub'] = $code;
                $elements['error'] =
                    $template->replace_elements($template->get_page_content('create-error'), $elements, true);
            }
        } else {
            if (isset($_GET['create'])) {
                $elements['error'] = $template->get_page_content('fields-error');
            }
        }
        $content = $template->replace_entries_block($content, 'repo-folders', $folders);
            // [ folder, name ]
        $content = $template->replace_elements($content, $elements, false);
        break;
    case 'edit':
        $content = $template->replace_elements($content, $elements, false);
        break;
    case 'users':
        break;
    case 'accesses':
        break;
    case 'ver':
    default:
        break;
}

echo $content;

?>
