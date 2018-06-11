<?php

$url = $_REQUEST['url'] ?? '';
$cache_grab = new CacheGrab();
$result = $cache_grab->get_url($url);
$last_modified_header = 'Last-Modified: ' . @$_SERVER['HTTP_IF_MODIFIED_SINCE'];
$etag_header = 'ETag: ' . @$_SERVER['HTTP_IF_NONE_MATCH'];
$headers = $result['headers'] ?? [];

foreach ($headers as $header) {
    header($header);
}
foreach ($headers as $header) {
    if ($header === $etag_header || $header === $last_modified_header) {
        header("HTTP/1.1 304 Not Modified");
        return;
    }
}

echo $result['content'];

class CacheGrab {
    public function hello() {
        $d = dir(self::get_temp_path());
        $contents = [
            'Cache content:'
        ];
        while (($file = $d->read()) !== false){
            if (strrpos($file, 'cache_grab_') === 0) {
                $split = explode('cache_grab_', $file);
                $url = urldecode($split[1]);
                $contents[] = "<a href='/?url={$split[1]}'>$url</a> [<a href='$url'>original</a>]";
            }
        }
        $d->close();
        return [
            'content' => implode("<br>\n", $contents),
        ];
    }

    public function get_url(string $url) {
        if (empty($url)) {
            return $this->hello();
        }
        $result = $this->get_from_cache($url);
        if (!$result) {
            $seconds_to_cache = 60*60*24*2;
            $content = file_get_contents($url);
            error_log("Fetching: $url");
            $headers = [];

            $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
            $headers['Expires'] = "Expires: $ts";
            $headers['Pragma'] = "Pragma: cache";
            $headers['Cache-Control'] = "Cache-Control: max-age=$seconds_to_cache";

            foreach ($http_response_header as $header) {
                if (strpos($header, 'Content-Type: ') === 0
                    || strpos($header, 'ETag: ') === 0
                    || strpos($header, 'Last-Modified: ') === 0)
                {
                    list($key) = explode(': ',$header);
                    $headers[$key] = $header;
                }
            }

            if (!isset($headers['ETag'])) {
                $headers['ETag'] = 'ETag: ' . md5($content);
            }
            if (!isset($header['Last-Modified'])) {
                $headers['Last-Modified'] = 'Last-Modified: ' . gmdate("D, d M Y H:i:s", time()) . " GMT";
            }
            if (!isset($header['Content-Type'])) {
                $url_part = explode('?', $url)[0];
                $url_split = explode('.', $url_part);
                $ext = array_pop($url_split);
                $mime_type = self::EXT_TO_MIMETYPE[$ext] ?? null;
                if ($mime_type) {
                    $headers['Content-Type'] = 'Content-Type: ' . $mime_type;
                }
            }

            $headers['Access-Control-Allow-Origin'] = 'Access-Control-Allow-Origin: *';

            $result = [
                'content' => $content,
                'headers' => array_values($headers),
            ];
            $this->set($url, $result, $seconds_to_cache);
        }
        return $result;
    }

    private function get_from_cache(string $key) {
        $filename = urlencode($key);
        $tmp_path = self::get_temp_path();
        /** @noinspection PhpIncludeInspection */
        @include "$tmp_path/cache_grab_$filename";
        if(isset($expire) && $expire < time()) {
            return false;
        }
        return isset($val) ? $val : false;
    }

    private static function get_temp_path() {
        return realpath(__DIR__."/../tmp");
    }

    public function set(string $key, $val, int $expire) {
        $filename = urlencode($key);
        $tmp_path = self::get_temp_path();

        if ($val) {
            $val = var_export($val, true);
            $expire = var_export(time() + $expire, true);
            // HHVM fails at __set_state, so just use object cast for now
            $val = str_replace('stdClass::__set_state', '(object)', $val);
            // Write to temp file first to ensure atomicity
            $tmp = "$tmp_path/$filename." . uniqid('', true) . '.tmp';
            file_put_contents($tmp, '<?php $val = ' . $val . '; $expire=' . $expire . ';', LOCK_EX);
            rename($tmp, "$tmp_path/cache_grab_$filename");
        } else {
            unlink("$tmp_path/cache_grab_$filename");
        }
    }

