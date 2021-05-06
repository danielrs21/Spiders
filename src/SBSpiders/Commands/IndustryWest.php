<?php
namespace Spiders\Commands;
 
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableCell;
use Spiders\Helpers\Webdriver;
use Spiders\Helpers\Common;
use Spiders\Helpers\Log;
use Spiders\Models\CacheModel;
 
class IndustryWest extends Command
{
    /**
     * Identificador de tipo de producto en cache
     */
    const PRODUCT_TYPE_ID = 3;

    /**
    *   @var $info Definición de valores asociados a la tienda
    *   - Los valores definidos en esta sección aplican para todo el comando
    */
    protected $info = [
        'site_name'     => 'IndustryWest',
        'site_base_url' => 'https://www.industrywest.com',
        'site_url'      => 'https://www.industrywest.com/sale.html',
        'profit_percent'=> 1.15,
        'shipping_rate' => 0.25 // PROMEDIO CALCULADO EN BASE A VERIFICACION DE CHECKOUT DE 15 ARTICULOS DISTINTOS
    ];

    /**
     *  @var $tags Definición de tags utilizados para obtener información de los productos
     */
    protected $tags = [
        'product_url'           => '.product-item-name a',                      // Tag de url de producto en listado
        'name'                  => '.page-title',                               // Tag del nombre de producto
        'description'           => '.content-item .description p',          // Tag de la descripcion de producto
        'specifications'        => '.content-items .specs p',                       // Tag de la tabla de especificaciones
        'normal_price'          => '.old-price .price',                         // Tag de precio normal
        'special_price'         => '.special-price .price',                     // Tag de precio especial
        'image_first'           => '.product-preview .media .fotorama-item #magnifier-item-0',                         // Tag de Imagen principal
        'sku'                   => '.product-info-stock-sku .sku',              // Tag de Sku del producto
        'stock_excluded'        => '.product-info-stock-sku .available span'    // Tag para excluir productos sin stock        
    ];

    /** 
    *   @var $categories Definición de categorias a obtener
    *   - Se definen con el id de la categoria en tienda y el string de categoria magento donde seran ubicadas 
    */
    protected $categories = [
        'all' => [ 'category_string' => 'DRS/Home & Garden/Furniture']
    ];

    /**
     * @var $nosave Indica si se ejecutará sin realizar cambios en la cache
     */
    protected $nosave = false; 

    /**
     * @var $count Contadores de acciones con los productos obtenidos
     */ 
    protected $count = [
        'inserted'              => 0,
        'updated'               => 0,
        'disabled'              => 0,
        'invalid-title'         => 0,
        'invalid-description'   => 0,
        'invalid-price'         => 0,
        'invalid-image'         => 0,
        'invalid-item-code'     => 0,
        'invalid-shipping'      => 0,
        'invalid-stock'         => 0,
        'invalid-sponsored'     => 0,
        'error-product-url'     => 0,
        'error-page-url'        => 0,
        'error-insert-product'  => 0,
        'error-update-product'  => 0
    ];

    /**
     * @var $log Log de productos procesados
     */ 
    protected $log = [
        'inserted'              => array(),
        'updated'               => array(),
        'disabled'              => array(),
        'invalid-title'         => array(),
        'invalid-description'   => array(),
        'invalid-price'         => array(),
        'invalid-image'         => array(),
        'invalid-item-code'     => array(),
        'invalid-shipping'      => array(),
        'invalid-stock'         => array(),
        'invalid-sponsored'     => array(),
        'error-product-url'     => array(),
        'error-page-url'        => array(),
        'error-insert-product'  => array(),
        'error-update-product'  => array()
    ];

    /**
     * @var $products_affected Item Code de Productos insertados o actualizados 
     */
    protected $products_affected = array();

    /**
     * @var $products Registros de productos
     */ 
    protected $products = array();

    /* 
    *   Sección de configuración del comando
    *   - Se define el nombre de como debe ser invocado el comando, la descripción, texto de ayuda y los argumentos
    */
    protected function configure()
    {
        $this->setName('industrywest')
                ->setDescription('Industry West Products')
                ->setHelp('Scrapping products from Industry West.')
                ->addOption('nosave', null, InputOption::VALUE_NONE, 'Run in test mode. No database save.');
    }

    /*
    *   Sección de ejecución del comando
    *   - Se validad los argumentos, y se inicia el proceso de scraping 
    */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if($input->getOption('nosave')) {
            $this->nosave = true;
        }
        
        $output->writeln('<bg=green;options=bold;fg=white>[ SPIDERS - Industry West ]</>');

        if($this->nosave) {
            $output->writeln('<bg=blue;options=bold;fg=white>Run in Test mode - No save data.</>');
        }

