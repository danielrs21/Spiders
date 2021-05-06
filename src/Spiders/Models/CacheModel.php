<?php
namespace Spiders\Models;

use Spiders\Config\Database;

/**
* Modelo de tabla cache
*
* @author Daniel Rodríguez [drs]
*
* @package Spiders
*/
class CacheModel { 

	const TABLA_CACHE = 'drs_syncfeed_cache';
	const TABLA_CATEGORY_MATCH = 'drs_syncfeed_categorymatch';

	/**
	 * @var $db Objeto conexión db
	 */
	protected $db; 

	/**
	 * @var $table Tabla Cache
	 */
	protected $table;

	/**
	 * @var $table_category_match Tabla Category Match 
	 */
	protected $table_category_match; 

	/**
	 * @access public
	 * Constructor, establece conexión con base de datos y define tabla cache con prefijo
	 */
	public function __construct() {
		$db = new Database();	
		$this->db = $db->connect();
		$this->table = $db->getPrefix().self::TABLA_CACHE;
		$this->table_category_match = $db->getPrefix().self::TABLA_CATEGORY_MATCH;
	}

	/**
	 * @access public
	 * Función que devuelve un producto buscado por $item_code
	 * 
	 * @param String $item_code Código de producto a buscar
	 * 
	 * @return Array con datos del producto
	 */
    public function getProduct($item_code) {

		$queryStr = "SELECT * FROM $this->table WHERE item_code='$item_code' LIMIT 1"; 
		$result = $this->db->query( $queryStr );
		
		if($result){
			if($result->num_rows > 0 ) {
				$product = $result->fetch_assoc();
			} else {
				$product = false;
			}
			$result->close();
		} else {
			$product = false;
		}
		
		return $product;
    
	}

	/**
	 * @access public
	 * Función para desactivar productos de la tienda y categoria indicados
	 * @param String $store_name 	Nombre de la tienda 
	 * @param String $category		Ruta completa de categoria
	 * 
	 * @return Integer Cantidad de registros desactivados
	 */
	public function disableProducts( $store_name, $category=FALSE, array $items ){

		$items = implode(',',$items); 

		if($category) {
			$queryStr = "UPDATE	$this->table SET item_status = 0 WHERE store_name='$store_name' AND	item_code not in($items) AND category LIKE '%$category%'";
		} else {
			$queryStr = "UPDATE	$this->table SET item_status = 0 WHERE store_name='$store_name' AND	item_code not in($items)";
		}
		$result = $this->db->query( $queryStr );
	//	print_r($this->db);
	//	print_r($result);die;
		if($result){
			if($this->db->affected_rows > 0 ) {
				$disabled = $this->db->affected_rows;
			} else {
				$disabled = false;
			}
		} else {
			$disabled = false;
		}
		$this->db->close();
		return $disabled;
	}
	
	/**
	 * @access public
	 * Función para actualizar productos
	 * @param Array $datos Valores a actualizar
	 * 
	 * @return Boolean resultado de la operación
	 */
	/*store_public_id='".isset($datos['store_public_id']) ? $datos['store_public_id'] : null."',
	service_group='".isset($datos['service_group']) ? $datos['service_group'] : null."',*/
	public function update( array $datos ){

        $registro = "
			UPDATE 
				$this->table 
			SET 
				item_status = ".$datos['item_status'].", 
				item_title='".$datos['item_title']."', 
				item_description='".$datos['item_description']."', 
				item_image_url='".$datos['item_image_url']."', 
				item_last_price='".$datos['item_last_price']."', 
				item_last_normal_price='".$datos['item_last_normal_price']."', 
				manufacturer_name='".$datos['manufacturer_name']."',
				store_public_id='".(isset($datos['store_public_id']) ? $datos['store_public_id'] : null)."',
				service_group='".(isset($datos['service_group']) ? $datos['service_group'] : null)."',
				category='".$datos['category']."',
				external_category='".$datos['external_category']."',
				shipping_cost='".$datos['shipping_cost']."',
				item_updated='".date("Y-m-d H:i:s")."'  
			WHERE 
				item_code='".$datos['item_code']."' AND
				item_type_id=".$datos['item_type_id'];
		//echo $registro; die; 
		$result = $this->db->query( $registro ); 
	//	print_r($result);
		if($result){
			$updated = true;
		} else {
			$updated = false;
		}

		$this->db->close();
		return $updated;

	}

	/**
	 * @access public
	 * Función para insertar productos
	 * @param Array $datos Valores a insertar
	 * 
	 * @return Boolean resultado de la operación
	 */
	public function insert( array $datos ){

        $registro = "
            INSERT INTO 
				$this->table 
            (
                item_type_id,
                item_title,
                item_url_slug,
                item_description,
                item_buy_url,
                item_image_url,
                item_last_price,
                item_last_normal_price,
                shipping_cost,
                item_code,
                item_sku,
                manufacturer_name,
                store_public_id,
                service_group,
				category,
				external_category,
                store_name
            ) 
            VALUES 
            (
                ". $datos['item_type_id'].",
                '".$datos['item_title']."',
                '".$datos['item_url_slug']."',
                '".$datos['item_description']."',
                '".$datos['item_buy_url']."',
                '".$datos['item_image_url']."',
                '".$datos['item_last_price']."',
                '".$datos['item_last_normal_price']."',
                '".$datos['shipping_cost']."',
                '".$datos['item_code']."',
                '".$datos['item_sku']."',
                '".$datos['manufacturer_name']."',
                '".(isset($datos['store_public_id']) ? $datos['store_public_id'] : null)."',
                '".(isset($datos['service_group']) ? $datos['service_group'] : null)."',
				'".$datos['category']."',
				'".$datos['external_category']."',
                '".$datos['store_name']."'
            )";
			
			$result = $this->db->query( $registro ); 

			if($result){
				$inserted = true;
			} else {
				$inserted = false;
				echo 'ERROR: '.$registro.PHP_EOL; 
			}
	
			$this->db->close();
			return $inserted;
	}

	public function insertCategoryMatch($category) {

		$queryStr = "INSERT IGNORE INTO $this->table_category_match (category_feed) VALUES ('$category')"; 
		$result = $this->db->query( $queryStr ); 

		if($result){
			$inserted = true;
		} else {
			$inserted = false;
		}

		$this->db->close();
		return $inserted;
	}
}