<?php

/*
*   Database abstraction class written by Tam Denholm
*   ----
*   This class provides a layer to a MySQL database. Some significant
*   features to this class are lazy loading, usage of the active record
*   design pattern, which makes it far easier to work with. Rather than
*   writing out long queries, you can pass it raw data.
*
*   The "lazy loading" practice used here means that it will only
*   make a connection to the database server if it is needed, unlike
*   other abstraction classes that make a connection when the class
*   is initialised whether the connection is needed or not.
*/

class db {
    
    /*
    *    All class variables are private as they
    *    should only be manipulated using accessor methods.
    */
    private $conn; // Connection variable
    private $server = 'localhost'; // Default server
    private $username = 'user'; // Default user
    private $password = 'pass'; // Default pass
    private $database = 'database'; // Default db name
    private $result; // Result of the last query
    private $q_str; // Last query string
    private $q_count = 0; // Query count

    /*
    *    Constructor, here we set all connection based variables.
    */
    public function __construct($server = false, $username = false, $password = false, $database = false){
        if($server && $username && $password && $database){
            $this->server = $server;
            $this->username = $username;
            $this->password = $password;
            $this->database = $database;
        }else{
            $this->server = DB_HOST;
            $this->username = DB_USER;
            $this->password = DB_PASS;
            $this->database = DB_NAME;
        }
        /*
        *    Most people put the DB connection in here, which is
        *    a waste of resources as the class is usually initialised
        *    on every page even if its not making any queries.
        */
    }
    
    /*
    *    Destructor, the cleanup method called on script end.
    */
    public function __destruct(){
        // Clean up, not really needed as PHP does this on script end
        $this->free(); // Free result
        $this->close();
    }

    /*
    *    The connection method is only called if required.
    */
    private function conn(){
        if(!$this->conn){ // If there is no existing connection
            $this->conn = @mysqli_connect($this->server, $this->username, $this->password, $this->database); // Make connection
            if(!$this->conn){ // If there is no connection after trying to connect
                throw new Exception('Can\'t establish a connection to the database, check your connection variables.');
            }
            /*
            $db = @mysqli_select_db($this->database, $this->conn); // Select the database
            if(!$db){ // If script cannot find the database
                throw new Exception('Can\'t find the database, check your connection variables.');
            }
            */
            mysqli_set_charset($this->conn, 'utf8');
        }
    }
        
    /*
    *    Our main query method. This method calls the connection method
    *    to establish a connection, this is the "lazy loading" feature
    *    in practice.
    */
    public function query($q_str){
        $this->conn(); // Lazy loading
        $this->q_str = $q_str; // Store query string
        $this->result = mysqli_query($this->conn, $q_str); // Run the query
        
        if(!$this->result){ // Failed Query
            throw new Exception('Failed Query "'.$q_str.'" Error: '.$this->error());
        }else{ // Success
            $this->q_count++; // One more successful query
            return $this->result; // Return the result
        }
    }
    
    /*
    *    The insert function here is an interesting one because of
    *    how it works. The first perameter is the table you wish to
    *    insert data into and the second is an associative array.
    *    The key is a string defining the column of the table to input
    *    into and the value being the information to input.
    */
    public function insert($table, $arr = array()){
        /*
        *    Cleaning the key allows the developer to insert the entire
        *    $_POST array should he wish to and still be safe from attacks.
        */
        $keys = '`'.implode("`, `", $this->clean(array_keys($arr))).'`';
        // Values should always be cleaned
        $values = "'".implode("', '", $this->clean(array_values($arr)))."'";
        
        // Build the query string
        $q_str = "INSERT INTO `".$table."` (".$keys.") VALUES (".$values.")";
        $this->query($q_str); // Execute
    }
    
