<?php

class utils_helper {
    /**
     * 获取毫秒级 Unix 时间戳
     * @return float
     */
    public static function getMillisecond () {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }
}
