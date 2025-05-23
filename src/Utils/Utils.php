<?php
/*****************************************************************************
 * This file is part of LDLib and subject to the Version 2.0 of the          *
 * Apache License, you may not use this file except in compliance            *
 * with the License. You may obtain a copy of the License at :               *
 *                                                                           *
 *                http://www.apache.org/licenses/LICENSE-2.0                 *
 *                                                                           *
 * Unless required by applicable law or agreed to in writing, software       *
 * distributed under the License is distributed on an "AS IS" BASIS,         *
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.  *
 * See the License for the specific language governing permissions and       *
 * limitations under the License.                                            *
 *                                                                           *
 *                Author: Lans.D <lans.d.99@protonmail.com>                  *
 *                                                                           *
 *****************************************************************************/
namespace LDLib\Utils;

use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Server\ServerContext;
use SoftCreatR\MimeDetector\MimeDetector;
use Swoole\Http\Request;

class Utils {
    public static function getRootLink(?string $sub = null) {
        if (isset($_SERVER['LD_USE_DYNAMIC_ROOT_LINK']) && (bool)$_SERVER['LD_USE_DYNAMIC_ROOT_LINK']) {
            $root =  $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
        } else {
            $root = $_SERVER['LD_LINK_ROOT'];
        }

        if ($sub != null) $root = str_replace('://',"://$sub.",$root);

        return $root;
    }

    private static array $c_hosts = [];
    public static function removeHostAddressPortPart(string $address) {
        return self::$c_hosts["$address"] ??= preg_replace('/:\d+/','',$address,1);
    }

    public static function getTempFolderPath() {
        return realpath(__DIR__.'/../../tmp');
    }

