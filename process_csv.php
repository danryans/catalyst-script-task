<?php
/**
 * PHP script that processes a CSV file into a MySQL database
 * 
 * For Catalyst IT Australia Programming Evaluation
 * Daniel Shaw
 */

class CommandLineSettings {
    public $file_name;
    public $create_table;
    public $dry_run;
    public $db_host;
    public $db_username;
    public $db_password;

    /**
     * Check command line options
     * --file [csv file name] – this is the name of the CSV to be parsed
     * --create_table – this will cause the MySQL users table to be built (and no further
     * action will be taken)
     * --dry_run – this will be used with the --file directive in case we want to run the
     script but not insert into the DB. All other functions will be executed, but the
    database won't be altered
    * -u – MySQL username
    * -p – MySQL password
    * -h – MySQL host
    * --help – which will output the above list of directives with details.
    */
    public function __construct()
    {
        $options = getopt("u:p:h:", array(
            "file:",
            "create_table",
            "dry_run",
            "help"
        ));

        // check if help is specified, if so abandon script execution and output acceptable commands
        if (array_key_exists("help", $options)) {
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
}

$settings = new CommandLineSettings();

?>