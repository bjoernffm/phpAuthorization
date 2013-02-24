<?
/**
  * authorization class
  *
  * LICENSE
  *
  * This source file is subject to the GPL license that is bundled
  * with this package in the file LICENSE.txt.
  *
  * @package    authorization
  * @copyright  Copyright (c) 2012 through 2013, Björn Ebbrecht
  * @license    None
  */ 

  class phpAuthorization {
  
    /**
     *  $mysqli contents the mysqli handler of the current db connection and is
     *  routed to the database where our usertable is located
     *  
     *  @var handle          
     */              
    private $mysqli = false;
    
    /**
     *  constant USER_TABLE contains the name of the table where its userdata
     *  is stored
     *  
     *  @const string
     */                             
    const USER_TABLE = 'users';
      
    /**
     *  $user contains data of an logged in user as an array. This class doesn't
     *  need this array at all but a daugther-class which checks out the
     *  permissions of a loaded user needs it.
     *  
     *  @var array
     */                       
    var $user = array();
    
    /**
     *  this constructor tries to connect via mysqli and stores the hopefully
     *  granted connection into $this->mysqli. It also sets default variables
     *  for a efficient utf-8 transfer.
     *  
     *  @param string $dbUser - the mysql user
     *  @param string $dbPass - the mysql password
     *  @param string $dbHost - the mysql host (default: localhost)
     *  @param string $dbDatabase - the mysql database
     *  @param string $dbTable - deprecated! don't use it anymore
     *  @return none
     */                
    public function __construct($dbUser,
                                $dbPass,
                                $dbHost = 'localhost',
                                $dbDatabase,
                                $dbTable) {

      $this->mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbDatabase);
      if ($this->mysqli->connect_errno) {
      
        /**
         *  if the connection could not be established we display an error and
         *  stop the script to avoid other errors
         */                          
        echo 'Connection Error: #';
        echo $this->mysqli->connect_errno.' - '.$this->mysqli->connect_error;
        exit();
        
      } else {
        
        /**
         *  if everything went well we query our transmission-defaults for that
         *  connections - we want to have a smooth connection for umlauts
         */                          
        $this->mysqli->query("SET
                                character_set_results = 'utf8',
                                character_set_client = 'utf8',
                                character_set_connection = 'utf8',
                                character_set_database = 'utf8',
                                character_set_server = 'utf8'");
        
        /**
         *  Also start a session and set a cookie becaus this class controls our
         *  authorization, log-in, log-out and so on
         */                                   
        session_start();       
        setcookie(session_name(), session_id(), time()+2*60*60, '/');
      }
    } 
    
    /**
     *  This function generates a password containing capital-letters,
     *  small-letters and digits with a length of $length. You need this
     *  function e.g. if users forgot their password and you want to generate
     *  a new one.
     *       
     *  @param int $length - the length of the generated password
     *  @return string - the password
     */                            
    function generate_password($length = 8) {
    
      $buffer = ''; 
      
      /**
       *  for $i < $length add a random letter/digit to $buffer
       */             
      for ($i = 0; $i < 8; $i++) { 
        $buffer .= substr('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', mt_rand(0, 61), 1); 
      } 
      
      return $buffer;
    }
    
    /**
     *  this function generates a salt for our encryption-process with a length
     *  of 8 every time.
     *       
     *  @return string - the salt with a length of 9
     */                   
    function generate_salt() {
    
      $buffer = ''; 
      
      /**
       *  for $i < 9 add a random letter/digit to $buffer
       */  
      for ($i = 0; $i < 9; $i++) { 
        $buffer .= substr('./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', mt_rand(0, 63), 1); 
      } 
      
      return $buffer;
    }
    
    /**
     *  This function hashes a password given as a parameter. if
     *  $hash_to_compare is false a new and random hash will be generated by 
     *  generate_salt() and the password will be SHA-512-encrypted ($6$ ... 
     *  details on http://php.net/manual/de/function.crypt.php). If
     *  $hash_to_compare is set the included salt will be extracted and used to
     *  encrypt the given passwort (e.g. for comparing a plain and encrypted
     *  password - is that the right password?
     *       
     *  @param string $password - the password to encrypt
     *  @param string $hash_to_compare - a password where to extract the hash
     *  @return string - the encrypted $password
     */                                                      
    function hash_password($password, $hash_to_compare = false) { 
      if ($hash_to_compare) {
      
        /**
         *  Extract the salt and use it to encrypt the password.
         */                                   
        return crypt($password, $hash_to_compare);
      } else {
      
        /**
         *  Generating a new salt and encrypting the password
         */                 
        return crypt($password, '$6$'.$this->generate_salt().'$');
      }
    } 
    
    /**
     *  check_login_data() checks the vadility of $username and $password. The
     *  functions loads a record for $username and uses the internal
     *  hash_password() function to check if the given password equals password
     *  stored in the database
     *  
     *  @param string $username - the username to look for
     *  @param string $password - the related password
     *  @return bool - true if matching else false     
     */                                        
    function check_login_data($username, $password) {
      if ($username != '' and $password != '') {
      
        /**
         *  load a record for checking later
         */                 
        $result = $this->mysqli->query('SELECT
                                          `id`,
                                          `password`
                                        FROM
                                          `'.self::USER_TABLE.'`
                                        WHERE
                                          `status` = "ENABLED" AND
                                          `username` LIKE "'.
                                            $this->mysqli->real_escape_string(
                                              $username
                                            )
                                          .'" LIMIT 1;');
        
        if ($result->num_rows == 1) {
          $user = $result->fetch_assoc();
          
          /**
           *  compare the given password with the db-stored one
           */                     
          if ($this->hash_password($password,
                                    $user['password']) == $user['password']) {
            return true;
          } else {
            return false;
          }
        } else {
          return false;
        }
      } else {
        return false;
      }
    }
    
    /**
     *  Checks wether a user is logged in or not and returns a bool. This 
     *  function works only if a session could be started. If it could be 
     *  validated that the user is logged in it returns true - in any other case
     *  false.
     *  
     *  @return bool - if logged in it returns true else false 
     */                            
    function check_login_status() {
      if ($_SESSION['logged'] == 1 and $_SESSION['id'] != '') {
        return true;
      } else {
        return false;
      }
    }
    
    /**
     *  set_new_password sets a new password for a given $username and returns
     *  true if the setting worked fine else false. Use if a user forgot his/her
     *  password to set a new one or if he/she wants to set a new one by their
     *  own.     
     *  
     *  @param string $username - the username to change the password for
     *  @param string $new_password - the new password
     *  @return bool - worked pretty fine? true or false
     */                                       
    function set_new_password($username, $new_password) {
      if ($username != '' and $new_password != '') {
      
        /**
         *  for avoiding sql-injections - real escape the strings
         */                 
        $username = $this->mysqli->real_escape_string($username);
        
        $new_password = $this->hash_password($new_password);
        $new_password = $this->mysqli->real_escape_string($new_password);
        
        $this->mysqli->query('UPDATE
                                `'.self::USER_TABLE.'`
                              SET
                                `password` = "'.$new_password.'"
                              WHERE
                                `username` = "'.$username.'";');
        
        if ($this->mysqli->affected_rows == 1) {
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    }
    
    function create_user($name, $username, $password, $email) {
      if ($username != '' and $password != '') {
      
        /**
         *  for avoiding sql-injections - real escape the strings
         */ 
        $name = $this->mysqli->real_escape_string($name);
        $username = $this->mysqli->real_escape_string($username);
        $email = $this->mysqli->real_escape_string($email);
        $password = $this->hash_password($password);
        
        if ($this->mysqli->query('INSERT INTO
                                    `'.self::USER_TABLE.'`
                                  (
                                    `name`,
                                    `email`,
                                    `username`,
                                    `password`
                                  ) VALUES (
                                    "'.$name.'",
                                    "'.$email.'",
                                    "'.$username.'",
                                    "'.$password.'");') == true) {
          return true;
        } else {
          return false;
        }  
      } else {
        return false;
      }                
    }
    
    function change_user($username, $change_items = array()) {
      if ($username != '' and count($change_items) != 0) {
      
        $username = $this->mysqli->real_escape_string($username);
        $buffer = array();
        
        foreach ($change_items as $change_key => $change_value) {
          $buffer[] = '`'.
                        $this->mysqli->real_escape_string($change_key).
                      '` = "'.
                        $this->mysqli->real_escape_string($change_value).
                      '"';
        }
        
        $result = $this->mysqli->query('UPDATE
                                          `'.self::USER_TABLE.'`
                                        SET
                                          '.implode(', ', $buffer).'
                                        WHERE
                                          `username` = "'.$username.'";');
        
        if ($result->num_rows == 1) {
          return $result->fetch_assoc();
        } else {
          return false;
        }
      } else {
        return false;
      }
    }
    
    function get_user($id, $items = array()) {
      if ($id != '' and count($items) != 0) {   
        $id = $this->mysqli->real_escape_string($id);
        $buffer = array();
        
        foreach ($items as $item) {
          $buffer[] = '`'.$item.'`';
        }
        
        $result = $this->mysqli->query('SELECT
                                          '.implode(', ', $buffer).'
                                        FROM
                                          `'.self::USER_TABLE.'`
                                        WHERE
                                          `id` = '.$id.';');
        
        if ($this->mysqli->affected_rows == 1) {
          return $result->fetch_assoc();
        } else {
          return false;
        }
      } else {
        return false;
      }
    }
    
    function delete_user($username) {
      if ($username != '') {
        $username = $this->mysqli->real_escape_string($username);
        
        $this->mysqli->query('DELETE FROM
                                `'.self::USER_TABLE.'`
                              WHERE
                                `username` = "'.$username.'"
                              LIMIT 1;');
        
        if ($this->mysqli->affected_rows == 1) {
          return true;
        } else {
          return false;
        }
      } else {
        return false;
      }
    }
    
    function user_login($username) {
      if ($username != '') {
        $username = $this->mysqli->real_escape_string($username);
        $result = $this->mysqli->query('SELECT
                                          `id`
                                        FROM
                                          `'.self::USER_TABLE.'`
                                        WHERE
                                          `username` LIKE "'.$username.'"');
        if ($result->num_rows == 1) {
          $user = $result->fetch_assoc();
          
          $_SESSION['logged'] = 1;
          $_SESSION['id'] = $user['id'];
          $this->user['id'] = $user['id'];
        
          return true;
        } else {
          return false;
        }  
      } else {
        return false;
      }      
    }
    
    function user_logout() {
      $_SESSION = array();
      $this->user = array();
      return true;    
    }
  }
?>
