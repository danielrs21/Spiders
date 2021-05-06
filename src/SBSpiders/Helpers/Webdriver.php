<?php

namespace Spiders\Helpers;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Sunra\PhpSimple\HtmlDomParser;

/**
* Helper de conexión con servidor Selenium y consulta de sitios con Google Chrome
*
* @author Daniel Rodríguez [drs]
*
* @package Spiders
*/
class Webdriver {

    /* Valor por defecto de la aplicación java Selenium Stand Alone Server */ 
    const SELENIUM_HOST = 'http://localhost:4444/wd/hub';

    /**
     * @access public
     * @static Función para obtener el HTML de un sitio web con espera de carga completa de javascript
     *          - Es importante que se cierre la conexión $driver el cual maneja al navegador Google Chrome, de lo contrario
     *              se queda ejecutandose en memoria y puede colapsar la memoria Ram del webserver. 
     *          - Si se ejecuta en ambiente de pruebas y el proceso se detiene por el usuario, debe ingresarse en la consola el comando
     *              "killall chrome" sin comillas, para cerrar las sesiones de navegador que pudieran haber quedado activas. 
     * 
     * @param String    $url            URL del sitio a consultar
     * @param String    $tag_click      Tag CSS de un elemento para hacer click en el sitio (opcional)
     * @param Integer   $timeoutConnect Tiempo máximo de espera por conexión al sitio en milisegundos. Por defecto: 10 segundos
     * @param Integer   $timeoutLoad    Tiempo máximo de espera por carga completa del sitio en milisegundos. Por defecto: 60 segundos
     * 
     * @return Object Simple Html Dom Parser
     */                                                                                            
    public static function getHtml( $url, $tag_click = false, $timeoutConnect = 10000, $timeoutLoad = 60000 ) {
        
        $options = new ChromeOptions();
        $agent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.109 Safari/537.36';
        /* Se definen las opciones para inicializar Google Chrome */
        $options->addArguments(
            array(
                '--no-sandbox',
                '--headless',
                '--user-agent='.$agent,
             

            )
        );

        /* Se establece la conexión con Selenium y a traves de el se intenta obtener el sitio */ 
        $host = self::SELENIUM_HOST;
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        $driver = RemoteWebDriver::create( $host, $capabilities, $timeoutConnect, $timeoutLoad );

        try { 
            
            $driver->get($url);   
          
           /* $driver->manage()->addCookie({
                'name' => '',

            })*/
            /* Si se solicita hacer click en algun elemento se ejecuta a continuación */ 
            if($tag_click) {
                $element = $driver->findElement(WebDriverBy::cssSelector( $tag_click ));
                if($element){
                    $element->click();
                }
            }
    con:
            /* Se convierte el resultado en un objeto Simple Html Dom Parser */ 
            
            $html = HtmlDomParser::str_get_html( $driver->getPageSource() );
           
           
            /* Se cierra el navegador Google Chrome */ 
            $driver->quit();
            return $html;
      
        } catch (\Exception $e) {
            /* Si sucede un error y ha sido generado por no localizar el tag del elemento click se continua con la carga a "con:" */ 
            if(strpos($e->getMessage(),'no such element') !== FALSE){
                goto con;
            } else {
                if(isset($driver)) {
                    try {
                        $driver->quit(); 
                    } catch (\Exception $f){
                        return false;
                    }
                }
                return false;
            }      
        }
        
    }
}