    public static function getMimetypeFromName(string $name) {
        $mimeTypes = array(
            "323"       => "text/h323",
            "acx"       => "application/internet-property-stream",
            "ai"        => "application/postscript",
            "aif"       => "audio/x-aiff",
            "aifc"      => "audio/x-aiff",
            "aiff"      => "audio/x-aiff",
            "asf"       => "video/x-ms-asf",
            "asr"       => "video/x-ms-asf",
            "asx"       => "video/x-ms-asf",
            "au"        => "audio/basic",
            "avi"       => "video/x-msvideo",
            "axs"       => "application/olescript",
            "bas"       => "text/plain",
            "bcpio"     => "application/x-bcpio",
            "bin"       => "application/octet-stream",
            "bmp"       => "image/bmp",
            "c"         => "text/plain",
            "cat"       => "application/vnd.ms-pkiseccat",
            "cdf"       => "application/x-cdf",
            "cer"       => "application/x-x509-ca-cert",
            "class"     => "application/octet-stream",
            "clp"       => "application/x-msclip",
            "cmx"       => "image/x-cmx",
            "cod"       => "image/cis-cod",
            "cpio"      => "application/x-cpio",
            "crd"       => "application/x-mscardfile",
            "crl"       => "application/pkix-crl",
            "crt"       => "application/x-x509-ca-cert",
            "csh"       => "application/x-csh",
            "css"       => "text/css",
            "dcr"       => "application/x-director",
            "der"       => "application/x-x509-ca-cert",
            "dir"       => "application/x-director",
            "dll"       => "application/x-msdownload",
            "dms"       => "application/octet-stream",
            "doc"       => "application/msword",
            "dot"       => "application/msword",
            "dvi"       => "application/x-dvi",
            "dxr"       => "application/x-director",
            "eps"       => "application/postscript",
            "etx"       => "text/x-setext",
            "evy"       => "application/envoy",
            "exe"       => "application/octet-stream",
            "fif"       => "application/fractals",
            "flr"       => "x-world/x-vrml",
            "gif"       => "image/gif",
            "gtar"      => "application/x-gtar",
            "gz"        => "application/x-gzip",
            "h"         => "text/plain",
            "hdf"       => "application/x-hdf",
            "hlp"       => "application/winhlp",
            "hqx"       => "application/mac-binhex40",
            "hta"       => "application/hta",
            "htc"       => "text/x-component",
            "htm"       => "text/html",
            "html"      => "text/html",
            "htt"       => "text/webviewhtml",
            "ico"       => "image/x-icon",
            "ief"       => "image/ief",
            "iii"       => "application/x-iphone",
            "ins"       => "application/x-internet-signup",
            "isp"       => "application/x-internet-signup",
            "jfif"      => "image/pipeg",
            "jpe"       => "image/jpeg",
            "jpeg"      => "image/jpeg",
            "jpg"       => "image/jpeg",
            "js"        => "application/x-javascript",
            "latex"     => "application/x-latex",
            "lha"       => "application/octet-stream",
            "lsf"       => "video/x-la-asf",
            "lsx"       => "video/x-la-asf",
            "lzh"       => "application/octet-stream",
            "m13"       => "application/x-msmediaview",
            "m14"       => "application/x-msmediaview",
            "m3u"       => "audio/x-mpegurl",
            "man"       => "application/x-troff-man",
            "mdb"       => "application/x-msaccess",
            "me"        => "application/x-troff-me",
            "mht"       => "message/rfc822",
            "mhtml"     => "message/rfc822",
            "mid"       => "audio/mid",
            "mny"       => "application/x-msmoney",
            "mov"       => "video/quicktime",
            "movie"     => "video/x-sgi-movie",
            "mp2"       => "video/mpeg",
            "mp3"       => "audio/mpeg",
            "mpa"       => "video/mpeg",
            "mpe"       => "video/mpeg",
            "mpeg"      => "video/mpeg",
            "mpg"       => "video/mpeg",
            "mpp"       => "application/vnd.ms-project",
            "mpv2"      => "video/mpeg",
            "ms"        => "application/x-troff-ms",
            "mvb"       => "application/x-msmediaview",
            "nws"       => "message/rfc822",
            "oda"       => "application/oda",
            "p10"       => "application/pkcs10",
            "p12"       => "application/x-pkcs12",
            "p7b"       => "application/x-pkcs7-certificates",
            "p7c"       => "application/x-pkcs7-mime",
            "p7m"       => "application/x-pkcs7-mime",
            "p7r"       => "application/x-pkcs7-certreqresp",
            "p7s"       => "application/x-pkcs7-signature",
            "pbm"       => "image/x-portable-bitmap",
            "pdf"       => "application/pdf",
            "pfx"       => "application/x-pkcs12",
            "pgm"       => "image/x-portable-graymap",
            "pko"       => "application/ynd.ms-pkipko",
            "pma"       => "application/x-perfmon",
            "pmc"       => "application/x-perfmon",
            "pml"       => "application/x-perfmon",
            "pmr"       => "application/x-perfmon",
            "pmw"       => "application/x-perfmon",
            "pnm"       => "image/x-portable-anymap",
            "pot"       => "application/vnd.ms-powerpoint",
            "ppm"       => "image/x-portable-pixmap",
            "pps"       => "application/vnd.ms-powerpoint",
            "ppt"       => "application/vnd.ms-powerpoint",
            "prf"       => "application/pics-rules",
            "ps"        => "application/postscript",
            "pub"       => "application/x-mspublisher",
            "qt"        => "video/quicktime",
            "ra"        => "audio/x-pn-realaudio",
            "ram"       => "audio/x-pn-realaudio",
            "ras"       => "image/x-cmu-raster",
            "rgb"       => "image/x-rgb",
            "rmi"       => "audio/mid",
            "roff"      => "application/x-troff",
            "rtf"       => "application/rtf",
            "rtx"       => "text/richtext",
            "scd"       => "application/x-msschedule",
            "sct"       => "text/scriptlet",
            "setpay"    => "application/set-payment-initiation",
            "setreg"    => "application/set-registration-initiation",
            "sh"        => "application/x-sh",
            "shar"      => "application/x-shar",
            "sit"       => "application/x-stuffit",
            "snd"       => "audio/basic",
            "spc"       => "application/x-pkcs7-certificates",
            "spl"       => "application/futuresplash",
            "src"       => "application/x-wais-source",
            "sst"       => "application/vnd.ms-pkicertstore",
            "stl"       => "application/vnd.ms-pkistl",
            "stm"       => "text/html",
            "svg"       => "image/svg+xml",
            "sv4cpio"   => "application/x-sv4cpio",
            "sv4crc"    => "application/x-sv4crc",
            "t"         => "application/x-troff",
            "tar"       => "application/x-tar",
            "tcl"       => "application/x-tcl",
            "tex"       => "application/x-tex",
            "texi"      => "application/x-texinfo",
            "texinfo"   => "application/x-texinfo",
            "tgz"       => "application/x-compressed",
            "tif"       => "image/tiff",
            "tiff"      => "image/tiff",
            "tr"        => "application/x-troff",
            "trm"       => "application/x-msterminal",
            "tsv"       => "text/tab-separated-values",
            "txt"       => "text/plain",
            "uls"       => "text/iuls",
            "ustar"     => "application/x-ustar",
            "vcf"       => "text/x-vcard",
            "vrml"      => "x-world/x-vrml",
            "wav"       => "audio/x-wav",
            "wcm"       => "application/vnd.ms-works",
            "wdb"       => "application/vnd.ms-works",
            "wks"       => "application/vnd.ms-works",
            "wmf"       => "application/x-msmetafile",
            "wps"       => "application/vnd.ms-works",
            "wri"       => "application/x-mswrite",
            "wrl"       => "x-world/x-vrml",
            "wrz"       => "x-world/x-vrml",
            "xaf"       => "x-world/x-vrml",
            "xbm"       => "image/x-xbitmap",
            "xla"       => "application/vnd.ms-excel",
            "xlc"       => "application/vnd.ms-excel",
            "xlm"       => "application/vnd.ms-excel",
            "xls"       => "application/vnd.ms-excel",
            "xlsx"      => "vnd.ms-excel",
            "xlt"       => "application/vnd.ms-excel",
            "xlw"       => "application/vnd.ms-excel",
            "xof"       => "x-world/x-vrml",
            "xpm"       => "image/x-xpixmap",
            "xwd"       => "image/x-xwindowdump",
            "z"         => "application/x-compress",
            "zip"       => "application/zip"
        );
        if (preg_match('/\.(.*)$/',$name,$m) == 0) return null;
        return $mimeTypes[$m[1]]??null;
    }

