<?php
    namespace Spiders\Commands;

    use Spiders\Models\CacheModel;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Helper\Table;
    use Symfony\Component\Console\Helper\TableSeparator;
    use Symfony\Component\Console\Helper\TableCell;
    use Sunra\PhpSimple\HtmlDomParser;
    use Spiders\Helpers\Common;
    use Spiders\Helpers\Log;

    class OnlineCarStereo extends Command
    {
        const PRODUCT_TYPE_ID = 3;
        protected $site_info = [
            'store_name' => 'Onlinecarstereo',
            'main_url' => '',
            'important_links' => array(),
            'profit_percent' => 1.15
        ];
        protected $current_object;
        protected $products_affected = array();
        protected $categories = null;
        protected $categories_magento = [
            'Car Audio & Video' => [
                'cat_name' => 'Car Audio & Video',
                'sub_cat' => [
                    'Boundles & Packages',
		            'Car Stereo Packages',
		            'Bundles & Packages',
		            'Car Video Packages',
		            'Seasonal Packages',
                    'Bass Packages',
                    'Car Video & Multimedia',
		            'Overhead Flip Down Monitors',
		            'Headrest Monitors',
		            'In-Dash Video Receivers',
		            'In-Dash DVD Players',
		            'Rearview Monitors',
	                'GPS & Navigation',
		            'In-Dash Car Navigation Systems',
		            'Car Navigation Packages',
		            'OEM Fit In-Dash Car Navigation Systems',
		            'Portable GPS Navigation Systems',
		            'Add-On Car GPS Navigation',
	                'Subwoofer Enclosures',
		            'Enclosed Car Subwoofers',
		            'Powered Subwoofers',
		            'Ported Subwoofers Boxes',
		            'Sealed Subwoofer Boxes',
		            'Powered Subwoofer Enclosures',
		            'JL Audio Stealhbox Systems',
	                'Car Subwoofers',
		            'Component Car Subwoofers',
		            'Marine Subwoofers',
		            'Bass Tubes',
	                'Car Speakers',
		            'Full Range Car Speakers',
		            'Component Systems',
		            'Car Tweeters',
		            'Speaker Grilles',
                    'Motorcycle & Off-Road Speakers',
		            'Midbass Drivers',
	                'Wireless and Bluetooth',
                    'Stand Alone Hands-Free Bluetooth Devices',
		            'Universal Bluetooth Adapters',
		            'OEM Bluetooth Integration Adapters',
	                'Mobile Sound Processing',
		            'Line Output Converters',
		            'Ground Loop Isolators',
		            'Signal Processors',
		            'Equalizers',
		            'Crossovers',
		            'Bass Enhancers',
		            'Pre-Amps & Line Drivers',
	                'Car Batteries & Accessories',
		            'Capacitors',
		            'Battery Sleeves',
		            'Car Batteries',
		            '12 Volt Power Supplies',
	                'Car Amplifiers',
		            'Class D Amplifiers',
		            'Mono Subwoofer Amplifiers',
		            '2 Channel Amplifiers',
		            '4 Channel Amplifiers',
		            '5 Channel System Amplifiers',
                    '3 Channel Car Amplifiers',
		            '6 Channel or More System Amplifiers',
	                'Multimedia Accessories',
                    'Back-up Cameras',
		            'Car Stereo iPod Adapters',
		            'Power Inverters',
		            'Car Stereo USB Cables',
                    'Car Stereo HD Radio Tuners',
                    'Multimedia Accessories',
		            'FM Modulators',
		            'Car Headphones',
	                'In-Dash Receivers',
		            'Car Stereos with Bluetooth',
		            'Digital Media Receivers',
		            'Car CD Players',
		            'Car MP3 CD Players',
	                'Sound Bars',
                    'Motorcycle Amplifiers',
	                'Misc Accessories',
	                'Remote Controls',
	                'Subwoofers',
	                'Car Mounts',
	                'Backpacks and Cases',
		            'Misc Cases',
	                'Vehicle Lighting',
		            'Led Lightbar'
                ]
            ],
            'Installation & Accessories' => [
                'cat_name' => 'Installation & Accessories',
                'sub_cat' => [
                    'RCA & Interconnects',
                    'Audio Interconnects',
                    'RCA & Interconnects',
                    'Interconnect Couplers',
                    'Mini Jack Cables',
                    'Interconnect Adapters',
                    'Installation Accessories',
                    'Sound Damping',
                    'Wiring Harnesses',
                    'Installation Tools',
                    'Amplifier Installation',
                    'Amp Installation Kits',
                    'Amplifier Bass Remotes',
                    'Fuse Holders',
                    'Fuses',
                    'Factoy Integration',
                    'Dash Kits',
                    'Media Expansion Adapters',
                    'OEM Amp Interfaces',
                    'Vehicles Specific iPod Interfaces',
                    'OEM Feature Retention Interfaces',
                    'Steering Wheel Control Interfaces',
                    'OEM Backup Camera Interfaces',
                    'Vehicle Wiring & Terminals',
                    'Circuit Breakers',
                    'Power Cables',
                    'Vehicle Wiring & Terminals',
                    'Speaker Installation',
                    'Speaker Wire',
                    'Speaker Adapters Plates',
                    'Speaker Spacers'
                ]
            ],
            'Car Security & Safety' => [
                'cat_name' => 'Car Security & Safety',
                'sub_cat' => [
                    'Car Security',
		            'Interface Modules and Sensors',
		            'Car Alarms',
                    'Remote Starters',
		            'Remotes & Transmitters',
                    'Interface Harness'
                ]
            ],
            'Marine/Boat Audio' => [
                'cat_name' => 'Marine & Boat Audio',
                'sub_cat' => [
                    'Marine Electronics',
		            'Marine Remotes',
		            'Marine Speakers',
		            'Marine Amplifiers',
		            'Marine Accessories',
		            'Marine Packages',
		            'Marine Receivers',
		            'Marine Subwoofer Grilles',
		            'Marine Speaker Grilles'
                ]
            ],
            'Wheels/Rims' => [
                'cat_name' => 'Wheels & Rims',
                'sub_cat' => [
                    'Rims'
                ]
            ]
        ];
        protected $categories_magento2 = [
            'Pro Audio & DJ/ Stage Gear' => [
                'cat_name' => 'Audio & Headphones',
                'sub_cat' => [
                    'Pro Audio & DJ Equipment',
                    'DJ Mixers',
                    'Table Top CD Players',
                    'DJ & Stage Equipment',
                    'Headphones',
                    'PA Speakers',
                    'Pro Audio Speakers',
                    'Amplifiers',
                    'Remotes & Controllers',
                    'Audio/Video Interface',
                    'Club DJ Lighting',
                    'Signal Processors & Distributors',
                    'HDMI Cables & Adapters'
                ]
            ],
            'Home & Portable Electronics' => [
                'cat_name' => false,
                'sub_cat' => [
                    'Home Entertainment',
                    'Home Theater Amplifiers',
                    'Home Theater Receivers',
                    'Mini Box Speakers',
                    'iPod Docking Stations',
                    'Portable Electronics',
                    'iPod & iPhone Accessories',
                    'Camera & Photo',
                    'Dashcam',
                    'Wall Chargers Kits',
                    'Cigarette Lighter Adapters & Car Chargers',
                    'Portable Monitors',
                    'Sports and Fitness',
                    'Boomboxes',
                    'Phone & Tablet Accessories'
                ]
            ]
        ];

        /**
         * Configura los datos para el comando.
         */
        protected function configure()
        {
            $this
                ->setName('OnlineCarStereo:scrap')
                ->setDescription('Comienza el proceso de Scraping')
                ->setHelp('Debe suministrar la URL principal del sitio')
                ->addArgument('url', InputArgument::REQUIRED, 'URL para Scraping');
        }

        /**
         * Ejecuta los pasos para scrapear el sitio y muestra por pantalla el progreso actual de cada paso.
         *
         * @param InputInterface $input
         * @param OutputInterface $output
         * @return int|null|void
         */
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $this->site_info['main_url'] = $input->getArgument('url');
            $this->current_object = $this->get_html($this->site_info['main_url']);

            $log = Log::create('OnlineCarStereo');
            $log->info('++++++ SPIDERS. REPORT FOR SCRAPPING: Online Car Stereo ' . date('Y-m-d H:i:s') . ' ++++++');

            $output->writeln(PHP_EOL.'Step 1: Getting Important Links...');
            if ($this->get_important_links()) :

                $output->writeln('Links scraped.'.PHP_EOL.PHP_EOL.'Next Step...'.PHP_EOL);
                $output->writeln('Step 2: Getting Categories Per Section...');

                $this->categories = $this->scrap_links($this->site_info['important_links']);

                if ($this->categories) :

                    $log->info('Categories Scrapped:');

                    foreach ($this->categories as $key => $value) :

                        $output->writeln(count($value).' categories found in '.$key.' section');
                        $log->info(count($value).' categories found in '.$key.' section');

                    endforeach;

                    $output->writeln(PHP_EOL.'Next Step...');
                    $output->writeln(PHP_EOL.'Step 3: Getting Products Info...');
                    $products = $this->init_products_info();

                    if ($products) :
                        $products = $this->get_products_info($products);
                        $output->writeln('Goted Info.');
                        $output->writeln(PHP_EOL.'Next Step...');
                        $log->info('Products Scrapped: ');

                        $output->writeln('Step 4: Saving Products Data...');
                        foreach ($products as $product) :
                            $this->save_products($product, $log);
                        endforeach;

                        $this->disableProducts($log);
                    else:
                        $output->writeln('Cant Get Products Info.');
                        $log->info('Products Scrapped: 0');
                    endif;

                else :

                    $output->writeln('No Categories Found, Scrapping stoped.');
                    $log->info('No categories found, scrapping stoped.');

                endif;

            else:

                $output->writeln('Scrap Proccess Fail: Cant Find Scraped Element.');
                $log->info('Scrap Proccess Fail: cant find scraped element.');

            endif;

            $output->writeln(PHP_EOL.'Scrapping proccess has finish.');
            $output->writeln('Check log file to see more details.');
            $log->info('++++++ END REPORT FOR SCRAPPING: Online Car Stereo ' . date('Y-m-d H:i:s') . ' ++++++');
        }

        /**
         * Obtiene un objeto scraping a utilizar
         *
         * @param $url  Enlace para scrapear.
         * @return bool|simple_html_dom  Devuelve un objeto simple_html_dom para trabajar.
         */
        public function get_html($url)
        {
            return HtmlDomParser::file_get_html($url);
        }

        /**
         * Obtiene los enlaces de las secciones de la tienda con ofertas.
         *
         * @return bool En caso de no encontrar nada devuelve falso.
         */
        private function get_important_links()
        {
            $links = $this->current_object->find('ul[id=searchbar-icons] ul[class=dropdown-menu] a');

            foreach ($links as $link) :
                $important_links[] = $this->site_info['main_url'] . $link->href;
            endforeach;

            if (isset($important_links)) :

                $this->site_info['important_links']['clearance'] = $important_links[1];
                $this->site_info['important_links']['hot_deals'] = $important_links[2];
                $this->site_info['important_links']['on_sale'] = $important_links[4];
                $this->site_info['important_links']['newly'] = $important_links[5];

                return true;

            else:

                return false;

            endif;
        }

        /**
         * Obtiene las categorias encontradas dentro de cada sección de ofertas
         *
         * @param $important_links Enlaces de las secciones de oferta.
         * @return bool  En caso de no encontrar nada devuelve falso.
         */
        private function scrap_links($important_links)
        {
            $categories = false;

            foreach ($important_links as $key => $value) :

                $scrap = $this->get_html($value);
                $parents = $scrap->find('div[id=catSpecial] p');

                foreach ($parents as $parent) :

                    $selector = str_replace('TopCategory', 'TopCategoryContainer',  $parent->id);
                    $categories[$key][]['cat_name'] = trim(html_entity_decode($parent->plaintext));
                    $childs = $scrap->find('div[id=catSpecial] div[id='.$selector.'] a');

                    if (count($childs) > 0) :

                        foreach ($childs as $child) :

                            $subcat = explode('(', $child->innertext);
                            $categories[$key][count($categories[$key]) - 1]['sub_cat'][] = [
                                'cat_name' => $subcat[0],
                                'cat_url' => $child->href
                            ];

                        endforeach;

                    else :

                        return false;

                    endif;

                endforeach;

            endforeach;

            return $categories;
        }

        /**
         * Obtiene los valores iniciales de los productos a scrapear.
         *
         * @return array|bool  En caso de obtener los datos devuelve los productos en un array, de lo contrario devuelve falso.
         */
        private function init_products_info()
        {
            $products = false;
            foreach ($this->categories as $section_name => $section) :
                foreach ($section as $category) :
                    foreach ($category['sub_cat'] as $subcategory) :

                        $subcat_name = $subcategory['cat_name'];
                        $subcat_object = $this->get_html($subcategory['cat_url']);
                        $list_of_products = $subcat_object->find('div[id=divProductList] div[class=prod-list-compare]');

                        foreach ($list_of_products as $current_product) :
                            $pickable = explode(' ', $current_product->next_sibling()->class);
                            $pickable = array_search('hidden', $pickable);
                            if (!$pickable) :
                                $title = $current_product->parent()->find('span[class=h4]', 0)->innertext;
                                $url = $this->site_info['main_url'].$current_product->parent()->find('a', 0)->href;
                                $products[] = array(
                                    'item_status' => 1,
                                    'item_type_id' => self::PRODUCT_TYPE_ID,
                                    'store_name' => $this->site_info['store_name'],
                                    'item_code' => md5($url),
                                    'item_title' => addslashes($title),
                                    'item_buy_url' => $url,
                                    'item_url_slug' => Common::createSlug($title, $url),
                                    'category' => $this->search_cat(trim($subcat_name)),
                                    'external_category' => 0,
                                    'manufacturer_name' => ''
                                );
                            endif;
                        endforeach;
                    endforeach;
                endforeach;
            endforeach;

            $products = ($products) ? array_unique($products, SORT_REGULAR) : $products;
            foreach ($products as $key => $value){
                if ($value['category'] == '') {
                    unset($products[$key]);
                    continue;
                }
            }
            return $products;
        }

        /**
         * Obtiene el nombre de la categoría magento valida para el producto.
         *
         * @param $sub_cat  Nombre de la categoria de la tienda de origen.
         * @return string  Nombre de la categoría en magento.
         */
        private function search_cat($sub_cat)
        {
            $response = false;

            foreach ($this->categories_magento as $category) :
                if (in_array($sub_cat, $category['sub_cat'])) :
                    $response = 'DRS/Automotive/'.$category['cat_name'];
                endif;
            endforeach;

            if ($response == false) :
                foreach ($this->categories_magento2 as $cat) :
                    if (in_array($sub_cat, $cat['sub_cat'])) :
                        if ($cat['cat_name']) :
                            $response = 'DRS/Electronics/'.$cat['cat_name'];
                        else :
                            $response = 'DRS/Electronics';
                        endif;
                    endif;
                endforeach;
            endif;

            return $response;
        }

        /**
         * Devuelve la condición de venta del producto según el texto disponible en la tienda de origen.
         *
         * @param $condition  Condición de la tienda origen.
         * @return bool|string  Condición aceptada según magento.
         */
        private function item_condition($condition)
        {
            switch ($condition) :
                case 'Brand New' :
                    return 'New';
                break;
                case 'Factory Reconditioned' :
                    return 'Refurbished';
                break;
                default:
                    return false;
                break;
            endswitch;
        }

        /**
         * Obtiene la información restante del detalle del producto.
         *
         * @param $products  Productos a completar información.
         * @return mixed  Productos con toda la información.
         */
        private function get_products_info($products)
        {
            foreach ($products as $key => $value) :
                $scrap = $this->get_html($value['item_buy_url']);

                $price = $scrap->find('div[id=product-detail-details] span[id=spanOurPrice]', 0)->innertext;
                $price = str_replace(array('$', ','), '', $price);
                $normal_price = $scrap->find('div[id=product-detail-details] span[id=spanRegularPrice]', 0);
                if (is_object($normal_price)) :
                    $normal_price = $normal_price->innertext;
                else:
                    $eval = $scrap->find('div[id=product-detail-details] span[id=spanMsrp]', 0);
                    if (is_object($eval)) :
                        $normal_price = $eval->plaintext.PHP_EOL;
                    else:
                        $normal_price = $scrap->find('div[id=product-detail-details] span[id=spanOurPrice]', 0)->innertext;
                    endif;
                endif;
                $normal_price = str_replace(array('$', ','), '', $normal_price);

                $products[$key]['item_sku'] = '';
                $products[$key]['shipping_cost'] = ($price > 49) ? 0 : 11.95;
                $products[$key]['item_last_price'] = (int)$price * $this->site_info['profit_percent'];
                $products[$key]['item_last_normal_price'] = (int)$normal_price * $this->site_info['profit_percent'];
                $products[$key]['item_image_url'] = $this->site_info['main_url'].'/CarAudio/'.$scrap->find('div[id=product-detail-images] img[id=DIL1]', 0)->src;

                $description = $scrap->find('div[id=product-detail-tabs] div[id=divProdDesc]', 0);
                if (is_object($description)) :
                    $products[$key]['item_description'] = addslashes($description->innertext);
                else :
                    $desc = $scrap->find('div[id=product-detail-details] div[class=product-detail-shortdesc]', 0)->innertext;
                    $desc = explode('<span id="lblReview">', $desc);
                    $products[$key]['item_description'] = addslashes(trim($desc[0]));
                endif;
                $item_condition_brother = $scrap->find('div[id=product-detail-details] span[class=detail-product-number]');
                foreach ($item_condition_brother as $item) :
                    $string = explode('&nbsp;', $item->next_sibling()->innertext);
                endforeach;
                $products[$key]['item_condition'] = $this->item_condition($string[2]);
            endforeach;
            return $products;
        }

        /**
         * Guarda los productos obtenidos por scraping para almacenar en la base de datos.
         *
         * @param $product  Producto a actualizar o añadir en la tienda.
         * @param OutputInterface $output  muestra por consola.
         */
        private function save_products($product, $log)
        {
            $cache = new CacheModel();
            $product_exist = $cache->getProduct($product['item_code']);

            /* Actualizar producto */
            if($product_exist) {
                /* Se verifica si el producto posee otras categorias */
                $cache_categories = explode( ',', $product_exist['category'] );
                if(!in_array($product['category'], $cache_categories)) {
                    $cache_categories[] = $product['category'];
                    $product['category'] = implode(',', $cache_categories);
                }
                /* Se envia a la DB */
                $result = $cache->update( $product );
                if ( $result ) {
                    $this->products_affected[] = "'".$product['item_code']."'";
                    $log->info($product['item_code'].' Updated');
                }

                /* Nuevo producto */
            } else {
                /* Se envia a la DB */
                $result = $cache->insert( $product );
                if ( $result ) {
                    $this->products_affected[] = "'".$product['item_code']."'";
                    $log->info($product['item_code'].' Added');
                }
            }
        }

        /**
         * Permite desactivar o deshabilitar los productos que no fueron actualizados.
         *
         * @param $log para añadir al log los productos deshabilitados.
         */
        protected function disableProducts($log) {
            $cache = new CacheModel;
            $result = $cache->disableProducts( $this->site_info['store_name'],
                false,
                $this->products_affected );
            if($result) {
                $log->info('DISABLED PRODUCTS: '. $result);
            }
        }
    }