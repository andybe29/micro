<?php
/**
 * @author andy.bezbozhny <andy.bezbozhny@gmail.com>
 */
class simpleMySQLi
{
    /**
     * @var string  $str строка запроса
     * @var boolean $log логировать запросы?
     */
    public $str;
    public $log = false;

    /**
     * @var mixed    $db      конфиг подключения к БД
     * @var string   $err     сообщение об ошибке
     * @var int      $id      последнее добавленное значение auto_increment
     * @var string   $path    каталог для логов
     * @var int      $rows    кол-во строк при запросах select/update/delete
     * @var resource $res     результат выполнения запроса
     */
    private $db;
    private $err;
    private $id;
    private $path;
    private $rows;
    private $res;
    private $sql;

    /**
     *  $db => [
     *      'host'     => default_host
     *      'username' => default_user
     *      'passwd'   => default_pw
     *      'dbname'   => default_dbase
     *      'port'     => default_port
     *      'socket'   => default_socket
     *  ]
     */
    public function __construct($db, $logs = null)
    {
        $default = [
            'host'      => null,
            'username'  => null,
            'passwd'    => null,
            'dbname'    => null,
            'port'      => null,
            'socket'    => null
        ];

        $this->db   = array_merge($default, $db);
        $this->path = $logs;

        return $this->_connect();
    }

    public function __destruct()
    {
        if ($this->sql) {
            $this->sql->close();
        }
    }

    public function __get($key)
    {
        return in_array($key, ['err', 'id', 'path', 'rows']) ? $this->$key : null;
    }

    public function __set($key, $value)
    {
        if ($key == 'err' and empty($value)) $this->err = null;
    }

    public function __debugInfo()
    {
        return [
            'str'  => $this->str,
            'err'  => $this->err,
            'id'   => $this->id,
            'rows' => $this->rows
        ];
    }

    /**
     * выборка всех строк после select
     * @param boolean $num флаг гладкого или ассоциированного массива
     * @return array массив данных
     */
    public function all($num = false)
    {
        $data = $this->res->fetch_all($num ? MYSQLI_NUM : MYSQLI_ASSOC);
        $data = array_map(['self', '_strip'], $data);

        return $data;
    }

    /**
     * результат запроса - в ассоциированный массив
     * @return array массив данных
     */
    public function assoc()
    {
        $data = $this->res->fetch_assoc();
        return self::_strip($data);
    }

    /**
     * экранирование строки
     * @param string $str эранируемая строка
     * @return string экранированная строка
     */
    public function escape($str)
    {
        return $this->sql->real_escape_string($str);
    }

    /**
     * выполнение запроса
     * @param boolean $log логировать данный запрос?
     * @return boolean результат выполнения запроса
     */
    public function execute($log = false)
    {
        $this->err  = null;
        $this->rows = $this->id = 0;

        $this->str = is_array($this->str) ? implode(' ', $this->str) : $this->str;
        $this->str = trim($this->str);

        $this->res = $this->sql->query($this->str);

        if ($this->sql->errno) {

            $this->err = $this->sql->errno . ': ' . $this->sql->error;

        } else if (($pos = mb_stripos($this->str, 'DELETE')) === 0) {

            $this->rows = $this->rows();

        } else if (($pos = mb_stripos($this->str, 'INSERT')) === 0) {

            $this->id   = $this->last();
            $this->rows = $this->rows();

        } else if (($pos = mb_stripos($this->str, 'REPLACE')) === 0) {

            $this->rows = $this->rows();

        } else if (($pos = mb_stripos($this->str, 'SELECT')) === 0) {

            $this->rows = $this->res->num_rows;

        } else if (($pos = mb_stripos($this->str, 'UPDATE')) === 0) {

            $this->id   = $this->last();
            $this->rows = $this->rows();

        }

        if ($this->log or $log or $this->err) {
            $this->_write2log();
        }

        return empty($this->err);
    }

    /**
     * результат запроса в гладкий массив
     * @return array массив данных
     */
    public function fetch()
    {
        $data = $this->res->fetch_array(MYSQLI_NUM);
        return self::_strip($data);
    }

    /**
     * высвобождение результата запроса
     */
    public function free()
    {
        $this->res->close();
    }

    /**
     * кол-во изменённных строк (после DELETE/INSERT/REPLACE/UPDATE)
     * @return int кол-во строк
     */
    public function rows()
    {
        return $this->sql->affected_rows;
    }