    const EXT_TO_MIMETYPE = [
        'ai'      => 'application/postscript',
        'aif'     => 'audio/x-aiff',
        'aifc'    => 'audio/x-aiff',
        'aiff'    => 'audio/x-aiff',
        'asc'     => 'text/plain',
        'atom'    => 'application/atom+xml',
        'au'      => 'audio/basic',
        'avi'     => 'video/x-msvideo',
        'bcpio'   => 'application/x-bcpio',
        'bin'     => 'application/octet-stream',
        'bmp'     => 'image/bmp',
        'cdf'     => 'application/x-netcdf',
        'cgm'     => 'image/cgm',
        'class'   => 'application/octet-stream',
        'cpio'    => 'application/x-cpio',
        'cpt'     => 'application/mac-compactpro',
        'csh'     => 'application/x-csh',
        'css'     => 'text/css',
        'csv'     => 'text/csv',
        'dcr'     => 'application/x-director',
        'dir'     => 'application/x-director',
        'djv'     => 'image/vnd.djvu',
        'djvu'    => 'image/vnd.djvu',
        'dll'     => 'application/octet-stream',
        'dmg'     => 'application/octet-stream',
        'dms'     => 'application/octet-stream',
        'doc'     => 'application/msword',
        'dtd'     => 'application/xml-dtd',
        'dvi'     => 'application/x-dvi',
        'dxr'     => 'application/x-director',
        'eps'     => 'application/postscript',
        'etx'     => 'text/x-setext',
        'exe'     => 'application/octet-stream',
        'ez'      => 'application/andrew-inset',
        'gif'     => 'image/gif',
        'gram'    => 'application/srgs',
        'grxml'   => 'application/srgs+xml',
        'gtar'    => 'application/x-gtar',
        'hdf'     => 'application/x-hdf',
        'hqx'     => 'application/mac-binhex40',
        'htm'     => 'text/html',
        'html'    => 'text/html',
        'ice'     => 'x-conference/x-cooltalk',
        'ico'     => 'image/x-icon',
        'ics'     => 'text/calendar',
        'ief'     => 'image/ief',
        'ifb'     => 'text/calendar',
        'iges'    => 'model/iges',
        'igs'     => 'model/iges',
        'jpe'     => 'image/jpeg',
        'jpeg'    => 'image/jpeg',
        'jpg'     => 'image/jpeg',
        'js'      => 'application/x-javascript',
        'json'    => 'application/json',
        'kar'     => 'audio/midi',
        'latex'   => 'application/x-latex',
        'lha'     => 'application/octet-stream',
        'lzh'     => 'application/octet-stream',
        'm3u'     => 'audio/x-mpegurl',
        'man'     => 'application/x-troff-man',
        'mathml'  => 'application/mathml+xml',
        'me'      => 'application/x-troff-me',
        'mesh'    => 'model/mesh',
        'mid'     => 'audio/midi',
        'midi'    => 'audio/midi',
        'mif'     => 'application/vnd.mif',
        'mov'     => 'video/quicktime',
        'movie'   => 'video/x-sgi-movie',
        'mp2'     => 'audio/mpeg',
        'mp3'     => 'audio/mpeg',
        'mpe'     => 'video/mpeg',
        'mpeg'    => 'video/mpeg',
        'mpg'     => 'video/mpeg',
        'mpga'    => 'audio/mpeg',
        'ms'      => 'application/x-troff-ms',
        'msh'     => 'model/mesh',
        'mxu'     => 'video/vnd.mpegurl',
        'nc'      => 'application/x-netcdf',
        'oda'     => 'application/oda',
        'ogg'     => 'application/ogg',
        'pbm'     => 'image/x-portable-bitmap',
        'pdb'     => 'chemical/x-pdb',
        'pdf'     => 'application/pdf',
        'pgm'     => 'image/x-portable-graymap',
        'pgn'     => 'application/x-chess-pgn',
        'png'     => 'image/png',
        'pnm'     => 'image/x-portable-anymap',
        'ppm'     => 'image/x-portable-pixmap',
        'ppt'     => 'application/vnd.ms-powerpoint',
        'ps'      => 'application/postscript',
        'qt'      => 'video/quicktime',
        'ra'      => 'audio/x-pn-realaudio',
        'ram'     => 'audio/x-pn-realaudio',
        'ras'     => 'image/x-cmu-raster',
        'rdf'     => 'application/rdf+xml',
        'rgb'     => 'image/x-rgb',
        'rm'      => 'application/vnd.rn-realmedia',
        'roff'    => 'application/x-troff',
        'rss'     => 'application/rss+xml',
        'rtf'     => 'text/rtf',
        'rtx'     => 'text/richtext',
        'sgm'     => 'text/sgml',
        'sgml'    => 'text/sgml',
        'sh'      => 'application/x-sh',
        'shar'    => 'application/x-shar',
        'silo'    => 'model/mesh',
        'sit'     => 'application/x-stuffit',
        'skd'     => 'application/x-koan',
        'skm'     => 'application/x-koan',
        'skp'     => 'application/x-koan',
        'skt'     => 'application/x-koan',
        'smi'     => 'application/smil',
        'smil'    => 'application/smil',
        'snd'     => 'audio/basic',
        'so'      => 'application/octet-stream',
        'spl'     => 'application/x-futuresplash',
        'src'     => 'application/x-wais-source',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc'  => 'application/x-sv4crc',
        'svg'     => 'image/svg+xml',
        'svgz'    => 'image/svg+xml',
        'swf'     => 'application/x-shockwave-flash',
        't'       => 'application/x-troff',
        'tar'     => 'application/x-tar',
        'tcl'     => 'application/x-tcl',
        'tex'     => 'application/x-tex',
        'texi'    => 'application/x-texinfo',
        'texinfo' => 'application/x-texinfo',
        'tif'     => 'image/tiff',
        'tiff'    => 'image/tiff',
        'tr'      => 'application/x-troff',
        'tsv'     => 'text/tab-separated-values',
        'txt'     => 'text/plain',
        'ustar'   => 'application/x-ustar',
        'vcd'     => 'application/x-cdlink',
        'vrml'    => 'model/vrml',
        'vxml'    => 'application/voicexml+xml',
        'wav'     => 'audio/x-wav',
        'wbmp'    => 'image/vnd.wap.wbmp',
        'wbxml'   => 'application/vnd.wap.wbxml',
        'wml'     => 'text/vnd.wap.wml',
        'wmlc'    => 'application/vnd.wap.wmlc',
        'wmls'    => 'text/vnd.wap.wmlscript',
        'wmlsc'   => 'application/vnd.wap.wmlscriptc',
        'wrl'     => 'model/vrml',
        'xbm'     => 'image/x-xbitmap',
        'xht'     => 'application/xhtml+xml',
        'xhtml'   => 'application/xhtml+xml',
        'xls'     => 'application/vnd.ms-excel',
        'xml'     => 'application/xml',
        'xpm'     => 'image/x-xpixmap',
        'xsl'     => 'application/xml',
        'xslt'    => 'application/xslt+xml',
        'xul'     => 'application/vnd.mozilla.xul+xml',
        'xwd'     => 'image/x-xwindowdump',
        'xyz'     => 'chemical/x-xyz',
        'zip'     => 'application/zip',
    ];
}