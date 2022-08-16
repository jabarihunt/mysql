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
             * @var int $port Database Port
             ********************************************************************************/

                private static string|null $host;
                private static string $database;
                private static string $username;
                private static string $password;
                private static string|null $socket;
                private static int $port;

            /********************************************************************************
             * CLASS VARIABLES
             * @var object $db MySQLi instance
             * @var object $instance Singleton instance of this class
             ********************************************************************************/

                private static mysqli $db;
                private static MySQL|null $instance = NULL;

            /********************************************************************************
             * CLASS CONSTANTS
             * @const array DATA_TYPE_INTEGER  - tinyint, smallint, mediumint, int, bigint, bit
             * @const array DATA_TYPE_REAL     - float, double, decimal
             * @const array DATA_TYPE_TEXT     - char, varchar, tinytext, text, mediumtext, longtext
             * @const array DATA_TYPE_BINARY   - binary, varbinary, blob, tinyblob, mediumblob, longblob
             * @const array DATA_TYPE_TEMPORAL - date, time, year, datetime, timestamp
             * @const array DATA_TYPE_SPATIAL  - point, linestring, polygon, geometry, multipoint, multilinestring, multipolygon, geometrycollection
             * @const array DATA_TYPE_OTHER    - enum, set
             * @const int TRANSACTION_START
             * @const int TRANSACTION_COMMIT
             * @const int TRANSACTION_ROLLBACK
             ********************************************************************************/

                const DATA_TYPE_INTEGER  = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'bit'];
                const DATA_TYPE_REAL     = ['float', 'double', 'decimal'];
                const DATA_TYPE_TEXT     = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'];
                const DATA_TYPE_BINARY   = ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob'];
                const DATA_TYPE_TEMPORAL = ['date', 'time', 'year', 'datetime', 'timestamp'];
                const DATA_TYPE_SPATIAL  = ['point', 'linestring', 'polygon', 'geometry', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection'];
                const DATA_TYPE_OTHER    = ['enum', 'set'];

                const TRANSACTION_START    = 0;
                const TRANSACTION_COMMIT   = 1;
                const TRANSACTION_ROLLBACK = 2;

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

                        self::$host     = !empty($_ENV['MYSQL_HOST']) ? $_ENV['MYSQL_HOST'] : getenv('MYSQL_HOST');
                        self::$database = !empty($_ENV['MYSQL_DATABASE']) ? $_ENV['MYSQL_DATABASE'] : getenv('MYSQL_DATABASE');
                        self::$username = !empty($_ENV['MYSQL_USERNAME']) ? $_ENV['MYSQL_USERNAME'] : getenv('MYSQL_USERNAME');
                        self::$password = !empty($_ENV['MYSQL_PASSWORD']) ? $_ENV['MYSQL_PASSWORD'] : getenv('MYSQL_PASSWORD');
                        self::$socket   = !empty($_ENV['MYSQL_SOCKET']) ? $_ENV['MYSQL_SOCKET'] : getenv('MYSQL_SOCKET');
                        self::$password = !empty($_ENV['MYSQL_PASSWORD']) ? $_ENV['MYSQL_PASSWORD'] : getenv('MYSQL_PASSWORD');
                        self::$port     = !empty($_ENV['MYSQL_PORT']) ? $_ENV['MYSQL_PORT'] : getenv('MYSQL_PORT');

                        if (
                            empty(self::$socket) ||
                            in_array(strtoupper(self::$socket), ['NULL', 'FALSE', '0'])
                        ) {
                            self::$socket = NULL;
                        }

                    // CONNECT TO DATABASE | CHECK CONNECTION

                        self::$db = (self::$socket === NULL) ? @new mysqli(self::$host, self::$username, self::$password, self::$database, self::$port) : @new mysqli(NULL, self::$username, self::$password, self::$database, self::$port, self::$socket);

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
                 * @return array|int
                 ********************************************************************************/

                    public static function query(string $query): array|int {

                        // RETURN FALSE IF QUERY IS EMPTY OR NOT A STRING

                            if (empty($query) || !is_string($query)) {
                                return FALSE;
                            }

                        // TRIM QUERY & GET QUERY TYPE | RUN QUERY

                            $query     = trim($query);
                            $queryType = strtoupper(
                                trim(
                                    substr(
                                        $query,
                                        0,
                                        strpos($query, ' ')
                                    )
                                )
                            );

                            $result = self::get()->query($query);

                        // RETURN RESULT BASED OF QUERY TYPE

                            return match($queryType) {
                                'INSERT'                                => self::get()->insert_id,
                                'SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN' => $result->fetch_all(MYSQLI_ASSOC),
                                default                                 => self::get()->affected_rows
                            };
                    }

            /********************************************************************************
             * TRANSACTION METHOD
             * @param int $transactionCommandId
             * @return bool
             ******************************************************************************
             */

                    public static function transaction(int $transactionCommandId): bool {

                        // MAKE SURE A VALID VALUE WAS PASSED | RUN QUERY | RETURN BOOLEAN BASED ON RESULT

                            if ($transactionCommandId < 0 || $transactionCommandId > 2) {
                                return FALSE;
                            }

                            switch ($transactionCommandId) {
                                case 0: self::get()->begin_transaction(); break;
                                case 1: self::get()->commit(); break;
                                case 2: self::get()->rollback(); break;
                            }

                            return (self::get()->errno === 0);

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

                            $query = trim($query);

                            $queryType = strtoupper(
                                trim(
                                    substr(
                                        $query,
                                        0,
                                        strpos($query, ' ')
                                    )
                                )
                            );

                            if (empty($paramTypeString)) {
                                for ($i = 0; $i < count($paramValues); $i++) {
                                    $paramTypeString .= 's';
                                }
                            }

                        // CREATE AND EXECUTE PREPARED STATEMENT | RETURN FALSE ON ERROR

                            $statement = self::get()->prepare($query);
                            $statement->bind_param($paramTypeString, ...self::arrayReferenceValues($paramValues));
                            $statement->execute();

                            if ($statement->errno > 0) {
                                return FALSE;
                            }

                        // GET RESULT RESULT | SET RESPONSE

                            $result = $statement->get_result();

                            $response = match($queryType) {
                                'INSERT'                                => $statement->insert_id,
                                'SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN' => $result->fetch_all(MYSQLI_ASSOC),
                                default                                 => $statement->affected_rows
                            };

                        // CLOSE STATEMENT | RETURN RESPONSE

                            $statement->close();
                            return $response;

                    }

                /********************************************************************************
                 * SANITIZE METHOD
                 * Used to sanitize individual field values for database insertion.
                 * @param mixed $value The value to be sanitized.
                 * @param array|string $dataType The DB datatype of the passed value
                 * @return mixed
                 *******************************************************************************/

                    public static function sanitize(mixed $value, array|string $dataType = self::DATA_TYPE_TEXT): mixed {

                        // IF DATATYPE IS A STRING, GET THE CORRECT ASSOCIATED ARRAY

                            if (is_string($dataType)) {

                                $dataType = match (TRUE) {
                                    in_array($dataType, self::DATA_TYPE_BINARY) => self::DATA_TYPE_BINARY,
                                    in_array($dataType, self::DATA_TYPE_INTEGER) => self::DATA_TYPE_INTEGER,
                                    in_array($dataType, self::DATA_TYPE_OTHER) => self::DATA_TYPE_OTHER,
                                    in_array($dataType, self::DATA_TYPE_REAL) => self::DATA_TYPE_REAL,
                                    in_array($dataType, self::DATA_TYPE_SPATIAL) => self::DATA_TYPE_SPATIAL,
                                    in_array($dataType, self::DATA_TYPE_TEMPORAL) => self::DATA_TYPE_TEMPORAL,
                                    default => self::DATA_TYPE_TEXT
                                };

                            }

                        // MAKE SURE VALUE ISN'T NULL | SANITIZE BASED ON DATA TYPE | RETURN VALUE

                            if (!empty($value)) {
                                $value = match ($dataType) {
                                    self::DATA_TYPE_INTEGER => filter_var($value, FILTER_SANITIZE_NUMBER_INT),
                                    self::DATA_TYPE_REAL    => filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                                    default                 => filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS)
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
                 * GET INSERT ID METHOD
                 * @return mysqli
                 ********************************************************************************/

                    public static function getInsertId(): int {
                        return self::get()->insert_id;
                    }

                /********************************************************************************
                 * GET ERROR NUMBER
                 * @return int
                 ********************************************************************************/

                    public static function getErrorNumber(): int {
                        return self::get()->errno;
                    }

                /********************************************************************************
                 * GET ERROR NUMBER
                 * @return string
                 ********************************************************************************/

                    public static function getErrorMessage(): string {
                        return self::get()->error;
                    }

                /********************************************************************************
                 * BACKUP METHOD
                 * @param string $directory
                 * @return void
                 ********************************************************************************/

                    public static function backup(string $directory): void {

                        $user = self::$username;
                        $password = self::$password;
                        $database = self::$database;
                        $location = rtrim($directory, '/') . "/{$database}-" . date('Y-m-d') . '_' . time() . '.sql';

                        exec("mysqldump --user='{$user}' --password='{$password}' --single-transaction --routines --triggers {$database} > {$location}");

                    }

                /********************************************************************************
                 * ARRAY REFERENCE VALUES METHOD
                 *
                 * Returns an array with the values as a reference
                 *
                 * @param array $data
                 * @return array
                 ********************************************************************************/

                    final protected static function arrayReferenceValues(array $data): array {

                        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {

                            $i = 0;

                            foreach ($data as $key => $value) {
                                $referencedValues[$i] = &$data[$key];
                                $i++;
                            }

                        }

                        return !empty($referencedValues) ? $referencedValues : $data;

                    }

        }

?>
