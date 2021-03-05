<?php namespace jabarihunt;

    use ErrorException;
    use mysqli;
    use mysqli_result;

    /********************************************************************************
     * MYSQL HANDLER
     * @author Jabari J. Hunt <jabari@jabari.net>
     *
     * A simple class that handles MySQL database instances with support for both TCP
     * and Socket connections.  I created this class as a standard way to interact
     * with MySQL databases from within other projects with very low overhead.  I
     * personally use it as a dependency in those projects, but it's perfectly capable
     * of being used as a standalone handler.
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
                    if (self::$db instanceof mysqli) {
                        self::$db->close();
                    }
                }

            /********************************************************************************
             * PUBLIC DB METHODS
             ********************************************************************************/

                /********************************************************************************
                 * QUERY METHOD
                 * @param string $query
                 * @return mysqli_result|false
                 ********************************************************************************/

                    public static function query(string $query): mysqli_result|false {

                        // RETURN FALSE IF QUERY IS EMPTY OR NOT A STRING

                            if (empty($query) || !is_string($query)) {
                                return FALSE;
                            }

                        // TRIM QUERY & GET QUERY TYPE | RUN QUERY

                            $query     = trim($query);
                            $queryType = strtoupper(
                                substr(
                                    $query,
                                    0,
                                    (strpos($query, ' ') - 1)
                                )
                            );

                            $result = self::get()->query($query);

                        // RETURN RESULT BASED OF QUERY TYPE

                            return in_array($queryType, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN']) ? $result->fetch_all(MYSQLI_ASSOC) : $result;
                    }

                /********************************************************************************
                 * PREPARE METHOD
                 * @param string $query
                 * @param array $paramValues
                 * @param string|null $paramTypeString
                 * @return array|int
                 *******************************************************************************/

                    public static function prepare(string $query, array $paramValues, string $paramTypeString = NULL): array|int {

                        // RETURN FALSE IF QUERY IS EMPTY OR NOT A STRING

                            if (empty($query) || !is_string($query)) {
                                return FALSE;
                            }

                        // TRIM QUERY & GET QUERY TYPE | CREATE PARAM TYPES STRING IF NOT PASSED

                            $query     = trim($query);
                            $queryType = strtoupper(
                                substr(
                                    $query,
                                    0,
                                    (strpos($query, ' ') - 1)
                                )
                            );

                            if (empty($paramTypeString)) {
                                for ($i = 0; $i < count($paramValues); $i++) {
                                    $paramTypeString .= 's';
                                }
                            }

                        // CREATE AND EXECUTE PREPARED STATEMENT | RETURN FALSE ON ERROR

                            $statement = self::get()->prepare($query);
                            $statement->bind_param($paramTypeString, ...$paramValues);
                            $statement->execute();

                            if ($statement->errno > 0) {
                                return FALSE;
                            }

                        // EXTRACT RESULT & AFFECTED ROWS | CLOSE STATEMENT

                            $result       = $statement->get_result();
                            $affectedRows = $statement->affected_rows;
                            $statement->close();

                        // RETURN RESULT BASED OF QUERY TYPE

                            return in_array($queryType, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN']) ? $result->fetch_all(MYSQLI_ASSOC) : $affectedRows;

                    }

                /********************************************************************************
                 * SANITIZE METHOD
                 * Used to sanitize individual field values for database insertion.
                 * @param mixed $value The value to be sanitized.
                 * @param array $dataType The DB datatype of the passed value
                 * @return mixed
                 *******************************************************************************/

                    public static function sanitize(mixed $value, array $dataType = self::DATA_TYPE_TEXT): mixed {

                        // MAKE SURE VALUE ISN'T NULL | SANITIZE BASED ON DATA TYPE | RETURN VALUE

                            if (!empty($value)) {
                                $value = match ($dataType) {
                                    self::DATA_TYPE_INTEGER => filter_var($value, FILTER_SANITIZE_NUMBER_INT),
                                    self::DATA_TYPE_REAL    => filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                                    default                 => filter_var($value, FILTER_SANITIZE_STRING)
                                };
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