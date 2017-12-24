<?php
/**
 * Class: DB
 * Because $wpdb makes me write bad code, and PDO is available everywhere.
 *
 * @see \PDO
 */

namespace k1sul1;

if (!function_exists("db")) {
  class DB extends \PDO {
    public static $instance;
    public $prefix;

    public function __construct() {
      $dsn = "mysql:host=" . \DB_HOST . ";dbname=" . \DB_NAME . ";charset=" . \DB_CHARSET;
      $opt = [
        DB::ATTR_ERRMODE => DB::ERRMODE_EXCEPTION,
        DB::ATTR_DEFAULT_FETCH_MODE => DB::FETCH_ASSOC,
        DB::ATTR_EMULATE_PREPARES => 0
      ];
      self::$instance = parent::__construct($dsn, \DB_USER, \DB_PASSWORD, $opt);

      $this->prefix = $GLOBALS["wpdb"]->prefix;
      $this->collate = \DB_COLLATE;
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     * Replaces hard coded db prefix.
     *
     * @param string $statement
     * @param array $options
     */
    public function prepare($statement, $options = null) {
      if ($this->prefix !== "wp_") {
        $statement = str_replace("wp_", $this->prefix, $statement);
      }

      $options = is_null($options) ? [] : $options;

      return parent::prepare($statement, $options);
    }

    public static function singleton() {
      if (is_null(self::$instance)) {
        return new DB;
      }

      return self::instance;
    }
  }

  function db() {
    return DB::singleton();
  }
}