    /**
     * добавление в таблицу
     * @param string $table название таблицы
     * @param array  $data  массив данных, где ключ - названия поля
     * @return boolean результат выполнения операции
     */
    public function insert($table = '', $data = [])
    {
        if (!$data or !$table) return false;

        $this->str   = [];
        $this->str[] = 'INSERT INTO ' . $table;
        $this->str[] = '(' . implode(', ', array_keys($data)) . ')';
        $this->str[] = 'VALUES (' . implode(', ', $data) . ')';

        return $this->execute() ? ($this->id ? $this->id : true) : false;
    }

    /**
     * последние значение auto_increment
     * @return int значение insert_id
     */
    public function last()
    {
        return $this->sql->insert_id;
    }

    /**
     * проверка соединения
     */
    public function ping()
    {
        return ($this->sql and $this->sql->ping()) ? true : $this->_connect();
    }

    /**
     * добавление с заменой в таблицу
     * @param string $table название таблицы
     * @param array  $data  массив данных, где ключ - названия поля
     * @return boolean результат выполнения операции
     */
    public function replace($table = '', $data = [])
    {
        if (!$data or !$table) return false;

        $this->str   = [];
        $this->str[] = 'REPLACE INTO ' . $table;
        $this->str[] = '(' . implode(', ', array_keys($data)) . ')';
        $this->str[] = 'VALUES (' . implode(', ', $data) . ')';

        return $this->execute() ? $this->rows : false;
    }

    /**
     * возврат первого элемента массива результата
     * @return mixed результат
     */
    public function single()
    {
        $data = $this->fetch();

        return array_shift($data);
    }

    /**
     * обновление строки
     * @param string $table название таблицы
     * @param array  $data  массив данных, где ключ - названия поля
     * @param array  $where массив условий
     * @param string $sep   оператор условия
     * @return int|boolean кол-во изменённых строк либо false в случае ошибки
     */
    public function update($table = '', $data = [], $where = [], $sep = 'AND')
    {
        if (!$data or !$table) return false;

        $this->str  = 'UPDATE ' . $table . ' SET ' . implode(', ', self::_assoc2plain($data));
        $this->str .= ($where) ? (' WHERE ' . implode(' ' . $sep . ' ', $where)) : '';

        return $this->execute() ? $this->rows : false;
    }

    /**
     * заключение строки в двойные кавычки
     * @param string $str исходная строка
     * @return string результат
     */
    public function varchar($str = '')
    {
        return '"' . $this->escape($str) . '"';
    }

    /**
     * подключение к БД
     */
    private function _connect()
    {
        $this->str = null;

        $this->sql = new mysqli(
            $this->db['host'],
            $this->db['username'],
            $this->db['passwd'],
            $this->db['dbname'],
            $this->db['port'],
            $this->db['socket']
        );

        if ($this->sql->connect_error) {
            $this->err = $this->sql->connect_errno . ': ' . $this->sql->connect_error;
            $this->_write2log();

            return false;
        } else {
            $this->sql->set_charset('utf8');

            return true;
        }

    }

    /**
     * запись в лог
     */
    private function _write2log()
    {
        if ($this->path and file_exists($this->path)) {
            $log   = [PHP_EOL];
            $log[] = date('H:i:s');
            if ($this->str) $log[] = $this->str;
            if ($this->err) $log[] = $this->err;
            error_log(implode(PHP_EOL, $log), 3, $logfile = $this->path . '/mysql.' . date('Y.m.d') . '.log');
            chmod($logfile, 0644);
        }
    }

    /**
     * преобразование ассоциированного массива в гладкий
     */
    static function _assoc2plain($u = [])
    {
        return array_map(function($key, $val) { return $key . ' = ' . $val; }, array_keys($u), $u);
    }

    static function _and($u = [])
    {
        return implode(' AND ', $u);
    }

    static function _int($r = [])
    {
        $int = ['id'];
        foreach ($r as $key => $val) {
            $r[$key] = in_array($key, $int) ? (int)$val : $val;
        }
        return $r;
    }

    /**
     * строковое представление даты/времени
     * @param boolean $full флаг полноты
     * @return string результат
     */
    static function _now($full = true)
    {
        return $full ? date('Y-m-d H:i:s') : date('Y-m-d');
    }

    static function _or($u = [])
    {
        return implode(' OR ', $u);
    }

    /**
     * очистка массива/объекта от слешей
     * @param mixed $obj исходный массив/объект
     * @return mixed результат
     */
    static function _strip($data)
    {
        if (is_array($data)) {
            return array_map(function($val) { return is_string($val) ? stripcslashes($val) : $val; }, $data);
        } else if (is_object($val)) {
            return (object)array_map(function($val) { return is_string($val) ? stripcslashes($val) : $val; }, (array)$data);
        } else if (is_string($data)) {
            return stripcslashes($data);
        } else {
            return $data;
        }
    }
}