    /*
    *    The update method works much in the same way as the insert
    *    method, except it takes an additional perameter which is the
    *    WHERE clause of the SQL query string which can be a string or
    *    an array coupled with the $andor perameter.
    */
    public function update($table, $arr = array(), $where = false, $andor = 'AND'){
        // Start the query string
        $q_str = "UPDATE `".$table."` SET ";
        
        // Build the SET part of the query string
        foreach($arr as $key => $value){
            $q_str .= '`'.$this->clean($key)."` = '".$this->clean($value)."', ";
        }
        $q_str = rtrim($q_str, ', ');
        
        // Add WHERE clause if given
        if(is_array($where) && count($where) > 0){
            foreach($where as $key => $value){
                $w_str .= '`'.$this->clean($key)."` = '".$this->clean($value)."' ".$andor." ";
            }
            $w_str = rtrim($w_str, $andor.' '); // Trim the last AND/OR off
            $q_str .= "WHERE ".$w_str;
        }elseif(is_string($where) && strlen($where) > 0){
            $q_str .= "WHERE ".$where;
        }
        
        $this->query($q_str); // Execute
    }
    
    /*
    *    This function takes an associative array from the database
    *    and then stores each value in another array. The sole reason
    *    for this is so that the returned array from this method may
    *    be called in a foreach loop. Handy for templating systems.
    */
    public function arr(){
        if(!$this->result){ // Require a previous query
            throw new Exception('You cannot call the arr() method without first making a query.');
        }
        
        // Store in another array
        while($arr = @mysqli_fetch_assoc($this->result)){
            $newarr[] = $arr;
        }
        return $newarr;
    }
    
    /*
    *    The purpose of this method is to take a query string,
    *    execute it and return a single value from the database.
    */
    public function single($q_str){
        $this->query($q_str); // Execute the query
        $arr = $this->arr(); // Take the array
        if(is_array($arr)){
            /*
            *    Because we store the assoc array in another array
            *    we have to "shift" it twice to get the single value.
            */
            $value = array_shift($arr);
            $value = array_shift($value);
            return $value;
        }else{
            // Fail
            return false;
        }
    }
    
    /*
    *   This method will take a given table name and return an integer
    *   counting the rows within the table.
    */
    public function count($table){ // yes, we're using a native php function name...
        if(!$table){
            return 0;
        }
        $count = $this->single("SELECT count(*) FROM `".$table."`");
        return intval($count);
    }
    
    /*
    *    This is a standard "clean" method used to make sure
    *    query strings are safe.
    */
    public function clean($input){
        $this->conn(); // Requires a connection to determine charset of db
        if(is_array($input)){
            foreach($input as $key => $str){
                $arr[$this->clean($key)] = $this->clean($str); // Call self this time with a string
            }
            return $arr;
        }elseif(is_string($input)){
            if(get_magic_quotes_gpc()){ // If magic quotes is set to on
                $input = stripslashes($input); // Undo what magic quotes did
            }
            
            if(!is_numeric($input)){ // Only if not numeric
                $input = mysqli_real_escape_string($this->conn, $input); // Escape
            }
        }
        return $input;
    }
    
    /*
    *    Gets the id from the last INSERT query.
    */
    public function id(){
        $result = @mysqli_insert_id($this->conn);
        if(!$result){
            throw new Exception('Failed getting last insert id. Error: '.$this->error());
        }else{
            return $result;
        }
    }
    
    /*
    *    Counts number of rows in last query.
    */
    public function num(){
        if(!$this->result){ // Require a previous query
            throw new Exception('You cannot call the num() method without first making a query.');
        }
        $num = mysqli_num_rows($this->result);
        return $num;
    }
    
    /*
    *    Shows the amount of affected rows in last query.
    */
    public function affected(){
        if(!$this->result){ // Require a previous query
            throw new Exception('You cannot call the affected() method without first making a query.');
        }
        $rows = mysqli_affected_rows($this->conn);
        return $rows;
    }
    
    /*
    *    Frees the memory used by the result
    */
    public function free(){
        if(is_resource($this->result)){
            return mysqli_free_result($this->result);
        }else{
            return;
        }
    }
    
    /*
    *    Returns a mysql generated error.
    */
    public function error(){
        return mysqli_error($this->conn);
    }
    
    /*
    *    Closes the mysql connection.
    */
    public function close(){
        if(is_resource($this->conn)){
            return mysqli_close($this->conn);
        }
    }
    
    /*
    *    Returns the last stored query string
    */
    public function q_str(){
        return $this->q_str;
    }
    
    /*
    *    Returns the amount of queries ran.
    */
    public function q_count(){
        return $this->q_count;
    }
}

?>
