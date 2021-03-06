<?php
/**
 * PHP script that processes a CSV file into a MySQL database
 * 
 * For Catalyst IT Australia Programming Evaluation
 * Daniel Shaw
 */

class CommandLineSettings {
    public $database;
    public $file;

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

            // drop, create and use database
            $this->database->createDatabase();
            $this->database->useDatabase();

            // create users table
            $this->database->createUsersTable();

            // close connection
            $this->database->close();
            return;
        }

        // check if file is specified
        if (!array_key_exists("file", $options)) {
            echo "[Error] Missing script option: CSV File (--file)\n";
            return;
        }

        // dry run
        if (array_key_exists("dry_run", $options)) {
            // create file processor
            $this->file = new File($options['file']);

            // parse users
            $this->file->parseUsers();
        }

        // no dry_run, no create_table - begin script process and database insertion
        if (!array_key_exists("dry_run", $options) && !array_key_exists("create_table", $options)) {
            // create database connection and use database
            $this->createDatabaseConnection($options);
            $this->database->useDatabase();

            // create file processor
            $this->file = new File($options['file']);

            // parse users
            $this->file->parseUsers();

            // get sql statements of all users
            $statements = $this->file->generateSqlStatements($this->database);
            
            // execute statements
            $this->database->parseUserStatements($statements);

            // success :)
            echo "[Success] Inserted " . count($statements) . " users\n";
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
 * File class
 * Responsible for handling CSV file
 */
class File {
    public $users;
    public $filename;

    // Instantiate class, and complete integrity checks on CSV file
    public function __construct($filename) {
        // verify file exists
        if (!file_exists($filename)) {
            die("[Error] File does not exist: $filename\n");
        }

        // verify it is a csv file
        if (pathinfo($filename, PATHINFO_EXTENSION) != "csv") {
            die("[Error] File is not a csv file: $filename\n");
        }

        // verify file is not empty
        $contents = file_get_contents($filename);
        if (empty($contents)) {
            die("[Error] File is empty: $filename\n");
        }

        // set filename
        $this->filename = $filename;;
    }

    // Parse users into an associative array
    public function parseUsers() {
        // check for filename
        if (!$this->filename) {
            die("[Error] Filename not set");
        }

        // reset users array
        $this->users = array();

        // read csv in as array lines and remove first entry with titles
        $raw_lines = file($this->filename);
        array_shift($raw_lines);

        // iterate each line and process
        foreach ($raw_lines as $line_index => $line) {
            // creates array of [0] name, [1] surname, [2] email
            $user_values = array("name", "surname", "email");
            $user = str_getcsv($line);

            // iterate, check for empty values, trim excess spaces and lowercase each value
            foreach ($user as $index => &$element) {
                // check element exists, error if not
                if (empty($element)) {
                    die("[Error] Parsing file error, missing value on entry " . ($line_index + 1) . " for " . $user_values[$index] . "\n");
                }

                // trim spaces
                $element = trim($element);

                // lowercase
                $element = strtolower($element);
            }

            // capitalise name and surname
            $user[0] = ucfirst($user[0]);
            $user[1] = ucfirst($user[1]);

            // validate valid email address
            $email = $user[2];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                die("[Error] Invalid email address on entry " . ($line_index + 1) . ": $email\n");
            }

            $this->users[] = $user;
        }

        echo "[Success] Parsed users into array\n";
    }

    // Generate sql query statement for each user
    public function generateSqlStatements($database) {
        // check for users
        if (!$this->users || empty($this->users)) {
            die("[Error] Users have not been parsed or variable is empty.\n");
        }

        // statements variable to hold all the sql lines
        $statements = [];
        foreach ($this->users as $user) {
            // clean each value
            $name = $database->cleanString($user[0]);
            $surname = $database->cleanString($user[1]);
            $email = $database->cleanString($user[2]);

            // add statement
            $statements[] = "INSERT INTO users VALUES (NULL, '$name', '$surname', '$email')";
        }

        // return statements
        return $statements;
    }
}

/**
 * Database class
 * Responsible for all database transactions
 */
class Database {
    private $connection;
    private $database_name = "catalyst";

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
    }

    // Execute query (or fail)
    public function execute($query) {
        if (!$this->connection) {
            die("[Error] Database connection missing\n");
        }

        // run query
        if (!$this->connection->query($query)) {
            die("[Error] Database query error: " . $this->connection->error . "\n");
        }
    }

    // Create database (drops if already exists) - this includes users table
    public function createDatabase() {
        // drop if exists
        $this->execute("DROP DATABASE IF EXISTS $this->database_name");

        // create database
        $this->execute("CREATE DATABASE $this->database_name");

        echo "[Success] Created database $this->database_name\n";
    }

    // Use database
    public function useDatabase() {
        // use database
        $this->execute("USE $this->database_name");

        echo "[Success] Using database $this->database_name\n";
    }

    // Create users table
    public function createUsersTable() {
        $this->execute("CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL, surname VARCHAR(60) NOT NULL, email VARCHAR(80) NOT NULL, UNIQUE KEY unique_email (email))");
        echo "[Success] Created users table\n";
    }

    // Parse statements of users
    public function parseUserStatements($statements) {
        foreach ($statements as $statement) {
            $this->execute($statement);
        }
    }

    // Clean string
    public function cleanString($string) {
        if (!$this->connection) {
            die("[Error] No database connection");
        }

        return $this->connection->real_escape_string($string);
    }

    // Close database
    public function close() {
        $this->connection->close();
    }
}
$settings = new CommandLineSettings();

?>