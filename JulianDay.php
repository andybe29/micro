<?php
/**
 * @author andy.bezbozhny <andy.bezbozhny@gmail.com>
 */
class JulianDay
{
    # Converts Unix timestamp to Julian Day
    # unixtojd
    public static function _utime2jday($value = null) {
        $value = (null === $value) ? time() : (int)$value;
        $value = getdate($value);

        return self::_gcal2jday($value['mon'], $value['mday'], $value['year']);
    }

    # Converts a Gregorian Calendar date to Julian Day
    # GregorianToJD
    public static function _gcal2jday($month, $day, $year) {
        if (checkdate($month, $day, $year)) {
            $a = floor((14 - $month) / 12);
            $y = floor($year + 4800 - $a);
            $m = floor($month + 12 * $a - 3);
            return $day + floor((153 * $m + 2) / 5) + $y * 365 + floor($y / 4) - floor($y / 100) + floor($y / 400) - 32045;
        }

        return false;
    }

    # Converts Julian Day to Unix timestamp
    # jdtounix
    public static function _jday2utime($value = null) {
        if (null === $value or !is_int($value)) return false;

        $l = $value + 68569;
        $n = self::_aint((4 * $l) / 146097);
        $l = $l - self::_aint((146097 * $n + 3) / 4);
        $i = self::_aint((4000 * ($l + 1)) / 1461001);
        $l = $l - self::_aint((1461 * $i) / 4) + 31;
        $j = self::_aint((80 * $l) / 2447);

        $day = $l - self::_aint((2447 * $j) / 80);

        $l = self::_aint($j / 11);

        $month = $j + 2 - (12 * $l);
        $year  = 100 * ($n - 49) + $i + $l;

        return mktime(0, 0, 0, $month, $day, $year);
    }

    public static function _aint($value) {
        return ($value > 0) ? floor($value) : ceil($value);
    }
}
