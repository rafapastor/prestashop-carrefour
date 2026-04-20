<?php
/**
 * PHPUnit bootstrap — defines the guards that module files use to refuse
 * direct web access, so classes can be loaded outside of a PrestaShop runtime.
 */
if (!defined('CARREFOUR_TEST_MODE')) {
    define('CARREFOUR_TEST_MODE', true);
}
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0');
}
if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', __DIR__ . '/../carrefourmarketplace/');
}

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

/* Minimal stubs for the PrestaShop core types we reference but cannot load outside PS. */
if (!class_exists('ObjectModel', false)) {
    class ObjectModel
    {
        const TYPE_INT = 1;
        const TYPE_BOOL = 2;
        const TYPE_STRING = 3;
        const TYPE_FLOAT = 4;
        const TYPE_DATE = 5;
        const TYPE_HTML = 6;
        const TYPE_NOTHING = 7;
        const TYPE_SQL = 8;

        public $id;

        public static $definition = [];

        public function __construct($id = null)
        {
            $this->id = $id;
        }

        public function add()
        {
            return true;
        }

        public function update()
        {
            return true;
        }

        public function save()
        {
            return true;
        }

        public function delete()
        {
            return true;
        }
    }
}

if (!class_exists('Validate', false)) {
    class Validate
    {
        public static function isLoadedObject($obj)
        {
            return is_object($obj) && !empty($obj->id);
        }
    }
}

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

/**
 * Minimal Db stub that counts queries and lets tests pre-queue result rows.
 * Not a full PS Db replacement — just enough to exercise code paths.
 */
if (!class_exists('Db', false)) {
    class Db
    {
        /** @var int */
        public static $queryCount = 0;

        /** @var array queued results consumed by getValue/getRow/executeS (FIFO) */
        public static $nextResults = [];

        /** @var self|null */
        private static $instance = null;

        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function getValue($sql)
        {
            self::$queryCount++;

            return self::pop();
        }

        public function getRow($sql)
        {
            self::$queryCount++;

            return self::pop();
        }

        public function executeS($sql)
        {
            self::$queryCount++;
            $result = self::pop();

            return $result === null ? [] : $result;
        }

        public function execute($sql)
        {
            self::$queryCount++;

            return true;
        }

        private static function pop()
        {
            if (empty(self::$nextResults)) {
                return null;
            }

            return array_shift(self::$nextResults);
        }

        public static function reset()
        {
            self::$queryCount = 0;
            self::$nextResults = [];
        }
    }
}

/* Autoloader mirroring the one in carrefourmarketplace.php: load module classes
   (Carrefour- and Mirakl-prefixed) from classes/<subdir>/<ClassName>.php. */
spl_autoload_register(function ($class) {
    if (strpos($class, 'Carrefour') !== 0 && strpos($class, 'Mirakl') !== 0) {
        return;
    }
    $base = __DIR__ . '/../carrefourmarketplace/classes/';
    static $dirs = ['Model/', 'Logger/', 'Util/', 'Api/', 'Service/', 'Queue/', 'Queue/Jobs/'];
    foreach ($dirs as $dir) {
        $file = $base . $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;

            return;
        }
    }
});
