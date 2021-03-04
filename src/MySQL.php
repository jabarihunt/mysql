<?php namespace jabarihunt;

    use ErrorException;
    use JetBrains\PhpStorm\Pure;
    use mysqli;
    use mysqli_result;
    use mysqli_stmt;

    /********************************************************************************
     * MYSQL HANDLER
     * @author Jabari J. Hunt <jabari@jabari.net>
     *
     * A simple class that handles MySQL database instances with support for both TCP and Socket connections.  I created this class as a standard way to interact with MySQL databases from within other projects with very low overhead.  I will personally use it as a dependency in those projects, but it's perfectly capable of being used as a standalone handler.
     *
     ********************************************************************************/

        class MySQL {

            /********************************************************************************
             * CONNECTION VARIABLES
             * @var string $host Database Server
             * @var string $database Database Instance
             * @var string $user Database Username
             * @var string $password Database Password
             * @var string $socket Database Socket
             ********************************************************************************/

                private static string|null $host;
                private static string $database;
                private static string $user;
                private static string $password;
                private static string|null $socket;

            /********************************************************************************
             * CLASS VARIABLES
             * @var object $db MySQLi instance
             * @var object $instance Singleton instance of this class
             ********************************************************************************/

                private static mysqli $db;
                private static MySQL|null $instance = NULL;

            /********************************************************************************
             * CLASS CONSTANTS
             * @var integer DATA_TYPE_INTEGER  - tinyint, smallint, mediumint, int, bigint, bit
             * @var integer DATA_TYPE_REAL     - float, double, decimal
             * @var integer DATA_TYPE_TEXT     - char, varchar, tinytext, text, mediumtext, longtext
             * @var integer DATA_TYPE_BINARY   - binary, varbinary, blob, tinyblob, mediumblob, longblob
             * @var integer DATA_TYPE_TEMPORAL - date, time, year, datetime, timestamp
             * @var integer DATA_TYPE_SPATIAL  - point, linestring, polygon, geometry, multipoint, multilinestring, multipolygon, geometrycollection
             * @var integer DATA_TYPE_OTHER    - enum, set
             ********************************************************************************/

                const DATA_TYPE_INTEGER  = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'bit'];
                const DATA_TYPE_REAL     = ['float', 'double', 'decimal'];
                const DATA_TYPE_TEXT     = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'];
                const DATA_TYPE_BINARY   = ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob'];
                const DATA_TYPE_TEMPORAL = ['date', 'time', 'year', 'datetime', 'timestamp'];
                const DATA_TYPE_SPATIAL  = ['point', 'linestring', 'polygon', 'geometry', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection'];
                const DATA_TYPE_OTHER    = ['enum', 'set'];

            /********************************************************************************
             * SINGLETON INSTANCE METHOD
             * @return mysqli|null
             ********************************************************************************/

                private static function get(): mysqli|null {

                    if (self::$instance === NULL) {self::$instance = new MySQL();}
                    return self::$db;

                }

            /********************************************************************************
             * CONSTRUCTOR
             * @throws ErrorException
             ********************************************************************************/

                private function __construct() {

                    // SET DATABASE AND SESSION CLASS VARIABLES

                        self::$host     = getenv('MYSQL_HOST');
                        self::$database = getenv('MYSQL_DATABASE');
                        self::$user     = getenv('MYSQL_USER');
                        self::$password = getenv('MYSQL_PASSWORD');
                        self::$socket   = getenv('MYSQL_SOCKET');

                        if (
                            empty(self::$socket) ||
                            in_array(strtoupper(self::$socket), ['NULL', 'FALSE', '0'])
                        ) {
                            self::$socket = NULL;
                        }

                    // CONNECT TO DATABASE | CHECK CONNECTION

                        self::$db = (self::$socket === NULL) ? @new mysqli(self::$host, self::$user, self::$password, self::$database) : @new mysqli(NULL, self::$user, self::$password, self::$database, NULL, self::$socket);

                        if (self::$db->connect_errno > 0) {

                            $connectionError = 'MySQL Connection Error #' . self::$db->connect_errno . ': ' . self::$db->connect_error;
                            throw new ErrorException($connectionError, 0, E_ERROR, __FILE__, __LINE__);

                        }

                }

            /********************************************************************************
             * DESTRUCTOR
             ********************************************************************************/

                public function __destruct() {

                    // CLOSE DATABASE

                        if (self::$db instanceof mysqli) {
                            self::$db->close();
                        }

                }

            /********************************************************************************
             * PUBLIC DB METHODS -> BACKUP | SET INSTANCE | PREPARE | QUERY
             ********************************************************************************/

                /********************************************************************************
                 * QUERY METHOD
                 * @param string $query
                 * @return mysqli_result|false
                 ********************************************************************************/

                    public static function query(string $query): mysqli_result|false {
                        return self::get()->query($query);
                    }

                /********************************************************************************
                 * PREPARE METHOD
                 * @param string $query
                 * @param array $params
                 * @param string $paramTypes
                 * @return array
                 ********************************************************************************/

                    public static function prepare(string $query, array $params, string $paramTypes = NULL): array {

                        // CREATE PARAM TYPES STRINGS IF NOT PASSED

                            if (empty($paramTypes)) {

                                for ($i = 0; $i < count($params); $i++) {
                                    $paramTypes .= 's';
                                }

                            }

                        // CREATE AND EXECUTE PREPARED STATEMENT

                            $statement = self::get()->prepare($query);
                            $statement->bind_param($paramTypes, ...$params);
                            $statement->execute();

                        // EXTRACT AND RETURN RESULTS

                            return $statement->get_result()->fetch_all(MYSQLI_ASSOC);

                    }

                /********************************************************************************
                 * SANITIZE METHOD
                 * Used to sanitize individual field values for database insertion.
                 * @param mixed $value The value to be sanitized.
                 * @param string|null $dataType The DB datatype of the passed value
                 * @return string|int|float|null
                 *******************************************************************************/

                    #[Pure]
                    public static function sanitize(mixed $value, array $dataType = self::DATA_TYPE_TEXT): string|int|float {

                        // MAKE SURE VALUE ISN'T NULL | SANITIZE BASED ON DATA TYPE | RETURN VALUE

                            $value = ($value === NULL) ? '' : $value;

                            switch ($dataType) {
                                case self::DATA_TYPE_TEXT:
                                    $value = filter_var($value, FILTER_SANITIZE_STRING);
                                    break;
                                case self::DATA_TYPE_INTEGER:
                                    $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
                                    break;
                                case self::DATA_TYPE_REAL:
                                    $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                                    break;
                                default:
                                    $value = filter_var($value, FILTER_SANITIZE_STRING);
                            }

                            return $value;

                    }

                /********************************************************************************
                 * GET MYSQL OBJECT METHOD
                 * @return mysqli
                 ********************************************************************************/

                    public static function getMySQLObject(): mysqli {
                        return self::get();
                    }

                /********************************************************************************
                 * BACKUP METHOD
                 * @param string $directory
                 * @return void
                 ********************************************************************************/

                    public static function backup(string $directory): void {

                    $user     = self::$user;
                    $password = self::$password;
                    $database = self::$database;
                    $location = rtrim($directory, '/') . "/{$database}-" . date('Y-m-d') . '_' . time() . '.sql';

                exec("mysqldump --user='{$user}' --password='{$password}' --single-transaction --routines --triggers {$database} > {$location}");

            }


        }

?>