        $this->runScraping($input,$output);

    }

    /**
     * Inicia el proceso del scrapping  
     */
    protected function runScraping( InputInterface $input, OutputInterface $output ) {   

        /* Se obtiene HTML de la pagina */ 
        $page_html = $this->getPageHtml();

        if(!$page_html) {
            $output->writeln('<error>Error obteniendo Url</error>');
            $this->log['error-page_url'] = $this->info['site_url'];
            $this->count['error-page-url']++;
        } else {
            $this->getProducts($page_html, 1, $output);

            /* Desactivar productos que no han sido actualizados de la categoria procesada */ 
            if(!$this->nosave) {
                $this->disableProducts( 'all' ); 
            }
        }

        /* Imprime resumen */ 
        $this->printLog( $output );
    }

    /** 
     *  @access Protected
     *  Función para obtener el codigo HTML del sitio en base a los parametros suministrados
     *
     *  @return simple_html_dom_parser object
     */
    protected function getPageHtml() {
        try
        {	                    
            /* 
            *   Se intenta obtener el html de la pagina 
            *   - Se implementa la clase Webdriver que a su vez hace uso de las herramientas externas Selenium y Google Chrome
            */
            echo 'Url: '.$this->info['site_url'].PHP_EOL;	
            return Webdriver::getHtml($this->info['site_url']);

        } catch (\Exception $e) {
           echo 'ERROR:'.$e->getMessage();
        }
    }

    /**
     * @access protected 
     * Realiza la lectura de los productos resultantes en la busqueda y obtiene sus valores
     * 
     * @param $html_page    Objeto Simple Html dom parser con contenido del resultado de la busqueda
     * @param $cat          Codigo de categoria actual
     * @param $output       Output Interface para impresion en consola
     * @param $i            Contador de registros  
     * @return Int Contador del ultimo registro procesado
     */
    protected function getProducts($page_html, $i, $output) {
        
        foreach ( $page_html->find($this->tags['product_url'] ) as $url) {

            $product = array();

            /* Se obtiene el html de la pagina de producto */ 
            $product_html = $this->getProductHTML($url);

            /* Si no se obtiene resultado se continua con el siguiente producto */ 
            if(!$product_html) {
                $output->writeln('<error>Error obteniendo Url, se continua con siguiente producto</error>');
                $this->log['error-product-url'][] = $url->href; 
                $this->count['error-product-url']++;
                continue;
            }

            /* Captura de valores del producto */ 
            $product['category']                = $this->categories['all']['category_string'];
            $product['item_title']              = Common::getElement( $product_html, $this->tags['name'], 0, 'plaintext', 1 );
            $product['item_description']        = $this->getProductDescription($product_html, $product['item_title']);
            $product['item_sku']                = Common::getElement( $product_html, $this->tags['sku'], 0, 'plaintext', 1,false,'SKU');
            $product['item_buy_url']            = $url->href;
            $product['item_url_slug']           = Common::createSlug( $product['item_title'], $product['item_buy_url'] );
            $product['item_code']               = md5( $product['item_buy_url'] );
            $product['item_image_url']          = $this->getProductImage( $product_html );
            $prices                             = Common::getPrices( $product_html, $this->tags['normal_price'], 'plaintext', $this->tags['special_price'], 'plaintext', $this->info['profit_percent'] );
            $product['item_last_normal_price']  = $prices['normal'];
            $product['item_last_price']         = $prices['special'];
            $product['shipping_cost']           = $prices['special'] * $this->info['shipping_rate']; // Free Shipping todos los productos en este particular 
            $product['valid_stock']             = $this->validStock( $product_html );

            /* Si el producto supera la validación de guarda */
            if( $this->validProduct( $product ) ) {
                if(!$this->nosave) {
                    $this->saveProduct( $product );
                }
            }

            $this->printProduct( $product, $output, $i );

            /* Se elimina el array de producto antes de continuar con el siguiente */ 
            unset($product);

            $i++;
        }

        return;
    }

    /**
     * @access protected 
     * Función para obtener el HTML de la vista de producto
     * 
     * @param $url URL de la vista producto
     */
    protected function getProductHTML($url) {

        try	{
            /* 
            *  Se envia como segundo parametro el tag de un elemento para hacer clic 
            *  y obtener la ficha de especificaciones del producto
            */
            echo 'Producto: '.$url->href.PHP_EOL;
            return Webdriver::getHtml($url->href);
        
        } catch (Exception $e) {
            echo 'ERROR:'.$e->getMessage().PHP_EOL;
        }

    }

    /**
     * @access Protected 
     * Función para obtener la descripción y contenido adicional de un producto
     * 
     * @param $html         Contenido html del producto
     * @param $product_name Nombre del producto para ser usado en caso de no existir contenido en descripcion.
     */
    protected function getProductDescription( $html, $product_name ) {

        /* Se obtiene la descripción corta y si existe la tabla de especificaciones se le concatena */ 
        $description    = Common::getElement($html, $this->tags['description'], 0, 'innertext', 1);

        $specifications = Common::getElement($html, $this->tags['specifications'], 0, 'outertext');
        //print_r($specifications);die;
        /* Si no existe descripcion se coloca el nombre del producto */ 
        if(!$description) {
            $description = $product_name; 
        }

        if($specifications) {
            $description.= $specifications;
        }

        return $description; 

    }

    /**
     * @access Protected
     * Obtiene la imagen de un producto
     * @param $html Contenido html del producto
     */
    protected function getProductImage ( $html ) {

        $image = Common::getElement( $html, $this->tags['image_first'], 0, 'src' );

        if($image) {

            return $image;

        } else {

            return false;
        }

        

    }

    /**
     * Valida si el shipping es correcto en base a los valores definidos en $this->shippingValid
     */
    protected function validShipping( $html ) {

        /* Se predefine como shipping invalido antes de validar */ 
        $valid = FALSE; 

        /* Se busca la etiqueta que contiene el valor de shipping */ 
        if( Common::getElement( $html, $this->tags['shipping_info'], 0 ) ) {

            /* Se obtiene el texto plano del div que contiene el shipping */ 
            $shipping_info = trim(strtolower($html->find($this->tags['shipping_info'],0)->parent()->plaintext));

            /* Se verifica si el texto del shipping valido existe en el string */ 
            foreach($this->shippingValid as $value) {

                /* Se valida si existe el valor valido y adicional no esta el valor invalido */ 
                if(strpos( $shipping_info, $value ) !== FALSE && $shipping_info != $this->shippingInvalid){
                    $valid = TRUE;
                }

            }
        }

        return $valid;
    }

    /** 
     * Valida que el stock sea valido en base a lo definido en $this->stockInvalid
     */
    protected function validStock( $html ) {
        $valid = false;
        $info_stock = $html->find($this->tags['stock_excluded']);

        foreach($info_stock as $stock) {
            if (strpos(trim($stock->plaintext), 'In stock' ) !== false) {
                $valid = true;
            }
        }
        return $valid;
    }

    /**
     * Valida que un producto sea correcto, verificando que los campos principales tengan datos
     */
    protected function validProduct( $product ){

        /* Se verifica los valores principales, si alguno falta se invalida el producto */ 
        if( $product['item_title']              == null )  { $this->count['invalid-title']++;       $this->log['invalid-title'][]       = $product['item_buy_url'];   return false; } 
        if( $product['item_description']        == null )  { $this->count['invalid-description']++; $this->log['invalid-description'][] = $product['item_buy_url'];   return false; } 
        if( $product['item_last_normal_price']  == null )  { $this->count['invalid-price']++;       $this->log['invalid-price'][]       = $product['item_buy_url'];   return false; } 
        if( $product['item_image_url']          == null )  { $this->count['invalid-image']++;       $this->log['invalid-image'][]       = $product['item_buy_url'];   return false; } 
        if( $product['item_code']               == null )  { $this->count['invalid-item-code']++;   $this->log['invalid-item-code'][]   = $product['item_buy_url'];   return false; } 
        if( $product['valid_stock']             == false ) { $this->count['invalid-stock']++;       $this->log['invalid-stock'][]       = $product['item_buy_url'];   return false; } 

        /* Si se superan las verificaciones previas, se valida el producto */ 
        return true;
  
    }
    
    /**
     * Guarda el producto en BD
     */
    protected function saveProduct( $product ){
        $cache = new CacheModel;
        $product_exist =  $cache->getProduct( $product['item_code'] );

        /* Se definen valores faltantes globales */ 
        $product['item_status']         = 1;
        $product['item_type_id']        = self::PRODUCT_TYPE_ID; 
        $product['store_name']          = $this->info['site_name'];
        $product['external_category']   = 0;

        /* Actualizar producto */ 
        if($product_exist) {
            /* Se verifica si el producto posee otras categorias */ 
            $cache_categories = explode( ',', $product_exist['category'] );
            if(!in_array($product['category'], $cache_categories)) {
                $product['category'] = $cache_categories.','.$product['category'];
            }
            /* Se envia a la DB */ 
            $result = $cache->update( $product );   
            /* Se actualizan contadores y log segun el resultado */   
            if( $result ) {
                /* Se agrega el item code a variable para luego desactivar los demas */ 
                $this->products_affected[] = "'".$product['item_code']."'";
                /* Se registra la url del producto en log que luego sera registrado en archivo */ 
                $this->log['updated'][] = $product['item_buy_url'];
                /* Aumenta el contador de productos actualizados */
                $this->count['updated']++;
            } else {
                $this->log['error-update-product'][] = $product['item_buy_url'];
                $this->count['error-update-product']++;
            }
        /* Nuevo producto */
        } else {
            /* Se envia a la DB */ 
            $result = $cache->insert( $product );
            if( $result ) {
                /* Se agrega el item code a variable para luego desactivar los demas */ 
                $this->products_affected[] = "'".$product['item_code']."'"; 
                /* Se registra la url del producto en log que luego sera registrado en archivo */ 
                $this->log['inserted'][] = $product['item_buy_url'];
                /* Aumenta el contador de productos actualizados */
                $this->count['inserted']++;
            } else {
                $this->log['error-insert-product'][] = $product['item_buy_url'];
                $this->count['error-update-product']++;
            }
        }
    }

    /**
     * Desactiva productos no actualizados en la categoria
     */
    protected function disableProducts( $cat ) {
        $cache = new CacheModel; 
        $result = $cache->disableProducts( $this->info['site_name'], 
                                           $this->categories[$cat]['category_string'], 
                                           $this->products_affected );
        if($result) {
            $this->count['disabled'] = $result; 
        } 
    }
 
    /**
     * Imprime en consola los datos obtenidos del producto
     */
    protected function printProduct( $product, $output, $i ) {

        $table = new Table($output);
        $table
            ->setHeaders(array('Nro', 'Campo', 'Valor'))
            ->setRows( array (
                array ( new TableCell((string) $i, array('rowspan' => 11)), 
                        'Product Name', 
                        substr($product['item_title'],0,100).'...' ),
                array ( 'Product Description', substr($product['item_description'],0,100).'...' ),
                array ( 'Product SKU', $product['item_sku'] ),
                array ( 'Product Code', $product['item_code'] ),
                array ( 'Product Normal Price', $product['item_last_normal_price'] ),
                array ( 'Product Special Price', $product['item_last_price'] ),
                array ( 'Product Slug Url', substr($product['item_url_slug'],0,100).'...' ),
                array ( 'Product Shipping Cost', $product['shipping_cost'] ),
                array ( 'Product Valid Stock', $product['valid_stock'] ),
            ) );
        $table->render();

    }

    /**
     * Imprime en archivo log y consola los resultados del scrapping
     */
    protected function printLog( $output ) {
        
        /* Imprime log en archivo */ 
        $recordLog = Log::create('industrywest');
        $recordLog->info('++++++ SPIDERS. REPORT FOR SCRAPPING: INDUSTRY WEST '.date('Y-m-d H:i:s').' ++++++');

        foreach( $this->log as $key => $values ){
            if(count($values) > 0) {
                $recordLog->info('«««««« Products: '.$key.' »»»»»»');
                $i = 1; 
                foreach($values as $value) {
                    $recordLog->info($i.' | '.$key.' | '.$value);
                    $i++;
                }
            }
        }
        if( $this->count['disabled'] > 0 ) {
            $recordLog->info('Disabled '.$this->count['disabled'].' products with no update info'); 
        }
        $recordLog->info('++++++ END REPORT FOR SCRAPPING: INDUSTRY WEST '.date('Y-m-d H:i:s').' ++++++');
    
        /* Imprime resumen en pantalla */ 
        $output->writeln('Resume for scrapping:');
        $table = new Table($output);
        $table
            ->setHeaders(array('Count', 'Total'))
            ->setRows( array ( 
                array ('Products Inserted', $this->count['inserted']),
                array ('Products Updated', $this->count['updated']),
                array ('Products Disabled', $this->count['disabled']),
                array ('Invalid Product without Title', $this->count['invalid-title']),
                array ('Invalid Product without Description', $this->count['invalid-description']),
                array ('Invalid Product without Price', $this->count['invalid-price']),
                array ('Invalid Product without Image', $this->count['invalid-image']),
                array ('Invalid Product without Code', $this->count['invalid-item-code']),
                array ('Invalid Product without Shipping', $this->count['invalid-shipping']),
                array ('Invalid Product without Stock', $this->count['invalid-stock']),
                array ('Error in Product URL', $this->count['error-product-url']),
                array ('Error in Pages URL', $this->count['error-page-url']),
                array ('Error Inserting Product', $this->count['error-insert-product']),
                array ('Error Updating Product', $this->count['error-update-product'])
             ) );
        $table->render();
        $output->writeln('For more details view the log file.');
    }
}