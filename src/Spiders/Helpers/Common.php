<?php

namespace Spiders\Helpers;

/**
* Helper de funciones comunes
*
* @author Daniel Rodríguez [drs]­
*
* @package Spiders
*/
class Common {

    /**
     * @access public
     * @static Función para limpiar cadenas de texto o html
     * 
     * @param String    $string      Cadena de texto o html a limpiar
     * @param Integer   $level       Nivel de profundidad de la limpieza
     * @param Boolean   $removeTags  Indica si desea remover los tags html de la cadena
     * 
     * @return string
     */
    public static function cleanString( $string, $level = 1, $removeTags = false ) {
        
        /* Patrones de limpieza por nivel, 1 es basico, 2 completo para descripciones. */
        $clean_patterns = [
            1 => ["'","'s","&copy;","&reg;","™","’","â€™","â€","”","Â","Â®","®","WALMART", "ONLINESPORTS",
                    "PLEASE NOTE: THIS ITEM CANNOT SHIP VIA 3-DAY DELIVERY."],
            2 => ["~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", 
                    "[", "{", "]","}", "\\", "|", ";", ":", "\"", "'", "‘", "’", "“", "”", "–", "—",
                    "â€”", "â€“", ",", "<", ".", ">", "/", "?","®","Â®","®","&copy;","&reg;","™","amp",
                    "â€","”","Â","WALMART", "ONLINESPORTS", "PLEASE NOTE: THIS ITEM CANNOT SHIP VIA 3-DAY DELIVERY."]
        ];

        /* Aplica el reemplazo de valores indicados en los patrones y remueve etiquetas html si es indicado */
        if($removeTags) {
            $clean_1 = trim( str_replace( $clean_patterns[$level], "", strip_tags($string) ) );
        } else {
            $clean_1 = trim( str_replace( $clean_patterns[$level], "", $string ) );
        }

        /* Elimina enlaces tipo anchor */ 
        $clean_2 = preg_replace('#<a.*?>([^>]*)</a>#i', '', $clean_1);

        return $clean_2;
        
    }

    /**
     * @access public
     * @static Función para crear el slug de un producto a partir del nombre y url
     * 
     * @param String    $string     String que contiene el nombre del producto
     * @param String    $url        String que contiene la URL del producto
     * @param String    $separator  Separador a utilizar para reemplazar espacios en blanco
     * 
     * @return String Slug generado
     */
    public static function createSlug( $string, $url, $separator = '-' ) {

        /* Se limpia el string */ 
        $cleanedString = self::cleanString( $string, 2, true );

        /* Se reemplazan espacios por el separador */
        $cleanedString = preg_replace('/\s+/', $separator, $cleanedString);

        /* Se reemplazan dobles separadores */ 
        $cleanedString = preg_replace("/\-\-/","",$cleanedString);

        /* Se construye el slug y se retorna */         
        return $cleanedString . $separator . substr(md5($url), 0, 6);

    }

    /**
     * @access public
     * @static Función para obtener el valor de un elemento limpio
     * 
     * @param Object    $html           Objeto Simple Html Dom Parser
     * @param String    $tag            Tag del Elemento a buscar
     * @param Integer   $tag_node       Nodo a seleccionar del tag, si no se indica se devuelve array con objetos
     * @param String    $attribute      Atributo a obtener del elemento
     * @param Integer   $cleanLevel     Nivel de limpieza del valor obtenido (opcional)
     * 
     * @return String si se indica $tag_node o sino un Array 
    */
    public static function getElement($html, $tag, $tag_node = FALSE, $attribute = FALSE, $cleanLevel = FALSE, $cleanTags = FALSE, $cleanText = FALSE ){
        
        if($tag_node !== FALSE ) { 
            $element = $html->find( $tag , $tag_node );
            
            if($element) {
                /* Se limpia el contenido de las etiquetas si es solicitado */ 
                if($cleanTags) {
                    $children = $element->children;
                    foreach ($children AS $child) {
                        $child->outertext = '';
                    }
                }
                /* Si se indica el atributo se analizan otras opciones */ 
                if($attribute) {
                    $element = trim($element->$attribute);
                    
                    /* Elimina el texto solicitado */
                    if($cleanText) {
                        $element = trim( str_replace( $cleanText, '', $element ) );
                    }

                    /* Se limpia el texto segun el nivel solicitado */ 
                    if($cleanLevel) {
                        $element = self::cleanString( trim($element), $cleanLevel );
                    }
                    $element = trim($element);
                }
                
                return $element;
            
            } else {
                return false;
            }
            
        } else {
            return $html->find( $tag );
        }      

    }

    /**
     * @access public
     * @static Función para obtener y calcular los precios normales y especial (oferta)
     * 
     * @param Object    $html                       Objeto Simple Html Dom Parser 
     * @param String    $tag_normal_price           Tag Html del elemento precio normal
     * @param String    $attribute_normal_price     Atributo a obtener del elemento precio normal
     * @param String    $tag_special_price          Tag Html del elemento precio special
     * @param String    $attribute_special_price    Atributo a obtener del elemento precio especial
     * @param Decimal   $profit_percent             Valor decimal de incremento al precio (ganancia)
     * 
     * @return Array de precios
     */
    public static function getPrices( $html, $tag_normal_price, $attribute_normal_price, $tag_special_price, $attribute_special_price, $profit_percent ){
        
        $normal_price = false;
        $special_price = false;

        /* Se intenta obtener el precio normal */
        if( $html->find( $tag_normal_price, 0 ) ) {
            $normal_price = $html->find( $tag_normal_price, 0 );
        }

        /* Se intenta obtener el precio special */ 
        if( $html->find( $tag_special_price, 0 ) ) {
            $special_price = $html->find( $tag_special_price, 0 );
        }

        /* Se intenta obtener el valor con formato decimal */ 
        if($normal_price) {
            preg_match("([0-9]+[\,]*[0-9]*[\.]*[0-9]*)", str_replace(",","",$normal_price->$attribute_normal_price), $value);
            $normal_price = $value[0];
            $normal_price = round( $normal_price * $profit_percent, 2 );
        }

        /* Se intenta obtener el valor con formato decimal */
        if($special_price) {
            preg_match("([0-9]+[\,]*[0-9]*[\.]*[0-9]*)", str_replace(",","",$special_price->$attribute_special_price), $value);
            $special_price = $value[0];
            $special_price = round( $special_price * $profit_percent, 2 );
        }
                
        /**
         *   Se verifican los valores obtenidos 
         *   - Si no existe ningun valor se devuelve el array con false en ambos precios
         *   - Si existe alguno de los valores se aplica al no existente de modo que queden iguales
         */ 
        $prices = [ 'normal' => false, 'special' => false ];

        if( $normal_price && $special_price ){ 
            if($normal_price > $special_price ) {
                $prices['normal'] = $normal_price;
                $prices['special'] = $special_price;
            }
        } elseif($normal_price) {
            $prices['normal'] = $normal_price;
            $prices['special'] = $normal_price;
        } elseif($special_price){
            $prices['normal'] = $special_price;
            $prices['special'] = $special_price;           
        }

        return $prices;
    }
}