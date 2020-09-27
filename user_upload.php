<?php
/**
 * PHP script that processes a CSV file into a MySQL database
 * 
 * For Catalyst IT Australia Programming Evaluation
 * Daniel Shaw
 */

class CommandLineSettings {
    public $file_name;
    public $dry_run;
    public $database;

    /**
     * Check command line options
     * --file [csv file name] – this is the name of the CSV to be parsed
     * --create_table – this will cause the MySQL users table to be built (and no further action will be taken)
     * --dry_run – this will be used with the --file directive in case we want to run the script but not insert into the DB. All other functions will be executed, but the database won't be altered
     * -u – MySQL username
     * -p – MySQL password
     * -h – MySQL host
     * --help – which will output the above list of directives with details.
     */
    public function __construct() {
        $options = getopt("u:p:h:", array(
            "file:",
            "create_table",
            "dry_run",
            "help"
        ));

        // check if help is specified, if so abandon script execution and output acceptable commands
        if (array_key_exists("help", $options)) {
            $this->showHelp();
        }

        // check if create table is specified, create the tables and abandon further script execution
        if (array_key_exists("create_table", $options)) {
            $this->createDatabaseConnection($options);
        }
    }

    // Parse, verify and connect to database if we have the necessary information from command line options
    public function createDatabaseConnection($options) {
        $required_values = array("h" => "Host name (-h)",
                                "u" => "Username (-u)",
                                "p" => "Password (-p)");

        // iterate and check we have each property
        foreach ($required_values as $option => $property) {
            if (!array_key_exists($option, $options)) {
                echo "[Error] Missing script option: $property\n";
                return;
            }
        }

        // attempt connection
        $this->database = new Database($options['h'], $options['u'], $options['p']);
    }

    // Outputs script usage and exits
    public function showHelp() {
        echo <<<EOL
        == CSV Processor Usage ==
        --file [csv file name] – this is the name of the CSV to be parsed
        --create_table – this will cause the MySQL users table to be built (and no further action will be taken)
        --dry_run – this will be used with the --file directive in case we want to run the script but not insert into the DB. All other functions will be executed, but the database won't be altered
        -u – MySQL username
        -p – MySQL password
        -h – MySQL host
       --help – this list of commands again\n
       EOL;
       return;
    }
}

/**
 * Database class
 * Responsible for all database transactions
 */
class Database {
    public $connection;
    public $database_name = "catalyst";

    // Instantiate class and create a connection to mysql
    public function __construct($host, $username, $password) {
        // attempt connection
        $this->connection = @new mysqli($host, $username, $password);

        // exit on error
        if (!$this->connection || mysqli_connect_errno()) {
            echo "[Error] Database error: " . mysqli_connect_error() . "\n";
            return;
        }
        echo "[Success] DB connection successful\n";

        $this->createAndUseDatabase();
    }

    // Execute query (or fail)
    public function execute($query) {
        if (!$this->connection) {
            die("[Error] Database connection missing\n");
        }

        // clean query
        $query = $this->connection->real_escape_string($query);

        // run query
        if (!$this->connection->query($query)) {
            echo "[Error] Database query error: " . $this->connection->error . "\n";
            return;
        }
    }

    // Create and use database (drops if already exists)
    public function createAndUseDatabase() {

        // drop if exists
        $this->execute("DROP DATABASE IF EXISTS $this->database_name");

        // create database
        $this->execute("CREATE DATABASE $this->database_name");

        // use database
        $this->execute("USE $this->database_name");
    }
}
$settings = new CommandLineSettings();

?>