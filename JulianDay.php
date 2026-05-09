<?php
/**
 * @author andy.bezbozhny <andy.bezbozhny@gmail.com>
 */
class JulianDay
{
    /**
     * Перевод метки времени Unix в юлианский день (unixtojd)
     * @param int $value метка времени Unix для преобразования либо null (текущий момент)
     * @return mixed число дней в юлианском летоисчислении или false, если возникла ошибка
     */
    public static function _utime2jday($value = null)
    {
        $value = (null === $value) ? time() : (int)$value;
        $value = getdate($value);

        return self::_gcal2jday($value['mon'], $value['mday'], $value['year']);
    }

    /**
     * Преобразование даты по григорианскому календарю в количество дней в юлианском летоисчислении (gregoriantojd)
     * @param int $month месяц
     * @param int $day   день
     * @param int $year  год
     * @return mixed число дней в юлианском летоисчислении или false, если возникла ошибка
     */
    public static function _gcal2jday(int $month, int $day, int $year)
    {
        if (!checkdate($month, $day, $year)) return false;

        $a = floor((14 - $month) / 12);
        $y = floor($year + 4800 - $a);
        $m = floor($month + 12 * $a - 3);

        return $day + floor((153 * $m + 2) / 5) + $y * 365 + floor($y / 4) - floor($y / 100) + floor($y / 400) - 32045;
    }

    /**
     * Перевод числа дней в юлианском летоисчислении в метку времени Unix (jdtounix)
     * @param int $value номер дня в юлианском летоисчислении
     * @return int метка времени Unix на момент начала (полночь) юлианского дня
     */
    public static function _jday2utime(int $value)
    {
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

    public static function _aint(int $value)
    {
        return ($value > 0) ? floor($value) : ceil($value);
    }
}