    public static function getMimeType(array $file):string {
        $mimeType = mime_content_type($file['tmp_name']);
        if ($mimeType !== 'text/plain') {
            try { $mimeType2 = (new MimeDetector($file['tmp_name']))->getMimeType(); }
            catch (\Exception $e) {
                $mimeType2 = null;
                Logger::log(LogLevel::WARN,'Mime Detector',$e->getMessage());
            }
            if (!empty($mimeType2)) $mimeType = $mimeType2;
        }
        return $mimeType;
    }

    public static function getEnumValue(string $enum, mixed $value) {
        $cases = $enum::cases();
        $index = array_search($value, array_column($cases,'name'));
        if ($index !== false) return $cases[$index];
        return null;
    }

    public static function loremipsum(int $length = 3000) {
        $lorem = "malesuada bibendum arcu vitae elementum curabitur vitae nunc sed velit dignissim sodales ut eu sem integer vitae justo eget magna fermentum iaculis eu non diam phasellus vestibulum lorem sed risus ultricies tristique nulla aliquet enim tortor at auctor urna nunc id cursus metus aliquam eleifend mi in nulla posuere sollicitudin aliquam ultrices sagittis orci a scelerisque purus semper eget duis at tellus at urna condimentum mattis pellentesque id nibh tortor id aliquet lectus proin nibh nisl condimentum id venenatis a condimentum vitae sapien pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas sed tempus urna et pharetra pharetra massa massa ultricies mi quis hendrerit dolor magna eget est lorem ipsum dolor sit amet consectetur adipiscing elit pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas integer eget aliquet nibh praesent tristique magna sit amet purus gravida quis blandit turpis cursus in hac habitasse platea dictumst quisque sagittis purus sit amet volutpat consequat mauris nunc congue nisi vitae suscipit tellus mauris a diam maecenas sed enim ut sem viverra aliquet eget sit amet tellus cras adipiscing enim eu turpis egestas pretium aenean pharetra magna ac placerat vestibulum lectus mauris ultrices eros in cursus turpis massa tincidunt dui ut ornare lectus sit amet est placerat in egestas erat imperdiet sed euismod nisi porta lorem mollis aliquam ut porttitor leo a diam sollicitudin tempor id eu nisl nunc mi ipsum faucibus vitae aliquet nec ullamcorper sit amet risus nullam eget felis eget nunc lobortis mattis aliquam faucibus purus in massa tempor nec feugiat nisl pretium fusce id velit ut tortor pretium viverra suspendisse potenti nullam ac tortor vitae purus faucibus ornare suspendisse sed nisi lacus sed viverra tellus in hac habitasse platea dictumst vestibulum rhoncus est pellentesque elit ullamcorper dignissim cras tincidunt lobortis feugiat vivamus at augue eget arcu dictum varius duis at consectetur lorem donec massa sapien faucibus et molestie ac feugiat sed lectus vestibulum mattis ullamcorper velit sed ullamcorper morbi tincidunt ornare massa eget egestas purus viverra accumsan in nisl nisi scelerisque eu ultrices vitae auctor eu augue ut lectus arcu bibendum at varius vel pharetra vel turpis nunc eget lorem dolor sed viverra ipsum nunc aliquet bibendum enim facilisis gravida neque convallis a cras semper auctor neque vitae tempus quam pellentesque nec nam aliquam sem et tortor consequat id porta nibh venenatis cras sed felis eget velit aliquet sagittis id consectetur purus ut faucibus pulvinar elementum integer enim neque volutpat ac tincidunt vitae semper quis lectus nulla at volutpat diam ut venenatis tellus in metus vulputate eu scelerisque felis imperdiet proin fermentum leo vel orci porta non pulvinar neque laoreet suspendisse interdum consectetur libero id faucibus nisl tincidunt eget nullam non nisi est sit amet facilisis magna etiam tempor orci eu lobortis elementum nibh tellus molestie nunc non blandit massa enim nec dui nunc mattis enim ut tellus elementum sagittis vitae et leo duis ut diam quam nulla porttitor massa id neque aliquam vestibulum morbi blandit cursus risus at ultrices mi tempus imperdiet nulla malesuada pellentesque elit eget gravida cum sociis natoque penatibus et magnis dis parturient montes nascetur ridiculus mus mauris vitae ultricies leo integer malesuada nunc vel risus commodo viverra maecenas accumsan lacus vel facilisis volutpat est velit egestas dui id ornare arcu odio ut sem nulla pharetra diam sit amet nisl suscipit adipiscing bibendum est ultricies integer quis auctor elit sed vulputate mi sit amet mauris commodo quis imperdiet massa tincidunt nunc pulvinar sapien et ligula ullamcorper malesuada proin libero nunc consequat interdum varius sit amet mattis vulputate enim nulla aliquet porttitor lacus luctus accumsan tortor posuere ac ut consequat semper viverra nam libero justo laoreet sit amet cursus sit amet dictum sit amet justo donec enim diam vulputate ut pharetra sit amet aliquam id diam maecenas ultricies mi eget mauris pharetra et ultrices neque ornare aenean euismod elementum nisi quis eleifend quam adipiscing vitae proin sagittis nisl rhoncus mattis rhoncus urna neque viverra justo nec ultrices dui sapien eget mi proin sed libero enim sed faucibus turpis in eu mi bibendum neque egestas congue quisque egestas diam in arcu cursus euismod quis viverra nibh cras pulvinar mattis nunc sed blandit libero volutpat sed cras ornare arcu dui vivamus arcu felis bibendum ut tristique et egestas quis ipsum suspendisse ultrices gravida dictum fusce ut placerat orci nulla pellentesque dignissim enim sit amet venenatis urna cursus eget nunc scelerisque viverra mauris in aliquam sem fringilla ut morbi tincidunt augue interdum velit euismod in pellentesque massa placerat duis ultricies lacus sed turpis tincidunt id aliquet risus feugiat in ante metus dictum at tempor commodo ullamcorper a lacus vestibulum sed arcu non odio euismod lacinia at quis risus sed vulputate odio ut enim blandit volutpat maecenas volutpat blandit aliquam etiam erat velit scelerisque in dictum non consectetur a erat nam at lectus urna duis convallis convallis tellus id interdum velit laoreet id donec ultrices tincidunt arcu non sodales neque sodales ut etiam sit amet nisl purus in mollis nunc sed id semper risus in hendrerit gravida rutrum quisque non tellus orci ac auctor augue mauris augue neque gravida in fermentum et sollicitudin ac orci phasellus egestas tellus rutrum tellus pellentesque eu tincidunt tortor aliquam nulla facilisi cras fermentum odio eu feugiat pretium nibh ipsum consequat nisl vel pretium lectus quam id leo in vitae turpis massa sed elementum tempus egestas sed sed risus pretium quam vulputate dignissim suspendisse in est ante in nibh mauris cursus mattis molestie a iaculis at erat pellentesque adipiscing commodo elit at imperdiet dui accumsan sit amet nulla facilisi morbi tempus iaculis urna id volutpat lacus laoreet non curabitur gravida arcu ac tortor dignissim convallis aenean et tortor at risus viverra adipiscing at in tellus integer feugiat scelerisque varius morbi enim nunc faucibus a pellentesque sit amet porttitor eget dolor morbi non arcu risus quis varius quam quisque id diam vel quam elementum pulvinar etiam non quam lacus suspendisse faucibus interdum posuere lorem ipsum dolor sit amet consectetur adipiscing elit duis tristique sollicitudin nibh sit amet commodo";
        $s = "";
        while (strlen($s) < $length) $s .= $lorem;
        return substr($s, 0, $length);
    }

    public static function getRealRemoteAddress(Request $request):string {
        return isset($request->header['x-ld-cloudfront']) && in_array($request->header['x-ld-cloudfront'],explode(',',$_SERVER['LD_AWS_TRUSTED_CLOUDFRONTS']??'')) ?
            $request->header['x-forwarded-for'] : $request->server['remote_addr'];
    }

    public static int $fileN = 0;
    public static function getUniqueFileName(string $prefix='file', string $suffix='') {
        if (self::$fileN == 0) self::$fileN = random_int(1,30000);
        if (self::$fileN > 100000) self::$fileN = random_int(1,30000);
        self::$fileN++;
        return "$prefix-".microtime(true).'-'.self::$fileN.$suffix;
    }

    public static function getTempFilePath(string $name='file') {
        return ServerContext::$tempPath.'/'.self::getUniqueFileName($name);
    }
}
?>