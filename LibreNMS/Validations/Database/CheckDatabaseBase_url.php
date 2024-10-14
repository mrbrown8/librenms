<?php
/*
 * CheckDatabaseBase_url.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2024 Curtis J. Brown
 * @author     Curtis J. Brown <mrbrown8@juno.com>
 */

namespace LibreNMS\Validations\Database;

use Illuminate\Support\Arr;
use LibreNMS\DB\Eloquent;
use Illuminate\Support\Facades\DB;
//use LibreNMS\DB\Schema;
use LibreNMS\Interfaces\Validation;
use LibreNMS\Interfaces\ValidationFixer;
use LibreNMS\ValidationResult;

class CheckDatabaseBase_url implements Validation, ValidationFixer
{
    
    /** @var string */
    private $new_base_url = "";   // in case of mismatch, use this to build new url
    /** @var string */
    private $new_sql_update = "";  // after new url is built, use this to put together new sql update
    
    /**
     * @inheritDoc
     */
    public function validate(): ValidationResult
    {
        
        $db_base_url_pieces = [];
        
        if ($_SERVER['SERVER_NAME'] === $_SERVER['SERVER_ADDR'] ) {
            return ValidationResult::warn("Domainname not set or not set properly in webserver (says '" . $_SERVER['SERVER_NAME'] . "')");  // do you web config?
        }

        $db_base_url_sql = "SELECT * FROM `config` WHERE `config_name` = 'base_url' ";
        //$db_base_url = Arr::first(Eloquent::DB()->selectOne($db_base_url_sql));
        $db_base_url = DB::select($db_base_url_sql);
        
        //$db_base_url_pieces = parse_url($db_base_url);
        
        //error_log("SQL is:" . $db_base_url_sql . "\n");
        //error_log("URL type is:" . gettype($db_base_url) . "\n");
        //error_log("URL size is:" . count($db_base_url) . "\n");
        error_log("URL is:" . $db_base_url[0] . "\n");
        //d_echo("$db_base_url_pieces\n");
        //error_log(var_dump($db_base_url_pieces) . "\n");
        
        //if ( strtolower($db_base_url_pieces["host"]) === strtolower($_SERVER['SERVER_NAME']) ) {
        if ( true ) {
            return ValidationResult::ok("Webserver name matches domainname in DB value of config:base_url");   //move along
        } else {
            
            // prep potential fix (credit: thomas at gielfeldt dot com, from https://www.php.net/manual/en/function.parse-url.php)
            $scheme   = isset($db_base_url_pieces['scheme']) ? $parsed_url['scheme'] . '://' : '';
            $host     = $_SERVER['SERVER_NAME'];
            $port     = isset($db_base_url_pieces['port']) ? ':' . $db_base_url_pieces['port'] : '';
            $user     = isset($db_base_url_pieces['user']) ? $db_base_url_pieces['user'] : '';
            $pass     = isset($db_base_url_pieces['pass']) ? ':' . $db_base_url_pieces['pass']  : '';
            $pass     = ($user || $pass) ? "$pass@" : '';
            $path     = isset($db_base_url_pieces['path']) ? $db_base_url_pieces['path'] : '';
            $query    = isset($db_base_url_pieces['query']) ? '?' . $db_base_url_pieces['query'] : '';
            $fragment = isset($db_base_url_pieces['fragment']) ? '#' . $db_base_url_pieces['fragment'] : '';

            $this->new_base_url = "$scheme$user$pass$host$port$path$query$fragment";
            $this->new_sql_update =  "UPDATE `config` SET `config_value` = '\"" . this->$new_base_url . "\"' WHERE `config_name` = 'base_url';";
            
            return ValidationResult::fail("Webserver name (" . $_SERVER['SERVER_NAME'] . ") is different from domainname in DB value of config:base_url (" . $db_base_url_pieces['host'] . ")")
                ->setFix('Run the following SQL statement to fix it')
                ->setFixer(__CLASS__)
                ->setList('SQL Statement', this->$new_sql_update);
            
        }
        
        
    }       
        
        
        
    /**
     * @inheritDoc
     */
    public function fix(): bool
    {
        try {
                DB::statement(this->$new_sql_update);
        } 
        catch (QueryException $e) {
            return false;
        }

        return true;
    }

    public function enabled(): bool
    {
        return Eloquent::isConnected();
    }


}
