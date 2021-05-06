<?php

namespace Spiders\Config;

/**
* Clase de configuración de Base de Datos y conexión
*
* @author Daniel Rodríguez [drs]
*
* @package Spiders
*/
class Database {
     
    /**
     * @access private
     * @var $dbProfile Define la configuración de base de datos a utilizar en la conexión 
     */
    private $dbProfile = 'dev';

    /**
     * @access private
     * @var $dbParam Contiene los perfiles de conexion con base de datos
     */
    private $dbParam = 
        [
        'dev' => [
            'dbname'    => 'drs_test',
            'host'      => 'localhost',
            'username'  => 'dbdrs',
            'password'  => 'secret',
            'prefix'    => 'drs_',
            'port'      =>  3306
            ],
        'pro' => [
            'dbname'    => 'drs_prod',
            'host'      => 'localhost',
            'username'  => 'dbdrs',
            'password'  => 'secret',
            'prefix'    => 'drs_',
            'port'      =>  3306
            ]
        ];

    /**
     * @access public
     * Función que realiza conexión con la base datos 
     * 
     * @return Objeto Mysqli con conexión activa
     */
    public function connect() {
        try {
            $mysqli = new \mysqli(
                $this->dbParam[$this->dbProfile]['host'],
                $this->dbParam[$this->dbProfile]['username'],
                $this->dbParam[$this->dbProfile]['password'],
                $this->dbParam[$this->dbProfile]['dbname'],
                $this->dbParam[$this->dbProfile]['port']
            );

            if ($mysqli->connect_errno) {
                echo "Fallo al conectar a MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
                exit;
            }    

            return $mysqli;

        } catch (Exception $e) {
            echo "Excepción: {$e->getMessage()}\n";
            exit;
        }
    }

    /**
     * @access public
     * Función que devuelve el prefijo de tabla definido en el perfil activo.
     * 
     * @return String 
     */
    public function getPrefix(){
        return $this->dbParam[$this->dbProfile]['prefix'];
    }

}