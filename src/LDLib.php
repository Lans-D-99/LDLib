<?php
namespace LDLib;

define('LDLIB_VERSION','v0.4.0-RC2');
class LDLib {
    public static function init() {
        define('LDLIB_VERSION_WITH_SUFFIX',LDLIB_VERSION.($_SERVER['LDLIB_VERSION_SUFFIX']??''));
    }
}
?>